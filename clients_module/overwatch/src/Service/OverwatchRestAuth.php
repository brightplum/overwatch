<?php

namespace Drupal\overwatch\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Handle Authentication methods.
 */
class OverwatchRestAuth extends ServiceProviderBase {
  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * Provides a way to display Drupal messages to the user.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The string translation service.
   *
   * Provides a way to translate strings for localization.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new OverwatchConfigForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service for displaying messages to the user.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service for translating strings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   */
  public function __construct(MessengerInterface $messenger, TranslationInterface $string_translation, ConfigFactoryInterface $configFactory) {
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->configFactory = $configFactory;
  }

  /**
   * Triggers a POST request to retrieve authentication token.
   *
   * @param $requestBody
   *   Array with form parameters.
   *
   * @return array|mixed
   *   Array with response from Overwatch.
   */
  public function requestToken($requestBody) {
    $request_response = [];

    // Initialize the Guzzle HTTP client.
    $client = new Client();

    //Get monitoring_site_url.
    $config = $this->configFactory->get('overwatch.settings');
    $monitoring_url = $config->get('monitoring_site_url');

    // Define the API endpoint for token retrieval.
    $tokenEndpoint = $monitoring_url . '/oauth/token';

    try {
      // Make an HTTP POST request to the token endpoint using the request body.
      $response = $client->post($tokenEndpoint, [
        'form_params' => $requestBody,
      ]);

      // Check the response status code.
      if ($response->getStatusCode() === 200) {
        // Connection test successful. Save the token and expire time.
        $request_response = json_decode($response->getBody()->getContents(), TRUE);
      }

      return $request_response;
    }
    catch (RequestException $e) {
      // An error occurred during the HTTP request.
      $this->messenger->addError($this->t('Connection test failed. An error occurred: @error', ['@error' => $e->getMessage()]));
      return $request_response;
    }

  }

}
