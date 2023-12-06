<?php

namespace Drupal\overwatch_bp_rest\Plugin\rest\resource;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Route;
use Drupal\views\Views;

/**
 * Represents System Data records as resources.
 *
 * @RestResource (
 *   id = "overwatch_bp_rest_system_data_resource",
 *   label = @Translation("System Data Resource"),
 *   uri_paths = {
 *     "create" = "/api/overwatch/system_data"
 *   }
 * )
 */
class SystemDataResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $entity_field_manager);
    $this->entityTypeManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Responds to POST requests and saves the new record.
   */
  public function post(array $data) {
    // Validates incoming data against schema.
    if (!$this->isValidData($data)) {
      throw new BadRequestHttpException('Incorrect data received, check logs.');
    }

    try {
      // Create ErrorWarning and ExtensionInformation entities
      $error_entities = $this->createErrorWarningEntities($data['errors_and_warnings']['errors'], "error");
      $warning_entities = $this->createErrorWarningEntities($data['errors_and_warnings']['warnings'], "warning");
      $combined_errores_warning_entities = array_merge($error_entities, $warning_entities);
      $extension_entities = $this->createExtensionInformationEntities($data['extensions']);

      try {
        // Create new SystemData entity.
        $new_system_data = $this->entityTypeManager
          ->getStorage('node')
          ->create(['type' => 'systemdata']);

        // Set direct mappings for simple fields
        $new_system_data->set('title', $data['site_name']);
        $new_system_data->set('field_site_type', $data['site_type']);
        $new_system_data->set('field_site_machine_name', $data['site_machine_name']);
        $new_system_data->set('field_core_version_number', $data['core_version']);
        $date_time = new \DateTime($data['report_time']);
        $formatted_timestamp = $date_time->format('Y-m-d\TH:i:s');
        $new_system_data->set('field_last_cron_run_time', $formatted_timestamp);
        $new_system_data->set('field_extension_count', $data['extensions_count']);
        $new_system_data->set('field_status_report', json_encode($data['status_report']));
        $new_system_data->set('field_all_updates', $data['updates_available']['all_updates']);
        $new_system_data->set('field_security_updates', $data['updates_available']['security_updates']);

        // Set references to ErrorWarning and ExtensionInformation entities
        $new_system_data->set('field_error_warning', $combined_errores_warning_entities);
        $new_system_data->set('field_error_number', count($error_entities));
        $new_system_data->set('field_warning_number', count($warning_entities));
        $new_system_data->set('field_extension_information', $extension_entities);

        $new_system_data->enforceIsNew();
        $new_system_data->save();

        $this->clearViewsCache();
      }
      catch (\Exception $e) {
        $this->logger->error('Error in error-warning: @message', ['@message' => $e->getMessage()]);
      }
      // Log id of new system data created.
      $this->logger->notice('Created new system data @id.', ['@id' => $new_system_data->id()]);

      // Add to data new id assigned by Drupal.
      $data['id'] = $new_system_data->id();

      // Return the newly created record in the response body.
      return new ModifiedResourceResponse($data, 201);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

  /**
   * Create ErrorWarning entities from provided data.
   *
   * @param array $items
   *    Data for creating ErrorWarning entities.
   * @param string $type 
   *    Data for creating ErrorWarning entities.
   * @return array
   *    Created entity IDs.
   */
  private function createErrorWarningEntities(array $items, $type) {
    $entityIds = [];
    foreach ($items as $item) {
      try {
        $dateTime = new \DateTime($item['timestamp']);
        $formattedTimestamp = $dateTime->format('Y-m-d\TH:i:s');
        $entity = $this->entityTypeManager
          ->getStorage('node')
          ->create([
            'type' => 'errorwarning',
            'title' => $item['title'],
            'field_description' => $item['description'],
            'field_timestamp' => $formattedTimestamp,
            'field_type' => $type,
          ]);
  
        $entity->save();
        $entityIds[] = $entity->id();
      }
      catch (\Exception $e) {
        $this->logger->error('Error in error-warning: @message', ['@message' => $e->getMessage()]);
      }
    }
    return $entityIds;
  }

  /**
   * Create ExtensionInformation entities from provided data.
   *
   * @param array $extensions 
   *    Data for creating ExtensionInformation entities.
   * @return array
   *    Created entity IDs.
   */
  private function createExtensionInformationEntities(array $extensions) {
    $entityIds = [];
    foreach ($extensions as $extension) {
      try {
        $entity = $this->entityTypeManager
          ->getStorage('node')
          ->create([
            'type' => 'extensioninformation',
          ]);

        $entity->set('title', $extension['extension_name']);
        $entity->set('field_current_version', $extension['current_version']);
        $entity->set('field_recommend_version', $extension['recommended_version']);
        $entity->set('field_update_available', $extension['update_available']);
        $entity->set('field_security_update', $extension['security_update']);
        $entity->save();
        $entityIds[] = $entity->id();
      }
      catch (\Exception $e) {
        $this->logger->error('Error in extension-information: @message', ['@message' => $e->getMessage()]);
      }
    }
    return $entityIds;
  }  

  /**
   * Validates incoming data against schema.
   *
   * @param array $data
   *   Data received.
   *
   * @return bool
   *   TRUE if valid, otherwise FALSE.
   */
  private function isValidData($data) {
    $valid = TRUE;
    $validator = new Validator();
    $systemDataCreateSchema = new SystemDataCreateSchema();
    $validator->check($data, $systemDataCreateSchema->getCreateSchema());
    if (!$validator->isValid()) {
      $msg = '';
      foreach ($validator->getErrors() as $error) {
        $msg .= 'Field: ' . $error['property'] . '. Error: ' . $error['message'];
      }
      $this->logger->error('Incorrect data received: @msg.', ['@msg' => $msg]);
      $valid = FALSE;
    }
    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method): Route {
    $route = parent::getBaseRoute($canonical_path, $method);
    // Set ID validation pattern.
    if ($method !== 'POST') {
      $route->setRequirement('id', '\d+');
    }
    return $route;
  }

  /**
   * Clear views cache.
   */
  protected function clearViewsCache() {
    \Drupal::service('cache_rebuild')->rebuild();
  }
}
