<?php

namespace Drupal\drupal_headless\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\drupal_headless\Service\WebhookManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes webhook delivery queue.
 *
 * @QueueWorker(
 *   id = "drupal_headless_webhooks",
 *   title = @Translation("Drupal Headless Webhook Delivery"),
 *   cron = {"time" = 60}
 * )
 */
class WebhookQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The webhook manager.
   *
   * @var \Drupal\drupal_headless\Service\WebhookManager
   */
  protected $webhookManager;

  /**
   * Maximum number of retry attempts.
   *
   * @var int
   */
  const MAX_ATTEMPTS = 3;

  /**
   * Constructs a WebhookQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\drupal_headless\Service\WebhookManager $webhook_manager
   *   The webhook manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    WebhookManager $webhook_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->webhookManager = $webhook_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drupal_headless.webhook_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $webhook = $data['webhook'];
    $payload = $data['payload'];
    $attempts = $data['attempts'];

    // Try to send the webhook.
    $success = $this->webhookManager->send($webhook, $payload);

    // If failed and we haven't exceeded max attempts, requeue.
    if (!$success && $attempts < self::MAX_ATTEMPTS) {
      $queue = \Drupal::queue('drupal_headless_webhooks');

      $data['attempts'] = $attempts + 1;

      // Exponential backoff: wait longer between retries.
      $delay = pow(2, $attempts) * 60; // 1 min, 2 min, 4 min...

      // Note: Drupal's queue system doesn't support delayed items natively,
      // so we'll need to handle this in cron or use a contrib module.
      $queue->createItem($data);
    }
  }

}
