<?php

namespace Drupal\drupal_headless\Form;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\drupal_headless\Service\ConfigurationManager;
use Drupal\drupal_headless\Service\ConsumerManager;
use Drupal\drupal_headless\Service\OAuth2KeyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a multi-step setup wizard for Drupal Headless Module.
 */
class SetupWizardForm extends FormBase {

  /**
   * The configuration manager service.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * The consumer manager service.
   *
   * @var \Drupal\drupal_headless\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * The OAuth2 key manager service.
   *
   * @var \Drupal\drupal_headless\Service\OAuth2KeyManager
   */
  protected $keyManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a SetupWizardForm object.
   *
   * @param \Drupal\drupal_headless\Service\ConfigurationManager $config_manager
   *   The configuration manager service.
   * @param \Drupal\drupal_headless\Service\ConsumerManager $consumer_manager
   *   The consumer manager service.
   * @param \Drupal\drupal_headless\Service\OAuth2KeyManager $key_manager
   *   The OAuth2 key manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigurationManager $config_manager, ConsumerManager $consumer_manager, OAuth2KeyManager $key_manager, RequestStack $request_stack) {
    $this->configManager = $config_manager;
    $this->consumerManager = $consumer_manager;
    $this->keyManager = $key_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('drupal_headless.configuration_manager'),
      $container->get('drupal_headless.consumer_manager'),
      $container->get('drupal_headless.oauth2_key_manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_headless_setup_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Initialize step if not set.
    if (!$form_state->has('step')) {
      $form_state->set('step', 1);
    }

    $step = $form_state->get('step');

    // Progress indicator.
    $form['progress'] = [
      '#type' => 'markup',
      '#markup' => '<div class="setup-wizard-progress">' .
        '<h2>' . $this->t('Setup Wizard - Step @step of 5', ['@step' => $step]) . '</h2>' .
        '<div class="progress-bar">' .
        '<div class="progress-fill" style="width: ' . ($step * 20) . '%;"></div>' .
        '</div>' .
        '</div>',
      '#attached' => [
        'library' => ['drupal_headless/setup_wizard'],
      ],
    ];

    // Build the appropriate step.
    switch ($step) {
      case 1:
        $this->buildStepEnvironment($form, $form_state);
        break;

      case 2:
        $this->buildStepKeys($form, $form_state);
        break;

      case 3:
        $this->buildStepConsumer($form, $form_state);
        break;

      case 4:
        $this->buildStepCors($form, $form_state);
        break;

      case 5:
        $this->buildStepComplete($form, $form_state);
        break;
    }

    // Navigation buttons.
    $form['actions'] = ['#type' => 'actions'];

    if ($step > 1 && $step < 5) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#submit' => ['::stepBack'],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ];
    }

    if ($step < 5) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $step == 4 ? $this->t('Complete Setup') : $this->t('Next'),
        '#submit' => ['::stepForward'],
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }
    else {
      $form['actions']['finish'] = [
        '#type' => 'submit',
        '#value' => $this->t('Go to Dashboard'),
        '#submit' => ['::finish'],
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    return $form;
  }

  /**
   * Builds step 1: Environment validation.
   */
  protected function buildStepEnvironment(array &$form, FormStateInterface $form_state) {
    $form['intro'] = [
      '#markup' => '<h3>' . $this->t('Welcome to Drupal Headless Module Setup') . '</h3>' .
        '<p>' . $this->t('This wizard will guide you through setting up your headless Drupal backend in just a few minutes.') . '</p>',
    ];

    $form['environment'] = [
      '#type' => 'details',
      '#title' => $this->t('Environment Check'),
      '#open' => TRUE,
    ];

    // Check dependencies.
    $missing_deps = $this->configManager->checkDependencies();
    $deps_ok = empty($missing_deps);

    $form['environment']['dependencies'] = [
      '#type' => 'item',
      '#title' => $this->t('Required Modules'),
      '#markup' => $deps_ok
        ? '<span style="color: green;">✓ All required modules are installed</span>'
        : '<span style="color: red;">✗ Missing modules: ' . implode(', ', $missing_deps) . '</span>',
    ];

    // Check private file system.
    $file_system = \Drupal::service('file_system');
    $private_path = $file_system->realpath('private://');
    $private_ok = !empty($private_path);

    $form['environment']['private_files'] = [
      '#type' => 'item',
      '#title' => $this->t('Private File System'),
      '#markup' => $private_ok
        ? '<span style="color: green;">✓ Configured at: ' . $private_path . '</span>'
        : '<span style="color: red;">✗ Not configured</span>',
    ];

    if (!$private_ok) {
      $form['environment']['private_help'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<p>' . $this->t('Private file system is required for secure OAuth2 key storage.') . '</p>' .
          '<p>' . $this->t('Add this to your settings.php:') . '</p>' .
          '<pre>$settings[\'file_private_path\'] = \'../private\';</pre>' .
          '</div>',
      ];
    }

    // Check OpenSSL.
    $openssl_ok = function_exists('openssl_pkey_new');

    $form['environment']['openssl'] = [
      '#type' => 'item',
      '#title' => $this->t('OpenSSL Extension'),
      '#markup' => $openssl_ok
        ? '<span style="color: green;">✓ Available</span>'
        : '<span style="color: red;">✗ Not available</span>',
    ];

    // Store validation results.
    $form_state->set('environment_ok', $deps_ok && $private_ok && $openssl_ok);
  }

  /**
   * Builds step 2: OAuth2 keys generation.
   */
  protected function buildStepKeys(array &$form, FormStateInterface $form_state) {
    $form['intro'] = [
      '#markup' => '<h3>' . $this->t('OAuth2 Security Keys') . '</h3>' .
        '<p>' . $this->t('OAuth2 requires RSA keys for secure token signing. We will generate these automatically.') . '</p>',
    ];

    $keys_exist = $this->keyManager->keysExist();
    $validation = $this->keyManager->validateKeys();

    if ($keys_exist) {
      $paths = $this->keyManager->getKeyPaths();

      $form['existing_keys'] = [
        '#type' => 'details',
        '#title' => $this->t('Existing Keys Found'),
        '#open' => TRUE,
      ];

      $form['existing_keys']['info'] = [
        '#markup' => '<p>' . $this->t('OAuth2 keys already exist at: @dir', ['@dir' => $paths['dir']]) . '</p>',
      ];

      $form['existing_keys']['status'] = [
        '#markup' => $validation['status']
          ? '<span style="color: green;">✓ Keys are valid and configured</span>'
          : '<span style="color: red;">✗ Keys have issues</span>',
      ];

      $form['regenerate'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Regenerate keys (this will invalidate all existing API tokens)'),
        '#default_value' => FALSE,
      ];

      $form_state->set('keys_exist', TRUE);
    }
    else {
      $form['generate_info'] = [
        '#markup' => '<div class="messages messages--info">' .
          '<p>' . $this->t('No OAuth2 keys found. New keys will be generated automatically when you proceed.') . '</p>' .
          '<ul>' .
          '<li>' . $this->t('Key type: RSA-2048') . '</li>' .
          '<li>' . $this->t('Storage: Private file system') . '</li>' .
          '<li>' . $this->t('Permissions: Secure (private key: 0600, public key: 0644)') . '</li>' .
          '</ul>' .
          '</div>',
      ];

      $form_state->set('keys_exist', FALSE);
    }
  }

  /**
   * Builds step 3: Consumer creation.
   */
  protected function buildStepConsumer(array &$form, FormStateInterface $form_state) {
    $form['intro'] = [
      '#markup' => '<h3>' . $this->t('Create API Consumer') . '</h3>' .
        '<p>' . $this->t('Consumers are OAuth2 clients that can access your API. Create your first consumer for your frontend application.') . '</p>',
    ];

    $form['consumer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Name'),
      '#description' => $this->t('A descriptive name for your frontend application.'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('consumer_name', 'My Frontend App'),
    ];

    $form['consumer_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional description of what this consumer is used for.'),
      '#rows' => 2,
      '#default_value' => $form_state->getValue('consumer_description', ''),
    ];

    $form['framework'] = [
      '#type' => 'select',
      '#title' => $this->t('Frontend Framework'),
      '#description' => $this->t('Select your frontend framework for optimized configuration.'),
      '#options' => [
        'nextjs' => 'Next.js',
        'react' => 'React',
        'vue' => 'Vue.js',
        'astro' => 'Astro',
        'other' => 'Other',
      ],
      '#default_value' => $form_state->getValue('framework', 'nextjs'),
      '#required' => TRUE,
    ];
  }

  /**
   * Builds step 4: CORS configuration.
   */
  protected function buildStepCors(array &$form, FormStateInterface $form_state) {
    $form['intro'] = [
      '#markup' => '<h3>' . $this->t('CORS Configuration') . '</h3>' .
        '<p>' . $this->t('Cross-Origin Resource Sharing (CORS) allows your frontend to communicate with this Drupal backend.') . '</p>',
    ];

    $config = $this->configManager->getConfig();

    $form['enable_cors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CORS'),
      '#description' => $this->t('Required for headless setups. Recommended: Enable now.'),
      '#default_value' => $form_state->getValue('enable_cors', $config->get('cors.enabled') ?? TRUE),
    ];

    $form['cors_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed Origins'),
      '#description' => $this->t('One origin per line. Use * for development (not recommended for production).'),
      '#rows' => 4,
      '#default_value' => $form_state->getValue('cors_origins', $config->get('cors.allowed_origins') ?? "http://localhost:3000\nhttp://localhost:4321"),
      '#states' => [
        'visible' => [
          ':input[name="enable_cors"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['enable_rate_limiting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Rate Limiting'),
      '#description' => $this->t('Protect your API from abuse. Recommended for production.'),
      '#default_value' => $form_state->getValue('enable_rate_limiting', $config->get('rate_limiting.enabled') ?? FALSE),
    ];
  }

  /**
   * Builds step 5: Completion summary.
   */
  protected function buildStepComplete(array &$form, FormStateInterface $form_state) {
    $consumer_credentials = $form_state->get('consumer_credentials');

    $form['intro'] = [
      '#markup' => '<h3 style="color: green;">✓ ' . $this->t('Setup Complete!') . '</h3>' .
        '<p>' . $this->t('Your Drupal Headless Module is now configured and ready to use.') . '</p>',
    ];

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration Summary'),
      '#open' => TRUE,
    ];

    $form['summary']['items'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('✓ OAuth2 keys generated and configured'),
        $this->t('✓ API consumer created: @name', ['@name' => $form_state->getValue('consumer_name')]),
        $this->t('✓ CORS @status', ['@status' => $form_state->getValue('enable_cors') ? $this->t('enabled') : $this->t('disabled')]),
        $this->t('✓ Rate limiting @status', ['@status' => $form_state->getValue('enable_rate_limiting') ? $this->t('enabled') : $this->t('disabled')]),
      ],
    ];

    // Show consumer credentials.
    if ($consumer_credentials) {
      $form['credentials'] = [
        '#type' => 'details',
        '#title' => $this->t('API Credentials'),
        '#open' => TRUE,
      ];

      $form['credentials']['warning'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<strong>' . $this->t('Important: Save these credentials now!') . '</strong><br>' .
          $this->t('The client secret cannot be retrieved again after leaving this page.') .
          '</div>',
      ];

      $base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

      $form['credentials']['client_id'] = [
        '#type' => 'item',
        '#title' => $this->t('Client ID'),
        '#markup' => '<code style="background: #f5f5f5; padding: 8px; display: block; margin: 5px 0;">' .
          $consumer_credentials['client_id'] .
          '</code>',
      ];

      $form['credentials']['client_secret'] = [
        '#type' => 'item',
        '#title' => $this->t('Client Secret'),
        '#markup' => '<code style="background: #f5f5f5; padding: 8px; display: block; margin: 5px 0;">' .
          $consumer_credentials['client_secret'] .
          '</code>',
      ];

      $form['credentials']['token_url'] = [
        '#type' => 'item',
        '#title' => $this->t('Token URL'),
        '#markup' => '<code style="background: #f5f5f5; padding: 8px; display: block; margin: 5px 0;">' .
          $base_url . '/oauth/token' .
          '</code>',
      ];

      $form['credentials']['api_url'] = [
        '#type' => 'item',
        '#title' => $this->t('API URL'),
        '#markup' => '<code style="background: #f5f5f5; padding: 8px; display: block; margin: 5px 0;">' .
          $base_url . '/jsonapi' .
          '</code>',
      ];
    }

    $form['next_steps'] = [
      '#type' => 'details',
      '#title' => $this->t('Next Steps'),
      '#open' => TRUE,
    ];

    $form['next_steps']['list'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Configure your frontend application with the credentials above'),
        $this->t('Visit the <a href="@url">Dashboard</a> to manage consumers and settings', [
          '@url' => Url::fromRoute('drupal_headless.dashboard')->toString(),
        ]),
        $this->t('Check the <a href="@url">JSON:API</a> to explore available resources', [
          '@url' => Url::fromRoute('jsonapi.resource_list')->toString(),
        ]),
      ],
    ];
  }

  /**
   * Step forward submit handler.
   */
  public function stepForward(array &$form, FormStateInterface $form_state) {
    $current_step = $form_state->get('step');

    // Process current step before moving forward.
    switch ($current_step) {
      case 1:
        // Validate environment.
        if (!$form_state->get('environment_ok')) {
          $form_state->setError($form, $this->t('Please fix the environment issues before proceeding.'));
          return;
        }
        break;

      case 2:
        // Generate or regenerate keys.
        $keys_exist = $form_state->get('keys_exist');
        $regenerate = $form_state->getValue('regenerate', FALSE);

        if (!$keys_exist || $regenerate) {
          if ($regenerate) {
            $success = $this->keyManager->regenerateKeys();
          }
          else {
            $success = $this->keyManager->generateKeys();
          }

          if (!$success) {
            $form_state->setError($form, $this->t('Failed to generate OAuth2 keys. Please check the logs.'));
            return;
          }
        }
        break;

      case 3:
        // Create consumer.
        $consumer = $this->consumerManager->createConsumer(
          $form_state->getValue('consumer_name'),
          $form_state->getValue('consumer_description')
        );

        if (!$consumer) {
          $form_state->setError($form, $this->t('Failed to create consumer. Please try again.'));
          return;
        }

        // Generate and store secret.
        $secret = Uuid::generate();
        $consumer->set('secret', $secret);
        $consumer->save();

        // Store credentials for display in final step.
        $form_state->set('consumer_credentials', [
          'client_id' => $consumer->uuid(),
          'client_secret' => $secret,
          'framework' => $form_state->getValue('framework'),
        ]);
        break;

      case 4:
        // Save CORS configuration.
        $config = $this->configManager->getConfig();
        $config->set('cors.enabled', $form_state->getValue('enable_cors'));

        if ($form_state->getValue('enable_cors')) {
          $origins = array_filter(array_map('trim', explode("\n", $form_state->getValue('cors_origins'))));
          $config->set('cors.allowed_origins', implode(',', $origins));
        }

        $config->set('rate_limiting.enabled', $form_state->getValue('enable_rate_limiting'));
        $config->save();
        break;
    }

    // Move to next step.
    $form_state->set('step', $current_step + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Step back submit handler.
   */
  public function stepBack(array &$form, FormStateInterface $form_state) {
    $current_step = $form_state->get('step');
    $form_state->set('step', $current_step - 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Finish submit handler.
   */
  public function finish(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Setup completed successfully! Your Drupal Headless Module is ready.'));
    $form_state->setRedirect('drupal_headless.dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Main submit is handled by step-specific handlers.
  }

}
