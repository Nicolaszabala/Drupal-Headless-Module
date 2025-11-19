<?php

namespace Drupal\drupal_headless\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Manages OAuth2 key generation and configuration.
 */
class OAuth2KeyManager {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs an OAuth2KeyManager object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('drupal_headless');
    $this->messenger = $messenger;
  }

  /**
   * Generates OAuth2 keys (private and public).
   *
   * @return bool
   *   TRUE if keys were generated successfully, FALSE otherwise.
   */
  public function generateKeys() {
    $private_path = $this->fileSystem->realpath('private://');

    if (!$private_path) {
      $this->messenger->addError(t('Private file system is not configured. Cannot generate OAuth2 keys.'));
      $this->logger->error('Cannot generate OAuth2 keys: private file system not configured.');
      return FALSE;
    }

    $keys_path = $private_path . '/oauth_keys';

    // Create oauth_keys directory if it doesn't exist.
    if (!$this->fileSystem->prepareDirectory($keys_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->messenger->addError(t('Could not create OAuth2 keys directory at @path', ['@path' => $keys_path]));
      $this->logger->error('Could not create OAuth2 keys directory at @path', ['@path' => $keys_path]);
      return FALSE;
    }

    $private_key_path = $keys_path . '/private.key';
    $public_key_path = $keys_path . '/public.key';

    // Check if keys already exist.
    if (file_exists($private_key_path) && file_exists($public_key_path)) {
      $this->messenger->addWarning(t('OAuth2 keys already exist. Use regenerate if you need new keys.'));
      return FALSE;
    }

    // Generate private key using openssl.
    $private_key = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($private_key === FALSE) {
      $this->messenger->addError(t('Failed to generate private key.'));
      $this->logger->error('OpenSSL private key generation failed.');
      return FALSE;
    }

    // Export private key.
    openssl_pkey_export($private_key, $private_key_pem);

    // Get public key.
    $public_key_details = openssl_pkey_get_details($private_key);
    $public_key_pem = $public_key_details['key'];

    // Write keys to files.
    if (file_put_contents($private_key_path, $private_key_pem) === FALSE) {
      $this->messenger->addError(t('Failed to write private key to file.'));
      $this->logger->error('Failed to write private key to @path', ['@path' => $private_key_path]);
      return FALSE;
    }

    if (file_put_contents($public_key_path, $public_key_pem) === FALSE) {
      $this->messenger->addError(t('Failed to write public key to file.'));
      $this->logger->error('Failed to write public key to @path', ['@path' => $public_key_path]);
      return FALSE;
    }

    // Set restrictive permissions.
    chmod($private_key_path, 0600);
    chmod($public_key_path, 0644);

    $this->messenger->addStatus(t('OAuth2 keys generated successfully.'));
    $this->logger->info('OAuth2 keys generated at @path', ['@path' => $keys_path]);

    // Auto-configure Simple OAuth if it's enabled.
    if (\Drupal::moduleHandler()->moduleExists('simple_oauth')) {
      $this->configureSimpleOAuth($private_key_path, $public_key_path);
    }

    return TRUE;
  }

  /**
   * Regenerates OAuth2 keys (replaces existing ones).
   *
   * @return bool
   *   TRUE if keys were regenerated successfully, FALSE otherwise.
   */
  public function regenerateKeys() {
    $private_path = $this->fileSystem->realpath('private://');

    if (!$private_path) {
      return FALSE;
    }

    $keys_path = $private_path . '/oauth_keys';
    $private_key_path = $keys_path . '/private.key';
    $public_key_path = $keys_path . '/public.key';

    // Backup existing keys before regenerating.
    if (file_exists($private_key_path)) {
      $backup_path = $keys_path . '/private.key.backup.' . time();
      copy($private_key_path, $backup_path);
    }

    // Delete existing keys.
    if (file_exists($private_key_path)) {
      unlink($private_key_path);
    }
    if (file_exists($public_key_path)) {
      unlink($public_key_path);
    }

    // Generate new keys.
    return $this->generateKeys();
  }

  /**
   * Checks if OAuth2 keys exist.
   *
   * @return bool
   *   TRUE if both keys exist, FALSE otherwise.
   */
  public function keysExist() {
    $private_path = $this->fileSystem->realpath('private://');

    if (!$private_path) {
      return FALSE;
    }

    $keys_path = $private_path . '/oauth_keys';
    $private_key_path = $keys_path . '/private.key';
    $public_key_path = $keys_path . '/public.key';

    return file_exists($private_key_path) && file_exists($public_key_path);
  }

  /**
   * Gets the paths to the OAuth2 keys.
   *
   * @return array
   *   Array with 'private' and 'public' key paths, or empty if not configured.
   */
  public function getKeyPaths() {
    $private_path = $this->fileSystem->realpath('private://');

    if (!$private_path) {
      return [];
    }

    $keys_path = $private_path . '/oauth_keys';

    return [
      'private' => $keys_path . '/private.key',
      'public' => $keys_path . '/public.key',
      'dir' => $keys_path,
    ];
  }

  /**
   * Auto-configures Simple OAuth module with generated keys.
   *
   * @param string $private_key_path
   *   Path to private key.
   * @param string $public_key_path
   *   Path to public key.
   */
  protected function configureSimpleOAuth($private_key_path, $public_key_path) {
    $config = \Drupal::configFactory()->getEditable('simple_oauth.settings');

    $config->set('public_key', $public_key_path);
    $config->set('private_key', $private_key_path);
    $config->save();

    $this->messenger->addStatus(t('Simple OAuth module configured with generated keys.'));
    $this->logger->info('Simple OAuth auto-configured with OAuth2 keys.');
  }

  /**
   * Validates that OAuth2 keys are properly configured.
   *
   * @return array
   *   Array of validation results with 'status' and 'messages' keys.
   */
  public function validateKeys() {
    $results = [
      'status' => TRUE,
      'messages' => [],
    ];

    // Check if private file system exists.
    $private_path = $this->fileSystem->realpath('private://');
    if (!$private_path) {
      $results['status'] = FALSE;
      $results['messages'][] = t('Private file system not configured.');
      return $results;
    }

    // Check if keys exist.
    if (!$this->keysExist()) {
      $results['status'] = FALSE;
      $results['messages'][] = t('OAuth2 keys do not exist.');
      return $results;
    }

    // Check if keys are readable.
    $paths = $this->getKeyPaths();
    if (!is_readable($paths['private'])) {
      $results['status'] = FALSE;
      $results['messages'][] = t('Private key is not readable.');
    }
    if (!is_readable($paths['public'])) {
      $results['status'] = FALSE;
      $results['messages'][] = t('Public key is not readable.');
    }

    // Check if Simple OAuth is configured.
    if (\Drupal::moduleHandler()->moduleExists('simple_oauth')) {
      $config = \Drupal::config('simple_oauth.settings');
      $configured_public = $config->get('public_key');
      $configured_private = $config->get('private_key');

      if (empty($configured_public) || empty($configured_private)) {
        $results['status'] = FALSE;
        $results['messages'][] = t('Simple OAuth module is not configured with key paths.');
      }
    }

    if ($results['status']) {
      $results['messages'][] = t('OAuth2 keys are properly configured and valid.');
    }

    return $results;
  }

}
