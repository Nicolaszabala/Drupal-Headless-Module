<?php

namespace Drupal\drupal_headless\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Manages Drupal Headless Module configuration.
 */
class ConfigurationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a ConfigurationManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger_factory->get('drupal_headless');
  }

  /**
   * Gets the Drupal Headless Module settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  public function getSettings() {
    return $this->configFactory->get('drupal_headless.settings');
  }

  /**
   * Checks if CORS is enabled.
   *
   * @return bool
   *   TRUE if CORS is enabled, FALSE otherwise.
   */
  public function isCorsEnabled() {
    return (bool) $this->getSettings()->get('enable_cors');
  }

  /**
   * Gets allowed CORS origins.
   *
   * @return array
   *   Array of allowed origin URLs.
   */
  public function getAllowedOrigins() {
    $origins = $this->getSettings()->get('cors_allowed_origins');
    return is_array($origins) ? $origins : [];
  }

  /**
   * Checks if rate limiting is enabled.
   *
   * @return bool
   *   TRUE if rate limiting is enabled, FALSE otherwise.
   */
  public function isRateLimitingEnabled() {
    return (bool) $this->getSettings()->get('enable_rate_limiting');
  }

  /**
   * Gets rate limit configuration.
   *
   * @return array
   *   Array with 'requests' and 'window' keys.
   */
  public function getRateLimitConfig() {
    return [
      'requests' => $this->getSettings()->get('rate_limit_requests') ?? 100,
      'window' => $this->getSettings()->get('rate_limit_window') ?? 3600,
    ];
  }

  /**
   * Gets configured frontend frameworks.
   *
   * @return array
   *   Array of framework configurations.
   */
  public function getFrameworks() {
    $frameworks = $this->getSettings()->get('frontend_frameworks');
    return is_array($frameworks) ? $frameworks : [];
  }

  /**
   * Adds a framework configuration.
   *
   * @param array $framework
   *   Framework configuration array with keys: name, type, preview_url.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function addFramework(array $framework) {
    if (empty($framework['name']) || empty($framework['type'])) {
      $this->logger->error('Framework name and type are required.');
      return FALSE;
    }

    $config = $this->configFactory->getEditable('drupal_headless.settings');
    $frameworks = $config->get('frontend_frameworks') ?? [];
    $frameworks[] = $framework;
    $config->set('frontend_frameworks', $frameworks)->save();

    $this->logger->info('Added framework configuration: @name', [
      '@name' => $framework['name'],
    ]);

    return TRUE;
  }

  /**
   * Removes a framework configuration.
   *
   * @param string $consumer_uuid
   *   The consumer UUID associated with the framework.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function removeFramework($consumer_uuid) {
    $config = $this->configFactory->getEditable('drupal_headless.settings');
    $frameworks = $config->get('frontend_frameworks') ?? [];

    $updated_frameworks = array_filter($frameworks, function ($framework) use ($consumer_uuid) {
      return !isset($framework['consumer_uuid']) || $framework['consumer_uuid'] !== $consumer_uuid;
    });

    if (count($updated_frameworks) === count($frameworks)) {
      return FALSE;
    }

    $config->set('frontend_frameworks', array_values($updated_frameworks))->save();
    return TRUE;
  }

  /**
   * Checks if required dependencies are enabled.
   *
   * @return array
   *   Array of missing module names, empty if all are present.
   */
  public function checkDependencies() {
    $required = ['jsonapi', 'consumers', 'simple_oauth'];
    $missing = [];

    foreach ($required as $module) {
      if (!$this->moduleHandler->moduleExists($module)) {
        $missing[] = $module;
      }
    }

    return $missing;
  }

}
