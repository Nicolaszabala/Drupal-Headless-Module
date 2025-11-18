<?php

namespace Drupal\headless_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\headless_integration\Service\ConfigurationManager;
use Drupal\headless_integration\Service\ConsumerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dashboard for headless integration.
 */
class DashboardController extends ControllerBase {

  /**
   * The configuration manager service.
   *
   * @var \Drupal\headless_integration\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * The consumer manager service.
   *
   * @var \Drupal\headless_integration\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->configManager = $container->get('headless_integration.configuration_manager');
    $instance->consumerManager = $container->get('headless_integration.consumer_manager');
    return $instance;
  }

  /**
   * Displays the headless integration dashboard.
   *
   * @return array
   *   A render array.
   */
  public function overview() {
    $build = [];

    // Status section.
    $build['status'] = [
      '#type' => 'details',
      '#title' => $this->t('System Status'),
      '#open' => TRUE,
    ];

    $missing_deps = $this->configManager->checkDependencies();

    $build['status']['dependencies'] = [
      '#type' => 'item',
      '#title' => $this->t('Required Dependencies'),
      '#markup' => empty($missing_deps)
        ? $this->t('<span style="color: green;">✓ All required modules are enabled</span>')
        : $this->t('<span style="color: red;">✗ Missing modules: @modules</span>', [
          '@modules' => implode(', ', $missing_deps),
        ]),
    ];

    $build['status']['cors'] = [
      '#type' => 'item',
      '#title' => $this->t('CORS'),
      '#markup' => $this->configManager->isCorsEnabled()
        ? $this->t('Enabled')
        : $this->t('Disabled'),
    ];

    $build['status']['rate_limiting'] = [
      '#type' => 'item',
      '#title' => $this->t('Rate Limiting'),
      '#markup' => $this->configManager->isRateLimitingEnabled()
        ? $this->t('Enabled')
        : $this->t('Disabled'),
    ];

    // Consumers section.
    $build['consumers'] = [
      '#type' => 'details',
      '#title' => $this->t('API Consumers'),
      '#open' => TRUE,
    ];

    $consumers = $this->consumerManager->getConsumers();

    if (empty($consumers)) {
      $build['consumers']['empty'] = [
        '#markup' => $this->t('No consumers configured yet.'),
      ];
    }
    else {
      $rows = [];
      foreach ($consumers as $consumer) {
        $rows[] = [
          $consumer->label(),
          $consumer->get('description')->value ?? '',
          $consumer->uuid(),
        ];
      }

      $build['consumers']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Description'),
          $this->t('UUID'),
        ],
        '#rows' => $rows,
      ];
    }

    // Quick links section.
    $build['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Quick Links'),
      '#open' => TRUE,
    ];

    $build['links']['list'] = [
      '#theme' => 'item_list',
      '#items' => [
        [
          '#markup' => $this->t('<a href="@url">Configure Settings</a>', [
            '@url' => Url::fromRoute('headless_integration.settings')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">Manage Consumers</a>', [
            '@url' => Url::fromRoute('entity.consumer.collection')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">JSON:API Resources</a>', [
            '@url' => Url::fromRoute('jsonapi.resource_list')->toString(),
          ]),
        ],
      ],
    ];

    // API documentation hint.
    $build['documentation'] = [
      '#type' => 'details',
      '#title' => $this->t('API Documentation'),
      '#open' => FALSE,
    ];

    $build['documentation']['info'] = [
      '#markup' => $this->t('<p>Your JSON:API endpoint is available at: <code>@base_url/jsonapi</code></p>', [
        '@base_url' => $this->getRequest()->getSchemeAndHttpHost(),
      ]),
    ];

    return $build;
  }

}
