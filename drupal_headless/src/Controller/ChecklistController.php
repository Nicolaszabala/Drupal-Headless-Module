<?php

namespace Drupal\drupal_headless\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupal_headless\Service\HealthCheckManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for setup checklist.
 */
class ChecklistController extends ControllerBase {

  /**
   * The health check manager.
   *
   * @var \Drupal\drupal_headless\Service\HealthCheckManager
   */
  protected $healthCheckManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->healthCheckManager = $container->get('drupal_headless.health_check_manager');
    return $instance;
  }

  /**
   * Displays the setup checklist.
   *
   * @return array
   *   A render array.
   */
  public function checklist() {
    $build = [];

    $checks = $this->healthCheckManager->getAllChecks();
    $completion = $this->healthCheckManager->getCompletionPercentage();

    $build['#attached']['library'][] = 'drupal_headless/checklist';

    $build['intro'] = [
      '#markup' => '<h2>' . $this->t('Setup Checklist') . '</h2>' .
        '<p>' . $this->t('Complete these steps to configure your headless Drupal installation. Items marked with ✓ are complete, and some can be fixed automatically with a single click.') . '</p>',
    ];

    // Progress bar.
    $build['progress'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checklist-progress']],
    ];

    $build['progress']['bar'] = [
      '#markup' => '<div class="progress-bar">' .
        '<div class="progress-fill" style="width: ' . $completion . '%;">' .
        '<span class="progress-text">' . $completion . '%</span>' .
        '</div>' .
        '</div>',
    ];

    if ($completion === 100) {
      $build['complete_message'] = [
        '#markup' => '<div class="messages messages--status checklist-complete">' .
          '<h3>🎉 ' . $this->t('Setup Complete!') . '</h3>' .
          '<p>' . $this->t('All required configuration is complete. Your headless Drupal is ready to use!') . '</p>' .
          '</div>',
      ];
    }

    // Checklist items.
    $build['checklist'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checklist-items']],
    ];

    $priority_order = ['critical', 'high', 'medium', 'optional'];
    $grouped_checks = [];

    // Group by priority.
    foreach ($checks as $key => $check) {
      $priority = $check['priority'] ?? 'medium';
      $grouped_checks[$priority][$key] = $check;
    }

    foreach ($priority_order as $priority) {
      if (empty($grouped_checks[$priority])) {
        continue;
      }

      $priority_label = ucfirst($priority);
      if ($priority === 'optional') {
        $priority_label = 'Optional Features';
      }

      $build['checklist'][$priority] = [
        '#type' => 'details',
        '#title' => $this->t('@priority Items', ['@priority' => $priority_label]),
        '#open' => $priority === 'critical' || $priority === 'high',
        '#attributes' => ['class' => ['checklist-group', 'priority-' . $priority]],
      ];

      foreach ($grouped_checks[$priority] as $key => $check) {
        $build['checklist'][$priority][$key] = $this->buildChecklistItem($key, $check);
      }
    }

    // Quick actions.
    $build['quick_actions'] = [
      '#type' => 'details',
      '#title' => $this->t('Quick Actions'),
      '#open' => FALSE,
    ];

    $build['quick_actions']['run_wizard'] = [
      '#type' => 'link',
      '#title' => $this->t('Run Setup Wizard'),
      '#url' => Url::fromRoute('drupal_headless.setup_wizard'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['quick_actions']['dashboard'] = [
      '#type' => 'link',
      '#title' => $this->t('Go to Dashboard'),
      '#url' => Url::fromRoute('drupal_headless.dashboard'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $build;
  }

  /**
   * Builds a single checklist item.
   *
   * @param string $key
   *   The check key.
   * @param array $check
   *   The check data.
   *
   * @return array
   *   Render array for the item.
   */
  protected function buildChecklistItem($key, array $check) {
    $status_icon = [
      'complete' => '✓',
      'incomplete' => '✗',
      'warning' => '⚠',
      'optional' => '○',
    ];

    $status_class = [
      'complete' => 'success',
      'incomplete' => 'error',
      'warning' => 'warning',
      'optional' => 'optional',
    ];

    $icon = $status_icon[$check['status']] ?? '○';
    $class = $status_class[$check['status']] ?? 'optional';

    $item = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checklist-item', 'status-' . $class]],
    ];

    $item['status'] = [
      '#markup' => '<span class="checklist-status">' . $icon . '</span>',
    ];

    $item['content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checklist-content']],
    ];

    $item['content']['title'] = [
      '#markup' => '<h4>' . $check['title'] . '</h4>',
    ];

    $item['content']['description'] = [
      '#markup' => '<p class="checklist-description">' . $check['description'] . '</p>',
    ];

    $item['content']['details'] = [
      '#markup' => '<p class="checklist-details">' . $check['details'] . '</p>',
    ];

    // Add action button if available.
    if (!empty($check['action'])) {
      $item['content']['action'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['checklist-action']],
      ];

      if ($check['auto_fix'] && $check['status'] !== 'complete') {
        // Auto-fix button.
        $item['content']['action']['button'] = [
          '#type' => 'link',
          '#title' => $this->t('Fix Automatically'),
          '#url' => Url::fromRoute('drupal_headless.checklist_action', [
            'action' => $check['action'],
          ]),
          '#attributes' => [
            'class' => ['button', 'button--primary', 'button--small', 'use-ajax'],
          ],
        ];
      }
      elseif ($check['status'] !== 'complete') {
        // Manual action button.
        $url = $this->getActionUrl($check['action']);
        if ($url) {
          $item['content']['action']['button'] = [
            '#type' => 'link',
            '#title' => $this->t('Configure'),
            '#url' => $url,
            '#attributes' => [
              'class' => ['button', 'button--small'],
            ],
          ];
        }
      }
    }

    return $item;
  }

  /**
   * Gets the URL for a manual action.
   *
   * @param string $action
   *   The action name.
   *
   * @return \Drupal\Core\Url|null
   *   The URL or NULL.
   */
  protected function getActionUrl($action) {
    $routes = [
      'create_consumer' => 'drupal_headless.create_consumer',
      'configure_preview' => 'drupal_headless.settings',
      'configure_webhooks' => 'drupal_headless.webhooks',
    ];

    if (isset($routes[$action])) {
      return Url::fromRoute($routes[$action]);
    }

    return NULL;
  }

  /**
   * Executes a checklist action.
   *
   * @param string $action
   *   The action to execute.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function executeAction($action, Request $request) {
    $result = $this->healthCheckManager->executeAction($action);

    if ($result['success']) {
      $this->messenger()->addStatus($result['message']);
    }
    else {
      $this->messenger()->addError($result['message']);
    }

    // Redirect back to checklist.
    return new RedirectResponse(Url::fromRoute('drupal_headless.checklist')->toString());
  }

}
