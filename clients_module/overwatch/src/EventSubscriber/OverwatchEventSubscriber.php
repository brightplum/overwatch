<?php

namespace Drupal\overwatch\EventSubscriber;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\core_event_dispatcher\EntityHookEvents;
use Drupal\core_event_dispatcher\Event\Entity\EntityDeleteEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Captures events.
 */
class OverwatchEventSubscriber implements EventSubscriberInterface {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

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
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
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
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    UuidInterface $uuid,
    AccountProxyInterface $current_user,
    ContainerAwareInterface $queue_factory
  ) {
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->uuidService = $uuid;
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityHookEvents::ENTITY_INSERT => 'onEntityInsert',
      EntityHookEvents::ENTITY_UPDATE => 'onEntityUpdate',
      EntityHookEvents::ENTITY_DELETE => 'onEntityDelete',
    ];
  }

  /**
   * Respond to the entity insert event.
   *
   * @param EntityInsertEvent $entity
   *   The entity being inserted.
   */
  public function onEntityInsert(EntityInsertEvent $entity) {
    $this->handleEvent($entity->getEntity(), 'insert');
  }

  /**
   * Respond to the entity update event.
   *
   * @param EntityUpdateEvent $entity
   *   The entity being updated.
   */
  public function onEntityUpdate(EntityUpdateEvent $entity) {
    $this->handleEvent($entity->getEntity(), 'update');
  }

  /**
   * Respond to the entity delete event.
   *
   * @param EntityDeleteEvent $entity
   *   The entity being deleted.
   */
  public function onEntityDelete(EntityDeleteEvent $entity) {
    $this->handleEvent($entity->getEntity(), 'delete');
  }

  /**
   * Handle the entity event based on its type.
   *
   * @param EntityInterface $entity
   *   The entity.
   * @param string $action
   *   The action being performed (insert, update, delete).
   */
  private function handleEvent(EntityInterface $entity, string $action) {
    // Detect entity type.
    $entity_type = $entity->getEntityTypeId();

    // This code is only for nodes, users and block.
    if (!in_array($entity_type, ['node', 'user', 'block'])) {
      return;
    }

    try {
      // Log the action and event type.
      $this->loggerFactory->get('overwatch')->info('Creating event for entity Type: @entityType on action: @action', [
        '@entityType' => $entity_type,
        '@action' => $action,
      ]);

      // Get bundle.
      $bundle = $entity->bundle();

      // Get author performing the action.
      $author = $this->currentUser->getAccountName();

      // Get title.
      $title = 'Event on ' . $entity_type;
      switch ($entity_type) {
        case 'node':
          $title = $entity->get('title')->value;
          break;

        case 'block':
        case 'user':
          $title = $entity->label();
          break;
      }

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

      // Prepare data for JSON.
      $eventData = [
        'uuid' => $this->uuidService->generate(),
        'title' => $title,
        'author' => $author,
        'bundle' => $bundle,
        'entity' => $entity_type,
        'timestamp' => time(),
        'type' => $action,
        'site_base_url' => $base_url,
        'site_machine_name' => $site_machine_name,
        'site_name' => $site_name,
        'severity' => 'low',
        'context' => '',
      ];

      // Convert to JSON string.
      $jsonString = json_encode($eventData);

      // Get the queue service.
      $queue = $this->queueFactory->get('overwatch_queue');

      // Enqueue the JSON data.
      $queue->createItem($jsonString);
    }
    catch (\Exception $e) {
      // Log the action and event type.
      $this->loggerFactory->get('overwatch')->error($e->getMessage());
    }
  }

}
