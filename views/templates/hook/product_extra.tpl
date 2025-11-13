{* views/templates/hook/product_extra.tpl (form-save version) *}
<div class="panel" id="rmcap-panel">
  <h3><i class="icon-cogs"></i> RM Preorder Cap</h3>
  <p>
    <small>
      {l s='Product ID' mod='rmpreordercap'}: {$id_product}
      — {l s='Shop' mod='rmpreordercap'}: {$id_shop}<br/>
      {l s='Current out_of_stock' mod='rmpreordercap'}:
      <code>{$rm_current_out_of_stock}</code> (0=deny,1=allow,2=use default).<br/>
      {l s='Current stock qty' mod='rmpreordercap'}:
      <strong>{$rm_quantity}</strong>.<br/>
      {l s='Preorder remaining' mod='rmpreordercap'}:
      <strong>{$rm_remaining}</strong>.
    </small>
  </p>

  <div class="form-horizontal" id="rmcap-fields">
    <div class="form-group">
      <label class="control-label col-lg-3">
        {$t_enable|escape:'html':'UTF-8'}
      </label>
      <div class="col-lg-9">
        <span class="switch prestashop-switch fixed-width-lg">
          <input type="radio"
                 name="rmcap_enabled"
                 id="rmcap_enabled_on"
                 value="1"
                 {if $rm_enabled}checked="checked"{/if} />
          <label for="rmcap_enabled_on">
            {l s='Yes' mod='rmpreordercap'}
          </label>

          <input type="radio"
                 name="rmcap_enabled"
                 id="rmcap_enabled_off"
                 value="0"
                 {if !$rm_enabled}checked="checked"{/if} />
          <label for="rmcap_enabled_off">
            {l s='No' mod='rmpreordercap'}
          </label>

          <a class="slide-button btn"></a>
        </span>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">
        {$t_cap_units|escape:'html':'UTF-8'}
      </label>
      <div class="col-lg-9">
        <input type="text"
               name="rmcap_cap"
               id="rmcap_cap"
               value="{$rm_cap|intval}"
               class="fixed-width-sm" />
        <p class="help-block">
          {$t_help|escape:'html':'UTF-8'}
        </p>
      </div>
    </div>
  </div>
</div>
