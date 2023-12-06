<?php

namespace Drupal\overwatch_bp_dropdown\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for managing client switcher options.
 */
class ClientSwitcher {

  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Helper function to retrieve options based on nodes data.
   *
   * @return array
   *   An array of distinct field values.
   */
  public function getOptions() {
    $options = [];
    $values = $this->getDistinctClients();

    foreach ($values as $value) {
      if (!isset($options[$value])) {
        // Replace underscores with blank spaces.
        $formatted_value = str_replace('_', ' ', $value);

        // Uppercase the first letter of each word.
        $formatted_value = ucwords($formatted_value);

        $options[$value] = $formatted_value;
      }
    }

    return $options;
  }

  /**
   * Get distinct field values from the database.
   *
   * @return array
   *   An array of distinct field values.
   */
  public function getDistinctClients() {
    $query = $this->database->select('node__field_site_machine_name', 'nf');
    $query->fields('nf', ['field_site_machine_name_value']);
    $query->distinct();
    $result = $query->execute()->fetchAll();

    $field_values = [];
    foreach ($result as $row) {
      $field_values[] = $row->field_site_machine_name_value;
    }

    return $field_values;
  }

}
