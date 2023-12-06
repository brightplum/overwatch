<?php

namespace Drupal\overwatch_system_data\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\overwatch_system_data\Service\OverwatchSystemDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the Overwatch System Data.
 */
class OverwatchSystemDataController extends ControllerBase {

  /**
   * The database manager service.
   *
   * @var \Drupal\overwatch_system_data\Service\OverwatchSystemDataService
   */
  protected $databaseManager;

  /**
   * Constructs a new OverwatchSystemDataController object.
   *
   * @param \Drupal\overwatch_system_data\Service\OverwatchSystemDataService $databaseManager
   *   The database manager service.
   */
  public function __construct(OverwatchSystemDataService $databaseManager) {
    $this->databaseManager = $databaseManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('overwatch_system_data.system_data_service')
    );
  }

  /**
   * Returns the render array for the Overwatch System Data page.
   *
   * @return array
   *   A render array.
   */
  public function content() {
    $client_site = $this->databaseManager->getClientSite();

    $data = [
      'client_site' => $client_site,
    ];

    $content = [
      '#theme' => 'system_data_dashboard',
      '#data' => $data,
    ];

    $content['#cache'] = [
      'max-age' => 0,
    ];

    $content['#attached']['library'][] = 'overwatch_system_data/overwatch-system-data';

    return $content;
  }

  /**
   * Provides JSON data for the latest version of system data.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack to access the current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the latest system data.
   */
  public function systemDataTechnicalJsonData() {
    // Retrieve the latest version of system data for each site.
    $data = $this->databaseManager->getLatestVersionBasedOnUrlParam();
    // Return the data as a JSON response.
    return new JsonResponse($data);
  }

  /**
   * Provides JSON data for the latest version of system data only Errors.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack to access the current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the latest system data.
   */
  public function systemDataTechnicalJsonDataErrors() {
    // Retrieve the latest version of system data errors for each site.
    $data = $this->databaseManager->getErrorsWarningForSystemData('error');
    // Return the data as a JSON response.
    return new JsonResponse($data);
  }

  /**
   * Provides JSON data for the latest version of system data only warnings.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack to access the current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the latest system data.
   */
  public function systemDataTechnicalJsonDataWanings() {
    // Retrieve the latest version of system data warning for each site.
    $data = $this->databaseManager->getErrorsWarningForSystemData('warning');
    // Return the data as a JSON response.
    return new JsonResponse($data);
  }

  /**
   * Get JSON data for extensions.
   *
   * @param string|null $type
   *   The type of extension data to retrieve. Null for all, 'security' for security updates, 'update' for all updates.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the extension data.
   */
  public function systemDataTechnicalJsonDataExtensions($type = NULL) {
    $data = $this->databaseManager->getExtensionInformationForSystemData($type);
    return new JsonResponse($data);
  }

  /**
   * Provides JSON data for the latest version of system data for a specific node.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the latest system data for the specified node.
   */
  public function systemDataTechnicalJsonDataByNode() {
    $data = $this->databaseManager->getLatestVersionBasedOnUrlParam(TRUE);
    return new JsonResponse($data);
  }

  /**
   * Provides JSON data for the latest version of system data errors for a specific node.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the latest system data errors for the specified node.
   */
  public function systemDataTechnicalJsonDataErrorsByNode() {
    $data = $this->databaseManager->getErrorsWarningForSystemData('error', TRUE);
    return new JsonResponse($data);
  }

  /**
   * Provides JSON data for the latest version of system data warnings for a specific node.
   *
   * @param int $node_id
   *   The ID of the node to retrieve warning data for.
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the latest system data warnings for the specified node.
   */
  public function systemDataTechnicalJsonDataWarningsByNode() {
    $data = $this->databaseManager->getErrorsWarningForSystemData('warning', TRUE);
    return new JsonResponse($data);
  }

  /**
   * Get JSON data for extensions for a specific node.
   *
   * @param string|null $type
   *   The type of extension data to retrieve. Null for all, 'security' for security updates, 'update' for all updates.
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the extension data for the specified node.
   */
  public function systemDataTechnicalJsonDataExtensionsByNode($type = NULL) {
    $data = $this->databaseManager->getExtensionInformationForSystemData($type, TRUE);
    return new JsonResponse($data);
  }

}
