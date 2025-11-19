<?php

namespace Drupal\drupal_headless\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupal_headless\Service\PreviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for preview functionality.
 */
class PreviewController extends ControllerBase {

  /**
   * The preview manager.
   *
   * @var \Drupal\drupal_headless\Service\PreviewManager
   */
  protected $previewManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->previewManager = $container->get('drupal_headless.preview_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Generates a preview URL and redirects to frontend.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to preview URL.
   */
  public function preview($entity_type_id, $entity_id, Request $request) {
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity = $storage->load($entity_id);

      if (!$entity) {
        $this->messenger()->addError($this->t('Entity not found.'));
        return $this->redirect('<front>');
      }

      // Get framework from query parameter.
      $framework = $request->query->get('framework');

      // Generate preview URL.
      $preview_url = $this->previewManager->generatePreviewUrl($entity, $framework);

      if (!$preview_url) {
        $this->messenger()->addError(
          $this->t('Preview URL not configured. Please configure preview settings.')
        );
        return $this->redirect('<front>');
      }

      return new RedirectResponse($preview_url);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to generate preview: @error', [
        '@error' => $e->getMessage(),
      ]));
      return $this->redirect('<front>');
    }
  }

  /**
   * API endpoint to validate preview token and get entity data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with entity data or error.
   */
  public function validateToken(Request $request) {
    $token = $request->query->get('token');

    if (!$token) {
      return new JsonResponse([
        'error' => 'Missing token parameter',
      ], 400);
    }

    // Validate token.
    $token_data = $this->previewManager->validateToken($token);

    if (!$token_data) {
      return new JsonResponse([
        'error' => 'Invalid or expired token',
      ], 403);
    }

    // Load entity.
    try {
      $storage = $this->entityTypeManager->getStorage($token_data['entity_type']);
      $entity = $storage->load($token_data['entity_id']);

      if (!$entity) {
        return new JsonResponse([
          'error' => 'Entity not found',
        ], 404);
      }

      // Return entity data (let JSON:API handle the serialization).
      return new JsonResponse([
        'valid' => TRUE,
        'entity_type' => $token_data['entity_type'],
        'entity_id' => $token_data['entity_id'],
        'entity_uuid' => $token_data['entity_uuid'],
        'bundle' => $token_data['bundle'],
        'expires' => $token_data['expires'],
        'message' => 'Token is valid. Use this UUID with JSON:API to fetch the entity.',
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => 'Failed to load entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Shows preview configuration page.
   *
   * @return array
   *   A render array.
   */
  public function configuration() {
    $build = [];

    $config = $this->previewManager->getPreviewConfigurations();

    $build['intro'] = [
      '#markup' => '<h2>' . $this->t('Preview Configuration') . '</h2>' .
        '<p>' . $this->t('Configure preview URLs for your frontend applications. When editors click "Preview", they will be redirected to these URLs with a temporary preview token.') . '</p>',
    ];

    $build['current'] = [
      '#type' => 'details',
      '#title' => $this->t('Current Configuration'),
      '#open' => TRUE,
    ];

    $build['current']['default'] = [
      '#type' => 'item',
      '#title' => $this->t('Default Preview URL'),
      '#markup' => $config['default_url'] ? '<code>' . $config['default_url'] . '</code>' : '<em>' . $this->t('Not configured') . '</em>',
    ];

    if (!empty($config['framework_urls'])) {
      $items = [];
      foreach ($config['framework_urls'] as $framework => $url) {
        $items[] = '<strong>' . ucfirst($framework) . ':</strong> <code>' . $url . '</code>';
      }

      $build['current']['frameworks'] = [
        '#type' => 'item',
        '#title' => $this->t('Framework-specific URLs'),
        '#markup' => '<ul><li>' . implode('</li><li>', $items) . '</li></ul>',
      ];
    }

    $build['current']['token_lifetime'] = [
      '#type' => 'item',
      '#title' => $this->t('Token Lifetime'),
      '#markup' => $this->t('@minutes minutes', ['@minutes' => $config['token_lifetime'] / 60]),
    ];

    $build['configure'] = [
      '#type' => 'link',
      '#title' => $this->t('Configure Preview URLs'),
      '#url' => \Drupal\Core\Url::fromRoute('drupal_headless.settings'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    // Show active tokens.
    $tokens = $this->previewManager->getActiveTokens();

    $build['active_tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Active Preview Tokens (@count)', ['@count' => count($tokens)]),
      '#open' => FALSE,
    ];

    if (!empty($tokens)) {
      $rows = [];
      foreach ($tokens as $token => $data) {
        $entity_link = \Drupal\Core\Link::createFromRoute(
          $data['entity_type'] . ':' . $data['entity_id'],
          'entity.' . $data['entity_type'] . '.canonical',
          [$data['entity_type'] => $data['entity_id']]
        )->toString();

        $expires_in = $data['expires'] - time();
        $expires_text = $expires_in > 0
          ? $this->t('@minutes min', ['@minutes' => round($expires_in / 60)])
          : $this->t('Expired');

        $rows[] = [
          substr($token, 0, 20) . '...',
          $entity_link,
          $data['bundle'],
          $expires_text,
          \Drupal::service('date.formatter')->format($data['created'], 'short'),
        ];
      }

      $build['active_tokens']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Token'),
          $this->t('Entity'),
          $this->t('Bundle'),
          $this->t('Expires In'),
          $this->t('Created'),
        ],
        '#rows' => $rows,
      ];
    }
    else {
      $build['active_tokens']['empty'] = [
        '#markup' => '<p><em>' . $this->t('No active preview tokens.') . '</em></p>',
      ];
    }

    $build['usage'] = [
      '#type' => 'details',
      '#title' => $this->t('Frontend Implementation'),
      '#open' => FALSE,
    ];

    $build['usage']['content'] = [
      '#markup' => '<h3>' . $this->t('How to use preview tokens in your frontend') . '</h3>' .
        '<ol>' .
        '<li>' . $this->t('When a preview URL is opened, extract the <code>preview</code> token from the URL query parameters.') . '</li>' .
        '<li>' . $this->t('Call the token validation endpoint: <code>GET /drupal-headless/preview/validate?token=TOKEN</code>') . '</li>' .
        '<li>' . $this->t('If valid, use the returned <code>entity_uuid</code> to fetch the entity from JSON:API.') . '</li>' .
        '<li>' . $this->t('Use the preview secret in the Authorization header to bypass published status checks.') . '</li>' .
        '</ol>' .
        '<h4>' . $this->t('Example (Next.js)') . '</h4>' .
        '<pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; font-size: 13px;">// pages/articles/[uuid].js
export async function getServerSideProps(context) {
  const { preview, uuid } = context.query;

  if (preview) {
    // Validate preview token
    const validation = await fetch(
      `${process.env.DRUPAL_BASE_URL}/drupal-headless/preview/validate?token=${preview}`
    ).then(r => r.json());

    if (!validation.valid) {
      return { notFound: true };
    }

    // Fetch entity using UUID
    const article = await fetch(
      `${process.env.DRUPAL_BASE_URL}/jsonapi/node/article/${validation.entity_uuid}`,
      {
        headers: {
          \'Authorization\': `Bearer ${accessToken}`
        }
      }
    ).then(r => r.json());

    return {
      props: {
        article: article.data,
        preview: true
      }
    };
  }

  // Normal published content fetch...
}</pre>',
    ];

    return $build;
  }

  /**
   * Access check for preview.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function previewAccess($entity_type_id, $entity_id, AccountInterface $account) {
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity = $storage->load($entity_id);

      if (!$entity) {
        return AccessResult::forbidden('Entity not found');
      }

      // Check if user has edit access to the entity.
      return $entity->access('update', $account, TRUE);
    }
    catch (\Exception $e) {
      return AccessResult::forbidden($e->getMessage());
    }
  }

}
