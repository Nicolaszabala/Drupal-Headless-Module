<?php

namespace Drupal\drupal_headless\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupal_headless\Service\ConfigurationManager;
use Drupal\drupal_headless\Service\ConsumerManager;
use Drupal\drupal_headless\Service\OAuth2KeyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dashboard for Drupal Headless Module.
 */
class DashboardController extends ControllerBase {

  /**
   * The configuration manager service.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager
   */
  protected $configManager;

  /**
   * The consumer manager service.
   *
   * @var \Drupal\drupal_headless\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * The OAuth2 key manager service.
   *
   * @var \Drupal\drupal_headless\Service\OAuth2KeyManager
   */
  protected $keyManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->configManager = $container->get('drupal_headless.configuration_manager');
    $instance->consumerManager = $container->get('drupal_headless.consumer_manager');
    $instance->keyManager = $container->get('drupal_headless.oauth2_key_manager');
    return $instance;
  }

  /**
   * Displays the Drupal Headless Module dashboard.
   *
   * @return array
   *   A render array.
   */
  public function overview() {
    $build = [];

    // Show setup wizard prompt if not fully configured.
    $keys_exist = $this->keyManager->keysExist();
    $consumers = $this->consumerManager->getConsumers();

    // Get completion percentage from health check manager.
    $health_check_manager = \Drupal::service('drupal_headless.health_check_manager');
    $completion = $health_check_manager->getCompletionPercentage();

    if ($completion < 100) {
      $build['setup_prompt'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--info" style="margin-bottom: 20px;">' .
          '<h3>' . $this->t('Setup Progress: @percent%', ['@percent' => $completion]) . '</h3>' .
          '<p>' . $this->t('Complete your headless Drupal configuration. Review the checklist to see what needs to be done.') . '</p>' .
          '<p>' .
          '<a href="' . Url::fromRoute('drupal_headless.checklist')->toString() . '" class="button button--primary button--large">' .
          $this->t('View Setup Checklist') .
          '</a> ' .
          '<a href="' . Url::fromRoute('drupal_headless.setup_wizard')->toString() . '" class="button button--large">' .
          $this->t('Run Setup Wizard') .
          '</a>' .
          '</p>' .
          '</div>',
      ];
    }
    else {
      $build['setup_complete'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--status" style="margin-bottom: 20px;">' .
          '<h3>🎉 ' . $this->t('Setup Complete!') . '</h3>' .
          '<p>' . $this->t('Your headless Drupal installation is fully configured and ready to use.') . '</p>' .
          '</div>',
      ];
    }

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

    // OAuth2 Keys status.
    $keys_validation = $this->keyManager->validateKeys();
    $keys_exist = $this->keyManager->keysExist();

    $build['status']['oauth_keys'] = [
      '#type' => 'item',
      '#title' => $this->t('OAuth2 Keys'),
      '#markup' => $keys_validation['status']
        ? $this->t('<span style="color: green;">✓ Configured</span>')
        : $this->t('<span style="color: red;">✗ Not configured</span>'),
    ];

    if (!$keys_exist) {
      $build['status']['oauth_keys_action'] = [
        '#type' => 'markup',
        '#markup' => '<p><a href="' . Url::fromRoute('drupal_headless.generate_keys')->toString() . '" class="button button--primary">' . $this->t('Generate OAuth2 Keys Now') . '</a></p>',
      ];
    }
    else {
      $paths = $this->keyManager->getKeyPaths();
      $build['status']['oauth_keys_info'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Keys location: @dir', ['@dir' => $paths['dir']]) . '</p>',
      ];
    }

    // Consumers section.
    $build['consumers'] = [
      '#type' => 'details',
      '#title' => $this->t('API Consumers'),
      '#open' => TRUE,
    ];

    $build['consumers']['create_button'] = [
      '#type' => 'markup',
      '#markup' => '<p><a href="' . Url::fromRoute('drupal_headless.create_consumer')->toString() . '" class="button button--primary">' . $this->t('Create New Consumer') . '</a></p>',
    ];

    $consumers = $this->consumerManager->getConsumers();

    if (empty($consumers)) {
      $build['consumers']['empty'] = [
        '#markup' => $this->t('<p>No consumers configured yet. Create your first consumer to get started.</p>'),
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
          '#markup' => $this->t('<a href="@url">Setup Checklist</a>', [
            '@url' => Url::fromRoute('drupal_headless.checklist')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">Configure Settings</a>', [
            '@url' => Url::fromRoute('drupal_headless.settings')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">Manage Consumers</a>', [
            '@url' => Url::fromRoute('entity.consumer.collection')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">API Tester</a>', [
            '@url' => Url::fromRoute('drupal_headless.api_test')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">Webhooks</a>', [
            '@url' => Url::fromRoute('drupal_headless.webhooks')->toString(),
          ]),
        ],
        [
          '#markup' => $this->t('<a href="@url">Preview Configuration</a>', [
            '@url' => Url::fromRoute('drupal_headless.preview_config')->toString(),
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
