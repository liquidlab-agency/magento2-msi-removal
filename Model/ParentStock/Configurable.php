<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Model\ParentStock;

use Magento\ConfigurableProduct\Model\Inventory\ChangeParentStockStatus;

/**
 * Configurable parents are in stock while at least one child is in stock
 */
class Configurable implements ProcessorInterface
{
    /**
     * @param ChangeParentStockStatus $changeParentStockStatus
     */
    public function __construct(
        private readonly ChangeParentStockStatus $changeParentStockStatus
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(int $childProductId): void
    {
        $this->changeParentStockStatus->execute([$childProductId]);
    }
}
