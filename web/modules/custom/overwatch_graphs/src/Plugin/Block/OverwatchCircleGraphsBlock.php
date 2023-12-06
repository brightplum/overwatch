<?php

namespace Drupal\overwatch_graphs\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\overwatch_graphs\Service\OverwatchGraphServices;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that displays circle chart for the events content type.
 *
 * @Block(
 *   id = "overwatch_graphs_circle_chart_block",
 *   admin_label = @Translation("Circle Graph for Events"),
 *   category = @Translation("Overwatch"),
 * )
 */
class OverwatchCircleGraphsBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The Overwatch graphs services.
   *
   * @var \Drupal\overwatch_graphs\Service\OverwatchGraphServices
   */
  protected $overwatchGraphServices;

  /**
   * Constructs a new OverwatchCircleGraphsBlock.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverwatchGraphServices $overwatchGraphServices) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->overwatchGraphServices = $overwatchGraphServices;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('overwatch_graphs.overwatch_graph_services')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    // Check if the user is authenticated.
    return AccessResult::allowedIf($account->isAuthenticated());

    $data_sets = [
      'node' => $this->overwatchGraphServices->getData('node'),
      'block' => $this->overwatchGraphServices->getData('block'),
      'user' => $this->overwatchGraphServices->getData('user'),
      'error' => $this->overwatchGraphServices->getData('error'),
    ];

    foreach ($data_sets as $data_set) {
      if (!empty($data_set['data']) || !empty($data_set['categories'])) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Obtain data sets for each entity type.
    $data_sets = [
      'node' => $this->overwatchGraphServices->getData('node'),
      'block' => $this->overwatchGraphServices->getData('block'),
      'user' => $this->overwatchGraphServices->getData('user'),
      'error' => $this->overwatchGraphServices->getData('error'),
    ];

    // Create three chart elements using the data sets.
    $chart1 = $this->overwatchGraphServices->createChartCircleElement(
      $data_sets['node']['data'],
      $this->sanitizeCategories($data_sets['node']['categories']),
      $this->t('Content Type'),
      '#31227c'
    );
    $chart2 = $this->overwatchGraphServices->createChartCircleElement(
      $data_sets['block']['data'],
      $this->sanitizeCategories($data_sets['block']['categories']),
      $this->t('Block Type'),
      '#f47a31'
    );
    $chart3 = $this->overwatchGraphServices->createChartCircleElement(
      $data_sets['user']['data'],
      $this->sanitizeCategories($data_sets['user']['categories']),
      $this->t('Users'),
      '#6f893a'
    );

    $chart4 = $this->overwatchGraphServices->createChartCircleElement(
      $data_sets['error']['data'],
      $this->sanitizeCategories($data_sets['error']['categories']),
      $this->t('Error'),
      '#D4AE77'
    );

    // Add the chart elements to a render array.
    $render_array = [
      'chart1' => $chart1,
      'chart2' => $chart2,
      'chart3' => $chart3,
      'chart4' => $chart4,
      '#attributes' => [
        'class' => ['chart-block'],
      ],
      '#attached' => [
        'library' => ['overwatch_graphs/chart-styles'],
      ],
    ];

    return $render_array;
  }

  /**
   * Sanitize categories array.
   *
   * @param array $categories
   *   An array of category strings to be modified.
   *
   * @return array
   *   An array of modified categories.
   */
  private function sanitizeCategories($categories) {
    $modifiedCategories = array_map(function ($category) {
      // Replace underscores with spaces.
      $category = str_replace('_', ' ', $category);

      // Capitalize the first letter of each word.
      $category = ucwords($category);

      return $category;
    }, $categories);

    return $modifiedCategories;
  }

}
