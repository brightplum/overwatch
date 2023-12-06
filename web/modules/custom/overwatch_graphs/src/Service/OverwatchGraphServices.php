<?php

namespace Drupal\overwatch_graphs\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides services for Overwatch graphs.
 */
class OverwatchGraphServices {
  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new OverwatchGraphServices.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(Connection $connection, RequestStack $request_stack) {
    $this->connection = $connection;
    $this->requestStack = $request_stack;
  }

  /**
   * Fetches and organizes data for a specific entity type.
   *
   * @param string $entityType
   *   The entity type (node, block, or user).
   *
   * @return array
   *   An array containing the categories and data for the specified type.
   */
  public function getData($entityType) {
    $client_site = $this->requestStack->getCurrentRequest()->query->get('client_site');

    // If client_site parameter is not present in the URL.
    if (empty($client_site)) {
      return ['categories' => [], 'data' => []];
    }

    // Build a query to obtain data for the specified entity type.
    $query = $this->connection->select('node__field_event_bundle', 'e');
    $query->join('node__field_event_entity', 'ent', 'e.entity_id = ent.entity_id');
    $query->join('node__field_site_machine_name', 's', 'e.entity_id = s.entity_id');
    $query->join('node__field_event_timestamp', 't', 'e.entity_id = t.entity_id');

    // Adding conditions.
    $query->condition('ent.field_event_entity_value', $entityType);
    $query->condition('s.field_site_machine_name_value', $client_site);

    $query->fields('e', ['field_event_bundle_value']);
    $query->fields('t', ['field_event_timestamp_value']);
    $query->addExpression('COUNT(e.entity_id)', 'count');
    $query->groupBy('e.field_event_bundle_value');

    // Execute the query and fetch the results.
    $results = $query->execute()->fetchAll();

    $categories = [];
    $data = [];
    $dates = [];
    // Process the results to populate the categories and data arrays.
    foreach ($results as $result) {
      $categories[] = $result->field_event_bundle_value;
      $data[] = (int) $result->count;
      $dates[] = $result->field_event_timestamp_value;
    }

    return ['categories' => $categories, 'data' => $data, 'dates' => $dates];
  }

  /**
   * Creates a chart element with specified parameters.
   *
   * @param array $data
   *   The data for the chart.
   * @param array $categories
   *   The categories for the chart.
   * @param string $title
   *   The title for the chart.
   * @param string $color
   *   The color for the chart.
   *
   * @return array
   *   The render array for the chart element.
   */
  public function createChartCircleElement($data, $categories, $title, $color) {
    // If both data and categories arrays are empty, return NULL.
    if (empty($data) && empty($categories)) {
      return NULL;
    }

    // Build the series data array.
    $series_data = [
      '#type' => 'chart_data',
      '#title' => $title,
      '#data' => $data,
      '#labels' => $categories,
      '#color' => $color,
    ];

    // Build the chart element array.
    $element = [
      '#type' => 'chart',
      '#chart_type' => 'pie',
      '#chart_library' => 'charts_google',
      'series' => $series_data,
      '#title' => $title,
      '#raw_options' => [
        'options' => [
          'legend' => [
            'position' => 'none',
          ],
        ],
        'plotOptions' => [
          'series' => ['grouping' => FALSE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Creates a chart element with specified parameters.
   *
   * @param array $series_one
   *   The series data one for the chart.
   * @param array $series_two
   *   The series data two for the chart.
   *
   * @return array
   *   The render array for the chart element.
   */
  public function createChartLineElement($series_one, $series_two) {
    $element = [
      '#type' => 'chart',
      '#chart_type' => 'line',
      '#chart_library' => 'charts_google',
      'series_one' => $series_one,
      'series_two' => $series_two,
      '#data_markers' => TRUE,
      'x_axis' => [
        '#type' => 'chart_xaxis',
        '#title' => $this->t('Time'),
      ],
      'y_axis' => [
        '#type' => 'chart_yaxis',
        '#title' => $this->t('Number of Events'),
      ],
      '#raw_options' => [
        'options' => [
          'legend' => [
            'position' => 'bottom',
            'alignment' => 'start',
          ],
          'chartArea' => [
            'bottom' => 50,
          ],
        ],
      ],
    ];

    return $element;
  }

}
