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

/**
 * Represents Event records as resources.
 *
 * @RestResource (
 *   id = "overwatch_bp_rest_event_resource",
 *   label = @Translation("Event Resource"),
 *   uri_paths = {
 *     "create" = "/api/overwatch/event"
 *   }
 * )
 */
class EventResource extends ResourceBase {

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
    // Get fields.
    $map_fields = $this->eventMap();

    try {
      // Validate incoming data to have expected keys.
      $data = array_combine(array_keys($map_fields), $data);
    }
    catch (\ValueError $e) {
      $this->logger->error('Incorrect data received: @msg.', ['@msg' => $e->getMessage()]);
      throw new BadRequestHttpException('Incorrect data received, check logs.');
    }

    // Validates incoming data against schema.
    if (!$this->isValidData($data)) {
      throw new BadRequestHttpException('Incorrect data received, check logs.');
    }

    if (!$this->validListFieldsValues($data)) {
      throw new BadRequestHttpException('Invalid entity value, check logs.');
    }

    try {
      // Create new event.
      $new_event = $this->entityTypeManager
        ->getStorage('node')
        ->create(['type' => 'event']);

      foreach ($map_fields as $field => $machine_name) {
        if (!empty($data[$field])) {
          // Set value on field for Event entity.
          $new_event->set($machine_name, $data[$field]);
        }
      }

      $new_event->enforceIsNew();
      $new_event->save();

      // Log id of new event created.
      $this->logger->notice('Created new event @id.', ['@id' => $new_event->id()]);

      // Add to data new id assigned by Drupal.
      $data['id'] = $new_event->id();

      // Return the newly created record in the response body.
      return new ModifiedResourceResponse($data, 201);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
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
    $eventCreateSchema = new EventCreateSchema();
    $validator->check($data, $eventCreateSchema->getCreateSchema());
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
   * Validate values of fields with lists.
   *
   * @param array $data
   *   Data received.
   *
   * @return bool
   *   TRUE if valid, otherwise FALSE.
   */
  private function validListFieldsValues($data) {
    $valid = TRUE;

    // Validate entity type.
    $event_fields_definition = $this->entityFieldManager->getFieldDefinitions('node', 'event');

    // Get allowed values for entity.
    $entity_settings = $event_fields_definition['field_event_entity']->getSettings();
    $entity_allowed_values = array_keys($entity_settings['allowed_values']);
    if (!in_array($data['entity'], $entity_allowed_values)) {
      $this->logger->error('Invalid entity value, allowed: @msg.', ['@msg' => implode(',', $entity_allowed_values)]);
      $valid = FALSE;
    }

    // Get allowed values for severity.
    $severity_settings = $event_fields_definition['field_event_severity']->getSettings();
    $severity_allowed_values = array_keys($severity_settings['allowed_values']);
    if (!in_array($data['severity'], $severity_allowed_values)) {
      $this->logger->error('Invalid severity value, allowed: @msg.', ['@msg' => implode(',', $severity_allowed_values)]);
      $valid = FALSE;
    }

    // Get allowed values for type.
    $type_settings = $event_fields_definition['field_event_type']->getSettings();
    $type_allowed_values = array_keys($type_settings['allowed_values']);
    if (!in_array($data['type'], $type_allowed_values)) {
      $this->logger->error('Invalid type value, allowed: @msg.', ['@msg' => implode(',', $type_allowed_values)]);
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
   * Helper function to map expected fields with Drupal's fields.
   *
   * @return string[]
   *   Array where keys are json fields and values drupal's.
   */
  private function eventMap() {
    return [
      'uuid' => 'field_event_uuid',
      'title' => 'title',
      'author' => 'field_event_author',
      'bundle' => 'field_event_bundle',
      'entity' => 'field_event_entity',
      'timestamp' => 'field_event_timestamp',
      'type' => 'field_event_type',
      'site_base_url' => 'field_site_base_url',
      'site_machine_name' => 'field_site_machine_name',
      'site_name' => 'field_site_name',
      'severity'  => 'field_event_severity',
      'context' => 'field_event_context',
    ];
  }

}
