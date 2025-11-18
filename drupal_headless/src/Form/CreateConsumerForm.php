<?php

namespace Drupal\drupal_headless\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\drupal_headless\Service\ConsumerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating API consumers with simplified workflow.
 */
class CreateConsumerForm extends FormBase {

  /**
   * The consumer manager service.
   *
   * @var \Drupal\drupal_headless\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a CreateConsumerForm object.
   *
   * @param \Drupal\drupal_headless\Service\ConsumerManager $consumer_manager
   *   The consumer manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private tempstore factory.
   */
  public function __construct(ConsumerManager $consumer_manager, PrivateTempStoreFactory $temp_store_factory) {
    $this->consumerManager = $consumer_manager;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('drupal_headless.consumer_manager'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_headless_create_consumer';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check if we just created a consumer and have credentials to display.
    $tempstore = $this->tempStoreFactory->get('drupal_headless');
    $credentials = $tempstore->get('new_consumer_credentials');

    if ($credentials) {
      return $this->buildCredentialsDisplay($form, $credentials);
    }

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Create a new API consumer for your frontend application. This will generate OAuth2 credentials that your app can use to authenticate with Drupal.') . '</p>',
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application Name'),
      '#description' => $this->t('A descriptive name for your frontend application (e.g., "Next.js Production Site").'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['description_field'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional description of this application and its purpose.'),
      '#rows' => 3,
    ];

    $form['framework'] = [
      '#type' => 'select',
      '#title' => $this->t('Frontend Framework'),
      '#description' => $this->t('Select the framework your frontend application uses.'),
      '#options' => [
        'nextjs' => 'Next.js',
        'react' => 'React',
        'vue' => 'Vue.js',
        'astro' => 'Astro',
        'other' => 'Other',
      ],
      '#default_value' => 'nextjs',
    ];

    $form['frontend_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Frontend URL'),
      '#description' => $this->t('The URL where your frontend application runs (e.g., https://example.com or http://localhost:3000 for development).'),
      '#placeholder' => 'https://example.com',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Consumer'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('drupal_headless.dashboard'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * Builds the credentials display form.
   *
   * @param array $form
   *   The form array.
   * @param array $credentials
   *   The credentials to display.
   *
   * @return array
   *   The form array.
   */
  protected function buildCredentialsDisplay(array $form, array $credentials) {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['success'] = [
      '#markup' => '<div class="messages messages--status">' .
        '<h2>' . $this->t('Consumer Created Successfully!') . '</h2>' .
        '<p>' . $this->t('Your API consumer has been created. <strong>Copy these credentials now</strong> - the secret will not be shown again.') . '</p>' .
        '</div>',
    ];

    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Credentials'),
    ];

    $form['credentials']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#value' => $credentials['client_id'],
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => 'font-family: monospace; background-color: #f5f5f5;',
      ],
      '#suffix' => '<button type="button" onclick="navigator.clipboard.writeText(\'' . $credentials['client_id'] . '\'); alert(\'Copied!\');" class="button button--small">' . $this->t('Copy') . '</button>',
    ];

    $form['credentials']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#value' => $credentials['client_secret'],
      '#description' => $this->t('<strong>Important:</strong> Store this secret securely. It will not be shown again.'),
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => 'font-family: monospace; background-color: #fff3cd; color: #856404;',
      ],
      '#suffix' => '<button type="button" onclick="navigator.clipboard.writeText(\'' . $credentials['client_secret'] . '\'); alert(\'Copied!\');" class="button button--small">' . $this->t('Copy') . '</button>',
    ];

    // Generate .env file content.
    $env_content = $this->generateEnvContent($credentials);

    $form['env_file'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Environment Variables'),
      '#description' => $this->t('Copy this to your .env.local file:'),
    ];

    $form['env_file']['content'] = [
      '#type' => 'textarea',
      '#value' => $env_content,
      '#rows' => 6,
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => 'font-family: monospace; background-color: #f5f5f5;',
      ],
      '#suffix' => '<button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert(\'Copied to clipboard!\');" class="button">' . $this->t('Copy All') . '</button>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['done'] = [
      '#type' => 'submit',
      '#value' => $this->t('Done - I\'ve saved my credentials'),
      '#button_type' => 'primary',
      '#submit' => ['::credentialsDoneSubmit'],
    ];

    $form['actions']['create_another'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Another Consumer'),
      '#submit' => ['::createAnotherSubmit'],
    ];

    return $form;
  }

  /**
   * Generates .env file content for the credentials.
   *
   * @param array $credentials
   *   The credentials array.
   *
   * @return string
   *   The .env file content.
   */
  protected function generateEnvContent(array $credentials) {
    $base_url = \Drupal::request()->getSchemeAndHttpHost();

    $framework = $credentials['framework'] ?? 'nextjs';

    // Framework-specific env variable names.
    $env_vars = [
      'nextjs' => [
        'NEXT_PUBLIC_DRUPAL_BASE_URL' => $base_url,
        'DRUPAL_CLIENT_ID' => $credentials['client_id'],
        'DRUPAL_CLIENT_SECRET' => $credentials['client_secret'],
      ],
      'react' => [
        'REACT_APP_DRUPAL_BASE_URL' => $base_url,
        'REACT_APP_CLIENT_ID' => $credentials['client_id'],
        'CLIENT_SECRET' => $credentials['client_secret'],
      ],
      'vue' => [
        'VUE_APP_DRUPAL_BASE_URL' => $base_url,
        'VUE_APP_CLIENT_ID' => $credentials['client_id'],
        'CLIENT_SECRET' => $credentials['client_secret'],
      ],
      'astro' => [
        'PUBLIC_DRUPAL_BASE_URL' => $base_url,
        'DRUPAL_CLIENT_ID' => $credentials['client_id'],
        'DRUPAL_CLIENT_SECRET' => $credentials['client_secret'],
      ],
      'other' => [
        'DRUPAL_BASE_URL' => $base_url,
        'CLIENT_ID' => $credentials['client_id'],
        'CLIENT_SECRET' => $credentials['client_secret'],
      ],
    ];

    $vars = $env_vars[$framework] ?? $env_vars['other'];

    $content = "# Drupal API Configuration\n";
    $content .= "# Generated by Drupal Headless Module\n\n";

    foreach ($vars as $key => $value) {
      $content .= "{$key}={$value}\n";
    }

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $form_state->getValue('label');
    $description = $form_state->getValue('description_field');
    $framework = $form_state->getValue('framework');

    // Create the consumer.
    $consumer = $this->consumerManager->createConsumer($label, $description);

    if ($consumer) {
      // Generate a random secret (UUID-based).
      $secret = \Drupal\Component\Uuid\Uuid::generate();

      // Store secret in consumer (this will encrypt it).
      $consumer->set('secret', $secret);
      $consumer->save();

      // Store credentials in temp storage for display (only this session).
      $tempstore = $this->tempStoreFactory->get('drupal_headless');
      $tempstore->set('new_consumer_credentials', [
        'client_id' => $consumer->uuid(),
        'client_secret' => $secret,
        'framework' => $framework,
        'label' => $label,
      ]);

      // Rebuild the form to show credentials.
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Submit handler for "Done" button.
   */
  public function credentialsDoneSubmit(array &$form, FormStateInterface $form_state) {
    // Clear the temporary credentials.
    $tempstore = $this->tempStoreFactory->get('drupal_headless');
    $tempstore->delete('new_consumer_credentials');

    $this->messenger()->addStatus($this->t('Consumer created successfully. You can now use these credentials in your frontend application.'));

    $form_state->setRedirect('drupal_headless.dashboard');
  }

  /**
   * Submit handler for "Create Another" button.
   */
  public function createAnotherSubmit(array &$form, FormStateInterface $form_state) {
    // Clear the temporary credentials.
    $tempstore = $this->tempStoreFactory->get('drupal_headless');
    $tempstore->delete('new_consumer_credentials');

    // Redirect back to the create form.
    $form_state->setRedirect('drupal_headless.create_consumer');
  }

}
