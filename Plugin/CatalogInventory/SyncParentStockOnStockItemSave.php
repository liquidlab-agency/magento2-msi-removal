<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Plugin\CatalogInventory;

use Liquidlab\MsiRemoval\Model\ParentStockSynchronizer;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Re-checks a product's parents after its stock item is saved, however the save happens
 *
 * This covers the saves that skip a product save: the REST stock item endpoint
 * (PUT /V1/products/{sku}/stockItems/{itemId}) that ERP stock syncs use, order and credit memo
 * stock changes, and direct stock item repository calls.
 */
class SyncParentStockOnStockItemSave
{
    /**
     * @param ParentStockSynchronizer $parentStockSynchronizer
     */
    public function __construct(
        private readonly ParentStockSynchronizer $parentStockSynchronizer
    ) {
    }

    /**
     * Queue the parent re-check for when the stock item's transaction commits
     *
     * A rolled back save drops the callback, and a failing re-check can't undo the save.
     *
     * @param StockItemResource $subject
     * @param AbstractModel $stockItem
     * @return void
     */
    public function beforeSave(StockItemResource $subject, AbstractModel $stockItem): void
    {
        $productId = (int)$stockItem->getData(StockItemInterface::PRODUCT_ID);
        if ($productId === 0 || $stockItem->isDeleted()) {
            return;
        }

        $subject->addCommitCallback(function () use ($productId): void {
            $this->parentStockSynchronizer->execute($productId);
        });
    }
}
