RM Preorder Cap v1.1.0 (PS 1.7.6, PHP 7.2)

What changed vs 1.0.0
---------------------
- Performance: added in-memory cache for product config (per request).
- Performance: cart quantity now read via direct SQL (avoids cart->getProducts() weight).
- Robustness: admin JS moved to external file (views/js/product_admin.js) and enqueued from hook.
- Safety: AJAX endpoint validates shop context and cap >= 0; uses atomic UPSERT (ON DUPLICATE KEY) to avoid races.
- i18n: template strings and admin messages routed through translation helpers.

Install / Upgrade
-----------------
- Replace the /modules/rmpreordercap/ folder with this package or install fresh.
- PrestaShop will detect the higher version (1.1.0). If needed, uninstall previous and install this one.

Notes
-----
- Scope unchanged: products without combinations, single shop, ASM OK, no FO messaging.
