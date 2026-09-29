<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Model\ParentStock;

/**
 * Updates the stock status of one composite product type's parents after a child's stock changed
 *
 * @api
 */
interface ProcessorInterface
{
    /**
     * Set the parents of the child in or out of stock from their children's stock
     *
     * @param int $childProductId
     * @return void
     */
    public function execute(int $childProductId): void;
}
