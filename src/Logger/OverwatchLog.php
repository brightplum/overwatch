<?php

namespace Drupal\overwatch\Logger;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Capture Logs and send them to the queue.
 */
class OverwatchLog implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * UUID Interface.
   *
   * @var UuidInterface|Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Account Proxy Interface.
   *
   * @var AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Queue factory.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerAwareInterface
   */
  protected $queueFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   * @param \Symfony\Component\DependencyInjection\ContainerAwareInterface $queue_factory
   *   The Queue factory.
   */
  public function __construct(
    LogMessageParserInterface $parser,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    UuidInterface $uuid,
    AccountProxyInterface $current_user,
    ContainerAwareInterface $queue_factory
  ) {
    $this->parser = $parser;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->uuidService = $uuid;
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
  }

  /**
  *  {@inheritdoc}
  */
  public function log($level, $message, array $context = []): void {
    if (!is_string($message) && !$message instanceof Stringable) {
      $message = (string) $message;
    }

    if ($level !== RfcLogLevel::ERROR) {
      return;
    }

    // Remove backtrace and exception since they may contain an
    // unserializable variable.
    unset($context['backtrace'], $context['exception']);

    // Entity type.
    $entity_type = 'error';
    $action = 'insert';
    $bundle = 'error';

    try {
      // Get author performing the action.
      $author = $this->currentUser->getAccountName();

      // Get system config data.
      $system_config = $this->configFactory->get('system.site');

      // Get name.
      $site_name = $system_config->get('name');

      // Build base url.
      $current_request = $this->requestStack->getCurrentRequest();
      $base_url = $current_request->getSchemeAndHttpHost();

      // Build site machine name.
      $site_machine_name = strtolower(str_replace(' ', '_', $site_name));
      $site_machine_name = substr($site_machine_name, 0, 32);

      // Convert PSR3-style messages to \Drupal\Component\Render\FormattableMarkup
      // style, so they can be translated too in runtime.
      $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
      $context['serialized_variables'] = serialize($message_placeholders);

      // Prepare data for JSON.
      $eventData = [
        'uuid' => $this->uuidService->generate(),
        'title' => 'Error log',
        'author' => $author,
        'bundle' => $bundle,
        'entity' => $entity_type,
        'timestamp' => time(),
        'type' => $action,
        'site_base_url' => $base_url,
        'site_machine_name' => $site_machine_name,
        'site_name' => $site_name,
        'severity' => 'high',
        'context' => serialize($context),
      ];

      // Convert to JSON string.
      $jsonString = json_encode($eventData);

      // Get the queue service.
      $queue = $this->queueFactory->get('overwatch_queue');

      // Enqueue the JSON data.
      $queue->createItem($jsonString);
    }
    catch (\Exception $e) {}
  }

}
