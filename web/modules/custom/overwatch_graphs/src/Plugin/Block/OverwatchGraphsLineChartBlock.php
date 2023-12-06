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
 * Provides a block that displays three Line charts for the events content type.
 *
 * @Block(
 *   id = "overwatch_graphs_line_chart_block",
 *   admin_label = @Translation("Line Graph for All Events"),
 *   category = @Translation("Overwatch"),
 * )
 */
class OverwatchGraphsLineChartBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The Overwatch graphs services.
   *
   * @var \Drupal\overwatch_graphs\Service\OverwatchGraphServices
   */
  protected $overwatchGraphServices;

  /**
   * Constructs a new OverwatchGraphsBarChartBlock.
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
      'error' => $this->overwatchGraphServices->getData('error'),
    ];

    $series = [
      '#type' => 'chart_data',
      '#title' => $this->t('Content Events'),
      '#data' => $data_sets['node']['data'],
      '#color' => '#31227c',
    ];

    $serie2 = [
      '#type' => 'chart_data',
      '#title' => $this->t('Error Events'),
      '#data' => $data_sets['error']['data'],
      '#color' => '#f47a31',
    ];

    // Create the chart element using the unified data.
    $chart1 = $this->overwatchGraphServices->createChartLineElement($series, $serie2);

    // Add the chart element to a render array.
    $render_array = [
      'chart1' => $chart1,
      '#attributes' => [
        'class' => ['chart-block'],
      ],
      '#attached' => [
        'library' => ['overwatch_graphs/chart-styles'],
      ],
    ];
    return $render_array;
  }

}
