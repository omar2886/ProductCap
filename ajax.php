<?php
require dirname(__FILE__).'/../../config/config.inc.php';
require dirname(__FILE__).'/../../init.php';

header('Content-Type: application/json');

$context = Context::getContext();
if (!$context->employee || !$context->employee->isLoggedBack()) {
    http_response_code(403);
    die(json_encode(array('ok'=>false,'error'=>'Forbidden')));
}

$token = Tools::getValue('token');
if (!$token || $token !== Tools::getAdminTokenLite('AdminProducts')) {
    http_response_code(403);
    die(json_encode(array('ok'=>false,'error'=>'Bad token')));
}

if (!Validate::isLoadedObject($context->shop)) {
    http_response_code(400);
    die(json_encode(array('ok'=>false,'error'=>'Invalid shop context')));
}

$id_product = (int)Tools::getValue('id_product');
$id_shop = (int)$context->shop->id;
$enabled = (int)Tools::getValue('rm_enabled');
$cap = (int)Tools::getValue('rm_cap');

if ($id_product <= 0) {
    http_response_code(400);
    die(json_encode(array('ok'=>false,'error'=>'Missing product')));
}
if ($cap < 0) {
    http_response_code(400);
    die(json_encode(array('ok'=>false,'error'=>'Invalid cap value')));
}

$module = Module::getInstanceByName('rmpreordercap');
if (!$module) {
    http_response_code(500);
    die(json_encode(array('ok'=>false,'error'=>'Module not loaded')));
}

$now = date('Y-m-d H:i:s');

if ($enabled && $cap > 0) {
    $existing = Db::getInstance()->getRow('SELECT original_out_of_stock FROM `'._DB_PREFIX_.'rm_preorder_cap` WHERE id_product='.(int)$id_product.' AND id_shop='.(int)$id_shop);
    if ($existing && $existing['original_out_of_stock'] !== null) {
        $orig = (int)$existing['original_out_of_stock'];
    } else {
        $orig = (int)StockAvailable::outOfStock($id_product, $id_shop);
    }
    StockAvailable::setProductOutOfStock($id_product, 1, $id_shop);

    $sql = 'INSERT INTO `'._DB_PREFIX_.'rm_preorder_cap` 
            (id_product, id_shop, enabled, cap, original_out_of_stock, date_upd) VALUES (
                '.(int)$id_product.',
                '.(int)$id_shop.',
                1,
                '.(int)$cap.',
                '.(int)$orig.',
                "'.pSQL($now).'"
            )
            ON DUPLICATE KEY UPDATE enabled=1, cap='.(int)$cap.', date_upd="'.pSQL($now).'"';
    Db::getInstance()->execute($sql);

    die(json_encode(array('ok'=>true, 'message'=>'Saved and enabled.')));
} else {
    $row = Db::getInstance()->getRow('SELECT original_out_of_stock FROM `'._DB_PREFIX_.'rm_preorder_cap` WHERE id_product='.(int)$id_product.' AND id_shop='.(int)$id_shop);
    $orig = ($row && $row['original_out_of_stock'] !== null) ? (int)$row['original_out_of_stock'] : 0;
    StockAvailable::setProductOutOfStock($id_product, (int)$orig, $id_shop);

    Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'rm_preorder_cap` SET enabled=0, cap=0, date_upd="'.pSQL($now).'" WHERE id_product='.(int)$id_product.' AND id_shop='.(int)$id_shop);

    die(json_encode(array('ok'=>true, 'message'=>'Disabled and restored.')));
}
