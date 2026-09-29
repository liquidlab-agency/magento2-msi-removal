<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Model;

use InvalidArgumentException;
use Liquidlab\MsiRemoval\Model\ParentStock\ProcessorInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Brings a product's configurable, bundle and grouped parents in line with their children's stock
 *
 * Without MSI, Magento does this only when a product is saved. MSI also did it on every stock item
 * save (InventoryCatalog's UpdateSourceItemAtLegacyStockItemSavePlugin), which this restores.
 */
class ParentStockSynchronizer
{
    /**
     * @var bool
     */
    private bool $isRunning = false;

    /**
     * @param LoggerInterface $logger
     * @param ProcessorInterface[] $processors
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $processors = []
    ) {
        foreach ($processors as $code => $processor) {
            if (!$processor instanceof ProcessorInterface) {
                throw new InvalidArgumentException(
                    sprintf('Parent stock processor "%s" must implement %s', $code, ProcessorInterface::class)
                );
            }
        }
    }

    /**
     * Update the parents of the product from their children's stock
     *
     * @param int $productId
     * @return void
     */
    public function execute(int $productId): void
    {
        // A processor's parent save comes back here through that save's own commit callback.
        // Parents have no parents of their own, so the nested call has nothing to do.
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        try {
            foreach ($this->processors as $code => $processor) {
                try {
                    $processor->execute($productId);
                } catch (Throwable $exception) {
                    $this->logger->error(
                        'Liquidlab_MsiRemoval: could not update the parent stock status.',
                        ['product_id' => $productId, 'parent_type' => $code, 'exception' => $exception]
                    );
                }
            }
        } finally {
            $this->isRunning = false;
        }
    }
}
