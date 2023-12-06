<?php

namespace Drupal\overwatch\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config form of Overwatch.
 */
class OverwatchAdminForm extends ConfigFormBase {

  /**
   * Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Overwatch Rest Authentication.
   *
   * @var \Drupal\Core\DependencyInjection\ServiceProviderInterface
   */
  protected $overwatchRestAuth;

  /**
   * OverwatchConfigForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\DependencyInjection\ServiceProviderInterface
   *   Overwatch Rest Authentication.
   */
  public function __construct(MessengerInterface $messenger, ConfigFactoryInterface $configFactory, TimeInterface $time, ServiceProviderInterface $overwatchRestAuth) {
    $this->messenger = $messenger;
    $this->configFactory = $configFactory;
    $this->time = $time;
    $this->overwatchRestAuth = $overwatchRestAuth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('overwatch.rest_authentication')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'overwatch_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'overwatch.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('overwatch.settings');

    $form['connection_status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Connection Status'),
      '#description' => $this->t('Shows current status with Overwatch platform.'),
    ];

    // Get current time.
    $current_time = $this->time->getRequestTime();

    // Check if token is expired.
    $expired = TRUE;
    $expires_in = $config->get('expires_in') ?? NULL;
    if ($expires_in) {
      // Compare current time when time token expires.
      $expired = $current_time > $expires_in;
    }

    // Get remaining days for token expiration.
    $remaining_days = NULL;
    if (!$expired) {
      $remaining_days = round(($expires_in - $current_time) / 86400);
    }

    // Check if token still valid.
    $is_valid_token = $config->get('access_token') && !$expired;

    // Build status message.
    $text_color = $is_valid_token ? 'green' : 'red';
    $message = $is_valid_token
      ? $this->t('Connected and token is saved, remaining time for expiration: @days days', [
        '@days' => $remaining_days,
      ])
      : $this->t('Not connected, a token request is needed');

    $form['connection_status']['status'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'style' => ['font-size: 16px; font-weight: bold; color: ' . $text_color . ';'],
      ],
      '#value' => $message,
    ];

    $form['connection_status']['test_connection'] = [
      '#type' => 'submit',
      '#attributes' => [
        'style' => ['margin: 5px 0 10px;'],
      ],
      '#value' => $this->t('Request Token'),
      '#submit' => ['::testConnectionSubmit'],
    ];

    $form['user_credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User Credentials'),
      '#description' => $this->t('Credentials for user in Overwatch platform.'),
    ];

    $form['user_credentials']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('username'),
    ];

    $form['user_credentials']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('password'),
      '#description' => $config->get('password')
        ? $this->t('Password saved')
        : $this->t("Add user's password"),
      '#attributes' => [
        'placeholder' => '************',
      ],
    ];

    $form['api_credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User Credentials'),
      '#description' => $this->t('Credentials to connect to the Overwatch API.'),
    ];

    $form['api_credentials']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['api_credentials']['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config->get('api_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit callback for the "Test Connection" button.
   */
  public function testConnectionSubmit(array &$form, FormStateInterface $form_state) {
    // Retrieve the form values.
    $username = $form_state->getValue('username');
    $password = $form_state->getValue('password');
    $api_key = $form_state->getValue('api_key');
    $api_secret = $form_state->getValue('api_secret');

    // Get current time.
    $current_time = $this->time->getRequestTime();

    // Parameters to be sent in the request body.
    $requestBody = [
      'client_id' => $api_key,
      'client_secret' => $api_secret,
      'username' => $username,
      'password' => $password,
      'grant_type' => 'client_credentials',
      'scope' => 'rest_api',
    ];

    // Request token.
    $data = $this->overwatchRestAuth->requestToken($requestBody);
    if (empty($data['access_token']) || empty($data['expires_in'])) {
      $this->messenger->addError($this->t('There was an error requesting token, check logs for details.'));
      return;
    }

    $token = $data['access_token'];
    $expires_in = $data['expires_in'];

    // Save the token for later use, e.g., to transmit data to Overwatch.
    $overwatch_settings = $this->configFactory->getEditable('overwatch.settings');
    $overwatch_settings
      ->set('access_token', $token)
      ->set('expires_in', ($current_time + $expires_in))
      ->save();

    $this->messenger->addStatus($this->t('Connection test successful. Token retrieved and saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('overwatch.settings')
      ->set('username', $form_state->getValue('username'))
      ->set('password', $form_state->getValue('password'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_secret', $form_state->getValue('api_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
