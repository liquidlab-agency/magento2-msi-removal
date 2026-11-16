<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Plugin\CatalogInventory;

use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceIndexProcessor;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reindexes a product's price after its stock status changes, also while the price indexer runs on schedule
 *
 * Core queues this reindex in the stock item resource's _afterSave(), but without force, so it does nothing while
 * the price indexer is on "Update by Schedule", and no mview subscription covers stock items. With "Display Out
 * of Stock Products" off, the price index drops out-of-stock products, so a restocked product stays without a
 * price row: listings leave it out, and Elasticsuite's descending price sort puts it first, which empties the
 * first pages. MSI forced this reindex (InventoryCatalog's PriceIndexUpdateProcessor); this restores it.
 */
class ReindexPriceOnStockStatusChange
{
    /**
     * The stock item fields the price index depends on, the same fields core checks in _afterSave()
     */
    private const PRICE_FIELDS = [
        StockItemInterface::IS_IN_STOCK,
        StockItemInterface::USE_CONFIG_MANAGE_STOCK,
        StockItemInterface::MANAGE_STOCK,
    ];

    /**
     * @param PriceIndexProcessor $priceIndexProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PriceIndexProcessor $priceIndexProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Queue the price reindex for when the stock item's transaction commits
     *
     * The stored values are read now, before the save writes them, and again after the commit. Stock items loaded
     * through the stock registry (the REST endpoint, the admin, checkout) carry no original data, so comparing
     * with it would reindex on every save. A status that the save itself switches (for example qty 0) counts.
     * The price indexer reindexes the product's configurable, bundle and grouped parents with it.
     *
     * @param StockItemResource $subject
     * @param AbstractModel $stockItem
     * @return void
     */
    public function beforeSave(StockItemResource $subject, AbstractModel $stockItem): void
    {
        $productId = (int)$stockItem->getData(StockItemInterface::PRODUCT_ID);
        // On "Update on Save", core's own reindex in _afterSave() runs, so there is nothing to force.
        if ($productId === 0 || $stockItem->isDeleted() || !$this->priceIndexProcessor->isIndexerScheduled()) {
            return;
        }

        $valuesBefore = $this->getStoredValues($subject, (int)$stockItem->getId());
        $subject->addCommitCallback(function () use ($subject, $stockItem, $valuesBefore, $productId): void {
            try {
                if ($this->getStoredValues($subject, (int)$stockItem->getId()) !== $valuesBefore) {
                    $this->priceIndexProcessor->reindexRow($productId, true);
                }
            } catch (Throwable $exception) {
                $this->logger->error(
                    'Liquidlab_MsiRemoval: could not reindex the price after a stock status change.',
                    ['product_id' => $productId, 'exception' => $exception]
                );
            }
        });
    }

    /**
     * The stored price-relevant stock values of a stock item, none for one that isn't saved yet
     *
     * @param StockItemResource $resource
     * @param int $itemId
     * @return array<string, int>
     */
    private function getStoredValues(StockItemResource $resource, int $itemId): array
    {
        if ($itemId === 0) {
            return [];
        }

        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from($resource->getMainTable(), self::PRICE_FIELDS)
            ->where($resource->getIdFieldName() . ' = ?', $itemId);

        return array_map('intval', $connection->fetchRow($select) ?: []);
    }
}
