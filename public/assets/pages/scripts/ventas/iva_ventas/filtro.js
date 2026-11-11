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

    var empresa = document.getElementById('empresa_id');
    if (empresa && empresa.tagName === 'SELECT') {
        empresa.addEventListener('change', function () {
            var campo = document.getElementById('tm_puntoventa_iva_ventas');
            if (!campo) {
                return;
            }
            var hidden = campo.querySelector('.puntoventa_id');
            var codigo = campo.querySelector('.codigopuntoventa');
            var nombre = campo.querySelector('.descripcionpuntoventa');
            if (hidden) {
                hidden.value = '';
            }
            if (codigo) {
                codigo.value = '';
            }
            if (nombre) {
                nombre.value = '';
            }
        });
    }

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

    // El tilde de totales por sucursal solo afecta PDF/Excel: actualiza los href sin reconsultar.
    var chkTotalesSucursal = document.getElementById('exportar_totales_sucursal');
    if (chkTotalesSucursal) {
        var syncExportTotalesSucursal = function () {
            var activo = chkTotalesSucursal.checked ? '1' : '0';
            document.querySelectorAll('a[href*="listar-iva-ventas"]').forEach(function (a) {
                try {
                    var url = new URL(a.href, window.location.origin);
                    url.searchParams.set('exportar_totales_sucursal', activo);
                    a.href = url.pathname + url.search + url.hash;
                } catch (e) {
                    /* ignore */
                }
            });
        };
        chkTotalesSucursal.addEventListener('change', syncExportTotalesSucursal);
        syncExportTotalesSucursal();
    }
})();
