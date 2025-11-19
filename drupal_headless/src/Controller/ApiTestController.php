<?php

namespace Drupal\drupal_headless\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupal_headless\Service\ConfigurationManager;
use Drupal\drupal_headless\Service\ConsumerManager;
use Drupal\drupal_headless\Service\OAuth2KeyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides API testing tools for Drupal Headless Module.
 */
class ApiTestController extends ControllerBase {

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
   * Displays the API test interface.
   *
   * @return array
   *   A render array.
   */
  public function testPage() {
    $build = [];

    $build['intro'] = [
      '#markup' => '<h2>' . $this->t('API Configuration Tester') . '</h2>' .
        '<p>' . $this->t('Use this tool to verify your API setup is working correctly. Test OAuth2 authentication, CORS configuration, and JSON:API access.') . '</p>',
    ];

    // System status checks.
    $build['status'] = [
      '#type' => 'details',
      '#title' => $this->t('System Status'),
      '#open' => TRUE,
    ];

    $keys_exist = $this->keyManager->keysExist();
    $keys_validation = $this->keyManager->validateKeys();

    $build['status']['keys'] = [
      '#type' => 'item',
      '#title' => $this->t('OAuth2 Keys'),
      '#markup' => $keys_validation['status']
        ? '<span style="color: green;">✓ Configured</span>'
        : '<span style="color: red;">✗ ' . implode(' ', $keys_validation['messages']) . '</span>',
    ];

    $consumers = $this->consumerManager->getConsumers();

    $build['status']['consumers'] = [
      '#type' => 'item',
      '#title' => $this->t('API Consumers'),
      '#markup' => !empty($consumers)
        ? '<span style="color: green;">✓ ' . count($consumers) . ' consumer(s) configured</span>'
        : '<span style="color: orange;">⚠ No consumers configured yet</span>',
    ];

    $cors_enabled = $this->configManager->isCorsEnabled();

    $build['status']['cors'] = [
      '#type' => 'item',
      '#title' => $this->t('CORS'),
      '#markup' => $cors_enabled
        ? '<span style="color: green;">✓ Enabled</span>'
        : '<span style="color: orange;">⚠ Disabled (required for frontend access)</span>',
    ];

    // API endpoints.
    $base_url = \Drupal::request()->getSchemeAndHttpHost();

    $build['endpoints'] = [
      '#type' => 'details',
      '#title' => $this->t('API Endpoints'),
      '#open' => TRUE,
    ];

    $endpoints = [
      [
        'label' => 'OAuth2 Token Endpoint',
        'url' => $base_url . '/oauth/token',
        'method' => 'POST',
        'description' => 'Use this endpoint to obtain access tokens with client credentials.',
      ],
      [
        'label' => 'JSON:API Entry Point',
        'url' => $base_url . '/jsonapi',
        'method' => 'GET',
        'description' => 'Discover all available JSON:API resources.',
      ],
      [
        'label' => 'JSON:API Node Endpoint',
        'url' => $base_url . '/jsonapi/node',
        'method' => 'GET',
        'description' => 'Access node content via JSON:API.',
      ],
    ];

    $rows = [];
    foreach ($endpoints as $endpoint) {
      $rows[] = [
        $endpoint['label'],
        '<code style="font-size: 12px;">' . $endpoint['method'] . '</code>',
        '<code style="font-size: 12px; word-break: break-all;">' . $endpoint['url'] . '</code>',
        $endpoint['description'],
      ];
    }

    $build['endpoints']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Endpoint'),
        $this->t('Method'),
        $this->t('URL'),
        $this->t('Description'),
      ],
      '#rows' => $rows,
    ];

    // Interactive tester.
    $build['tester'] = [
      '#type' => 'details',
      '#title' => $this->t('Interactive API Tester'),
      '#open' => TRUE,
      '#attached' => [
        'library' => ['drupal_headless/api_tester'],
      ],
    ];

    if (empty($consumers)) {
      $build['tester']['no_consumers'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<p>' . $this->t('No API consumers configured. <a href="@url">Create a consumer</a> to test authentication.', [
            '@url' => Url::fromRoute('drupal_headless.create_consumer')->toString(),
          ]) . '</p>' .
          '</div>',
      ];
    }
    else {
      $consumer_options = [];
      foreach ($consumers as $consumer) {
        $consumer_options[$consumer->uuid()] = $consumer->label();
      }

      $build['tester']['form'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'api-tester-form'],
      ];

      $build['tester']['form']['consumer_select'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Consumer'),
        '#options' => $consumer_options,
        '#attributes' => ['id' => 'api-tester-consumer'],
      ];

      $build['tester']['form']['client_secret'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret'),
        '#description' => $this->t('Enter the client secret for this consumer.'),
        '#attributes' => [
          'id' => 'api-tester-secret',
          'placeholder' => 'Enter client secret...',
        ],
      ];

      $build['tester']['form']['test_token'] = [
        '#type' => 'button',
        '#value' => $this->t('Test OAuth2 Token'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
          'onclick' => 'drupalHeadlessApiTester.testToken(); return false;',
        ],
      ];

      $build['tester']['form']['test_jsonapi'] = [
        '#type' => 'button',
        '#value' => $this->t('Test JSON:API Access'),
        '#attributes' => [
          'class' => ['button'],
          'onclick' => 'drupalHeadlessApiTester.testJsonApi(); return false;',
        ],
      ];

      $build['tester']['form']['test_cors'] = [
        '#type' => 'button',
        '#value' => $this->t('Test CORS Headers'),
        '#attributes' => [
          'class' => ['button'],
          'onclick' => 'drupalHeadlessApiTester.testCors(); return false;',
        ],
      ];

      $build['tester']['results'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'api-tester-results'],
        'content' => [
          '#markup' => '<div class="api-tester-results-placeholder">' .
            $this->t('Test results will appear here...') .
            '</div>',
        ],
      ];
    }

    // Example curl commands.
    $build['examples'] = [
      '#type' => 'details',
      '#title' => $this->t('Example cURL Commands'),
      '#open' => FALSE,
    ];

    $build['examples']['token'] = [
      '#type' => 'item',
      '#title' => $this->t('Get OAuth2 Token'),
      '#markup' => '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">curl -X POST ' . $base_url . '/oauth/token \\
  -H "Content-Type: application/x-www-form-urlencoded" \\
  -d "grant_type=client_credentials" \\
  -d "client_id=YOUR_CLIENT_ID" \\
  -d "client_secret=YOUR_CLIENT_SECRET"</pre>',
    ];

    $build['examples']['jsonapi'] = [
      '#type' => 'item',
      '#title' => $this->t('Access JSON:API with Token'),
      '#markup' => '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">curl -X GET ' . $base_url . '/jsonapi \\
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \\
  -H "Accept: application/vnd.api+json"</pre>',
    ];

    return $build;
  }

  /**
   * Test endpoint for API validation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testEndpoint(Request $request) {
    $test_type = $request->query->get('type', 'status');
    $base_url = $request->getSchemeAndHttpHost();

    $results = [
      'success' => TRUE,
      'timestamp' => time(),
      'base_url' => $base_url,
    ];

    switch ($test_type) {
      case 'cors':
        $results['cors'] = [
          'enabled' => $this->configManager->isCorsEnabled(),
          'allowed_origins' => $this->configManager->getAllowedOrigins(),
          'request_origin' => $request->headers->get('Origin', 'none'),
        ];
        break;

      case 'oauth':
        $results['oauth'] = [
          'keys_exist' => $this->keyManager->keysExist(),
          'keys_valid' => $this->keyManager->validateKeys()['status'],
          'token_endpoint' => $base_url . '/oauth/token',
        ];
        break;

      case 'jsonapi':
        $results['jsonapi'] = [
          'endpoint' => $base_url . '/jsonapi',
          'available' => $this->moduleHandler()->moduleExists('jsonapi'),
        ];
        break;

      default:
        $results['status'] = [
          'oauth_configured' => $this->keyManager->keysExist(),
          'cors_enabled' => $this->configManager->isCorsEnabled(),
          'consumers_count' => count($this->consumerManager->getConsumers()),
        ];
        break;
    }

    return new JsonResponse($results);
  }

}
