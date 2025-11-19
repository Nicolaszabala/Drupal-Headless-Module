<?php

namespace Drupal\drupal_headless\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\drupal_headless\Service\ConfigurationManager;
use Drupal\drupal_headless\Service\ConsumerManager;
use Drupal\drupal_headless\Service\OAuth2KeyManager;

/**
 * Manages health checks and setup verification.
 */
class HealthCheckManager {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The configuration manager.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * The consumer manager.
   *
   * @var \Drupal\drupal_headless\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * The OAuth2 key manager.
   *
   * @var \Drupal\drupal_headless\Service\OAuth2KeyManager
   */
  protected $keyManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a HealthCheckManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\drupal_headless\Service\ConfigurationManager $config_manager
   *   The configuration manager.
   * @param \Drupal\drupal_headless\Service\ConsumerManager $consumer_manager
   *   The consumer manager.
   * @param \Drupal\drupal_headless\Service\OAuth2KeyManager $key_manager
   *   The OAuth2 key manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ModuleInstallerInterface $module_installer,
    FileSystemInterface $file_system,
    ConfigurationManager $config_manager,
    ConsumerManager $consumer_manager,
    OAuth2KeyManager $key_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
    $this->fileSystem = $file_system;
    $this->configManager = $config_manager;
    $this->consumerManager = $consumer_manager;
    $this->keyManager = $key_manager;
    $this->logger = $logger_factory->get('drupal_headless');
  }

  /**
   * Gets all health checks with their current status.
   *
   * @return array
   *   Array of health checks with status.
   */
  public function getAllChecks() {
    return [
      'jsonapi' => $this->checkJsonApi(),
      'consumers' => $this->checkConsumers(),
      'simple_oauth' => $this->checkSimpleOauth(),
      'private_files' => $this->checkPrivateFiles(),
      'oauth_keys' => $this->checkOAuthKeys(),
      'consumer_created' => $this->checkConsumerCreated(),
      'cors' => $this->checkCors(),
      'preview_urls' => $this->checkPreviewUrls(),
      'webhooks' => $this->checkWebhooks(),
    ];
  }

  /**
   * Gets completion percentage.
   *
   * @return int
   *   Percentage of completed checks.
   */
  public function getCompletionPercentage() {
    $checks = $this->getAllChecks();
    $total = count($checks);
    $completed = 0;

    foreach ($checks as $check) {
      if ($check['status'] === 'complete') {
        $completed++;
      }
    }

    return $total > 0 ? round(($completed / $total) * 100) : 0;
  }

  /**
   * Checks if JSON:API module is enabled.
   */
  protected function checkJsonApi() {
    $enabled = $this->moduleHandler->moduleExists('jsonapi');

    return [
      'title' => 'JSON:API Module',
      'description' => 'Required for exposing content via RESTful API.',
      'status' => $enabled ? 'complete' : 'incomplete',
      'auto_fix' => !$enabled,
      'action' => 'enable_jsonapi',
      'priority' => 'critical',
      'details' => $enabled
        ? 'JSON:API is enabled and ready.'
        : 'JSON:API module needs to be enabled.',
    ];
  }

  /**
   * Checks if Consumers module is enabled.
   */
  protected function checkConsumers() {
    $enabled = $this->moduleHandler->moduleExists('consumers');

    return [
      'title' => 'Consumers Module',
      'description' => 'Required for managing API consumers.',
      'status' => $enabled ? 'complete' : 'incomplete',
      'auto_fix' => !$enabled,
      'action' => 'enable_consumers',
      'priority' => 'critical',
      'details' => $enabled
        ? 'Consumers module is enabled.'
        : 'Consumers module needs to be enabled.',
    ];
  }

  /**
   * Checks if Simple OAuth module is enabled.
   */
  protected function checkSimpleOauth() {
    $enabled = $this->moduleHandler->moduleExists('simple_oauth');

    return [
      'title' => 'Simple OAuth Module',
      'description' => 'Required for OAuth2 authentication.',
      'status' => $enabled ? 'complete' : 'incomplete',
      'auto_fix' => !$enabled,
      'action' => 'enable_simple_oauth',
      'priority' => 'critical',
      'details' => $enabled
        ? 'Simple OAuth is enabled.'
        : 'Simple OAuth module needs to be enabled.',
    ];
  }

  /**
   * Checks if private file system is configured.
   */
  protected function checkPrivateFiles() {
    $private_path = $this->fileSystem->realpath('private://');
    $configured = !empty($private_path);

    return [
      'title' => 'Private File System',
      'description' => 'Required for secure OAuth2 key storage.',
      'status' => $configured ? 'complete' : 'incomplete',
      'auto_fix' => FALSE,
      'action' => NULL,
      'priority' => 'critical',
      'details' => $configured
        ? 'Private file system is configured at: ' . $private_path
        : 'Private file system is not configured. Add $settings[\'file_private_path\'] = \'../private\'; to settings.php',
    ];
  }

  /**
   * Checks if OAuth2 keys are generated.
   */
  protected function checkOAuthKeys() {
    $exists = $this->keyManager->keysExist();
    $validation = $this->keyManager->validateKeys();

    return [
      'title' => 'OAuth2 Keys',
      'description' => 'RSA keys for OAuth2 token signing.',
      'status' => ($exists && $validation['status']) ? 'complete' : 'incomplete',
      'auto_fix' => !$exists,
      'action' => 'generate_oauth_keys',
      'priority' => 'critical',
      'details' => ($exists && $validation['status'])
        ? 'OAuth2 keys are generated and valid.'
        : 'OAuth2 keys need to be generated.',
    ];
  }

  /**
   * Checks if at least one consumer is created.
   */
  protected function checkConsumerCreated() {
    $consumers = $this->consumerManager->getConsumers();
    $has_consumers = !empty($consumers);

    return [
      'title' => 'API Consumer',
      'description' => 'At least one OAuth2 consumer for frontend apps.',
      'status' => $has_consumers ? 'complete' : 'incomplete',
      'auto_fix' => FALSE,
      'action' => 'create_consumer',
      'priority' => 'high',
      'details' => $has_consumers
        ? count($consumers) . ' consumer(s) configured.'
        : 'No consumers created yet. Create one to authenticate frontend apps.',
    ];
  }

  /**
   * Checks if CORS is enabled.
   */
  protected function checkCors() {
    $enabled = $this->configManager->isCorsEnabled();
    $origins = $this->configManager->getAllowedOrigins();

    return [
      'title' => 'CORS Configuration',
      'description' => 'Cross-Origin Resource Sharing for frontend access.',
      'status' => ($enabled && !empty($origins)) ? 'complete' : 'warning',
      'auto_fix' => !$enabled,
      'action' => 'enable_cors',
      'priority' => 'high',
      'details' => ($enabled && !empty($origins))
        ? 'CORS is enabled for ' . count($origins) . ' origin(s).'
        : ($enabled ? 'CORS is enabled but no origins configured.' : 'CORS is not enabled.'),
    ];
  }

  /**
   * Checks if preview URLs are configured.
   */
  protected function checkPreviewUrls() {
    $config = $this->configManager->getSettings();
    $default_url = $config->get('default_preview_url');
    $framework_urls = $config->get('preview_urls') ?? [];

    $configured = !empty($default_url) || !empty($framework_urls);

    return [
      'title' => 'Preview URLs',
      'description' => 'Frontend preview URLs for content editors.',
      'status' => $configured ? 'complete' : 'optional',
      'auto_fix' => FALSE,
      'action' => 'configure_preview',
      'priority' => 'medium',
      'details' => $configured
        ? 'Preview URLs are configured.'
        : 'Preview URLs not configured. Configure to enable content preview.',
    ];
  }

  /**
   * Checks if webhooks are configured.
   */
  protected function checkWebhooks() {
    $webhook_manager = \Drupal::service('drupal_headless.webhook_manager');
    $webhooks = $webhook_manager->getActiveWebhooks();

    $configured = !empty($webhooks);

    return [
      'title' => 'Webhooks',
      'description' => 'Notifications for content changes.',
      'status' => $configured ? 'complete' : 'optional',
      'auto_fix' => FALSE,
      'action' => 'configure_webhooks',
      'priority' => 'medium',
      'details' => $configured
        ? count($webhooks) . ' webhook(s) configured.'
        : 'No webhooks configured. Configure to notify frontend of content changes.',
    ];
  }

  /**
   * Executes an auto-fix action.
   *
   * @param string $action
   *   The action to execute.
   *
   * @return array
   *   Result with 'success' boolean and 'message' string.
   */
  public function executeAction($action) {
    try {
      switch ($action) {
        case 'enable_jsonapi':
          return $this->enableModule('jsonapi', 'JSON:API');

        case 'enable_consumers':
          return $this->enableModule('consumers', 'Consumers');

        case 'enable_simple_oauth':
          return $this->enableModule('simple_oauth', 'Simple OAuth');

        case 'generate_oauth_keys':
          $success = $this->keyManager->generateKeys();
          return [
            'success' => $success,
            'message' => $success
              ? 'OAuth2 keys generated successfully.'
              : 'Failed to generate OAuth2 keys. Check logs for details.',
          ];

        case 'enable_cors':
          $config = $this->configManager->getConfig();
          $config->set('enable_cors', TRUE);

          // Set default origins if none exist.
          $origins = $config->get('cors_allowed_origins') ?? [];
          if (empty($origins)) {
            $config->set('cors_allowed_origins', [
              'http://localhost:3000',
              'http://localhost:4321',
            ]);
          }

          $config->save();

          return [
            'success' => TRUE,
            'message' => 'CORS enabled with default localhost origins.',
          ];

        default:
          return [
            'success' => FALSE,
            'message' => 'Unknown action: ' . $action,
          ];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to execute action @action: @error', [
        '@action' => $action,
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Enables a module.
   *
   * @param string $module
   *   The module machine name.
   * @param string $label
   *   The module label.
   *
   * @return array
   *   Result with success and message.
   */
  protected function enableModule($module, $label) {
    if ($this->moduleHandler->moduleExists($module)) {
      return [
        'success' => TRUE,
        'message' => $label . ' is already enabled.',
      ];
    }

    try {
      $success = $this->moduleInstaller->install([$module]);

      return [
        'success' => $success,
        'message' => $success
          ? $label . ' enabled successfully.'
          : 'Failed to enable ' . $label . '.',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Error enabling ' . $label . ': ' . $e->getMessage(),
      ];
    }
  }

}
