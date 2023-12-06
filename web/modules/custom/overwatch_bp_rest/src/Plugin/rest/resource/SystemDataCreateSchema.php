<?php

namespace Drupal\overwatch_bp_rest\Plugin\rest\resource;

/**
 * Schema file to validate POST request.
 */
class SystemDataCreateSchema {

  /**
   * JSON Schema.
   */
  public function getCreateSchema() {
    return [
      'title' => 'SystemData',
      'description' => 'System Data JSON validation schema',
      'type' => 'array',
      'properties' => [
        'site_name' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'site_machine_name' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'site_type' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'core_version' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'report_time' => [
          'type' => 'string',
          'format' => 'date-time',
          'required' => TRUE,
        ],
        'extensions' => [
          'type' => 'array',
          'items' => [
            'type' => 'array',
            'properties' => [
              'extension_name' => ['type' => 'string', 'required' => TRUE],
              'current_version' => ['type' => 'string', 'required' => TRUE],
              'recommended_version' => ['type' => 'string'],
              'update_available' => ['type' => 'boolean', 'required' => TRUE],
              'security_update' => ['type' => 'boolean', 'required' => TRUE],
            ],
          ],
        ],
        'updates_available' => [
          'type' => 'array',
          'properties' => [
            'security_updates' => ['type' => 'integer'],
            'all_updates' => ['type' => 'integer'],
          ],
        ],
        'extensions_count' => [
          'type' => 'integer',
        ],
        'status_report' => [
          'type' => 'array',
          'properties' => [
            'database_system_version' => ['type' => 'string'],
            'php_version' => ['type' => 'string'],
          ],
        ],
        'errors_and_warnings' => [
          'type' => 'array',
          'properties' => [
            'errors' => [
              'type' => 'array',
              'items' => [
                'type' => 'array',
                'properties' => [
                  'title' => ['type' => 'string', 'required' => TRUE],
                  'description' => ['type' => 'string'],
                  'timestamp' => ['type' => 'string', 'format' => 'date-time', 'required' => TRUE],
                ],
              ],
            ],
            'warnings' => [
              'type' => 'array',
              'items' => [
                'type' => 'array',
                'properties' => [
                  'title' => ['type' => 'string', 'required' => TRUE],
                  'description' => ['type' => 'string'],
                  'timestamp' => ['type' => 'string', 'format' => 'date-time', 'required' => TRUE],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
