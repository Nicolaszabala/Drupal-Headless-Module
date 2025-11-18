<?php

namespace Drupal\drupal_headless\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Manages API consumers for Drupal Headless Module.
 */
class ConsumerManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration manager.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a ConsumerManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\drupal_headless\Service\ConfigurationManager $config_manager
   *   The configuration manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigurationManager $config_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configManager = $config_manager;
    $this->logger = $logger_factory->get('drupal_headless');
  }

  /**
   * Creates a new consumer for a frontend application.
   *
   * @param string $label
   *   The consumer label.
   * @param string $description
   *   The consumer description.
   * @param array $options
   *   Additional options like 'roles', 'user_id', etc.
   *
   * @return \Drupal\consumers\Entity\Consumer|null
   *   The created consumer entity or NULL on failure.
   */
  public function createConsumer($label, $description = '', array $options = []) {
    try {
      $storage = $this->entityTypeManager->getStorage('consumer');

      $consumer_data = [
        'label' => $label,
        'description' => $description,
        'is_default' => FALSE,
      ];

      // Add user reference if provided.
      if (!empty($options['user_id'])) {
        $consumer_data['user_id'] = $options['user_id'];
      }

      // Add roles/scopes if provided.
      if (!empty($options['roles'])) {
        $consumer_data['roles'] = $options['roles'];
      }

      $consumer = $storage->create($consumer_data);
      $consumer->save();

      $this->logger->info('Created consumer: @label', ['@label' => $label]);

      return $consumer;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create consumer: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Gets all Drupal Headless Module consumers.
   *
   * @return array
   *   Array of consumer entities.
   */
  public function getConsumers() {
    try {
      $storage = $this->entityTypeManager->getStorage('consumer');
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->sort('label', 'ASC');

      $ids = $query->execute();

      if (empty($ids)) {
        return [];
      }

      return $storage->loadMultiple($ids);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load consumers: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Deletes a consumer.
   *
   * @param string $uuid
   *   The consumer UUID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function deleteConsumer($uuid) {
    try {
      $storage = $this->entityTypeManager->getStorage('consumer');
      $consumers = $storage->loadByProperties(['uuid' => $uuid]);

      if (empty($consumers)) {
        $this->logger->warning('Consumer not found: @uuid', ['@uuid' => $uuid]);
        return FALSE;
      }

      $consumer = reset($consumers);
      $consumer->delete();

      // Also remove from framework configuration.
      $this->configManager->removeFramework($uuid);

      $this->logger->info('Deleted consumer: @uuid', ['@uuid' => $uuid]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to delete consumer: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Gets consumer secret for display purposes.
   *
   * @param string $uuid
   *   The consumer UUID.
   *
   * @return string|null
   *   The consumer secret or NULL if not found.
   */
  public function getConsumerSecret($uuid) {
    try {
      $storage = $this->entityTypeManager->getStorage('consumer');
      $consumers = $storage->loadByProperties(['uuid' => $uuid]);

      if (empty($consumers)) {
        return NULL;
      }

      $consumer = reset($consumers);

      // Note: Secrets are typically encrypted and may not be retrievable.
      // This is just for the structure - actual implementation depends on
      // the Consumers module's secret handling.
      if ($consumer->hasField('secret')) {
        return $consumer->get('secret')->value;
      }

      return NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get consumer secret: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
