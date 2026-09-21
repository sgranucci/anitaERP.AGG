(function () {
    'use strict';

    var formSync = document.getElementById('form-tn-sync');
    var formMasivo = document.getElementById('form-tn-masivo');
    var overlay = document.getElementById('tn-proceso-overlay');
    var titulo = document.getElementById('tn-proceso-titulo');
    var subtitulo = document.getElementById('tn-proceso-subtitulo');
    var desde = document.getElementById('filtro_desde');
    var hasta = document.getElementById('filtro_hasta');
    var syncDesde = document.getElementById('sync_desde');
    var syncHasta = document.getElementById('sync_hasta');
    var checkAll = document.getElementById('tn-check-all-listos');
    var countBadge = document.getElementById('tn-masivo-count');
    var btnMasivo = document.getElementById('btn-tn-masivo');

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
        return Array.prototype.slice.call(document.querySelectorAll('.tn-check-pedido'));
    }

    function actualizarContador() {
        var n = checks().filter(function (c) { return c.checked; }).length;
        if (countBadge) countBadge.textContent = String(n);
        if (btnMasivo) btnMasivo.disabled = n === 0;
    }

    if (formSync) {
        formSync.addEventListener('submit', function () {
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
        if (e.target && e.target.classList && e.target.classList.contains('tn-check-pedido')) {
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
            if (!confirm('¿Facturar ' + n + ' pedido(s) listo(s) con PV/depósito/medio sugeridos?')) {
                e.preventDefault();
                return;
            }
            mostrarOverlay('Facturando pedidos…', 'Emitiendo en ARCA uno por uno. No cierre la página.');
        });
    }

    actualizarContador();
    window.addEventListener('pageshow', ocultarOverlay);

    var formFiltros = document.getElementById('form-tn-filtros');
    if (formFiltros) {
        formFiltros.addEventListener('click', function (e) {
            var btn = e.target.closest('.tn-filtro-etiq');
            if (!btn || !formFiltros.contains(btn)) {
                return;
            }
            var campo = btn.getAttribute('data-campo');
            var valor = btn.getAttribute('data-valor');
            if (!campo) {
                return;
            }
            var hidden = document.getElementById('filtro_' + campo);
            if (hidden) {
                hidden.value = valor === null ? '' : String(valor);
            }
            formFiltros.submit();
        });
    }
})();
