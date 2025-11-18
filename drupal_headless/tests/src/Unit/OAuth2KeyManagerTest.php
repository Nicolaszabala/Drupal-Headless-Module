<?php

namespace Drupal\Tests\drupal_headless\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\drupal_headless\Service\OAuth2KeyManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for OAuth2KeyManager service.
 *
 * @group drupal_headless
 * @coversDefaultClass \Drupal\drupal_headless\Service\OAuth2KeyManager
 */
class OAuth2KeyManagerTest extends UnitTestCase {

  /**
   * The OAuth2KeyManager under test.
   *
   * @var \Drupal\drupal_headless\Service\OAuth2KeyManager
   */
  protected $keyManager;

  /**
   * Mock file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * Mock messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->keyManager = new OAuth2KeyManager(
      $this->fileSystem,
      $this->loggerFactory,
      $this->messenger
    );
  }

  /**
   * Tests keysExist returns FALSE when private path not configured.
   *
   * @covers ::keysExist
   */
  public function testKeysExistReturnsFalseWithoutPrivatePath() {
    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn(FALSE);

    $this->assertFalse($this->keyManager->keysExist());
  }

  /**
   * Tests getKeyPaths returns empty array without private path.
   *
   * @covers ::getKeyPaths
   */
  public function testGetKeyPathsWithoutPrivatePath() {
    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn(FALSE);

    $this->assertEquals([], $this->keyManager->getKeyPaths());
  }

  /**
   * Tests getKeyPaths returns correct paths when configured.
   *
   * @covers ::getKeyPaths
   */
  public function testGetKeyPathsReturnsCorrectPaths() {
    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn('/var/www/private');

    $paths = $this->keyManager->getKeyPaths();

    $this->assertEquals('/var/www/private/oauth_keys/private.key', $paths['private']);
    $this->assertEquals('/var/www/private/oauth_keys/public.key', $paths['public']);
    $this->assertEquals('/var/www/private/oauth_keys', $paths['dir']);
  }

  /**
   * Tests validateKeys returns error when private path not configured.
   *
   * @covers ::validateKeys
   */
  public function testValidateKeysWithoutPrivatePath() {
    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn(FALSE);

    $result = $this->keyManager->validateKeys();

    $this->assertFalse($result['status']);
    $this->assertNotEmpty($result['messages']);
  }

}
