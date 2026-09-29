<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Model\ParentStock;

use Magento\Bundle\Model\Inventory\ChangeParentStockStatus;

/**
 * Bundle parents are in stock while every option has a child in stock
 */
class Bundle implements ProcessorInterface
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
