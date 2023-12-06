<?php

namespace Drupal\overwatch_system_data\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\overwatch_system_data\Service\OverwatchSystemDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that displays System Data Summary By Site.
 *
 * @Block(
 *   id = "system_data_summary_by_site_block",
 *   admin_label = @Translation("System data summary by site"),
 *   category = @Translation("Overwatch"),
 * )
 */
class SystemDataSummayBySite extends BlockBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The database manager service.
   *
   * @var \Drupal\overwatch_system_data\Service\OverwatchSystemDataService
   */
  protected $databaseManager;

  /**
   * Constructs a new OverwatchSystemDataService.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverwatchSystemDataService $databaseManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->databaseManager = $databaseManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('overwatch_system_data.system_data_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    // Check if the user is authenticated.
    return AccessResult::allowedIf($account->isAuthenticated());
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Obtain data sets for each entity type.
    $data = $this->databaseManager->processAndStructureDataToSummaryBySite();

    // Add the chart elements to a render array.
    $render_array = [
      '#theme' => 'system_data_summary',
      '#data' => $data,
      '#attached' => [
        'library' => ['overwatch_system_data/overwatch-system-data'],
      ],
    ];

    $render_array['#cache'] = [
      'max-age' => 0,
    ];

    return $render_array;
  }

}
