<?php

namespace Drupal\drupal_headless\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_headless\Service\WebhookManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding/editing webhooks.
 */
class WebhookForm extends FormBase {

  /**
   * The webhook manager.
   *
   * @var \Drupal\drupal_headless\Service\WebhookManager
   */
  protected $webhookManager;

  /**
   * Constructs a WebhookForm object.
   *
   * @param \Drupal\drupal_headless\Service\WebhookManager $webhook_manager
   *   The webhook manager.
   */
  public function __construct(WebhookManager $webhook_manager) {
    $this->webhookManager = $webhook_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('drupal_headless.webhook_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_headless_webhook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $webhook_id = NULL) {
    $webhook = NULL;

    if ($webhook_id) {
      $webhooks = $this->webhookManager->getActiveWebhooks();
      $webhook = $webhooks[$webhook_id] ?? NULL;

      if (!$webhook) {
        $this->messenger()->addError($this->t('Webhook not found.'));
        return $this->redirect('drupal_headless.webhooks');
      }

      $form_state->set('webhook_id', $webhook_id);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('A descriptive label for this webhook.'),
      '#required' => TRUE,
      '#default_value' => $webhook['label'] ?? '',
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Webhook URL'),
      '#description' => $this->t('The URL where webhooks will be sent.'),
      '#required' => TRUE,
      '#default_value' => $webhook['url'] ?? '',
      '#placeholder' => 'https://example.com/api/webhooks',
    ];

    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#description' => $this->t('Optional secret for HMAC signature verification. The signature will be sent in X-Webhook-Signature header.'),
      '#default_value' => $webhook['secret'] ?? '',
      '#placeholder' => 'your-secret-key',
    ];

    $form['events'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Events'),
      '#description' => $this->t('Select which events should trigger this webhook.'),
      '#options' => [
        'create' => $this->t('Create (when content is created)'),
        'update' => $this->t('Update (when content is updated)'),
        'delete' => $this->t('Delete (when content is deleted)'),
      ],
      '#default_value' => $webhook['events'] ?? ['create', 'update', 'delete'],
      '#required' => TRUE,
    ];

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity Types'),
      '#description' => $this->t('Select which entity types should trigger this webhook.'),
      '#options' => [
        'node' => $this->t('Nodes (Content)'),
        'taxonomy_term' => $this->t('Taxonomy Terms'),
        'media' => $this->t('Media'),
      ],
      '#default_value' => $webhook['entity_types'] ?? ['node'],
      '#required' => TRUE,
    ];

    // Get available content types.
    $node_types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    $bundle_options = [];
    foreach ($node_types as $type) {
      $bundle_options[$type->id()] = $type->label();
    }

    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#description' => $this->t('Optionally limit to specific content types. Leave empty for all types.'),
      '#options' => $bundle_options,
      '#default_value' => $webhook['bundles'] ?? [],
      '#states' => [
        'visible' => [
          ':input[name="entity_types[node]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Uncheck to temporarily disable this webhook without deleting it.'),
      '#default_value' => $webhook['enabled'] ?? TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $webhook_id ? $this->t('Update Webhook') : $this->t('Add Webhook'),
      '#button_type' => 'primary',
    ];

    if ($webhook_id) {
      $form['actions']['test'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send Test'),
        '#submit' => ['::testWebhook'],
      ];

      $form['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#submit' => ['::deleteWebhook'],
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'onclick' => 'return confirm("' . $this->t('Are you sure you want to delete this webhook?') . '");',
        ],
      ];
    }

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('drupal_headless.webhooks'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $webhook_id = $form_state->get('webhook_id');

    // Filter out empty checkboxes.
    $events = array_values(array_filter($form_state->getValue('events')));
    $entity_types = array_values(array_filter($form_state->getValue('entity_types')));
    $bundles = array_values(array_filter($form_state->getValue('bundles')));

    $webhook = [
      'label' => $form_state->getValue('label'),
      'url' => $form_state->getValue('url'),
      'secret' => $form_state->getValue('secret'),
      'events' => $events,
      'entity_types' => $entity_types,
      'bundles' => $bundles,
      'enabled' => (bool) $form_state->getValue('enabled'),
    ];

    if ($webhook_id) {
      $this->webhookManager->updateWebhook($webhook_id, $webhook);
      $this->messenger()->addStatus($this->t('Webhook updated successfully.'));
    }
    else {
      $this->webhookManager->addWebhook($webhook);
      $this->messenger()->addStatus($this->t('Webhook added successfully.'));
    }

    $form_state->setRedirect('drupal_headless.webhooks');
  }

  /**
   * Submit handler for test button.
   */
  public function testWebhook(array &$form, FormStateInterface $form_state) {
    $webhook_id = $form_state->get('webhook_id');

    if (!$webhook_id) {
      return;
    }

    $webhooks = $this->webhookManager->getActiveWebhooks();
    $webhook = $webhooks[$webhook_id] ?? NULL;

    if (!$webhook) {
      $this->messenger()->addError($this->t('Webhook not found.'));
      return;
    }

    $success = $this->webhookManager->testWebhook($webhook);

    if ($success) {
      $this->messenger()->addStatus($this->t('Test webhook sent successfully. Check your endpoint to verify receipt.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to send test webhook. Check the logs for details.'));
    }

    $form_state->setRebuild();
  }

  /**
   * Submit handler for delete button.
   */
  public function deleteWebhook(array &$form, FormStateInterface $form_state) {
    $webhook_id = $form_state->get('webhook_id');

    if (!$webhook_id) {
      return;
    }

    $this->webhookManager->deleteWebhook($webhook_id);
    $this->messenger()->addStatus($this->t('Webhook deleted successfully.'));

    $form_state->setRedirect('drupal_headless.webhooks');
  }

}
