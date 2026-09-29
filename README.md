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
- Replaces core's `updateStockChangedAuto` plugin. Core marks every out-of-stock save of a configurable as set by
  hand, so a sold-out configurable that is re-saved in the admin (a new colour, a new description) never comes back
  in stock. This module marks it as set by hand only when someone switches it from In Stock to Out of Stock. MSI
  disabled the core plugin instead, which also undid statuses set by hand.
- The re-check runs after the stock item's transaction commits. A rolled-back save doesn't trigger it, and a
  failure is logged, never thrown into a checkout or an API call.

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
