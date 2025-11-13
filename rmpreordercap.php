<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Rmpreordercap extends Module
{
    protected static $productConfigCache = array();

    public function __construct()
    {
        $this->name = 'rmpreordercap';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'RockMa Tools';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('RM Preorder Cap');
        $this->description = $this->l('Per-product preorder (backorder) cap and auto-revert on stock arrival for PS 1.7.6');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '1.7.99.99');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        if (!$this->installSql()) {
            return false;
        }

        return $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('actionUpdateQuantity')
            && $this->registerHook('actionObjectStockAvailableUpdateAfter');
    }


public function uninstall()
    {
        $db = Db::getInstance();
        $prefix = _DB_PREFIX_;
        $rows = $db->executeS('SELECT id_product, id_shop, original_out_of_stock FROM `'.pSQL($prefix).'rm_preorder_cap` WHERE enabled = 1 AND original_out_of_stock IS NOT NULL');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $id_product = (int)$row['id_product'];
                $id_shop = (int)$row['id_shop'];
                $orig = (int)$row['original_out_of_stock'];
                if (class_exists('StockAvailable')) {
                    StockAvailable::setProductOutOfStock($id_product, $orig, $id_shop);
                }
            }
        }
        $this->uninstallSql();
        return parent::uninstall();
    }

    protected function installSql()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rm_preorder_cap` (
            `id_product` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `cap` INT UNSIGNED NOT NULL DEFAULT 0,
            `original_out_of_stock` TINYINT(1) NULL DEFAULT NULL,
            `date_upd` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id_product`, `id_shop`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
        return Db::getInstance()->execute($sql);
    }

    protected function uninstallSql()
    {
        $sql = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'rm_preorder_cap`';
        return Db::getInstance()->execute($sql);
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;
        if (!$id_product) {
            return '';
        }

        $id_shop = (int)$this->context->shop->id;
        $row = $this->getConfigRow($id_product, $id_shop);

        $current_out = (int)StockAvailable::outOfStock($id_product, $id_shop);
        $quantity = (int)StockAvailable::getQuantityAvailableByProduct($id_product, 0, $id_shop);
        $remaining = $this->getPreorderRemaining($id_product, $id_shop, $row);

        $this->context->smarty->assign(array(
            'id_product' => $id_product,
            'id_shop' => $id_shop,
            'rm_current_out_of_stock' => $current_out,
            'rm_quantity' => $quantity,
            'rm_remaining' => $remaining,
            'rm_enabled' => (int)$row['enabled'],
            'rm_cap' => (int)$row['cap'],
            't_enable' => $this->l('Enable preorder cap'),
            't_cap_units' => $this->l('Cap units (incoming quantity)'),
            't_help' => $this->l('When enabled, backorders are allowed up to the specified cap; once the total preordered quantity reaches the cap, backorders are blocked automatically.'),
        ));

        return $this->display(__FILE__, 'views/templates/hook/product_extra.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        $id_product = 0;

        if (isset($params['id_product'])) {
            $id_product = (int)$params['id_product'];
        } elseif (isset($params['product']) && isset($params['product']->id)) {
            $id_product = (int)$params['product']->id;
        }

        if ($id_product <= 0) {
            return;
        }

        $id_shop = (int)$this->context->shop->id;

        $enabledRaw = Tools::getValue('rmcap_enabled', null);
        $capRaw = Tools::getValue('rmcap_cap', null);

        // If fields are not present, this save did not come from the full product form
        if ($enabledRaw === null && $capRaw === null) {
            return;
        }

        $enabled = (int)$enabledRaw;
        $cap = (int)$capRaw;
        if ($cap < 0) {
            $cap = 0;
        }

        if ($enabled) {
            $row = $this->getConfigRow($id_product, $id_shop);

            $orig = null;
            if ($row && array_key_exists('original_out_of_stock', $row) && $row['original_out_of_stock'] !== null) {
                $orig = (int)$row['original_out_of_stock'];
            }
            if ($orig === null) {
                $orig = (int)StockAvailable::outOfStock($id_product, $id_shop);
            }

            StockAvailable::setProductOutOfStock($id_product, 1, $id_shop);
            $this->setConfigRow($id_product, $id_shop, 1, $cap, $orig);
        } else {
            $row = $this->getConfigRow($id_product, $id_shop);
            $orig = 0;
            if ($row && array_key_exists('original_out_of_stock', $row) && $row['original_out_of_stock'] !== null) {
                $orig = (int)$row['original_out_of_stock'];
            }

            StockAvailable::setProductOutOfStock($id_product, (int)$orig, $id_shop);
            $this->setConfigRow($id_product, $id_shop, 0, 0, $orig);
        }

        $cacheKey = (int)$id_product.'_'.$id_shop;
        if (isset(self::$productConfigCache[$cacheKey])) {
            unset(self::$productConfigCache[$cacheKey]);
        }
    }
    public function hookActionCartUpdateQuantityBefore($params)
    {
        $id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;
        $id_product_attribute = isset($params['id_product_attribute']) ? (int)$params['id_product_attribute'] : 0;
        $op = isset($params['operator']) ? $params['operator'] : null;
        $deltaQty = isset($params['quantity']) ? (int)$params['quantity'] : 0;

        if ($id_product <= 0 || $id_product_attribute > 0) {
            return;
        }

        $cart = (isset($params['cart']) && $params['cart'] instanceof Cart) ? $params['cart'] : $this->context->cart;
        if (!$cart || !Validate::isLoadedObject($cart)) {
            return;
        }

        $id_shop = (int)$cart->id_shop;
        if ($id_shop <= 0) {
            $id_shop = (int)$this->context->shop->id;
        }

        $row = $this->getConfigRow($id_product, $id_shop);
        if (!(int)$row['enabled'] || (int)$row['cap'] <= 0) {
            return;
        }

        $allowedTotal = $this->getAllowedTotalForCart($id_product, $id_shop, $row);
        $currentInCart = $this->getCartQuantityForProduct((int)$cart->id, $id_product);

        // Compute what the new quantity in cart would be
        $newQty = $currentInCart;
        if ($op === 'up' || $op === '+') {
            $newQty = $currentInCart + $deltaQty;
        } elseif ($op === 'down' || $op === '-') {
            $newQty = max(0, $currentInCart - $deltaQty);
        } elseif ($op === null || $op === 'update') {
            // Direct set
            if ($deltaQty > 0) {
                $newQty = $deltaQty;
            }
        }

        if ($newQty > $allowedTotal) {
            $remaining = $this->getPreorderRemaining($id_product, $id_shop, $row);
            $message = $this->l('The requested quantity exceeds the allowed preorder limit for this product.');

            $isAjax = (bool)Tools::getValue('ajax');
            if ($isAjax) {
                header('Content-Type: application/json');
                die(json_encode(array(
                    'hasError' => true,
                    'errors' => array($message),
                )));
            } else {
                if (isset($this->context->controller)) {
                    $this->context->controller->errors[] = $message;
                }
                Tools::redirect('index.php?controller=cart');
            }
        }
    }

    public function hookActionUpdateQuantity($params)
    {
        $id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;
        $id_product_attribute = isset($params['id_product_attribute']) ? (int)$params['id_product_attribute'] : 0;
        $id_shop = isset($params['id_shop']) ? (int)$params['id_shop'] : (int)$this->context->shop->id;

        if ($id_product <= 0 || $id_product_attribute > 0 || $id_shop <= 0) {
            return;
        }

        $this->maybeDisablePreorder($id_product, $id_shop);
    }

    public function hookActionObjectStockAvailableUpdateAfter($params)
    {
        if (!isset($params['object']) || !($params['object'] instanceof StockAvailable)) {
            return;
        }
        /** @var StockAvailable $stock */
        $stock = $params['object'];
        $id_product = (int)$stock->id_product;
        $id_product_attribute = (int)$stock->id_product_attribute;
        $id_shop = (int)$stock->id_shop;

        if ($id_product <= 0 || $id_product_attribute > 0 || $id_shop <= 0) {
            return;
        }

        $this->maybeDisablePreorder($id_product, $id_shop);
    }




protected function getConfigRow($id_product, $id_shop)
    {
        $cacheKey = (int)$id_product.'_'.$id_shop;
        if (isset(self::$productConfigCache[$cacheKey])) {
            return self::$productConfigCache[$cacheKey];
        }
        $row = Db::getInstance()->getRow('
            SELECT *
            FROM `'._DB_PREFIX_.'rm_preorder_cap`
            WHERE id_product='.(int)$id_product.' AND id_shop='.(int)$id_shop.'
        ');
        if (!$row) {
            $row = array(
                'id_product' => (int)$id_product,
                'id_shop' => (int)$id_shop,
                'enabled' => 0,
                'cap' => 0,
                'original_out_of_stock' => null,
                'date_upd' => null,
            );
        }
        self::$productConfigCache[$cacheKey] = $row;
        return $row;
    }

    protected function setConfigRow($id_product, $id_shop, $enabled, $cap, $original_out_of_stock = null)
    {
        $exists = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `'._DB_PREFIX_.'rm_preorder_cap`
            WHERE id_product='.(int)$id_product.' AND id_shop='.(int)$id_shop.'
        ');
        $now = date('Y-m-d H:i:s');
        $data = array(
            'enabled' => (int)$enabled,
            'cap' => (int)$cap,
            'date_upd' => pSQL($now),
        );
        if ($original_out_of_stock !== null) {
            $data['original_out_of_stock'] = (int)$original_out_of_stock;
        }
        if ($exists) {
            return Db::getInstance()->update('rm_preorder_cap', $data, 'id_product='.(int)$id_product.' AND id_shop='.(int)$id_shop);
        } else {
            $data['id_product'] = (int)$id_product;
            $data['id_shop'] = (int)$id_shop;
            return Db::getInstance()->insert('rm_preorder_cap', $data);
        }
    }

    protected function getPreorderRemaining($id_product, $id_shop, $row = null)
    {
        if ($row === null) {
            $row = $this->getConfigRow($id_product, $id_shop);
        }
        if (!(int)$row['enabled'] || (int)$row['cap'] <= 0) {
            return 0;
        }
        $qty = (int)StockAvailable::getQuantityAvailableByProduct($id_product, 0, $id_shop);
        $preorders_sold = $qty < 0 ? (0 - $qty) : 0;
        $rem = (int)$row['cap'] - $preorders_sold;
        return $rem > 0 ? $rem : 0;
    }

    protected function getAllowedTotalForCart($id_product, $id_shop, $row = null)
    {
        $qty = (int)StockAvailable::getQuantityAvailableByProduct($id_product, 0, $id_shop);
        $positive = $qty > 0 ? $qty : 0;
        $remaining = $this->getPreorderRemaining($id_product, $id_shop, $row);
        return $positive + $remaining;
    }

    protected function getCartQuantityForProduct($id_cart, $id_product)
    {
        return (int)Db::getInstance()->getValue('
            SELECT COALESCE(SUM(quantity),0)
            FROM '._DB_PREFIX_.'cart_product
            WHERE id_cart='.(int)$id_cart.'
              AND id_product='.(int)$id_product.'
              AND id_product_attribute=0
        ');
    }

    protected function maybeDisablePreorder($id_product, $id_shop)
    {
        $row = $this->getConfigRow($id_product, $id_shop);
        if (!(int)$row['enabled']) {
            return;
        }
        $qty = (int)StockAvailable::getQuantityAvailableByProduct($id_product, 0, $id_shop);
        $remaining = $this->getPreorderRemaining($id_product, $id_shop, $row);

        if ($qty > 0 || $remaining <= 0) {
            $orig = $row['original_out_of_stock'];
            if ($orig === null) {
                $orig = 0;
            }
            StockAvailable::setProductOutOfStock((int)$id_product, (int)$orig, (int)$id_shop);
            $this->setConfigRow($id_product, $id_shop, 0, 0, $orig);
            $cacheKey = (int)$id_product.'_'.$id_shop;
            if (isset(self::$productConfigCache[$cacheKey])) {
                unset(self::$productConfigCache[$cacheKey]);
            }
        }
    }
}
