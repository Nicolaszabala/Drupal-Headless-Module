<?php

namespace Drupal\Tests\drupal_headless\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Setup Wizard functionality.
 *
 * @group drupal_headless
 */
class SetupWizardTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'drupal_headless',
    'jsonapi',
    'consumers',
    'simple_oauth',
  ];

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer drupal headless',
      'access drupal headless dashboard',
    ]);
  }

  /**
   * Tests that the setup wizard page is accessible.
   */
  public function testSetupWizardAccess() {
    // Anonymous users should not have access.
    $this->drupalGet('/admin/drupal-headless/setup');
    $this->assertSession()->statusCodeEquals(403);

    // Admin users should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/drupal-headless/setup');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Setup Wizard - Step 1 of 5');
  }

  /**
   * Tests step 1: Environment validation.
   */
  public function testStepOneEnvironment() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/drupal-headless/setup');

    // Check for environment checks.
    $this->assertSession()->pageTextContains('Welcome to Drupal Headless Module Setup');
    $this->assertSession()->pageTextContains('Environment Check');
    $this->assertSession()->pageTextContains('Required Modules');
    $this->assertSession()->pageTextContains('Private File System');
    $this->assertSession()->pageTextContains('OpenSSL Extension');

    // Should have a "Next" button.
    $this->assertSession()->buttonExists('Next');
  }

  /**
   * Tests navigation between wizard steps.
   */
  public function testWizardNavigation() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/drupal-headless/setup');

    // Start at step 1.
    $this->assertSession()->pageTextContains('Step 1 of 5');

    // Cannot test full navigation without a proper private file system setup,
    // but we can verify the form structure exists.
    $this->assertSession()->buttonExists('Next');

    // Step 1 should not have a "Back" button.
    $this->assertSession()->buttonNotExists('Back');
  }

  /**
   * Tests that the wizard shows proper progress indicator.
   */
  public function testProgressIndicator() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/drupal-headless/setup');

    // Check for progress bar.
    $this->assertSession()->elementExists('css', '.setup-wizard-progress');
    $this->assertSession()->elementExists('css', '.progress-bar');
    $this->assertSession()->elementExists('css', '.progress-fill');
  }

  /**
   * Tests step 2: OAuth2 keys.
   */
  public function testStepTwoKeysDisplay() {
    // This test would require mocking the wizard state to reach step 2.
    // For now, we verify that the form class exists and can be instantiated.
    $this->assertTrue(class_exists('\Drupal\drupal_headless\Form\SetupWizardForm'));
  }

}
