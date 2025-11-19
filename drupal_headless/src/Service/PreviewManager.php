<?php

namespace Drupal\drupal_headless\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Manages preview functionality for headless frontends.
 */
class PreviewManager {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The configuration manager.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * Preview token lifetime in seconds (1 hour).
   */
  const TOKEN_LIFETIME = 3600;

  /**
   * Constructs a PreviewManager object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\drupal_headless\Service\ConfigurationManager $config_manager
   *   The configuration manager.
   */
  public function __construct(
    StateInterface $state,
    UuidInterface $uuid,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigurationManager $config_manager
  ) {
    $this->state = $state;
    $this->uuid = $uuid;
    $this->logger = $logger_factory->get('drupal_headless');
    $this->configManager = $config_manager;
  }

  /**
   * Generates a preview token for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The preview token.
   */
  public function generateToken(EntityInterface $entity) {
    $token = $this->uuid->generate();

    $tokens = $this->state->get('drupal_headless.preview_tokens', []);

    $tokens[$token] = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'entity_uuid' => $entity->uuid(),
      'bundle' => $entity->bundle(),
      'created' => time(),
      'expires' => time() + self::TOKEN_LIFETIME,
    ];

    $this->state->set('drupal_headless.preview_tokens', $tokens);

    $this->logger->info('Preview token generated for @type:@id', [
      '@type' => $entity->getEntityTypeId(),
      '@id' => $entity->id(),
    ]);

    return $token;
  }

  /**
   * Validates a preview token.
   *
   * @param string $token
   *   The preview token.
   *
   * @return array|null
   *   Token data if valid, NULL otherwise.
   */
  public function validateToken($token) {
    $tokens = $this->state->get('drupal_headless.preview_tokens', []);

    if (!isset($tokens[$token])) {
      return NULL;
    }

    $token_data = $tokens[$token];

    // Check if token has expired.
    if ($token_data['expires'] < time()) {
      $this->deleteToken($token);
      return NULL;
    }

    return $token_data;
  }

  /**
   * Deletes a preview token.
   *
   * @param string $token
   *   The preview token.
   */
  public function deleteToken($token) {
    $tokens = $this->state->get('drupal_headless.preview_tokens', []);

    if (isset($tokens[$token])) {
      unset($tokens[$token]);
      $this->state->set('drupal_headless.preview_tokens', $tokens);
    }
  }

  /**
   * Cleans up expired tokens.
   */
  public function cleanupExpiredTokens() {
    $tokens = $this->state->get('drupal_headless.preview_tokens', []);
    $current_time = time();
    $cleaned = 0;

    foreach ($tokens as $token => $data) {
      if ($data['expires'] < $current_time) {
        unset($tokens[$token]);
        $cleaned++;
      }
    }

    if ($cleaned > 0) {
      $this->state->set('drupal_headless.preview_tokens', $tokens);
      $this->logger->info('Cleaned up @count expired preview tokens', ['@count' => $cleaned]);
    }
  }

  /**
   * Generates a preview URL for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string|null $framework
   *   Optional framework identifier.
   *
   * @return string|null
   *   The preview URL or NULL if not configured.
   */
  public function generatePreviewUrl(EntityInterface $entity, $framework = NULL) {
    // Generate preview token.
    $token = $this->generateToken($entity);

    // Get preview base URL.
    $base_url = $this->getPreviewBaseUrl($framework);

    if (!$base_url) {
      return NULL;
    }

    // Build preview URL based on entity type.
    $path = $this->getPreviewPath($entity);

    // Construct full URL with token.
    $url = rtrim($base_url, '/') . '/' . ltrim($path, '/');
    $url .= (strpos($url, '?') === FALSE ? '?' : '&');
    $url .= 'preview=' . $token;

    return $url;
  }

  /**
   * Gets the preview base URL for a framework.
   *
   * @param string|null $framework
   *   Optional framework identifier.
   *
   * @return string|null
   *   The base URL or NULL if not configured.
   */
  protected function getPreviewBaseUrl($framework = NULL) {
    $config = $this->configManager->getSettings();

    // Get framework-specific preview URL.
    if ($framework) {
      $preview_urls = $config->get('preview_urls') ?? [];
      if (isset($preview_urls[$framework])) {
        return $preview_urls[$framework];
      }
    }

    // Fallback to default preview URL.
    return $config->get('default_preview_url');
  }

  /**
   * Gets the preview path for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The preview path.
   */
  protected function getPreviewPath(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // For nodes, use bundle-specific paths.
    if ($entity_type === 'node') {
      // Common Next.js/frontend patterns.
      switch ($bundle) {
        case 'article':
          return '/articles/' . $entity->uuid();

        case 'page':
          return '/pages/' . $entity->uuid();

        default:
          return '/' . $bundle . '/' . $entity->uuid();
      }
    }

    // Generic path for other entity types.
    return '/' . $entity_type . '/' . $entity->uuid();
  }

  /**
   * Gets preview configuration for all frameworks.
   *
   * @return array
   *   Array of preview configurations.
   */
  public function getPreviewConfigurations() {
    $config = $this->configManager->getSettings();

    return [
      'default_url' => $config->get('default_preview_url'),
      'framework_urls' => $config->get('preview_urls') ?? [],
      'token_lifetime' => self::TOKEN_LIFETIME,
    ];
  }

  /**
   * Updates preview configuration.
   *
   * @param string|null $default_url
   *   Default preview URL.
   * @param array $framework_urls
   *   Framework-specific preview URLs.
   */
  public function updatePreviewConfiguration($default_url = NULL, array $framework_urls = []) {
    $config = $this->configManager->getConfig();

    if ($default_url !== NULL) {
      $config->set('default_preview_url', $default_url);
    }

    if (!empty($framework_urls)) {
      $config->set('preview_urls', $framework_urls);
    }

    $config->save();
  }

  /**
   * Gets all active preview tokens.
   *
   * @return array
   *   Array of active tokens.
   */
  public function getActiveTokens() {
    $tokens = $this->state->get('drupal_headless.preview_tokens', []);
    $current_time = time();

    // Filter out expired tokens.
    return array_filter($tokens, function ($data) use ($current_time) {
      return $data['expires'] >= $current_time;
    });
  }

}
