<?php

namespace Drupal\overwatch_bp_dropdown\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\overwatch_bp_dropdown\Service\ClientSwitcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a custom form for the Overwatch BP Dropdown.
 */
class OverwatchBPDropdownForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The ClientSwitcher service.
   *
   * @var \Drupal\overwatch_bp_dropdown\Service\ClientSwitcher
   */
  protected $clientSwitcher;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Constructs a new OverwatchBPDropdownForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\overwatch_bp_dropdown\Service\ClientSwitcher $clientSwitcher
   *   The ClientSwitcher service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Request $request,
    Connection $database,
    ClientSwitcher $clientSwitcher,
    CacheBackendInterface $cache,
    CurrentRouteMatch $currentRouteMatch
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
    $this->database = $database;
    $this->clientSwitcher = $clientSwitcher;
    $this->cache = $cache;
    $this->currentRouteMatch = $currentRouteMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('database'),
      $container->get('overwatch_bp_dropdown.client_switcher'),
      $container->get('cache.default'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'overwatch_bp_dropdown_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Load options (cached or updated) for the dropdown.
    $options = $this->loadOptions();

    // Get the client_site parameter from the URL.
    $client_site = $this->request->query->get('client_site');

    // Dropdown element.
    $form['site_machine_name'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $client_site ?? '',
      '#attributes' => [
        'onchange' => 'this.form.submit();',
      ],
    ];

    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['visually-hidden']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $site_machine_name = $form_state->getValue('site_machine_name');

    if (!empty($site_machine_name)) {
      // Redirect to the same page with a query parameter.
      $url = Url::fromRoute('<current>');

      // Get the existing query parameters.
      $query_params = $this->request->query->all();

      // Add or overwrite the 'client_site' query parameter.
      $query_params['client_site'] = $site_machine_name;

      // Set the updated query parameters in the URL object.
      $url->setOption('query', $query_params);
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * Loads options for the dropdown element, either from cache or by updating.
   *
   * @return array
   *   An array of options for the dropdown element.
   */
  private function loadOptions() {
    // Check if the cached options are available.
    $cache_key = 'overwatch_cached_options';
    $cache = $this->cache->get($cache_key);

    if ($cache) {
      $options = $cache->data;
    }
    else {
      // If cached options are not available, update and cache them.
      $options = $this->clientSwitcher->getOptions();
      $this->cache->set($cache_key, $options, Cache::PERMANENT, ['overwatchbp']);
    }

    $current_path = $this->currentRouteMatch->getRouteName();
    if ($current_path === 'overwatch_system_data.content') {
      $options = ['all' => 'All'] + $options;
    }

    return $options;
  }

}
