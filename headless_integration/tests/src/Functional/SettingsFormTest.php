<?php

namespace Drupal\Tests\headless_integration\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the headless integration settings form.
 *
 * @group headless_integration
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'headless_integration',
    'consumers',
    'simple_oauth',
    'jsonapi',
  ];

  /**
   * A user with permission to administer headless integration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer headless integration',
    ]);
  }

  /**
   * Tests access to the settings form.
   */
  public function testSettingsFormAccess() {
    // Anonymous users should not have access.
    $this->drupalGet('/admin/config/services/headless-integration');
    $this->assertSession()->statusCodeEquals(403);

    // Admin user should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/headless-integration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('CORS Configuration');
  }

  /**
   * Tests submitting the settings form.
   */
  public function testSettingsFormSubmit() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/headless-integration');

    // Fill in the form.
    $edit = [
      'enable_cors' => TRUE,
      'cors_allowed_origins' => "https://example.com\nhttps://test.com",
      'enable_rate_limiting' => TRUE,
      'rate_limit_requests' => 150,
      'rate_limit_window' => 1800,
      'auto_configure_oauth' => TRUE,
      'default_token_expiration' => 7200,
    ];

    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the values were saved.
    $config = $this->config('headless_integration.settings');
    $this->assertTrue($config->get('enable_cors'));
    $this->assertEquals(['https://example.com', 'https://test.com'], $config->get('cors_allowed_origins'));
    $this->assertTrue($config->get('enable_rate_limiting'));
    $this->assertEquals(150, $config->get('rate_limit_requests'));
    $this->assertEquals(1800, $config->get('rate_limit_window'));
  }

  /**
   * Tests form validation with invalid CORS origin.
   */
  public function testSettingsFormValidation() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/headless-integration');

    // Submit with invalid URL.
    $edit = [
      'enable_cors' => TRUE,
      'cors_allowed_origins' => "not-a-valid-url\nhttps://example.com",
    ];

    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('Invalid URL: not-a-valid-url');
  }

  /**
   * Tests dashboard access and display.
   */
  public function testDashboardAccess() {
    // Create user with dashboard access.
    $dashboard_user = $this->drupalCreateUser([
      'access headless dashboard',
    ]);

    $this->drupalLogin($dashboard_user);
    $this->drupalGet('/admin/headless-integration/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('System Status');
    $this->assertSession()->pageTextContains('API Consumers');
  }

}
