<?php

namespace Drupal\drupal_headless\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_headless\Service\ConfigurationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Drupal Headless Module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The configuration manager service.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configManager = $container->get('drupal_headless.configuration_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['drupal_headless.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_headless_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('drupal_headless.settings');

    // Check dependencies first.
    $missing = $this->configManager->checkDependencies();
    if (!empty($missing)) {
      $this->messenger()->addWarning($this->t('Required modules are missing: @modules', [
        '@modules' => implode(', ', $missing),
      ]));
    }

    $form['cors'] = [
      '#type' => 'details',
      '#title' => $this->t('CORS Configuration'),
      '#open' => TRUE,
    ];

    $form['cors']['enable_cors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CORS'),
      '#description' => $this->t('Allow cross-origin requests from frontend applications.'),
      '#default_value' => $config->get('enable_cors'),
    ];

    $form['cors']['cors_allowed_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed Origins'),
      '#description' => $this->t('Enter one origin URL per line. Example: https://example.com'),
      '#default_value' => implode("\n", $config->get('cors_allowed_origins') ?? []),
      '#states' => [
        'visible' => [
          ':input[name="enable_cors"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security Settings'),
      '#open' => FALSE,
    ];

    $form['security']['enable_rate_limiting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Rate Limiting'),
      '#description' => $this->t('Limit the number of API requests per time window.'),
      '#default_value' => $config->get('enable_rate_limiting'),
    ];

    $form['security']['rate_limit_requests'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Requests'),
      '#description' => $this->t('Maximum number of requests allowed within the time window.'),
      '#default_value' => $config->get('rate_limit_requests') ?? 100,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="enable_rate_limiting"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['security']['rate_limit_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Time Window (seconds)'),
      '#description' => $this->t('Time window in seconds for rate limiting.'),
      '#default_value' => $config->get('rate_limit_window') ?? 3600,
      '#min' => 60,
      '#states' => [
        'visible' => [
          ':input[name="enable_rate_limiting"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['oauth'] = [
      '#type' => 'details',
      '#title' => $this->t('OAuth2 Settings'),
      '#open' => FALSE,
    ];

    $form['oauth']['auto_configure_oauth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-configure OAuth2'),
      '#description' => $this->t('Automatically configure OAuth2 settings when creating new consumers.'),
      '#default_value' => $config->get('auto_configure_oauth'),
    ];

    $form['oauth']['default_token_expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Token Expiration (seconds)'),
      '#description' => $this->t('Default expiration time for OAuth2 tokens.'),
      '#default_value' => $config->get('default_token_expiration') ?? 3600,
      '#min' => 300,
      '#max' => 86400,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate CORS origins.
    $origins_text = $form_state->getValue('cors_allowed_origins');
    if (!empty($origins_text)) {
      $origins = array_filter(array_map('trim', explode("\n", $origins_text)));

      foreach ($origins as $origin) {
        if (!filter_var($origin, FILTER_VALIDATE_URL)) {
          $form_state->setErrorByName('cors_allowed_origins', $this->t('Invalid URL: @url', ['@url' => $origin]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process CORS origins.
    $origins_text = $form_state->getValue('cors_allowed_origins');
    $origins = [];
    if (!empty($origins_text)) {
      $origins = array_filter(array_map('trim', explode("\n", $origins_text)));
    }

    $this->config('drupal_headless.settings')
      ->set('enable_cors', $form_state->getValue('enable_cors'))
      ->set('cors_allowed_origins', array_values($origins))
      ->set('enable_rate_limiting', $form_state->getValue('enable_rate_limiting'))
      ->set('rate_limit_requests', $form_state->getValue('rate_limit_requests'))
      ->set('rate_limit_window', $form_state->getValue('rate_limit_window'))
      ->set('auto_configure_oauth', $form_state->getValue('auto_configure_oauth'))
      ->set('default_token_expiration', $form_state->getValue('default_token_expiration'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
