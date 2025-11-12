(function() {
  function savePanel() {
    var btn = document.getElementById('rmcap-save');
    if (!btn) return false;
    var box = document.getElementById('rmcap-fields');
    var msg = document.getElementById('rmcap-msg');
    var ajaxUrlEl = document.getElementById('rmcap-ajax-url');
    if (!box || !msg || !ajaxUrlEl) return false;

    var ajaxUrl = ajaxUrlEl.value;
    var enabled = document.getElementById('rm_enabled_on') && document.getElementById('rm_enabled_on').checked ? 1 : 0;
    var capEl = document.getElementById('rm_cap');
    var cap = parseInt(capEl && capEl.value ? capEl.value : '0', 10);
    var idProduct = box.getAttribute('data-id-product');

    var form = new FormData();
    form.append('id_product', idProduct);
    form.append('rm_enabled', isNaN(enabled) ? 0 : enabled);
    form.append('rm_cap', isNaN(cap) ? 0 : cap);

    msg.innerHTML = '<span class="label label-info">Saving...</span>';
    fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form
    }).then(function(r){
      return r.json();
    }).then(function(json){
      if (json && json.ok) {
        msg.innerHTML = '<span class="label label-success">'+(json.message||'Saved')+'</span>';
      } else {
        msg.innerHTML = '<span class="label label-danger">'+(json && json.error ? json.error : 'Error')+'</span>';
      }
    }).catch(function(e){
      msg.innerHTML = '<span class="label label-danger">Network error</span>';
    });
    return true;
  }

  document.addEventListener('click', function(e){
    var t = e.target;
    if (!t) return;
    if (t.id === 'rmcap-save' || (t.closest && t.closest('#rmcap-save'))) {
      e.preventDefault();
      savePanel();
    }
  });

  function attachIfReady(){
    var btn = document.getElementById('rmcap-save');
    if (btn && !btn._rmcapBound) {
      btn.addEventListener('click', function(ev){ ev.preventDefault(); savePanel(); });
      btn._rmcapBound = true;
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachIfReady);
  } else {
    attachIfReady();
  }
})();