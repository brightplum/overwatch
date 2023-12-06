<?php

namespace Drupal\overwatch_system_data\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for database operations in the Overwatch System Data module.
 */
class OverwatchSystemDataService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new OverwatchSystemDataService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack to handle HTTP requests.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
  }

  /**
   * Generates a subquery to find the latest node id (nid) for each 'field_site_machine_name_value'.
   * If withHistory is true, limits results to nodes created in the last month.
   *
   * @param bool $withHistory 
   *   Determines whether to limit results to the past month.
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The constructed subquery.
   */
  public function generateSubquery($withHistory) {
    // Create a subquery to find the latest node id (nid) for each 'field_site_machine_name_value'.
    $subquery = $this->database->select('node__field_site_machine_name', 'sm');
    $subquery->join('node_field_data', 'nfd', 'sm.entity_id = nfd.nid');
    $subquery->condition('nfd.type', 'systemdata');
    $subquery->addField('sm', 'field_site_machine_name_value', 'site_machine_name');

    if ($withHistory) {
      // Limit to nodes created in the last month.
      $oneMonthAgo = strtotime('-1 month');
      $subquery->condition('nfd.created', $oneMonthAgo, '>=');
      $subquery->addField('sm', 'entity_id', 'latest_nid');
    }
    else {
      $subquery->addExpression('MAX(sm.entity_id)', 'latest_nid');
      $subquery->groupBy('sm.field_site_machine_name_value');
    }

    // You must add the 'latest_nid' field or expression before you can order by it.
    $subquery->orderBy('latest_nid', 'DESC');

    $clientSite = $this->getClientSite();
    // Filter for a specific site if the parameter is not 'all'.
    if ($clientSite !== 'all') {
      $subquery->condition('sm.field_site_machine_name_value', $clientSite);
    }

    return $subquery;
  }

  /**
   * Retrieves the latest version of sites based on the URL parameter.
   *
   * This method checks the 'client_site' URL parameter. If it's 'all', it fetches
   * the latest version for each site. Otherwise, it fetches the latest version
   * for the specified site.
   *
   * @param string $type
   *   The type of extension information to filter by.
   * @param boolean $withHistory
   *   The node id.
   * @return array
   *   An array of the latest versions of sites based on the URL parameter.
   */
  public function getLatestVersionBasedOnUrlParam($withHistory = FALSE) {
    // Subquery to find the latest node id (nid) for each 'field_site_machine_name_value'.
    $subquery = $this->generateSubquery($withHistory);

    // Main query to fetch data of the latest node of each site.
    $query = $this->database->select('node_field_data', 'n');
    $query->join($subquery, 'latest', 'n.nid = latest.latest_nid');
    $query->fields('n', ['nid', 'title', 'created']);
    $query->condition('n.type', 'systemdata');

    // Add joins for additional fields.
    $query->leftJoin('node__field_core_version_number', 'cvn', 'n.nid = cvn.entity_id');
    $query->leftJoin('node__field_all_updates', 'au', 'n.nid = au.entity_id');
    $query->leftJoin('node__field_extension_count', 'ec', 'n.nid = ec.entity_id');
    $query->leftJoin('node__field_last_cron_run_time', 'lcrt', 'n.nid = lcrt.entity_id');
    $query->leftJoin('node__field_security_updates', 'su', 'n.nid = su.entity_id');
    $query->leftJoin('node__field_site_type', 'st', 'n.nid = st.entity_id');
    $query->leftJoin('node__field_status_report', 'sr', 'n.nid = sr.entity_id');
    $query->leftJoin('node__field_error_number', 'ne', 'n.nid = ne.entity_id');
    $query->leftJoin('node__field_warning_number', 'nw', 'n.nid = nw.entity_id');
    $query->leftJoin('node__field_site_machine_name', 'sm', 'n.nid = sm.entity_id');

    // Add fields to select from the joined tables.
    $query->addField('cvn', 'field_core_version_number_value', 'core_version_number');
    $query->addField('au', 'field_all_updates_value', 'all_updates');
    $query->addField('ec', 'field_extension_count_value', 'extension_count');
    $query->addField('lcrt', 'field_last_cron_run_time_value', 'last_cron_run_time');
    $query->addField('su', 'field_security_updates_value', 'security_updates');
    $query->addField('st', 'field_site_type_value', 'site_type');
    $query->addField('sr', 'field_status_report_value', 'status_report');
    $query->addField('ne', 'field_error_number_value', 'errors');
    $query->addField('nw', 'field_warning_number_value', 'warnings');
    $query->addField('sm', 'field_site_machine_name_value', 'site_machine_name');
  
    // Execute the query and return the result.
    $result = $query->execute()->fetchAll();
    return $result;
  }

  /**
   * Retrieves errors and warnings associated with 'systemdata' content type.
   *
   * @param string $type
   *   The type of extension information to filter by.
   * @param boolean $withHistory
   *   The node id.
   * @return array
   *   An array of extension information node data.
   */
  public function getErrorsWarningForSystemData($type, $withHistory = FALSE) {
    // Subquery to find the latest node id (nid) for each 'field_site_machine_name_value'.
    $subquery = $this->generateSubquery($withHistory);

    // Main query to fetch error data for each 'systemdata' site.
    $query = $this->database->select('node_field_data', 'n');
    $query->join($subquery, 'latest', 'n.nid = latest.latest_nid');
    $query->fields('n', ['nid', 'title', 'created']);
    $query->condition('n.type', 'systemdata');

    // Join with the error warning reference field.
    $query->leftJoin('node__field_error_warning', 'few', 'n.nid = few.entity_id');
    $query->leftJoin('node_field_data', 'error', 'few.field_error_warning_target_id = error.nid');
    $query->leftJoin('node__field_description', 'nfd', 'few.field_error_warning_target_id = nfd.entity_id');
    $query->leftJoin('node__field_type', 'nft', 'few.field_error_warning_target_id = nft.entity_id');
    $query->leftJoin('node__field_timestamp', 'nfti', 'few.field_error_warning_target_id = nfti.entity_id');
    $query->leftJoin('node__field_site_machine_name', 'sm', 'n.nid = sm.entity_id');

    // Add specific fields related to error nodes.
    $query->fields('few', ['field_error_warning_target_id']);
    $query->fields('error', ['title', 'nid']);
    $query->fields('nfd', ['field_description_value']);
    $query->fields('nft', ['field_type_value']);
    $query->fields('nfti', ['field_timestamp_value']);
    $query->fields('sm', ['field_site_machine_name_value']);
    $query->condition('error.type', 'ErrorWarning');
    $query->condition('nft.field_type_value', $type);

    // Execute the query and return the result.
    $result = $query->execute()->fetchAll();
    return $result;
  }

  /**
   * Retrieves extension information associated with 'systemdata' content type.
   *
   * @param string $type
   *   The type of extension information to filter by.
   * @param boolean $withHistory
   *   The node id.
   * @return array
   *   An array of extension information node data.
   */
  public function getExtensionInformationForSystemData($type, $withHistory = FALSE) {
    // Subquery to find the latest node id (nid) for each 'field_site_machine_name_value'.
    $subquery = $this->generateSubquery($withHistory);

    // Main query to fetch extension information for each 'systemdata' site.
    $query = $this->database->select('node_field_data', 'n');
    $query->join($subquery, 'latest', 'n.nid = latest.latest_nid');
    $query->fields('n', ['nid', 'title', 'created']);
    $query->condition('n.type', 'systemdata');

    // Join with the extension information reference field.
    $query->leftJoin('node__field_extension_information', 'fei', 'n.nid = fei.entity_id');
    $query->leftJoin('node_field_data', 'extension', 'fei.field_extension_information_target_id = extension.nid');
    $query->leftJoin('node__field_current_version', 'fcv', 'fei.field_extension_information_target_id = fcv.entity_id');
    $query->leftJoin('node__field_recommend_version', 'frv', 'fei.field_extension_information_target_id = frv.entity_id');
    $query->leftJoin('node__field_security_update', 'fsu', 'fei.field_extension_information_target_id = fsu.entity_id');
    $query->leftJoin('node__field_update_available', 'fua', 'fei.field_extension_information_target_id = fua.entity_id');
    $query->leftJoin('node__field_site_machine_name', 'sm', 'n.nid = sm.entity_id');

    // Add specific fields related to extension information nodes.
    $query->fields('extension', ['title', 'nid']);
    $query->fields('fcv', ['field_current_version_value']);
    $query->fields('frv', ['field_recommend_version_value']);
    $query->fields('fsu', ['field_security_update_value']);
    $query->fields('fua', ['field_update_available_value']);
    $query->fields('sm', ['field_site_machine_name_value']);
    $query->condition('extension.type', 'ExtensionInformation');

    switch ($type) {
      case 'security':
        $query->condition('fsu.field_security_update_value', TRUE);
        break;
      case 'update':
        $query->condition('fua.field_update_available_value', TRUE);
        break;
    }

    // Execute the query and return the result.
    $result = $query->execute()->fetchAll();
    return $result;
  }

  /**
   * Processes and structures data from the latest version of each site.
   *
   * @return array
   *  The structured data.
   */
  public function processAndStructureDataToSummaryBySite() {
    // Retrieve data using the existing method.
    $raw_data = $this->getLatestVersionBasedOnUrlParam();
  
    $errors = 0;
    $warnings = 0;
    $security_updates = 0;
    $all_updates = 0;
    // Process and structure the raw data.
    foreach ($raw_data as $item) {

      $errors += $item->errors;
      $warnings += $item->warnings;
      $security_updates += $item->security_updates;
      $all_updates += $item->all_updates;
    }
  
    // Add data to the structured data array.
    $render_array = [
      'errors' => $errors,
      'warnings' => $warnings,
      'security_updates' => $security_updates,
      'all_updates' => $all_updates,
    ];
    return $render_array;
  }

  /**
   * Retrieves the 'client_site' parameter from the current URL.
   *
   * @return string
   *   The value of the 'client_site' parameter or 'all' if not present.
   */
  public function getClientSite() {
    // Retrieve the current request from the request stack.
    $currentRequest = $this->requestStack->getCurrentRequest();
  
    // Default to 'all' if the parameter is not present.
    $clientSite = $currentRequest->query->get('client_site', 'all');
  
    return $clientSite;
  }

}
