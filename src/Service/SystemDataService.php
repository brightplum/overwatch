<?php

namespace Drupal\overwatch\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Element\StatusReport;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\update\UpdateManager;
use Drupal\system\SystemManager;

/**
 * Service to retrieve update, error, and warning information for a Drupal site.
 */
class SystemDataService {
  use StringTranslationTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The update manager.
   *
   * @var \Drupal\update\UpdateManager
   */
  protected $updateManager;

  /**
   * The string translation service.
   *
   * Provides a way to translate strings for localization.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The system manager service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SystemDataService object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\update\UpdateManager $update_manager
   *   The update manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service for translating strings.
   * @param \Drupal\system\SystemManager $system_manager
   *   The system manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    UpdateManager $update_manager,
    TranslationInterface $string_translation,
    SystemManager $system_manager,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->loggerFactory = $logger_factory;
    $this->updateManager = $update_manager;
    $this->stringTranslation = $string_translation;
    $this->systemManager = $system_manager;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * Formats the collected data into a predefined JSON format.
   *
   * @return string
   *   The data formatted as a JSON string.
   */
  public function formatUpdateErrorWarningAsJson() {
    // Extract relevant components from the data.
    $errors_warnings = $this->getErrorAndWarning();
    $site_info = $this->getSiteInformation();
    $extensions_updates = $this->getExtendensUpdates();

    // Reformat and restructure the information.
    $formattedData = [
      "site_name" => $site_info['site_name'],
      "site_type" => $site_info['site_type'],
      "site_machine_name" => $site_info['site_machine_name'],
      "core_version" => $site_info['core_version'],
      "report_time" => $site_info['report_time'],
      "extensions" => $extensions_updates['extensions'],
      "updates_available" => $extensions_updates['updates_available'],
      "extensions_count" => $extensions_updates['extensions_count'],
      "status_report" => $site_info['status_report'],
      "errors_and_warnings" => [
        "errors" => $errors_warnings['errors'],
        "warnings" => $errors_warnings['warnings'],
      ]
    ];

    // Convert the final array to JSON.
    return json_encode($formattedData);
  }

  /**
   * Gets the errors and warnings from the site's status report.
   *
   * @return array
   *   An associative array containing the counts of errors and warnings.
   *
   * @throws \Exception
   *   Throws exception if the status report cannot be accessed or processed.
   */
  private function getErrorAndWarning() {
    $requirements = $this->systemManager->listRequirements();
    $statusElement = [
      '#requirements' => $requirements,
      '#priorities' => [
        'error',
        'warning',
        'checked',
        'ok',
      ],
    ];

    // Call the preRenderGroupRequirements method and store the result.
    $result = StatusReport::preRenderGroupRequirements($statusElement);
    $grouped_requirements = [];
    // Check if the result is an array and contains the '#grouped_requirements' key.
    if (is_array($result) && isset($result['#grouped_requirements'])) {
      $grouped_requirements = $result['#grouped_requirements'];
    }

    // Format the errors and warnings.
    $errors = $this->formatIssues($grouped_requirements['error']['items'] ?? []);
    $warnings = $this->formatIssues($grouped_requirements['warning']['items'] ?? []);

    return [
      'errors' => $errors,
      'warnings' => $warnings,
    ];
  }

  /**
   * Retrieves various site information and status report details.
   *
   * @return array
   *   An associative array containing site information and status report details.
   */
  private function getSiteInformation() {
    $site_name_config = $this->configFactory->get('system.site');
    $site_name = $site_name_config->get('name');
    $core_version = \Drupal::VERSION;
    $php_version = phpversion();

    $requirements = $this->systemManager->listRequirements();
    $database_status = $requirements['database_system_version']['value'] ?? 'Unknown';

    // Build site machine name.
    $site_machine_name = strtolower(str_replace(' ', '_', $site_name));
    $site_machine_name = substr($site_machine_name, 0, 32);

    return [
      'site_name' => $site_name,
      'site_type' => 'Drupal',
      'site_machine_name' => $site_machine_name,
      'core_version' => $core_version,
      'report_time' => date('c'),
      'status_report' => [
        'database_system_version' => $database_status,
        'php_version' => $php_version,
      ],
    ];
  }

  /**
   * Retrieves update information for extensions on the site.
   *
   * Gathers data about available updates for all modules and themes, 
   * including whether updates are available and if they are security updates.
   *
   * @return array
   *   An associative array containing information about extension updates.
   */
  private function getExtendensUpdates() {
    $update_data = [];
    $security_updates_count = 0;
    $all_updates_count = 0;
  
    // Retrieve available updates.
    $available = update_get_available(TRUE);
    // Load necessary update comparison functions.
    $this->moduleHandler->loadinclude('update', 'compare.inc');
    // Calculate the update data for each project.
    $project_data = update_calculate_project_data($available);
  
    foreach ($project_data as $project_name => $project_info) {
      // Validate if 'recommended' is available, otherwise use an empty string.
      $recommended_version = isset($project_info['recommended']) ? $project_info['recommended'] : '';
  
      // Initialize update availability and security update flags.
      $is_update_available = false;
      $is_security_update = false;
  
      if ($recommended_version !== '') {
        // If 'recommended' is not empty, compare with 'existing_version'.
        $is_update_available = ($project_info['existing_version'] !== $recommended_version);
        // Check if the update is a security update.
        $is_security_update = $this->isSecurityUpdate($project_info);
      }
      else {
        // If 'recommended' is empty, 'is_update_available' depends on 'is_security_update'.
        $is_security_update = $this->isSecurityUpdate($project_info);
        $is_update_available = $is_security_update;
      }
  
      // Append extension update information to the update data array.
      $update_data['extensions'][] = [
        'extension_name' => $project_name,
        'current_version' => $project_info['existing_version'],
        'recommended_version' => $recommended_version,
        'update_available' => $is_update_available,
        'security_update' => $is_security_update,
      ];
  
      // Increment update counts if an update is available.
      if ($is_update_available) {
        $all_updates_count++;
        if ($is_security_update) {
          $security_updates_count++;
        }
      }
    }
  
    // Aggregate the count of updates.
    $update_data['updates_available'] = [
      'security_updates' => $security_updates_count,
      'all_updates' => $all_updates_count,
    ];
    // Count the total number of extensions.
    $update_data['extensions_count'] = count($update_data['extensions']);
    return $update_data;
  }

  /**
   * Determines if an update is a security update.
   *
   * @param array $projectInfo
   *   Project information array.
   *
   * @return bool
   *   True if it's a security update, false otherwise.
   */
  private function isSecurityUpdate($projectInfo) {
    if (isset($projectInfo['security updates'])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Formats a list of issues for output.
   *
   * Takes an array of issues (errors or warnings) and formats each one,
   * including title, description, and a timestamp.
   *
   * @param array $issues
   *   An array of issues to format.
   *
   * @return array
   *   The formatted list of issues.
   */
  private function formatIssues(array $issues) {
    $formatted = [];
    foreach ($issues as $key => $issue) {
      $description = isset($issue['description']) ? $issue['description'] : '';
      $formatted[] = [
        'title' => $this->renderTranslatableMarkup($issue['title']),
        'description' => $this->renderDescription($description),
        'timestamp' => date('c'),
      ];
    }
    return $formatted;
  }

  /**
   * Renders a translatable markup object or returns a string.
   *
   * @param mixed $markup
   *   The markup to render.
   *
   * @return string
   *   The rendered string.
   */
  private function renderTranslatableMarkup($markup) {
    return $markup instanceof TranslatableMarkup ? $markup->render() : $markup;
  }

  /**
   * Renders complex descriptions or returns a string.
   *
   * @param mixed $description
   *   The description to render.
   *
   * @return string
   *   The rendered description.
   */
  private function renderDescription($description) {
    if ($description instanceof TranslatableMarkup) {
      // Render TranslatableMarkup and convert to plain text.
      return trim(strip_tags($description->render()));
    }
    elseif ($description instanceof Markup) {
      // Convert Markup to plain text.
      return trim(strip_tags(html_entity_decode($description->__toString())));
    }
    elseif (is_array($description)) {
      // Convert array to plain text.
      $rendered = $this->renderer->renderPlain($description);
      return trim(strip_tags(html_entity_decode($rendered)));
    }
    elseif (is_string($description)) {
      // Convert string to plain text.
      return trim(strip_tags($description));
    }

    return $this->t('Not loaded');
  }

}
