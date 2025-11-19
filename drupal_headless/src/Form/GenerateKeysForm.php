<?php

namespace Drupal\drupal_headless\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\drupal_headless\Service\OAuth2KeyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for generating OAuth2 keys.
 */
class GenerateKeysForm extends ConfirmFormBase {

  /**
   * The OAuth2 key manager.
   *
   * @var \Drupal\drupal_headless\Service\OAuth2KeyManager
   */
  protected $keyManager;

  /**
   * Constructs a GenerateKeysForm object.
   *
   * @param \Drupal\drupal_headless\Service\OAuth2KeyManager $key_manager
   *   The OAuth2 key manager service.
   */
  public function __construct(OAuth2KeyManager $key_manager) {
    $this->keyManager = $key_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('drupal_headless.oauth2_key_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_headless_generate_keys';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->keyManager->keysExist()) {
      return $this->t('Regenerate OAuth2 Keys?');
    }
    return $this->t('Generate OAuth2 Keys');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->keyManager->keysExist()) {
      return $this->t('This will replace your existing OAuth2 keys. A backup of the old keys will be created. All existing API tokens will be invalidated and clients will need to re-authenticate.');
    }
    return $this->t('This will generate new OAuth2 RSA keys (2048-bit) for securing API authentication. The keys will be stored in your private file system.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('drupal_headless.dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check if private file system is configured.
    $file_system = \Drupal::service('file_system');
    $private_path = $file_system->realpath('private://');

    if (!$private_path) {
      $this->messenger()->addError($this->t('Private file system is not configured. Please configure it in settings.php before generating keys.'));
      $form['error'] = [
        '#markup' => '<p>' . $this->t('Add the following to your settings.php:') . '</p>' .
          '<pre>$settings[\'file_private_path\'] = \'../private\';</pre>',
      ];
      return $form;
    }

    // Show current key status.
    $validation = $this->keyManager->validateKeys();

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Current Status'),
      '#open' => TRUE,
    ];

    if ($this->keyManager->keysExist()) {
      $paths = $this->keyManager->getKeyPaths();

      $form['status']['keys_exist'] = [
        '#markup' => '<p>' . $this->t('Keys already exist at:') . '</p>' .
          '<ul>' .
          '<li>' . $this->t('Private key: @path', ['@path' => $paths['private']]) . '</li>' .
          '<li>' . $this->t('Public key: @path', ['@path' => $paths['public']]) . '</li>' .
          '</ul>',
      ];

      foreach ($validation['messages'] as $message) {
        $form['status']['messages'][] = [
          '#markup' => '<p>' . $message . '</p>',
        ];
      }
    }
    else {
      $form['status']['no_keys'] = [
        '#markup' => '<p>' . $this->t('No OAuth2 keys found. Click "Generate" to create them.') . '</p>',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->keyManager->keysExist()) {
      $success = $this->keyManager->regenerateKeys();
    }
    else {
      $success = $this->keyManager->generateKeys();
    }

    if ($success) {
      $this->messenger()->addStatus($this->t('OAuth2 keys have been generated successfully.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
