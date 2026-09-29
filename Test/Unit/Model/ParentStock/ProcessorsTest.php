<?php
/**
 * Copyright © - Liquidlab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Liquidlab\MsiRemoval\Test\Unit\Model\ParentStock;

use Liquidlab\MsiRemoval\Model\ParentStock\Bundle;
use Liquidlab\MsiRemoval\Model\ParentStock\Configurable;
use Liquidlab\MsiRemoval\Model\ParentStock\Grouped;
use Magento\Bundle\Model\Inventory\ChangeParentStockStatus as BundleChangeParentStockStatus;
use Magento\ConfigurableProduct\Model\Inventory\ChangeParentStockStatus as ConfigurableChangeParentStockStatus;
use Magento\GroupedProduct\Model\Inventory\ChangeParentStockStatus as GroupedChangeParentStockStatus;
use PHPUnit\Framework\TestCase;

class ProcessorsTest extends TestCase
{
    public function testConfigurablePassesTheChildAsAList(): void
    {
        $core = $this->createMock(ConfigurableChangeParentStockStatus::class);
        $core->expects($this->once())->method('execute')->with([42]);

        (new Configurable($core))->execute(42);
    }

    public function testBundlePassesTheChildAsAList(): void
    {
        $core = $this->createMock(BundleChangeParentStockStatus::class);
        $core->expects($this->once())->method('execute')->with([42]);

        (new Bundle($core))->execute(42);
    }

    public function testGroupedPassesTheChildId(): void
    {
        $core = $this->createMock(GroupedChangeParentStockStatus::class);
        $core->expects($this->once())->method('execute')->with(42);

        (new Grouped($core))->execute(42);
    }
}
