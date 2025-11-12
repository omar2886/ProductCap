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
        $this->author = 'Eutanasio';
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

        $token = Tools::getAdminTokenLite('AdminProducts');
        $current_out = (int)StockAvailable::outOfStock($id_product, $id_shop);
        $quantity = (int)StockAvailable::getQuantityAvailableByProduct($id_product, 0, $id_shop);
        $remaining = $this->getPreorderRemaining($id_product, $id_shop, $row);

        if (isset($this->context->controller) && method_exists($this->context->controller, 'addJS')) {
            $this->context->controller->addJS($this->_path.'views/js/product_admin.js');
        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'id_product' => $id_product,
            'id_shop' => $id_shop,
            'rm_enabled' => (int)$row['enabled'],
            'rm_cap' => (int)$row['cap'],
            'rm_token' => $token,
            'rm_current_out_of_stock' => $current_out,
            'rm_quantity' => $quantity,
            'rm_remaining' => $remaining,
            'ajax_url' => $this->_path.'ajax.php',
            't_enable' => $this->l('Enable preorder cap'),
            't_cap_units' => $this->l('Cap units (incoming quantity)'),
            't_help' => $this->l('When enabled, backorders are allowed up to the cap. After reaching the cap or when stock becomes positive again, backorders will be blocked automatically.'),
            't_save' => $this->l('Save'),
            't_saving' => $this->l('Saving...'),
            't_saved' => $this->l('Saved'),
            't_error' => $this->l('Error'),
            't_network_error' => $this->l('Network error'),
        ));

        return $this->display(__FILE__, 'views/templates/hook/product_extra.tpl');
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

        $id_shop = (int)$this->context->shop->id;
        $row = $this->getConfigRow($id_product, $id_shop);
        if (!(int)$row['enabled'] || (int)$row['cap'] <= 0) {
            return;
        }

        $allowedTotal = $this->getAllowedTotalForCart($id_product, $id_shop, $row);
        $inCart = $this->getCartQuantityForProduct($cart->id, $id_product);

        $targetQty = $inCart;
        if ($op === 'up') {
            $targetQty = $inCart + $deltaQty;
        } elseif ($op === 'down') {
            $targetQty = max(0, $inCart - $deltaQty);
        } elseif ($deltaQty > 0) {
            $targetQty = $deltaQty;
        }

        if ($targetQty > $allowedTotal) {
            $remain = max(0, $allowedTotal - $inCart);
            $message = $this->l('Preorder limit reached for this product. Remaining allowable quantity: ').(int)$remain;
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
        if (isset($params['id_product'])) {
            $this->maybeDisablePreorder((int)$params['id_product'], (int)$this->context->shop->id);
        }
    }

    public function hookActionObjectStockAvailableUpdateAfter($params)
    {
        if (isset($params['object']) && isset($params['object']->id_product)) {
            $this->maybeDisablePreorder((int)$params['object']->id_product, (int)$this->context->shop->id);
        }
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
