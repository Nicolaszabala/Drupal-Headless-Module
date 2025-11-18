<?php

namespace Drupal\Tests\drupal_headless\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\drupal_headless\Service\ConsumerManager;

/**
 * Tests for ConsumerManager service.
 *
 * @group drupal_headless
 * @coversDefaultClass \Drupal\drupal_headless\Service\ConsumerManager
 */
class ConsumerManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'consumers',
    'serialization',
    'image',
    'drupal_headless',
  ];

  /**
   * The consumer manager under test.
   *
   * @var \Drupal\drupal_headless\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('consumer');
    $this->installConfig(['drupal_headless']);

    $this->consumerManager = $this->container->get('drupal_headless.consumer_manager');
  }

  /**
   * Tests creating a consumer.
   *
   * @covers ::createConsumer
   */
  public function testCreateConsumer() {
    $consumer = $this->consumerManager->createConsumer(
      'Test App',
      'A test application'
    );

    $this->assertNotNull($consumer);
    $this->assertEquals('Test App', $consumer->label());
    $this->assertEquals('A test application', $consumer->get('description')->value);
  }

  /**
   * Tests creating a consumer with options.
   *
   * @covers ::createConsumer
   */
  public function testCreateConsumerWithOptions() {
    // Create a test user first.
    $user = $this->createUser();

    $consumer = $this->consumerManager->createConsumer(
      'Advanced App',
      'An advanced test application',
      ['user_id' => $user->id()]
    );

    $this->assertNotNull($consumer);
    $this->assertEquals($user->id(), $consumer->get('user_id')->target_id);
  }

  /**
   * Tests getting all consumers.
   *
   * @covers ::getConsumers
   */
  public function testGetConsumers() {
    // Initially should be empty.
    $consumers = $this->consumerManager->getConsumers();
    $this->assertEmpty($consumers);

    // Create some consumers.
    $this->consumerManager->createConsumer('App 1', 'First app');
    $this->consumerManager->createConsumer('App 2', 'Second app');

    $consumers = $this->consumerManager->getConsumers();
    $this->assertCount(2, $consumers);
  }

  /**
   * Tests deleting a consumer.
   *
   * @covers ::deleteConsumer
   */
  public function testDeleteConsumer() {
    $consumer = $this->consumerManager->createConsumer('Delete Me', 'To be deleted');
    $uuid = $consumer->uuid();

    $result = $this->consumerManager->deleteConsumer($uuid);
    $this->assertTrue($result);

    // Verify it's really gone.
    $consumers = $this->consumerManager->getConsumers();
    $this->assertEmpty($consumers);
  }

  /**
   * Tests deleting a non-existent consumer.
   *
   * @covers ::deleteConsumer
   */
  public function testDeleteNonExistentConsumer() {
    $result = $this->consumerManager->deleteConsumer('fake-uuid-12345');
    $this->assertFalse($result);
  }

  /**
   * Creates a user for testing.
   *
   * @return \Drupal\user\Entity\User
   *   The created user.
   */
  protected function createUser() {
    $user = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->create([
        'name' => $this->randomMachineName(),
        'status' => 1,
      ]);
    $user->save();
    return $user;
  }

}
