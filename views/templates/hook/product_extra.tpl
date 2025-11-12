{* views/templates/hook/product_extra.tpl (v1.3.0) *}
<div class="panel" id="rmcap-panel">
  <h3><i class="icon-cogs"></i> RM Preorder Cap</h3>
  <p>
    <small>
      {$smarty.const._PS_VERSION_} — {l s='Product ID' mod='rmpreordercap'}: {$id_product} — {l s='Shop' mod='rmpreordercap'}: {$id_shop}<br/>
      {l s='Current out_of_stock' mod='rmpreordercap'}: <code>{$rm_current_out_of_stock}</code> (0=deny,1=allow,2=use default).
      {l s='Current stock qty' mod='rmpreordercap'}: <strong>{$rm_quantity}</strong>.
      {l s='Preorder remaining' mod='rmpreordercap'}: <strong>{$rm_remaining}</strong>.
    </small>
  </p>

  <div class="form-horizontal" id="rmcap-fields" data-id-product="{$id_product|intval}">
    <div class="form-group">
      <label class="control-label col-lg-3">{$t_enable|escape:'html':'UTF-8'}</label>
      <div class="col-lg-9">
        <span class="switch prestashop-switch fixed-width-lg">
          <input type="radio" name="rm_enabled" id="rm_enabled_on" value="1" {if $rm_enabled}checked="checked"{/if}>
          <label for="rm_enabled_on">{l s='Yes' mod='rmpreordercap'}</label>
          <input type="radio" name="rm_enabled" id="rm_enabled_off" value="0" {if !$rm_enabled}checked="checked"{/if}>
          <label for="rm_enabled_off">{l s='No' mod='rmpreordercap'}</label>
          <a class="slide-button btn"></a>
        </span>
        <p class="help-block">{$t_help|escape:'html':'UTF-8'}</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{$t_cap_units|escape:'html':'UTF-8'}</label>
      <div class="col-lg-9">
        <input type="number" min="0" step="1" id="rm_cap" class="form-control fixed-width-sm" value="{$rm_cap|intval}"/>
      </div>
    </div>

    <div class="form-group">
      <div class="col-lg-3"></div>
      <div class="col-lg-9">
        <button id="rmcap-save" type="button" class="btn btn-primary">
          <i class="icon-save"></i> {$t_save|escape:'html':'UTF-8'}
        </button>
        <span id="rmcap-msg" style="margin-left:10px;"></span>

        {* AdminModules AJAX URL with token *}
        {assign var=rmcap_admin_url value=$link->getAdminLink('AdminModules')|cat:'&configure=rmpreordercap&module_name=rmpreordercap&ajax=1&action=save'}
        <input type="hidden" id="rmcap-ajax-url" value="{$rmcap_admin_url|escape:'html':'UTF-8'}"/>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript" src="{$module_dir|escape:'html':'UTF-8'}views/js/product_admin.js?v=130"></script>
