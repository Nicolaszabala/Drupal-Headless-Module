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

  /**
   * Tests validateKeys returns success when keys are properly configured.
   *
   * @covers ::validateKeys
   */
  public function testValidateKeysSuccess() {
    $private_path = sys_get_temp_dir() . '/drupal_test_' . uniqid();
    mkdir($private_path . '/oauth_keys', 0755, TRUE);

    // Create dummy key files.
    file_put_contents($private_path . '/oauth_keys/private.key', 'test-private-key');
    file_put_contents($private_path . '/oauth_keys/public.key', 'test-public-key');

    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn($private_path);

    $result = $this->keyManager->validateKeys();

    $this->assertTrue($result['status']);
    $this->assertNotEmpty($result['messages']);

    // Cleanup.
    unlink($private_path . '/oauth_keys/private.key');
    unlink($private_path . '/oauth_keys/public.key');
    rmdir($private_path . '/oauth_keys');
    rmdir($private_path);
  }

  /**
   * Tests keysExist returns TRUE when both keys exist.
   *
   * @covers ::keysExist
   */
  public function testKeysExistReturnsTrueWhenBothKeysExist() {
    $private_path = sys_get_temp_dir() . '/drupal_test_' . uniqid();
    mkdir($private_path . '/oauth_keys', 0755, TRUE);

    // Create dummy key files.
    file_put_contents($private_path . '/oauth_keys/private.key', 'test-private-key');
    file_put_contents($private_path . '/oauth_keys/public.key', 'test-public-key');

    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn($private_path);

    $this->assertTrue($this->keyManager->keysExist());

    // Cleanup.
    unlink($private_path . '/oauth_keys/private.key');
    unlink($private_path . '/oauth_keys/public.key');
    rmdir($private_path . '/oauth_keys');
    rmdir($private_path);
  }

  /**
   * Tests keysExist returns FALSE when only private key exists.
   *
   * @covers ::keysExist
   */
  public function testKeysExistReturnsFalseWithOnlyPrivateKey() {
    $private_path = sys_get_temp_dir() . '/drupal_test_' . uniqid();
    mkdir($private_path . '/oauth_keys', 0755, TRUE);

    // Create only private key.
    file_put_contents($private_path . '/oauth_keys/private.key', 'test-private-key');

    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn($private_path);

    $this->assertFalse($this->keyManager->keysExist());

    // Cleanup.
    unlink($private_path . '/oauth_keys/private.key');
    rmdir($private_path . '/oauth_keys');
    rmdir($private_path);
  }

  /**
   * Tests generateKeys fails when private path not configured.
   *
   * @covers ::generateKeys
   */
  public function testGenerateKeysFailsWithoutPrivatePath() {
    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn(FALSE);

    $this->messenger->expects($this->once())
      ->method('addError');

    $this->assertFalse($this->keyManager->generateKeys());
  }

  /**
   * Tests regenerateKeys returns FALSE when private path not configured.
   *
   * @covers ::regenerateKeys
   */
  public function testRegenerateKeysFailsWithoutPrivatePath() {
    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn(FALSE);

    $this->assertFalse($this->keyManager->regenerateKeys());
  }

  /**
   * Tests validateKeys detects unreadable private key.
   *
   * @covers ::validateKeys
   */
  public function testValidateKeysDetectsUnreadablePrivateKey() {
    $private_path = sys_get_temp_dir() . '/drupal_test_' . uniqid();
    mkdir($private_path . '/oauth_keys', 0755, TRUE);

    // Create keys with restrictive permissions.
    file_put_contents($private_path . '/oauth_keys/private.key', 'test-private-key');
    file_put_contents($private_path . '/oauth_keys/public.key', 'test-public-key');
    chmod($private_path . '/oauth_keys/private.key', 0000);

    $this->fileSystem->method('realpath')
      ->with('private://')
      ->willReturn($private_path);

    $result = $this->keyManager->validateKeys();

    $this->assertFalse($result['status']);
    $this->assertNotEmpty($result['messages']);

    // Cleanup (restore permissions first).
    chmod($private_path . '/oauth_keys/private.key', 0644);
    unlink($private_path . '/oauth_keys/private.key');
    unlink($private_path . '/oauth_keys/public.key');
    rmdir($private_path . '/oauth_keys');
    rmdir($private_path);
  }

}
