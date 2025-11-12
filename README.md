# ProductCap

**PrestaShop 1.7.x / PHP 7.2+** micro-module to allow *limited* backorders (preorders) per product, and to auto-restore the original behavior when stock arrives or the preorder cap is reached.  
Designed for single-store, products **without combinations**.

## What it does

- Per product:
  - Enable a preorder “cap” (number of incoming units you are willing to accept as backorders).
  - While the cap is active, the module sets `out_of_stock = 1` so backorders are allowed.
  - When positive stock arrives **or** the cap is consumed, it automatically disables the cap and restores the original `out_of_stock` value.
- In cart:
  - Prevents adding more than `available_positive_stock + remaining_cap`.

## Requirements

- PrestaShop 1.7.6 (tested) – should work for 1.7.x
- PHP 7.2.x
- No multistore, no product combinations, ASM **on** is fine.

## Install

1. Copy the folder `rmpreordercap` into `modules/`.
2. Ensure the module tree is minimal:
rmpreordercap/
├─ rmpreordercap.php
├─ config.xml
├─ logo.png
└─ views/templates/hook/product_extra.tpl
3. In Back Office → Modules → search “Preorder Cap” → Install (or Reset).
4. Clear Symfony cache if needed: remove `var/cache/*`.

## How to use

1. Open a product in Back Office → **Modules** tab → **Preorder Cap** panel.
2. Set **Enable = Yes** and **Cap = N** units.
3. Save the **product** with the native **Save** button (top-right).

> There is **no AJAX** in this version. Fields are saved with the main product form via `actionProductUpdate`.

## Uninstall behavior

- If the cap is still enabled for any product, the module restores the original `out_of_stock` on uninstall and drops its table.

## Database


ps_rm_preorder_cap

id_product (PK)

id_shop (PK)

enabled TINYINT(1)

cap INT UNSIGNED

original_out_of_stock TINYINT(1) NULL

date_upd DATETIME NULL


## Hooks used

- `displayAdminProductsExtra`
- `actionProductUpdate`
- `actionCartUpdateQuantityBefore`
- `actionUpdateQuantity`
- `actionObjectStockAvailableUpdateAfter`

## Limitations

- No product combinations (id_product_attribute must be 0).
- No multistore logic (uses current shop context).
- No frontend messages; only cart-level restriction.

## Troubleshooting

- Module not showing correctly in module list:
  - Make sure `config.xml` exists in module root and is valid.
  - Clear cache `var/cache/*`.
- Product form “Network error/Forbidden”:
  - This version uses **no AJAX**. If you see those messages, you’re likely using an older build. Replace with this minimal build.
- Product page 500 after enabling:
  - Temporarily rename `modules/rmpreordercap` to `_rmpreordercap_off` to disable, clear cache, then recheck. Validate `config.xml` and PHP 7.2 syntax.

## License

MIT
