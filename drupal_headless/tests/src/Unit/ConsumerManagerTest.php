<?php

namespace Drupal\Tests\drupal_headless\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\drupal_headless\Service\ConfigurationManager;
use Drupal\drupal_headless\Service\ConsumerManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ConsumerManager service.
 *
 * @group drupal_headless
 * @coversDefaultClass \Drupal\drupal_headless\Service\ConsumerManager
 */
class ConsumerManagerTest extends UnitTestCase {

  /**
   * The ConsumerManager under test.
   *
   * @var \Drupal\drupal_headless\Service\ConsumerManager
   */
  protected $consumerManager;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock configuration manager.
   *
   * @var \Drupal\drupal_headless\Service\ConfigurationManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configManager;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * Mock entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->configManager = $this->createMock(ConfigurationManager::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->entityStorage = $this->createMock(EntityStorageInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->entityTypeManager->method('getStorage')
      ->with('consumer')
      ->willReturn($this->entityStorage);

    $this->consumerManager = new ConsumerManager(
      $this->entityTypeManager,
      $this->configManager,
      $this->loggerFactory
    );
  }

  /**
   * Tests createConsumer creates a consumer with basic data.
   *
   * @covers ::createConsumer
   */
  public function testCreateConsumerWithBasicData() {
    $label = 'Test Consumer';
    $description = 'Test Description';

    $consumer = $this->getMockBuilder('\Drupal\consumers\Entity\Consumer')
      ->disableOriginalConstructor()
      ->getMock();

    $consumer->expects($this->once())
      ->method('save');

    $this->entityStorage->expects($this->once())
      ->method('create')
      ->with($this->callback(function ($data) use ($label, $description) {
        return $data['label'] === $label
          && $data['description'] === $description
          && $data['is_default'] === FALSE;
      }))
      ->willReturn($consumer);

    $result = $this->consumerManager->createConsumer($label, $description);

    $this->assertSame($consumer, $result);
  }

  /**
   * Tests createConsumer with additional options.
   *
   * @covers ::createConsumer
   */
  public function testCreateConsumerWithOptions() {
    $label = 'Test Consumer';
    $description = 'Test Description';
    $options = [
      'user_id' => 1,
      'roles' => ['authenticated'],
    ];

    $consumer = $this->getMockBuilder('\Drupal\consumers\Entity\Consumer')
      ->disableOriginalConstructor()
      ->getMock();

    $consumer->expects($this->once())
      ->method('save');

    $this->entityStorage->expects($this->once())
      ->method('create')
      ->with($this->callback(function ($data) use ($label, $description, $options) {
        return $data['label'] === $label
          && $data['description'] === $description
          && $data['user_id'] === $options['user_id']
          && $data['roles'] === $options['roles'];
      }))
      ->willReturn($consumer);

    $result = $this->consumerManager->createConsumer($label, $description, $options);

    $this->assertSame($consumer, $result);
  }

  /**
   * Tests createConsumer returns NULL on exception.
   *
   * @covers ::createConsumer
   */
  public function testCreateConsumerHandlesException() {
    $this->entityStorage->expects($this->once())
      ->method('create')
      ->willThrowException(new \Exception('Test exception'));

    $result = $this->consumerManager->createConsumer('Test', 'Description');

    $this->assertNull($result);
  }

  /**
   * Tests getConsumers returns all consumers.
   *
   * @covers ::getConsumers
   */
  public function testGetConsumersReturnsAllConsumers() {
    $consumer1 = $this->getMockBuilder('\Drupal\consumers\Entity\Consumer')
      ->disableOriginalConstructor()
      ->getMock();

    $consumer2 = $this->getMockBuilder('\Drupal\consumers\Entity\Consumer')
      ->disableOriginalConstructor()
      ->getMock();

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('accessCheck')
      ->with(TRUE)
      ->willReturnSelf();

    $query->expects($this->once())
      ->method('sort')
      ->with('label', 'ASC')
      ->willReturnSelf();

    $query->expects($this->once())
      ->method('execute')
      ->willReturn([1, 2]);

    $this->entityStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);

    $this->entityStorage->expects($this->once())
      ->method('loadMultiple')
      ->with([1, 2])
      ->willReturn([$consumer1, $consumer2]);

    $result = $this->consumerManager->getConsumers();

    $this->assertCount(2, $result);
    $this->assertSame($consumer1, $result[0]);
    $this->assertSame($consumer2, $result[1]);
  }

  /**
   * Tests getConsumers returns empty array when no consumers exist.
   *
   * @covers ::getConsumers
   */
  public function testGetConsumersReturnsEmptyArrayWhenNone() {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $this->entityStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);

    $result = $this->consumerManager->getConsumers();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getConsumers handles exceptions.
   *
   * @covers ::getConsumers
   */
  public function testGetConsumersHandlesException() {
    $this->entityStorage->expects($this->once())
      ->method('getQuery')
      ->willThrowException(new \Exception('Test exception'));

    $result = $this->consumerManager->getConsumers();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests deleteConsumer deletes a consumer by UUID.
   *
   * @covers ::deleteConsumer
   */
  public function testDeleteConsumerByUuid() {
    $uuid = 'test-uuid-123';

    $consumer = $this->getMockBuilder('\Drupal\consumers\Entity\Consumer')
      ->disableOriginalConstructor()
      ->getMock();

    $consumer->expects($this->once())
      ->method('delete');

    $this->entityStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(['uuid' => $uuid])
      ->willReturn([$consumer]);

    $this->configManager->expects($this->once())
      ->method('removeFramework')
      ->with($uuid);

    $result = $this->consumerManager->deleteConsumer($uuid);

    $this->assertTrue($result);
  }

  /**
   * Tests deleteConsumer returns FALSE when consumer not found.
   *
   * @covers ::deleteConsumer
   */
  public function testDeleteConsumerReturnsFalseWhenNotFound() {
    $uuid = 'non-existent-uuid';

    $this->entityStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(['uuid' => $uuid])
      ->willReturn([]);

    $result = $this->consumerManager->deleteConsumer($uuid);

    $this->assertFalse($result);
  }

  /**
   * Tests deleteConsumer handles exceptions.
   *
   * @covers ::deleteConsumer
   */
  public function testDeleteConsumerHandlesException() {
    $this->entityStorage->expects($this->once())
      ->method('loadByProperties')
      ->willThrowException(new \Exception('Test exception'));

    $result = $this->consumerManager->deleteConsumer('test-uuid');

    $this->assertFalse($result);
  }

}
