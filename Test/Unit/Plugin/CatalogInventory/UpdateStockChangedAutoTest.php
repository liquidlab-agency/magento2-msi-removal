<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Test\Unit\Plugin\CatalogInventory;

use Liquidlab\MsiRemoval\Plugin\CatalogInventory\UpdateStockChangedAuto;
use Magento\Catalog\Model\ResourceModel\GetProductTypeById;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\CatalogInventory\Model\Stock\Item as StockItem;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateStockChangedAutoTest extends TestCase
{
    /**
     * @var GetProductTypeById&MockObject
     */
    private MockObject $getProductTypeById;

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var StockItemResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var UpdateStockChangedAuto
     */
    private UpdateStockChangedAuto $plugin;

    protected function setUp(): void
    {
        $this->getProductTypeById = $this->createMock(GetProductTypeById::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->resource = $this->createMock(StockItemResource::class);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getMainTable')->willReturn('cataloginventory_stock_item');
        $this->resource->method('getIdFieldName')->willReturn('item_id');
        $this->plugin = new UpdateStockChangedAuto($this->getProductTypeById);
    }

    public function testMarksASwitchFromInStockToOutOfStockAsSetByHand(): void
    {
        $this->getProductTypeById->method('execute')->with(7)->willReturn('configurable');
        $this->connection->method('fetchOne')->willReturn('1');
        $stockItem = $this->createStockItem(false, false, 12);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame(0, $stockItem->getData('stock_status_changed_auto'));
    }

    public function testKeepsTheAutomaticStatusWhenASoldOutConfigurableIsSavedAgain(): void
    {
        $this->getProductTypeById->method('execute')->willReturn('configurable');
        $this->connection->method('fetchOne')->willReturn('0');
        $stockItem = $this->createStockItem(false, false, 12);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame(1, $stockItem->getData('stock_status_changed_auto'));
    }

    public function testMarksANewOutOfStockConfigurableAsSetByHand(): void
    {
        $this->getProductTypeById->method('execute')->willReturn('configurable');
        $this->connection->expects($this->never())->method('fetchOne');
        $stockItem = $this->createStockItem(false, false, null);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame(0, $stockItem->getData('stock_status_changed_auto'));
    }

    public function testLeavesAStatusMagentoSetAutomaticallyAlone(): void
    {
        $this->getProductTypeById->expects($this->never())->method('execute');
        $stockItem = $this->createStockItem(false, true, 12);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame(1, $stockItem->getData('stock_status_changed_auto'));
    }

    public function testIgnoresAnInStockSave(): void
    {
        $this->getProductTypeById->expects($this->never())->method('execute');
        $stockItem = $this->createStockItem(true, false, 12);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame(1, $stockItem->getData('stock_status_changed_auto'));
    }

    public function testIgnoresOtherProductTypes(): void
    {
        $this->getProductTypeById->method('execute')->willReturn('simple');
        $this->connection->expects($this->never())->method('fetchOne');
        $stockItem = $this->createStockItem(false, false, 12);

        $this->plugin->beforeSave($this->resource, $stockItem);

        $this->assertSame(1, $stockItem->getData('stock_status_changed_auto'));
    }

    /**
     * A stock item that Magento had switched automatically before (stock_status_changed_auto = 1)
     *
     * @param bool $isInStock
     * @param bool $setAutomatically
     * @param int|null $itemId
     * @return StockItem&MockObject
     */
    private function createStockItem(bool $isInStock, bool $setAutomatically, ?int $itemId): MockObject
    {
        $stockItem = $this->getMockBuilder(StockItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsInStock', 'getId'])
            ->getMock();
        $stockItem->method('getIsInStock')->willReturn($isInStock);
        $stockItem->method('getId')->willReturn($itemId);
        $stockItem->setData('product_id', 7);
        $stockItem->setData('stock_status_changed_auto', 1);
        if ($setAutomatically) {
            $stockItem->setData('stock_status_changed_automatically_flag', true);
        }

        return $stockItem;
    }
}
