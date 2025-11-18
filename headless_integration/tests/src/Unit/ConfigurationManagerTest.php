<?php

namespace Drupal\Tests\headless_integration\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\headless_integration\Service\ConfigurationManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ConfigurationManager service.
 *
 * @group headless_integration
 * @coversDefaultClass \Drupal\headless_integration\Service\ConfigurationManager
 */
class ConfigurationManagerTest extends UnitTestCase {

  /**
   * The configuration manager under test.
   *
   * @var \Drupal\headless_integration\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Mock module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->configManager = new ConfigurationManager(
      $this->configFactory,
      $this->moduleHandler,
      $this->loggerFactory
    );
  }

  /**
   * Tests isCorsEnabled method.
   *
   * @covers ::isCorsEnabled
   */
  public function testIsCorsEnabled() {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('enable_cors')
      ->willReturn(TRUE);

    $this->configFactory->method('get')
      ->with('headless_integration.settings')
      ->willReturn($config);

    $this->assertTrue($this->configManager->isCorsEnabled());
  }

  /**
   * Tests isCorsEnabled returns false when disabled.
   *
   * @covers ::isCorsEnabled
   */
  public function testIsCorsDisabled() {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('enable_cors')
      ->willReturn(FALSE);

    $this->configFactory->method('get')
      ->with('headless_integration.settings')
      ->willReturn($config);

    $this->assertFalse($this->configManager->isCorsEnabled());
  }

  /**
   * Tests getAllowedOrigins method.
   *
   * @covers ::getAllowedOrigins
   */
  public function testGetAllowedOrigins() {
    $expected_origins = ['https://example.com', 'https://test.com'];

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('cors_allowed_origins')
      ->willReturn($expected_origins);

    $this->configFactory->method('get')
      ->with('headless_integration.settings')
      ->willReturn($config);

    $this->assertEquals($expected_origins, $this->configManager->getAllowedOrigins());
  }

  /**
   * Tests getAllowedOrigins returns empty array when not configured.
   *
   * @covers ::getAllowedOrigins
   */
  public function testGetAllowedOriginsEmpty() {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('cors_allowed_origins')
      ->willReturn(NULL);

    $this->configFactory->method('get')
      ->with('headless_integration.settings')
      ->willReturn($config);

    $this->assertEquals([], $this->configManager->getAllowedOrigins());
  }

  /**
   * Tests checkDependencies with all modules present.
   *
   * @covers ::checkDependencies
   */
  public function testCheckDependenciesAllPresent() {
    $this->moduleHandler->method('moduleExists')
      ->willReturn(TRUE);

    $missing = $this->configManager->checkDependencies();
    $this->assertEmpty($missing);
  }

  /**
   * Tests checkDependencies with missing modules.
   *
   * @covers ::checkDependencies
   */
  public function testCheckDependenciesWithMissing() {
    $this->moduleHandler->method('moduleExists')
      ->willReturnCallback(function ($module) {
        return $module !== 'simple_oauth';
      });

    $missing = $this->configManager->checkDependencies();
    $this->assertContains('simple_oauth', $missing);
    $this->assertCount(1, $missing);
  }

  /**
   * Tests getRateLimitConfig method.
   *
   * @covers ::getRateLimitConfig
   */
  public function testGetRateLimitConfig() {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['rate_limit_requests', 200],
        ['rate_limit_window', 7200],
      ]);

    $this->configFactory->method('get')
      ->with('headless_integration.settings')
      ->willReturn($config);

    $rate_config = $this->configManager->getRateLimitConfig();
    $this->assertEquals(200, $rate_config['requests']);
    $this->assertEquals(7200, $rate_config['window']);
  }

}
