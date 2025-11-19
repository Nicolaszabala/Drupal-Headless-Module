<?php

namespace Drupal\drupal_headless\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupal_headless\Service\WebhookManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for webhook management pages.
 */
class WebhookController extends ControllerBase {

  /**
   * The webhook manager.
   *
   * @var \Drupal\drupal_headless\Service\WebhookManager
   */
  protected $webhookManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->webhookManager = $container->get('drupal_headless.webhook_manager');
    return $instance;
  }

  /**
   * Lists all configured webhooks.
   *
   * @return array
   *   A render array.
   */
  public function listWebhooks() {
    $build = [];

    $build['intro'] = [
      '#markup' => '<p>' . $this->t('Webhooks notify your frontend application when content changes in Drupal. Configure webhook endpoints below to enable automatic cache invalidation and content updates.') . '</p>',
    ];

    $build['add_webhook'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Webhook'),
      '#url' => Url::fromRoute('drupal_headless.webhook_add'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
      ],
    ];

    $webhooks = $this->webhookManager->getActiveWebhooks();

    if (empty($webhooks)) {
      $build['empty'] = [
        '#markup' => '<div class="messages messages--warning" style="margin-top: 20px;">' .
          '<p>' . $this->t('No webhooks configured yet. <a href="@url">Add your first webhook</a> to start receiving notifications.', [
            '@url' => Url::fromRoute('drupal_headless.webhook_add')->toString(),
          ]) . '</p>' .
          '</div>',
      ];

      return $build;
    }

    $rows = [];
    foreach ($webhooks as $webhook) {
      $status = $webhook['enabled'] ? '<span style="color: green;">✓ Enabled</span>' : '<span style="color: orange;">⊘ Disabled</span>';

      $events = implode(', ', $webhook['events'] ?? []);
      $entity_types = implode(', ', $webhook['entity_types'] ?? []);

      $operations = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('drupal_headless.webhook_edit', ['webhook_id' => $webhook['id']]),
          ],
          'test' => [
            'title' => $this->t('Test'),
            'url' => Url::fromRoute('drupal_headless.webhook_test', ['webhook_id' => $webhook['id']]),
          ],
          'delete' => [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('drupal_headless.webhook_delete', ['webhook_id' => $webhook['id']]),
            'attributes' => [
              'onclick' => 'return confirm("' . $this->t('Are you sure?') . '");',
            ],
          ],
        ],
      ];

      $rows[] = [
        $webhook['label'],
        '<code style="font-size: 12px; word-break: break-all;">' . $webhook['url'] . '</code>',
        $events,
        $entity_types,
        $status,
        ['data' => $operations],
      ];
    }

    $build['webhooks'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('URL'),
        $this->t('Events'),
        $this->t('Entity Types'),
        $this->t('Status'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No webhooks configured.'),
    ];

    return $build;
  }

  /**
   * Shows webhook delivery logs.
   *
   * @return array
   *   A render array.
   */
  public function logs() {
    $build = [];

    $build['intro'] = [
      '#markup' => '<h2>' . $this->t('Webhook Delivery Logs') . '</h2>' .
        '<p>' . $this->t('View recent webhook delivery attempts and their results.') . '</p>',
    ];

    $logs = $this->webhookManager->getLogs(50);

    if (empty($logs)) {
      $build['empty'] = [
        '#markup' => '<div class="messages messages--info">' .
          '<p>' . $this->t('No webhook logs yet. Logs will appear here after webhooks are triggered.') . '</p>' .
          '</div>',
      ];

      return $build;
    }

    $rows = [];
    foreach ($logs as $log) {
      $status_class = $log['status'] === 'success' ? 'green' : 'red';
      $status_icon = $log['status'] === 'success' ? '✓' : '✗';
      $status = '<span style="color: ' . $status_class . ';">' . $status_icon . ' ' . ucfirst($log['status']) . '</span>';

      $timestamp = \Drupal::service('date.formatter')->format($log['timestamp'], 'short');

      $payload_summary = '';
      if (!empty($log['payload'])) {
        $event = $log['payload']['event'] ?? 'unknown';
        $entity_type = $log['payload']['entity_type'] ?? '';
        $entity_label = $log['payload']['entity_label'] ?? '';
        $payload_summary = "{$event}: {$entity_type} - {$entity_label}";
      }

      $error = $log['error'] ? '<details><summary>View Error</summary>' . $log['error'] . '</details>' : '-';

      $rows[] = [
        $timestamp,
        '<code style="font-size: 11px;">' . $log['url'] . '</code>',
        $payload_summary,
        $log['status_code'],
        $status,
        $error,
      ];
    }

    $build['logs'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Time'),
        $this->t('URL'),
        $this->t('Payload'),
        $this->t('Status Code'),
        $this->t('Status'),
        $this->t('Error'),
      ],
      '#rows' => $rows,
      '#attributes' => [
        'style' => 'font-size: 13px;',
      ],
    ];

    return $build;
  }

  /**
   * Tests a webhook by sending a test payload.
   *
   * @param string $webhook_id
   *   The webhook ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function testWebhook($webhook_id) {
    $webhooks = $this->webhookManager->getActiveWebhooks();
    $webhook = $webhooks[$webhook_id] ?? NULL;

    if (!$webhook) {
      $this->messenger()->addError($this->t('Webhook not found.'));
      return $this->redirect('drupal_headless.webhooks');
    }

    $success = $this->webhookManager->testWebhook($webhook);

    if ($success) {
      $this->messenger()->addStatus($this->t('Test webhook sent successfully to @url', ['@url' => $webhook['url']]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to send test webhook. Check the logs for details.'));
    }

    return $this->redirect('drupal_headless.webhooks');
  }

  /**
   * Deletes a webhook.
   *
   * @param string $webhook_id
   *   The webhook ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function deleteWebhook($webhook_id) {
    $success = $this->webhookManager->deleteWebhook($webhook_id);

    if ($success) {
      $this->messenger()->addStatus($this->t('Webhook deleted successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to delete webhook.'));
    }

    return $this->redirect('drupal_headless.webhooks');
  }

}
