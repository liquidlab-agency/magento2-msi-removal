# MSI Removal

This module (`Liquidlab_MsiRemoval`) is for stores that run **without Magento's Multi-Source Inventory**. It
removes Multi-Source Inventory, and it keeps a product with sizes or colours in stock for exactly as long as one of
its variants is in stock.

## What it solves

A product with sizes or colours, a bundle and a grouped product each have their own stock status, next to the
stock of their variants. Magento keeps that status in line with the variants. Without Multi-Source Inventory,
though, it only does so when a product is saved in the admin.

Stock that changes any other way doesn't reach the main product: a stock update from your ERP, an order, a refund.
So you restock a few sizes, the sizes have stock, and the product still shows **Out of stock**.

With this module, the main product is checked after every stock change:

- It goes **out of stock** when its last variant sells out.
- It comes **back in stock** as soon as one variant has stock again, whether the stock comes from your ERP, a
  refund or an edit in the admin.
- Editing a sold-out product in the admin (a new colour, a new description) no longer keeps it out of stock
  once its variants are restocked.

## Which products it covers

| Product type | In stock while… |
|---|---|
| Product with sizes or colours (configurable) | at least one variant is in stock |
| Bundle | every bundle option has at least one product in stock |
| Grouped | at least one product in the group is in stock |

## What it means for your team

- **Out of Stock set by hand still works.** A product with variants that you switch to *Out of Stock* yourself
  stays out of stock until you switch it back. To take a product off sale for good, disabling it is clearer.
- **Products that were stuck before the module was installed** may need to be set to *In Stock* once. From then
  on they follow their variants.
