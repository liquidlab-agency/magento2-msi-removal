<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Plugin\CatalogInventory;

use Magento\Catalog\Model\ResourceModel\GetProductTypeById;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Model\AbstractModel;

/**
 * Marks a configurable's out-of-stock status as set by hand only when someone switches it off
 *
 * Replaces core's updateStockChangedAuto plugin, which marks every out-of-stock save of a configurable as
 * set by hand. Re-saving a sold-out configurable in the admin then keeps it out of stock for good, even
 * after its children are restocked. MSI disabled that plugin outright, so that a status set by hand no
 * longer held; this keeps both working.
 */
class UpdateStockChangedAuto
{
    /**
     * @param GetProductTypeById $getProductTypeById
     */
    public function __construct(
        private readonly GetProductTypeById $getProductTypeById
    ) {
    }

    /**
     * Flag the status as set by hand when a configurable goes from in stock to out of stock
     *
     * @param StockItemResource $subject
     * @param AbstractModel $stockItem
     * @return void
     */
    public function beforeSave(StockItemResource $subject, AbstractModel $stockItem): void
    {
        if ($stockItem->getIsInStock()
            || $stockItem->hasStockStatusChangedAutomaticallyFlag()
            || $this->getProductTypeById->execute((int)$stockItem->getData(StockItemInterface::PRODUCT_ID))
                !== Configurable::TYPE_CODE
        ) {
            return;
        }

        if ($this->wasInStock($subject, $stockItem)) {
            $stockItem->setData(StockItemInterface::STOCK_STATUS_CHANGED_AUTO, 0);
        }
    }

    /**
     * Whether the saved stock item was in stock before this save; a new one counts as a switch
     *
     * @param StockItemResource $subject
     * @param AbstractModel $stockItem
     * @return bool
     */
    private function wasInStock(StockItemResource $subject, AbstractModel $stockItem): bool
    {
        $itemId = (int)$stockItem->getId();
        if ($itemId === 0) {
            return true;
        }

        $connection = $subject->getConnection();
        $select = $connection->select()
            ->from($subject->getMainTable(), [StockItemInterface::IS_IN_STOCK])
            ->where($subject->getIdFieldName() . ' = ?', $itemId);

        return (bool)$connection->fetchOne($select);
    }
}
