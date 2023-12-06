<?php

namespace Drupal\overwatch\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overwatch Queue Worker.
 *
 * @QueueWorker(
 *   id = "overwatch_queue",
 *   title = @Translation("Overwatch Queue"),
 *   cron = { "time" = 15 }
 * )
 */
class OverwatchQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * Main constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The connection to the database.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Connection $database, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $configFactory;
  }

  /**
   * Used to grab functionality from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('config.factory')
    );
  }

  /**
   * Processes an item in the queue.
   *
   * @param mixed $data
   *   The queue item data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function processItem($data) {
    $overwatch_settings = $this->configFactory->get('overwatch.settings');
    $token = $overwatch_settings->get('access_token');
    $this->loggerFactory->get('overwatch_queue')->debug('Processing event @data', ['@data' => $data]);

    // Build Headers.
    $headers['Authorization'] = 'Bearer ' . $token;
    $headers['Content-Type'] = 'application/json';

    //Get monitoring_site_url.
    $config = $this->configFactory->get('overwatch.settings');
    $monitoring_url = $config->get('monitoring_site_url');

    // Define the API endpoint for token retrieval.
    $eventEndpoint = $monitoring_url . '/api/overwatch/event?_format=json';

    // Initialize the Guzzle HTTP client.
    $client = new Client([
      'headers' => $headers,
    ]);

    // Make an HTTP POST request to the create event endpoint.
    $response = $client->post($eventEndpoint, [
      'body' => $data,
    ]);

    // Check the response status code.
    if ($response->getStatusCode() === 201) {
      // Connection test successful.
      $response_data = json_decode($response->getBody()->getContents(), TRUE);
      if (isset($response_data['id'])) {
        $this->loggerFactory->get('overwatch_queue')->info('Event created on Overwatch successfully');
      }
    }
    else {
      $this->loggerFactory->get('overwatch_queue')->error('Error sending data to Overwatch: @details', [
        '@details' => $response->getBody()->getContents(),
      ]);
      throw new \Exception('Error sending data to Overwatch');
    }
  }

}
