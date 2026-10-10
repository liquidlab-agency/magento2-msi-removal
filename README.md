# MSI Removal

**Removes Magento's Multi-Source Inventory (MSI) and keeps configurable, bundle and grouped products in stock
while their children are** (`Liquidlab_MsiRemoval`).

Require this package, not `yireo/magento2-replace-inventory` directly, whenever a project removes MSI. It pulls
in the replace package and brings back the parent stock behaviour that MSI provided. Without it, a configurable
can stay out of stock after its sizes are restocked.

## Features
- Removes every MSI package through `yireo/magento2-replace-inventory` (4.x, Magento Open Source 2.4).
- Re-checks the configurable, bundle and grouped parents after **every** stock item save, not only after a
  product save. That includes `PUT /V1/products/{sku}/stockItems/{itemId}` (ERP stock syncs) and order and
  credit memo stock changes.
  - MSI did this in `UpdateSourceItemAtLegacyStockItemSavePlugin`.
  - Without MSI, core runs `ChangeParentStockStatus` only from `SaveInventoryDataObserver`, the mass attribute
    update and the importer.
- Reindexes a product's price right after its stock status changes, also when the price indexer runs on
  *Update by Schedule*. The price indexer takes the product's configurable, bundle and grouped parents with it.
  - Core asks for this reindex without forcing it, which does nothing while the indexer is scheduled, and no
    mview trigger covers stock items. MSI forced it in `PriceIndexUpdateProcessor`.
  - With *Display Out of Stock Products* off, Magento drops out-of-stock products from the price index. Without
    the forced reindex, a restocked product comes back in stock with no price. Category pages then show fewer
    products than they count, and Elasticsuite's *price high to low* sort puts these products first, which can
    empty the first pages.
  - Only a change of *Stock Status* or *Manage Stock* triggers it, read from the saved stock item before and after
    the save. Qty updates that keep the status, which most ERP syncs are, reindex nothing.
- Replaces core's `updateStockChangedAuto` plugin. Core marks every out-of-stock save of a configurable as set by
  hand, so a sold-out configurable that is re-saved in the admin (a new colour, a new description) never comes back
  in stock. This module marks it as set by hand only when someone switches it from In Stock to Out of Stock. MSI
  disabled the core plugin instead, which also undid statuses set by hand.
- The re-check and the price reindex run after the stock item's transaction commits. A rolled-back save doesn't
  trigger them, and a failure is logged, never thrown into a checkout or an API call.

## Installation
```
composer require liquidlab-agency/magento2-msi-removal
php bin/magento setup:upgrade
```

If the project already requires `yireo/magento2-replace-inventory`, drop that line; this package requires it.
Requires Magento Open Source 2.4.5 or later.

## Behaviour to know
- A parent goes out of stock when its last child does and comes back when a child does. A configurable switched to
  *Out of Stock* by hand stays out of stock until someone switches it back.
- Parents already stuck before installing (out of stock while a child is in stock) are fixed by the next stock
  update of one of their children if Magento had switched them off. Parents switched off by hand, including the
  ones re-saved in the admin before installing, need setting to *In Stock* once.
- Products that lost their price row before you upgraded stay without one until a full price reindex. Run
  `php bin/magento indexer:reindex catalog_product_price catalogsearch_fulltext` once after upgrading.

## License
MIT, see [LICENSE.txt](LICENSE.txt).
