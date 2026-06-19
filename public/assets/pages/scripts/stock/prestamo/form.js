/**
 * Formulario de Préstamos: ítems con modal de artículos + SKU directo;
 * depósitos con modal compartido (campo_consulta_deposito).
 */
(function () {
    'use strict';

    function fmt(n) {
        if (n === null || n === undefined || Number.isNaN(Number(n))) return '—';
        const num = Number(n);
        if (Math.abs(num - Math.trunc(num)) < 1e-9) return String(Math.trunc(num));
        return num.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('tbody-prestamo-items');
        if (!tbody) return;

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
            // Silencioso.
        }

        const saldoUrl = (document.getElementById('prestamo-saldo-articulo-url') || {}).value || '';
        const inpDepOrigen = document.getElementById('prestamo_deposito_origen_id');
        const inpDepDestino = document.getElementById('prestamo_deposito_destino_id');
        const badgeOrigen = document.getElementById('badge-deposito-origen');
        const badgeDestino = document.getElementById('badge-deposito-destino');
        const template = document.getElementById('template-prestamo-item-row');

        function depositoOrigenId() {
            return inpDepOrigen ? parseInt(inpDepOrigen.value, 10) || 0 : 0;
        }

        function depositoDestinoId() {
            return inpDepDestino ? parseInt(inpDepDestino.value, 10) || 0 : 0;
        }

        function textoDeposito(inputId) {
            const desc = document.getElementById(inputId + '_descripcion');
            if (desc && desc.value.trim() !== '') {
                return desc.value.trim();
            }
            const cod = document.getElementById(inputId + '_codigo');
            if (cod && cod.value.trim() !== '') {
                return cod.value.trim();
            }
            return '—';
        }

        function actualizarBadges() {
            if (badgeOrigen) badgeOrigen.textContent = textoDeposito('prestamo_deposito_origen_id');
            if (badgeDestino) badgeDestino.textContent = textoDeposito('prestamo_deposito_destino_id');
        }

        function indexFila() {
            return tbody.querySelectorAll('tr.prestamo-item-row').length;
        }

        function renumerarFilas() {
            tbody.querySelectorAll('tr.prestamo-item-row').forEach(function (tr, idx) {
                tr.querySelectorAll('[name^="items["]').forEach(function (el) {
                    el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
                });
            });
        }

        function articuloIdFila(tr) {
            return parseInt((tr.querySelector('.articulo_id') || {}).value || '0', 10);
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
            const articuloId = articuloIdFila(tr);
            if (!articuloId) {
                tr.querySelector('.saldo-origen').textContent = '—';
                tr.querySelector('.saldo-destino').textContent = '—';
                return;
            }

            const depOId = depositoOrigenId();
            const depDId = depositoDestinoId();
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
                // silencioso
            }
            pintarSaldos(tr, articuloId);
        }

        function refrescarTodos() {
            Object.keys(cacheSaldos).forEach(function (k) { delete cacheSaldos[k]; });
            tbody.querySelectorAll('tr.prestamo-item-row').forEach(refrescarSaldosFila);
        }

        function aplicarArticuloEnFila(tr, art) {
            (tr.querySelector('.articulo_id') || {}).value = art.id || '';
            (tr.querySelector('.codigoarticulo') || {}).value = art.sku || '';
            (tr.querySelector('.descripcionarticulo') || {}).value = art.descripcion || '';
            if (typeof actualizarLinkEditarArticulo === 'function') {
                actualizarLinkEditarArticulo($(tr), art.id);
            }
            refrescarSaldosFila(tr);
        }

        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (!ctx || !ctx.row) return;
            const tr = ctx.row.jquery ? ctx.row[0] : ctx.row;
            if (!tr || !tr.closest('#tabla-prestamo-items')) return;
            aplicarArticuloEnFila(tr, {
                id: dataArticulo.id,
                sku: dataArticulo.sku,
                descripcion: dataArticulo.descripcion,
            });
        };

        function onCambioFila(ev) {
            const tr = ev.target.closest('tr.prestamo-item-row');
            if (!tr) return;
            if (ev.target.classList.contains('input-cantidad')) {
                pintarSaldos(tr, articuloIdFila(tr));
            }
        }

        function onClickEliminar(ev) {
            const btn = ev.target.closest('.btn-eliminar-item');
            if (!btn) return;
            const tr = btn.closest('tr.prestamo-item-row');
            if (!tr) return;
            const filas = tbody.querySelectorAll('tr.prestamo-item-row');
            if (filas.length <= 1) {
                tr.querySelectorAll('input').forEach(function (el) {
                    if (!el.classList.contains('descripcionarticulo')) {
                        el.value = '';
                    } else {
                        el.value = '';
                    }
                });
                refrescarSaldosFila(tr);
                return;
            }
            tr.remove();
            renumerarFilas();
        }

        function agregarFila() {
            if (!template) return;
            const idx = indexFila();
            const tr = template.content.firstElementChild.cloneNode(true);
            tr.querySelectorAll('[name^="items["]').forEach(function (el) {
                el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
            });
            tbody.appendChild(tr);
            refrescarSaldosFila(tr);
        }

        tbody.querySelectorAll('tr.prestamo-item-row').forEach(function (tr) {
            const articuloId = articuloIdFila(tr);
            if (articuloId) pintarSaldos(tr, articuloId);
        });
        actualizarBadges();

        tbody.addEventListener('change', onCambioFila);
        tbody.addEventListener('input', onCambioFila);
        tbody.addEventListener('click', onClickEliminar);
        document.getElementById('btn-agregar-item').addEventListener('click', agregarFila);

        [inpDepOrigen, inpDepDestino].forEach(function (inp) {
            if (!inp) return;
            inp.addEventListener('change', function () {
                actualizarBadges();
                refrescarTodos();
            });
        });

        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('click', '.eligeconsultadeposito', function () {
                setTimeout(function () {
                    actualizarBadges();
                    refrescarTodos();
                }, 150);
            });
            jQuery(document).on('change', '#prestamo_deposito_origen_id, #prestamo_deposito_destino_id', function () {
                actualizarBadges();
                refrescarTodos();
            });
        }

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
    });
})();
