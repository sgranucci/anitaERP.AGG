(function () {
    'use strict';

    var form = document.getElementById('form-iva-ventas');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function () {
        var btn = document.getElementById('btn-consultar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Consultando…';
        }
    });

    // Los toggles marcados como .js-auto-consultar reenvían la consulta al cambiar,
    // pero solo si ya hay un resultado en pantalla (para que funcionen como botón).
    var yaConsultado = document.getElementById('tabla-paginada') !== null;
    if (yaConsultado) {
        var toggles = form.querySelectorAll('.js-auto-consultar');
        Array.prototype.forEach.call(toggles, function (input) {
            input.addEventListener('change', function () {
                if (form.reportValidity && !form.reportValidity()) {
                    return;
                }
                form.submit();
            });
        });
    }
})();
