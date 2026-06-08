/**
 * Formulario de Préstamos: maneja la matriz de ítems y muestra los
 * saldos del depósito de origen / destino para cada artículo elegido.
 *
 * Estrategia:
 *  - El blade entrega los saldos iniciales precalculados (atributo
 *    value de los inputs hidden #prestamo-saldos-origen / -destino).
 *  - Cuando cambia el artículo o un depósito hacemos un fetch a la
 *    ruta `prestamo_saldo_articulo` (controller saldoArticulo) que
 *    retorna `{ saldos: { [deposito_id]: cantidad } }` para el par
 *    artículo + (origen, destino) seleccionado.
 *  - Si el artículo no se cambió y el depósito tampoco, usamos los
 *    saldos en cache locales para no pegarle a la red.
 */
(function () {
    'use strict';

    function $$(selector, ctx) { return Array.from((ctx || document).querySelectorAll(selector)); }
    function fmt(n) {
        if (n === null || n === undefined || Number.isNaN(Number(n))) return '—';
        const num = Number(n);
        if (Math.abs(num - Math.trunc(num)) < 1e-9) return String(Math.trunc(num));
        return num.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('tbody-prestamo-items');
        if (!tbody) return;

        const articulosData = JSON.parse(document.getElementById('prestamo-articulos-data').dataset.articulos || '[]');
        const cacheSaldos = {};
        try {
            const saldosOrigenInicial = JSON.parse(document.getElementById('prestamo-saldos-origen').value || '{}');
            Object.keys(saldosOrigenInicial).forEach(function (k) {
                cacheSaldos['o:' + k] = Number(saldosOrigenInicial[k]);
            });
            const saldosDestinoInicial = JSON.parse(document.getElementById('prestamo-saldos-destino').value || '{}');
            Object.keys(saldosDestinoInicial).forEach(function (k) {
                cacheSaldos['d:' + k] = Number(saldosDestinoInicial[k]);
            });
        } catch (e) {
            // Silencioso: si falla el JSON del blade no rompemos la UI.
        }

        const saldoUrl = (document.getElementById('prestamo-saldo-articulo-url') || {}).value || '';

        const selDepOrigen = document.getElementById('deposito_origen_id');
        const selDepDestino = document.getElementById('deposito_destino_id');
        const badgeOrigen = document.getElementById('badge-deposito-origen');
        const badgeDestino = document.getElementById('badge-deposito-destino');

        function actualizarBadges() {
            const orig = selDepOrigen.options[selDepOrigen.selectedIndex];
            const dest = selDepDestino.options[selDepDestino.selectedIndex];
            badgeOrigen.textContent = orig && orig.value ? orig.text : '—';
            badgeDestino.textContent = dest && dest.value ? dest.text : '—';
        }

        function indexFila() {
            return tbody.querySelectorAll('tr.prestamo-item-row').length;
        }

        function renumerarFilas() {
            tbody.querySelectorAll('tr.prestamo-item-row').forEach(function (tr, idx) {
                tr.querySelectorAll('select, input').forEach(function (el) {
                    if (!el.name) return;
                    el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
                });
            });
        }

        function pintarSaldos(tr, articuloId) {
            const saldoO = cacheSaldos['o:' + articuloId];
            const saldoD = cacheSaldos['d:' + articuloId];
            const cantidad = parseFloat((tr.querySelector('.input-cantidad') || {}).value || 0);

            const tdO = tr.querySelector('.saldo-origen');
            const tdD = tr.querySelector('.saldo-destino');

            tdO.textContent = fmt(saldoO);
            tdD.textContent = fmt(saldoD);

            tdO.classList.toggle('text-danger', !isNaN(cantidad) && cantidad > 0 && Number(saldoO || 0) < cantidad);
            tdO.classList.toggle('font-weight-bold', !isNaN(cantidad) && cantidad > 0 && Number(saldoO || 0) < cantidad);
        }

        async function refrescarSaldosFila(tr) {
            const articuloId = parseInt((tr.querySelector('.select-articulo') || {}).value || '0', 10);
            if (!articuloId) {
                tr.querySelector('.saldo-origen').textContent = '—';
                tr.querySelector('.saldo-destino').textContent = '—';
                return;
            }

            const depOId = parseInt(selDepOrigen.value || '0', 10);
            const depDId = parseInt(selDepDestino.value || '0', 10);
            const depositos = [depOId, depDId].filter(function (x) { return x > 0; });
            if (depositos.length === 0 || !saldoUrl) {
                pintarSaldos(tr, articuloId);
                return;
            }

            try {
                const url = new URL(saldoUrl, window.location.origin);
                url.searchParams.append('articulo_id', String(articuloId));
                depositos.forEach(function (d) { url.searchParams.append('depositos[]', String(d)); });
                const empresaId = parseInt((document.getElementById('empresa_id') || {}).value || '0', 10);
                if (empresaId > 0) {
                    url.searchParams.append('empresa_id', String(empresaId));
                }
                const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error('http ' + res.status);
                const data = await res.json();
                if (data && data.saldos) {
                    if (depOId > 0 && data.saldos[depOId] !== undefined) {
                        cacheSaldos['o:' + articuloId] = Number(data.saldos[depOId]);
                    }
                    if (depDId > 0 && data.saldos[depDId] !== undefined) {
                        cacheSaldos['d:' + articuloId] = Number(data.saldos[depDId]);
                    }
                }
            } catch (e) {
                // silencioso, mostramos lo que haya en cache.
            }
            pintarSaldos(tr, articuloId);
        }

        function refrescarTodos() {
            // Vaciar cache de saldos: cambió un depósito, los previos
            // no son confiables.
            Object.keys(cacheSaldos).forEach(function (k) { delete cacheSaldos[k]; });
            tbody.querySelectorAll('tr.prestamo-item-row').forEach(refrescarSaldosFila);
        }

        function onCambioFila(ev) {
            const tr = ev.target.closest('tr.prestamo-item-row');
            if (!tr) return;
            if (ev.target.classList.contains('select-articulo')) {
                refrescarSaldosFila(tr);
            }
            if (ev.target.classList.contains('input-cantidad')) {
                pintarSaldos(tr, parseInt((tr.querySelector('.select-articulo') || {}).value || '0', 10));
            }
        }

        function onClickEliminar(ev) {
            const btn = ev.target.closest('.btn-eliminar-item');
            if (!btn) return;
            const tr = btn.closest('tr.prestamo-item-row');
            if (!tr) return;
            const filas = tbody.querySelectorAll('tr.prestamo-item-row');
            if (filas.length <= 1) {
                // No dejamos vacío: sólo limpiamos.
                tr.querySelectorAll('input, select').forEach(function (el) { el.value = ''; });
                refrescarSaldosFila(tr);
                return;
            }
            tr.remove();
            renumerarFilas();
        }

        function agregarFila() {
            const idx = indexFila();
            const tr = document.createElement('tr');
            tr.className = 'prestamo-item-row';
            const optsArticulos = articulosData.map(function (a) {
                return '<option value="' + a.id + '">' + (a.sku ? a.sku + ' - ' : '') + (a.descripcion || '') + '</option>';
            }).join('');
            tr.innerHTML =
                '<td><select name="items[' + idx + '][articulo_id]" class="form-control select-articulo" required>' +
                    '<option value="">-- Elegir artículo --</option>' + optsArticulos +
                '</select></td>' +
                '<td><input type="number" step="0.000001" min="0.000001" name="items[' + idx + '][cantidad]" class="form-control input-cantidad" required></td>' +
                '<td><span class="saldo-origen text-monospace">—</span></td>' +
                '<td><span class="saldo-destino text-monospace">—</span></td>' +
                '<td><input type="text" name="items[' + idx + '][observaciones]" class="form-control" maxlength="255"></td>' +
                '<td><button type="button" class="btn btn-link text-danger btn-eliminar-item" title="Eliminar"><i class="fa fa-trash"></i></button></td>';
            tbody.appendChild(tr);
            refrescarSaldosFila(tr);
        }

        // Inicial: pintar saldos cargados desde el servidor.
        tbody.querySelectorAll('tr.prestamo-item-row').forEach(function (tr) {
            const articuloId = parseInt((tr.querySelector('.select-articulo') || {}).value || '0', 10);
            if (articuloId) pintarSaldos(tr, articuloId);
        });
        actualizarBadges();

        // Bindings.
        tbody.addEventListener('change', onCambioFila);
        tbody.addEventListener('input', onCambioFila);
        tbody.addEventListener('click', onClickEliminar);
        document.getElementById('btn-agregar-item').addEventListener('click', agregarFila);
        selDepOrigen.addEventListener('change', function () {
            actualizarBadges();
            refrescarTodos();
        });
        selDepDestino.addEventListener('change', function () {
            actualizarBadges();
            refrescarTodos();
        });
    });
})();
