<?php

namespace Drupal\drupal_headless\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Manages webhooks for content changes.
 */
class WebhookManager {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a WebhookManager object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    ClientInterface $http_client,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    QueueFactory $queue_factory,
    StateInterface $state
  ) {
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('drupal_headless');
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * Triggers webhooks for an entity event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $event
   *   The event type (create, update, delete).
   */
  public function trigger(EntityInterface $entity, $event) {
    $webhooks = $this->getActiveWebhooks();

    if (empty($webhooks)) {
      return;
    }

    $payload = $this->buildPayload($entity, $event);

    foreach ($webhooks as $webhook) {
      // Check if this webhook is interested in this event.
      if (!$this->shouldTrigger($webhook, $entity, $event)) {
        continue;
      }

      // Queue the webhook for asynchronous delivery.
      $this->queueWebhook($webhook, $payload);
    }
  }

  /**
   * Queues a webhook for delivery.
   *
   * @param array $webhook
   *   The webhook configuration.
   * @param array $payload
   *   The payload to send.
   */
  protected function queueWebhook(array $webhook, array $payload) {
    $queue = $this->queueFactory->get('drupal_headless_webhooks');

    $item = [
      'webhook' => $webhook,
      'payload' => $payload,
      'attempts' => 0,
      'created' => time(),
    ];

    $queue->createItem($item);
  }

  /**
   * Sends a webhook immediately.
   *
   * @param array $webhook
   *   The webhook configuration.
   * @param array $payload
   *   The payload to send.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function send(array $webhook, array $payload) {
    try {
      $url = $webhook['url'];
      $secret = $webhook['secret'] ?? '';

      // Build headers.
      $headers = [
        'Content-Type' => 'application/json',
        'User-Agent' => 'Drupal-Headless-Webhook/1.0',
      ];

      // Add HMAC signature for security.
      if (!empty($secret)) {
        $signature = $this->generateSignature($payload, $secret);
        $headers['X-Webhook-Signature'] = $signature;
      }

      // Send the webhook.
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'json' => $payload,
        'timeout' => 10,
      ]);

      $status_code = $response->getStatusCode();

      // Log success.
      $this->logWebhook($webhook, $payload, $status_code, 'success');

      return $status_code >= 200 && $status_code < 300;
    }
    catch (RequestException $e) {
      // Log failure.
      $this->logWebhook($webhook, $payload, $e->getCode(), 'failed', $e->getMessage());

      $this->logger->error('Webhook failed: @url - @error', [
        '@url' => $webhook['url'],
        '@error' => $e->getMessage(),
      ]);

      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Webhook exception: @error', [
        '@error' => $e->getMessage(),
      ]);

      return FALSE;
    }
  }

  /**
   * Builds the payload for a webhook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $event
   *   The event type.
   *
   * @return array
   *   The payload array.
   */
  protected function buildPayload(EntityInterface $entity, $event) {
    $payload = [
      'event' => $event,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_bundle' => $entity->bundle(),
      'entity_id' => $entity->id(),
      'entity_uuid' => $entity->uuid(),
      'timestamp' => time(),
    ];

    // Add entity label if available.
    if (method_exists($entity, 'label')) {
      $payload['entity_label'] = $entity->label();
    }

    // Add URL if entity has one.
    if ($entity->hasLinkTemplate('canonical')) {
      $payload['entity_url'] = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    }

    // For nodes, add additional metadata.
    if ($entity->getEntityTypeId() === 'node') {
      $payload['published'] = $entity->isPublished();
      $payload['created'] = $entity->getCreatedTime();
      $payload['changed'] = $entity->getChangedTime();

      if ($entity->hasField('uid')) {
        $author = $entity->get('uid')->entity;
        if ($author) {
          $payload['author'] = [
            'id' => $author->id(),
            'name' => $author->getDisplayName(),
          ];
        }
      }
    }

    return $payload;
  }

  /**
   * Generates HMAC signature for webhook payload.
   *
   * @param array $payload
   *   The payload.
   * @param string $secret
   *   The secret key.
   *
   * @return string
   *   The signature.
   */
  protected function generateSignature(array $payload, $secret) {
    $json = json_encode($payload);
    return 'sha256=' . hash_hmac('sha256', $json, $secret);
  }

  /**
   * Checks if webhook should be triggered for this entity/event.
   *
   * @param array $webhook
   *   The webhook configuration.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $event
   *   The event type.
   *
   * @return bool
   *   TRUE if should trigger, FALSE otherwise.
   */
  protected function shouldTrigger(array $webhook, EntityInterface $entity, $event) {
    // Check if event type is enabled.
    $events = $webhook['events'] ?? ['create', 'update', 'delete'];
    if (!in_array($event, $events)) {
      return FALSE;
    }

    // Check if entity type is enabled.
    $entity_types = $webhook['entity_types'] ?? ['node'];
    if (!in_array($entity->getEntityTypeId(), $entity_types)) {
      return FALSE;
    }

    // Check if bundle is enabled (if specified).
    if (!empty($webhook['bundles'])) {
      if (!in_array($entity->bundle(), $webhook['bundles'])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Gets all active webhooks.
   *
   * @return array
   *   Array of webhook configurations.
   */
  public function getActiveWebhooks() {
    return $this->state->get('drupal_headless.webhooks', []);
  }

  /**
   * Adds a new webhook.
   *
   * @param array $webhook
   *   The webhook configuration.
   *
   * @return string
   *   The webhook ID.
   */
  public function addWebhook(array $webhook) {
    $webhooks = $this->getActiveWebhooks();

    // Generate unique ID.
    $id = uniqid('webhook_', TRUE);
    $webhook['id'] = $id;
    $webhook['created'] = time();
    $webhook['enabled'] = TRUE;

    $webhooks[$id] = $webhook;

    $this->state->set('drupal_headless.webhooks', $webhooks);

    $this->logger->info('Webhook added: @url', ['@url' => $webhook['url']]);

    return $id;
  }

  /**
   * Updates a webhook.
   *
   * @param string $id
   *   The webhook ID.
   * @param array $webhook
   *   The updated webhook configuration.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function updateWebhook($id, array $webhook) {
    $webhooks = $this->getActiveWebhooks();

    if (!isset($webhooks[$id])) {
      return FALSE;
    }

    $webhook['id'] = $id;
    $webhooks[$id] = $webhook;

    $this->state->set('drupal_headless.webhooks', $webhooks);

    return TRUE;
  }

  /**
   * Deletes a webhook.
   *
   * @param string $id
   *   The webhook ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function deleteWebhook($id) {
    $webhooks = $this->getActiveWebhooks();

    if (!isset($webhooks[$id])) {
      return FALSE;
    }

    unset($webhooks[$id]);

    $this->state->set('drupal_headless.webhooks', $webhooks);

    $this->logger->info('Webhook deleted: @id', ['@id' => $id]);

    return TRUE;
  }

  /**
   * Logs webhook delivery.
   *
   * @param array $webhook
   *   The webhook configuration.
   * @param array $payload
   *   The payload sent.
   * @param int $status_code
   *   The HTTP status code.
   * @param string $status
   *   The delivery status (success/failed).
   * @param string $error
   *   Error message if failed.
   */
  protected function logWebhook(array $webhook, array $payload, $status_code, $status, $error = '') {
    $logs = $this->state->get('drupal_headless.webhook_logs', []);

    $log = [
      'webhook_id' => $webhook['id'] ?? 'unknown',
      'url' => $webhook['url'],
      'payload' => $payload,
      'status_code' => $status_code,
      'status' => $status,
      'error' => $error,
      'timestamp' => time(),
    ];

    // Keep only last 100 logs.
    array_unshift($logs, $log);
    $logs = array_slice($logs, 0, 100);

    $this->state->set('drupal_headless.webhook_logs', $logs);
  }

  /**
   * Gets webhook delivery logs.
   *
   * @param int $limit
   *   Number of logs to retrieve.
   *
   * @return array
   *   Array of log entries.
   */
  public function getLogs($limit = 50) {
    $logs = $this->state->get('drupal_headless.webhook_logs', []);
    return array_slice($logs, 0, $limit);
  }

  /**
   * Tests a webhook by sending a test payload.
   *
   * @param array $webhook
   *   The webhook configuration.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function testWebhook(array $webhook) {
    $payload = [
      'event' => 'test',
      'message' => 'This is a test webhook from Drupal Headless Module',
      'timestamp' => time(),
    ];

    return $this->send($webhook, $payload);
  }

}
