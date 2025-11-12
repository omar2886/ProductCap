ProductCap – Módulo micro para PrestaShop 1.7.x / PHP 7.2+

Permite activar, por producto, un “cupo” (cap) de preventa cuando no hay stock,
forzando out_of_stock=1 mientras queda cupo. Cuando entra stock positivo o se
consume el cupo, restaura automáticamente el comportamiento original.

Requisitos:
- PrestaShop 1.7.6 (probado), PHP 7.2.x
- Sin multitienda, sin combinaciones

Instalación:
1) Copia rmpreordercap/ en modules/
2) Debe existir config.xml en la raíz del módulo (no config_fr.xml)
3) BO → Módulos → instalar o reinicializar
4) Limpia cache si es necesario (elimina var/cache/*)

Uso:
- En la ficha del producto → pestaña “Módulos” → “Preorder Cap”
- Activa “Enable = Yes” y define “Cap = N”
- Guarda el producto con el botón nativo (no hay AJAX)

BD:
ps_rm_preorder_cap (id_product,id_shop,enabled,cap,original_out_of_stock,date_upd)

Licencia: MIT
