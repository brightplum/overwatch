<?php

namespace Drupal\overwatch_bp_rest\Plugin\rest\resource;

/**
 * Schema file to validate POST request.
 */
class EventCreateSchema {

  /**
   * JSON Schema.
   */
  public function getCreateSchema() {
    return [
      'title' => 'Event',
      'description' => 'Event JSON validation schema',
      'type' => 'array',
      'properties' => [
        'uuid' => [
          'type' => 'string',
        ],
        'title' => [
          'type' => 'string',
        ],
        'author' => [
          'type' => 'string',
        ],
        'bundle' => [
          'type' => 'string',
        ],
        'entity' => [
          'type' => 'string',
        ],
        'timestamp' => [
          'type' => 'number',
        ],
        'type' => [
          'type' => 'string',
        ],
        'site_base_url' => [
          'type' => 'string',
        ],
        'site_machine_name' => [
          'type' => 'string',
        ],
        'site_name' => [
          'type' => 'string',
        ],
        'severity' => [
          'type' => 'string',
        ],
        'context' => [
          'type' => 'string',
        ],
      ],
      'required' => [
        'uuid',
        'title',
        'author',
        'bundle',
        'entity',
        'timestamp',
        'type',
        'site_base_url',
        'site_machine_name',
        'site_name',
      ],
    ];
  }

}
