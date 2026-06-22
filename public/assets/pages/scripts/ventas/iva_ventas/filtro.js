(function () {
    'use strict';

    var form = document.getElementById('form-iva-ventas');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Consultando…';
            }
        });
    }
})();
