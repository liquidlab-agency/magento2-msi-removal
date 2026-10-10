<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Test\Unit\Plugin\CatalogInventory;

use Liquidlab\MsiRemoval\Plugin\CatalogInventory\ReindexPriceOnStockStatusChange;
use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceIndexProcessor;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\CatalogInventory\Model\Stock\Item as StockItem;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ReindexPriceOnStockStatusChangeTest extends TestCase
{
    /**
     * @var PriceIndexProcessor&MockObject
     */
    private MockObject $priceIndexProcessor;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var StockItemResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var ReindexPriceOnStockStatusChange
     */
    private ReindexPriceOnStockStatusChange $plugin;

    /**
     * @var callable[]
     */
    private array $commitCallbacks = [];

    /**
     * @var array<int, array{0: int, 1: bool}>
     */
    private array $reindexed = [];

    /**
     * @var array<int, array{0: string, 1: string[]}>
     */
    private array $selectedFrom = [];

    /**
     * @var array<int, array{0: string, 1: int}>
     */
    private array $selectedWhere = [];

    protected function setUp(): void
    {
        $this->priceIndexProcessor = $this->createMock(PriceIndexProcessor::class);
        $this->priceIndexProcessor->method('reindexRow')->willReturnCallback(
            function (int $productId, bool $forceReindex = false): void {
                $this->reindexed[] = [$productId, $forceReindex];
            }
        );
        $this->logger = $this->createMock(LoggerInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(function (string $table, array $columns) use ($select): Select {
            $this->selectedFrom[] = [$table, $columns];
            return $select;
        });
        $select->method('where')->willReturnCallback(function (string $condition, int $value) use ($select): Select {
            $this->selectedWhere[] = [$condition, $value];
            return $select;
        });
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->resource = $this->createMock(StockItemResource::class);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getMainTable')->willReturn('cataloginventory_stock_item');
        $this->resource->method('getIdFieldName')->willReturn('item_id');
        $this->resource->method('addCommitCallback')->willReturnCallback(
            function (callable $callback): StockItemResource {
                $this->commitCallbacks[] = $callback;
                return $this->resource;
            }
        );
        $this->plugin = new ReindexPriceOnStockStatusChange($this->priceIndexProcessor, $this->logger);
    }

    public function testForcesThePriceReindexOnceTheRestockCommits(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        $this->connection->method('fetchRow')->willReturnOnConsecutiveCalls($this->row(0), $this->row(1));

        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, 12));

        $this->assertSame([], $this->reindexed, 'The price must not be reindexed before the save commits.');
        $this->assertCount(1, $this->commitCallbacks);

        ($this->commitCallbacks[0])();

        $this->assertSame([[42, true]], $this->reindexed);
        $columns = ['is_in_stock', 'use_config_manage_stock', 'manage_stock'];
        $this->assertSame(
            [['cataloginventory_stock_item', $columns], ['cataloginventory_stock_item', $columns]],
            $this->selectedFrom
        );
        $this->assertSame([['item_id = ?', 12], ['item_id = ?', 12]], $this->selectedWhere);
    }

    public function testForcesThePriceReindexWhenTheProductSellsOut(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        // Also covers a status the save itself switches, as the repository does when qty reaches 0.
        $this->connection->method('fetchRow')->willReturnOnConsecutiveCalls($this->row(1), $this->row(0));

        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, 12));
        ($this->commitCallbacks[0])();

        $this->assertSame([[42, true]], $this->reindexed);
    }

    public function testForcesThePriceReindexWhenManageStockChanges(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        $this->connection->method('fetchRow')->willReturnOnConsecutiveCalls($this->row(1, 1, 1), $this->row(1, 0, 0));

        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, 12));
        ($this->commitCallbacks[0])();

        $this->assertSame([[42, true]], $this->reindexed);
    }

    public function testForcesThePriceReindexForANewStockItem(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        $this->connection->expects($this->once())->method('fetchRow')->willReturn($this->row(1));
        $stockItem = $this->createStockItem(42, null);

        $this->plugin->beforeSave($this->resource, $stockItem);
        // The insert gives the stock item its id.
        $stockItem->setData('item_id', 12);
        ($this->commitCallbacks[0])();

        $this->assertSame([[42, true]], $this->reindexed);
    }

    public function testSkipsAQtyChangeThatKeepsTheStatus(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        $this->connection->method('fetchRow')->willReturnOnConsecutiveCalls($this->row(1), $this->row(1));
        // Like the stock items the REST endpoint loads, this one has its stored values but no original data.
        $stockItem = $this->createStockItem(42, 12, $this->row(1) + ['qty' => '7.0000']);

        $this->plugin->beforeSave($this->resource, $stockItem);
        ($this->commitCallbacks[0])();

        $this->assertNull($stockItem->getOrigData('is_in_stock'));
        $this->assertSame([], $this->reindexed);
    }

    public function testLeavesTheReindexToCoreWhenThePriceIndexerUpdatesOnSave(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(false);
        $this->connection->expects($this->never())->method('fetchRow');

        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, 12));

        $this->assertSame([], $this->commitCallbacks);
    }

    public function testLogsAFailedReindexInsteadOfThrowing(): void
    {
        $priceIndexProcessor = $this->createMock(PriceIndexProcessor::class);
        $priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        $priceIndexProcessor->method('reindexRow')->willThrowException(new RuntimeException('Deadlock'));
        $this->connection->method('fetchRow')->willReturnOnConsecutiveCalls($this->row(0), $this->row(1));
        $this->expectLoggedError();
        $plugin = new ReindexPriceOnStockStatusChange($priceIndexProcessor, $this->logger);

        $plugin->beforeSave($this->resource, $this->createStockItem(42, 12));
        ($this->commitCallbacks[0])();
    }

    public function testLogsAFailedReadAfterTheCommitInsteadOfThrowing(): void
    {
        $this->priceIndexProcessor->method('isIndexerScheduled')->willReturn(true);
        $this->connection->method('fetchRow')->willReturnOnConsecutiveCalls(
            $this->row(0),
            $this->throwException(new RuntimeException('MySQL server has gone away'))
        );
        $this->expectLoggedError();

        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, 12));
        ($this->commitCallbacks[0])();

        $this->assertSame([], $this->reindexed);
    }

    public function testIgnoresAStockItemWithoutAProduct(): void
    {
        $this->plugin->beforeSave($this->resource, $this->createStockItem(null, 12));

        $this->assertSame([], $this->commitCallbacks);
    }

    public function testIgnoresADeletedStockItem(): void
    {
        $stockItem = $this->createStockItem(42, 12);
        $stockItem->isDeleted(true);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame([], $this->commitCallbacks);
    }

    /**
     * A stock item as the stock registry loads it: data only, no original data
     *
     * @param int|null $productId
     * @param int|null $itemId
     * @param array<string, string> $data
     * @return StockItem
     */
    private function createStockItem(?int $productId, ?int $itemId, array $data = []): StockItem
    {
        $stockItem = $this->getMockBuilder(StockItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $stockItem->setIdFieldName('item_id');
        $stockItem->addData($data);
        $stockItem->setData('item_id', $itemId);
        $stockItem->setData('product_id', $productId);

        return $stockItem;
    }

    /**
     * A stored stock item row, with the strings MySQL returns
     *
     * @param int $isInStock
     * @param int $useConfigManageStock
     * @param int $manageStock
     * @return array<string, string>
     */
    private function row(int $isInStock, int $useConfigManageStock = 1, int $manageStock = 0): array
    {
        return [
            'is_in_stock' => (string)$isInStock,
            'use_config_manage_stock' => (string)$useConfigManageStock,
            'manage_stock' => (string)$manageStock,
        ];
    }

    /**
     * @return void
     */
    private function expectLoggedError(): void
    {
        $this->logger->expects($this->once())->method('error')->with(
            'Liquidlab_MsiRemoval: could not reindex the price after a stock status change.',
            $this->callback(static fn (array $context): bool => $context['product_id'] === 42)
        );
    }
}
