(function () {
    'use strict';

    function reindexLineas() {
        var rows = document.querySelectorAll('#cdm-lineas-tbody .cdm-linea-row');
        rows.forEach(function (row, idx) {
            row.querySelectorAll('[name^="lineas["]').forEach(function (el) {
                el.name = el.name.replace(/lineas\[\d+]/, 'lineas[' + idx + ']');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btnAdd = document.getElementById('cdm-agregar-linea');
        var tbody = document.getElementById('cdm-lineas-tbody');
        var tpl = document.getElementById('cdm-template-linea');

        if (btnAdd && tbody && tpl) {
            btnAdd.addEventListener('click', function () {
                var idx = tbody.querySelectorAll('.cdm-linea-row').length;
                var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
                tbody.insertAdjacentHTML('beforeend', html);
            });
            tbody.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.cdm-quitar-linea');
                if (!btn) return;
                var row = btn.closest('tr');
                if (row) row.remove();
                reindexLineas();
            });
        }

        var btnArchivo = document.getElementById('cdm-agrega-renglon-archivo');
        var tbodyArch = document.getElementById('cdm-tbody-tabla-archivo');
        var tplArch = document.getElementById('cdm-template-renglon-archivo');
        if (btnArchivo && tbodyArch && tplArch) {
            btnArchivo.addEventListener('click', function () {
                tbodyArch.insertAdjacentHTML('beforeend', tplArch.innerHTML);
            });
            tbodyArch.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.cdm-eliminararchivo');
                if (!btn) return;
                var rows = tbodyArch.querySelectorAll('.item-archivo-cdm');
                var row = btn.closest('tr');
                if (!row) return;
                if (rows.length <= 1) {
                    var input = row.querySelector('input[type=file]');
                    if (input) input.value = '';
                    return;
                }
                row.remove();
            });
        }

        document.querySelectorAll('.cdm-quitar-archivo-existente').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.col-md-6, .col-lg-4, .card');
                var wrap = btn.closest('.col-md-6') || btn.closest('.col-lg-4');
                if (wrap) {
                    var hidden = wrap.querySelector('.cdm-conservar-archivo');
                    if (hidden) hidden.remove();
                    wrap.remove();
                } else if (card) {
                    card.remove();
                }
            });
        });

        var localSel = document.getElementById('local_venta_id');
        var empresaHidden = document.getElementById('empresa_id');
        if (localSel && empresaHidden) {
            localSel.addEventListener('change', function () {
                var opt = localSel.options[localSel.selectedIndex];
                empresaHidden.value = opt ? (opt.getAttribute('data-empresa') || '') : '';
            });
        }

        var btnBuscar = document.getElementById('btn-buscar-venta-original');
        var codigoInput = document.getElementById('venta_original_codigo');
        var idHidden = document.getElementById('venta_original_id');
        var hint = document.getElementById('venta_original_hint');
        function aplicarVenta(item) {
            if (!item) return;
            if (idHidden) idHidden.value = item.id;
            if (codigoInput) codigoInput.value = item.codigo || String(item.id);
            if (hint) {
                hint.textContent = 'Total: ' + (item.total != null ? Number(item.total).toFixed(2) : '') +
                    (item.cliente ? ' — ' + item.cliente : '');
            }
            var clienteId = document.getElementById('cliente_id');
            var receptor = document.getElementById('receptor_nombre');
            var doc = document.getElementById('receptor_documento');
            if (clienteId && item.cliente_id) clienteId.value = item.cliente_id;
            if (receptor && item.cliente) receptor.value = item.cliente;
            if (doc && item.documento) doc.value = item.documento;
        }

        function buscarVenta() {
            var q = (codigoInput && codigoInput.value || '').trim();
            if (q.length < 1 || !window.cdmBuscarVentaUrl) return;
            fetch(window.cdmBuscarVentaUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (json) {
                var list = (json && json.data) || [];
                if (list.length === 0) {
                    alert('No se encontró la factura.');
                    return;
                }
                if (list.length === 1) {
                    aplicarVenta(list[0]);
                    return;
                }
                var msg = list.map(function (it, i) {
                    return (i + 1) + ') ' + (it.codigo || it.id) + ' $' + Number(it.total || 0).toFixed(2) + ' ' + (it.cliente || '');
                }).join('\n');
                var n = parseInt(prompt('Varias coincidencias:\n' + msg + '\n\nNúmero de opción:'), 10);
                if (n >= 1 && n <= list.length) aplicarVenta(list[n - 1]);
            }).catch(function () {
                alert('Error al buscar la factura.');
            });
        }

        if (btnBuscar) btnBuscar.addEventListener('click', buscarVenta);
        if (codigoInput) {
            codigoInput.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    buscarVenta();
                }
            });
        }
    });
})();
