<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Test\Unit\Plugin\CatalogInventory;

use Liquidlab\MsiRemoval\Model\ParentStockSynchronizer;
use Liquidlab\MsiRemoval\Plugin\CatalogInventory\SyncParentStockOnStockItemSave;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\CatalogInventory\Model\Stock\Item as StockItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SyncParentStockOnStockItemSaveTest extends TestCase
{
    /**
     * @var ParentStockSynchronizer&MockObject
     */
    private MockObject $synchronizer;

    /**
     * @var StockItemResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var SyncParentStockOnStockItemSave
     */
    private SyncParentStockOnStockItemSave $plugin;

    /**
     * @var callable[]
     */
    private array $commitCallbacks = [];

    protected function setUp(): void
    {
        $this->synchronizer = $this->createMock(ParentStockSynchronizer::class);
        $this->resource = $this->createMock(StockItemResource::class);
        $this->resource->method('addCommitCallback')->willReturnCallback(
            function (callable $callback): StockItemResource {
                $this->commitCallbacks[] = $callback;
                return $this->resource;
            }
        );
        $this->plugin = new SyncParentStockOnStockItemSave($this->synchronizer);
    }

    public function testReChecksTheParentsOnlyOnceTheSaveCommits(): void
    {
        $synced = [];
        $this->synchronizer->method('execute')->willReturnCallback(
            function (int $productId) use (&$synced): void {
                $synced[] = $productId;
            }
        );

        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, false));

        $this->assertSame([], $synced, 'The parents must not be re-checked before the save commits.');
        $this->assertCount(1, $this->commitCallbacks);

        ($this->commitCallbacks[0])();

        $this->assertSame([42], $synced);
    }

    public function testIgnoresAStockItemWithoutAProduct(): void
    {
        $this->plugin->beforeSave($this->resource, $this->createStockItem(null, false));

        $this->assertSame([], $this->commitCallbacks);
    }

    public function testIgnoresADeletedStockItem(): void
    {
        $this->plugin->beforeSave($this->resource, $this->createStockItem(42, true));

        $this->assertSame([], $this->commitCallbacks);
    }

    /**
     * @param int|null $productId
     * @param bool $isDeleted
     * @return StockItem&MockObject
     */
    private function createStockItem(?int $productId, bool $isDeleted): MockObject
    {
        $stockItem = $this->createMock(StockItem::class);
        $stockItem->method('getData')->with('product_id')->willReturn($productId);
        $stockItem->method('isDeleted')->willReturn($isDeleted);

        return $stockItem;
    }
}
