(function () {
    'use strict';

    var formSync = document.getElementById('form-ml-sync');
    var formMasivo = document.getElementById('form-ml-masivo');
    var overlay = document.getElementById('ml-proceso-overlay');
    var titulo = document.getElementById('ml-proceso-titulo');
    var subtitulo = document.getElementById('ml-proceso-subtitulo');
    var desde = document.querySelector('#form-ml-filtros input[name="desde"]');
    var hasta = document.querySelector('#form-ml-filtros input[name="hasta"]');
    var syncDesde = document.querySelector('#form-ml-sync input[name="desde"]');
    var syncHasta = document.querySelector('#form-ml-sync input[name="hasta"]');
    var checkAll = document.getElementById('ml-check-all-listos');
    var countBadge = document.getElementById('ml-masivo-count');
    var btnMasivo = document.getElementById('btn-ml-masivo');
    var btnSync = document.getElementById('btn-ml-sync');

    function mostrarOverlay(tit, sub) {
        if (!overlay) return;
        if (titulo && tit) titulo.textContent = tit;
        if (subtitulo && sub) subtitulo.textContent = sub;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function checks() {
        return Array.prototype.slice.call(document.querySelectorAll('.ml-check-pedido'));
    }

    function actualizarContador() {
        var n = checks().filter(function (c) { return c.checked; }).length;
        if (countBadge) countBadge.textContent = String(n);
        if (btnMasivo) btnMasivo.disabled = n === 0;
    }

    if (formSync && btnSync) {
        btnSync.addEventListener('click', function () {
            if (syncDesde && desde) syncDesde.value = desde.value;
            if (syncHasta && hasta) syncHasta.value = hasta.value;
            mostrarOverlay('Sincronizando pedidos…', 'Puede demorar según el rango. No cierre la página.');
        });
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checks().forEach(function (c) { c.checked = checkAll.checked; });
            actualizarContador();
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('ml-check-pedido')) {
            actualizarContador();
        }
    });

    if (formMasivo) {
        formMasivo.addEventListener('submit', function (e) {
            var n = checks().filter(function (c) { return c.checked; }).length;
            if (n === 0) {
                e.preventDefault();
                alert('Seleccione al menos un pedido listo.');
                return;
            }
            if (!confirm('¿Facturar ' + n + ' pedido(s) listo(s)? Las órdenes del mismo pack se agrupan en una sola factura.')) {
                e.preventDefault();
                return;
            }
            mostrarOverlay('Facturando pedidos…', 'Emitiendo en ARCA uno por uno. No cierre la página.');
        });
    }

    actualizarContador();
    window.addEventListener('pageshow', ocultarOverlay);
})();
