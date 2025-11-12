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
