<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Test\Unit\Model;

use InvalidArgumentException;
use Liquidlab\MsiRemoval\Model\ParentStock\ProcessorInterface;
use Liquidlab\MsiRemoval\Model\ParentStockSynchronizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

class ParentStockSynchronizerTest extends TestCase
{
    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testRunsEveryProcessorForTheProduct(): void
    {
        $configurable = $this->createMock(ProcessorInterface::class);
        $configurable->expects($this->once())->method('execute')->with(42);
        $grouped = $this->createMock(ProcessorInterface::class);
        $grouped->expects($this->once())->method('execute')->with(42);

        $synchronizer = new ParentStockSynchronizer(
            $this->logger,
            ['configurable' => $configurable, 'grouped' => $grouped]
        );
        $synchronizer->execute(42);
    }

    /**
     * @dataProvider failures
     * @param Throwable $exception
     */
    public function testLogsAFailingProcessorAndStillRunsTheOthers(Throwable $exception): void
    {
        $bundle = $this->createMock(ProcessorInterface::class);
        $bundle->method('execute')->willThrowException($exception);
        $grouped = $this->createMock(ProcessorInterface::class);
        $grouped->expects($this->once())->method('execute')->with(42);

        $this->logger->expects($this->once())->method('error')->with(
            $this->stringContains('could not update the parent stock status'),
            ['product_id' => 42, 'parent_type' => 'bundle', 'exception' => $exception]
        );

        $synchronizer = new ParentStockSynchronizer(
            $this->logger,
            ['bundle' => $bundle, 'grouped' => $grouped]
        );
        $synchronizer->execute(42);
    }

    /**
     * @return array<string, Throwable[]>
     */
    public static function failures(): array
    {
        return [
            'database error' => [new RuntimeException('Deadlock found')],
            'php error' => [new TypeError('Argument #1 must be of type int')],
        ];
    }

    public function testSkipsTheNestedCallFromAParentSave(): void
    {
        $synchronizer = null;
        $calls = [];
        $configurable = $this->createMock(ProcessorInterface::class);
        $configurable->method('execute')->willReturnCallback(
            function (int $productId) use (&$synchronizer, &$calls): void {
                $calls[] = $productId;
                // Saving the parent's stock item queues the parent itself; that call must be a no-op.
                $synchronizer->execute(7);
            }
        );

        $synchronizer = new ParentStockSynchronizer($this->logger, ['configurable' => $configurable]);
        $synchronizer->execute(42);
        $synchronizer->execute(43);

        $this->assertSame([42, 43], $calls);
    }

    public function testRejectsAProcessorOfTheWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParentStockSynchronizer($this->logger, ['configurable' => new stdClass()]);
    }
}
