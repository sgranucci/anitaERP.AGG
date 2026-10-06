(function () {
    'use strict';

    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function money(valor) {
        return Number(valor || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function reindexLineas() {
        var rows = document.querySelectorAll('#cdm-lineas-tbody .cdm-linea-row');
        rows.forEach(function (row, idx) {
            row.querySelectorAll('[name^="lineas["]').forEach(function (el) {
                el.name = el.name.replace(/lineas\[\d+]/, 'lineas[' + idx + ']');
                el.name = el.name.replace(/lineas\[__IDX__]/, 'lineas[' + idx + ']');
            });
        });
    }

    function poner(row, selector, valor) {
        var el = row.querySelector(selector);
        if (el) {
            el.value = valor == null ? '' : valor;
        }
    }

    function limpiarCampoVariante(row, cual) {
        poner(row, '.' + cual + '_id', '');
        poner(row, '.codigo' + cual, '');
        poner(row, '.descripcion' + cual, '');
    }

    function setCelda(row, selector, activa) {
        var td = row.querySelector(selector);
        if (!td) {
            return;
        }
        var box = td.querySelector('.cdm-var-box');
        var na = td.querySelector('.cdm-var-na');
        if (box) {
            box.classList.toggle('d-none', !activa);
        }
        if (na) {
            na.classList.toggle('d-none', activa);
        }
        td.querySelectorAll('input, button').forEach(function (el) {
            el.disabled = !activa;
        });
        if (!activa) {
            td.querySelectorAll('input').forEach(function (el) {
                el.value = '';
            });
        }
    }

    function aplicarModo(row, modo) {
        var combinacion = modo === 'combinacion';
        var color = modo === 'color_talle';
        var talle = combinacion || color;
        row.dataset.modo = modo || '';
        setCelda(row, '.cdm-celda-combinacion', combinacion);
        setCelda(row, '.cdm-celda-color', color);
        setCelda(row, '.cdm-celda-talle', talle);
    }

    function combinacionesDeFila(row) {
        try {
            var lista = JSON.parse((row && row.dataset.combinaciones) || '[]');
            return Array.isArray(lista) ? lista : [];
        } catch (e) {
            return [];
        }
    }

    function usarListaCombinacionFila(row) {
        window.cdmFilaCombinacion = row || null;
        window.cdmCombinacionesActivas = combinacionesDeFila(row);
    }

    function resolverCombinacionFila(row, avisar) {
        if (!row) {
            return;
        }
        var input = row.querySelector('.codigocombinacion');
        var idEl = row.querySelector('.combinacion_id');
        var descEl = row.querySelector('.descripcioncombinacion');
        var codigo = String(input && input.value || '').trim();
        if (codigo === '') {
            if (idEl) {
                idEl.value = '';
            }
            if (descEl) {
                descEl.value = '';
            }
            return;
        }
        if (row.dataset.variantesCargadas !== '1') {
            row.dataset.combinacionPendiente = avisar ? 'avisar' : '1';
            return;
        }
        var lista = combinacionesDeFila(row);
        var codigoLower = codigo.toLowerCase();
        var hallada = null;
        for (var i = 0; i < lista.length; i++) {
            if (String(lista[i].codigo || '').toLowerCase() === codigoLower
                || String(lista[i].id) === codigo) {
                hallada = lista[i];
                break;
            }
        }
        if (!hallada) {
            if (idEl) {
                idEl.value = '';
            }
            if (descEl) {
                descEl.value = '';
            }
            if (avisar && input) {
                var sku = (row.querySelector('.codigoarticulo') && row.querySelector('.codigoarticulo').value) || '';
                var opciones = lista.map(function (c) {
                    return (c.codigo || c.id) + ' ' + (c.nombre || '');
                }).join(', ');
                var msg = 'La combinación ' + codigo + ' no está en el artículo' + (sku ? ' ' + sku : '') + '.';
                msg += opciones
                    ? ' En local están: ' + opciones + '.'
                    : ' Ese artículo no tiene combinaciones activas en local.';
                window.setTimeout(function () {
                    alert(msg);
                    input.focus();
                }, 0);
            }
            return;
        }
        if (idEl) {
            idEl.value = hallada.id || '';
        }
        if (input) {
            input.value = hallada.codigo || codigo;
        }
        if (descEl) {
            descEl.value = hallada.nombre || '';
        }
        traerPrecioFila(row);
    }

    function traerPrecioFila(row) {
        if (!row || !window.cdmPrecioUrl || !window.cdmEditable) {
            return;
        }
        var tipo = row.querySelector('.cdm-tipo');
        if (!tipo || tipo.value !== 'reemplazo') {
            return;
        }
        var artEl = row.querySelector('.articulo_id');
        var artId = artEl ? String(artEl.value || '').trim() : '';
        if (artId === '') {
            return;
        }
        var localSel = document.getElementById('local_venta_id');
        var combEl = row.querySelector('.combinacion_id');
        var talleEl = row.querySelector('.talle_id');
        var token = String(Date.now()) + Math.random();
        row.dataset.precioToken = token;
        var qs = 'articulo_id=' + encodeURIComponent(artId)
            + '&combinacion_id=' + encodeURIComponent((combEl && combEl.value) || '0')
            + '&talle_id=' + encodeURIComponent((talleEl && talleEl.value) || '0')
            + '&local_id=' + encodeURIComponent((localSel && localSel.value) || '');
        fetch(window.cdmPrecioUrl + '?' + qs, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (json) {
            if (!row.isConnected || row.dataset.precioToken !== token || tipo.value !== 'reemplazo') {
                return;
            }
            poner(row, '.cdm-precio', json && json.precio != null ? json.precio : 0);
        }).catch(function () {});
    }

    function cargarVariantes(row) {
        var artEl = row.querySelector('.articulo_id');
        var artId = artEl ? String(artEl.value || '').trim() : '';
        row.dataset.variantesCargadas = '0';
        if (!artId || !window.cdmVariantesUrl) {
            row.dataset.combinaciones = '[]';
            row.dataset.variantesCargadas = '1';
            aplicarModo(row, 'sin_variante');
            return;
        }
        var urlVariantes = String(window.cdmVariantesUrl || '').replace('__ID__', encodeURIComponent(artId));
        fetch(urlVariantes, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (json) {
            var modo = (json && json.modo) || 'sin_variante';
            row.dataset.combinaciones = JSON.stringify((json && json.combinaciones) || []);
            row.dataset.variantesCargadas = '1';
            aplicarModo(row, modo);
            var pend = row.dataset.combinacionPendiente || '';
            row.dataset.combinacionPendiente = '';
            if (pend) {
                resolverCombinacionFila(row, pend === 'avisar');
            }
        }).catch(function () {
            row.dataset.combinaciones = '[]';
            row.dataset.variantesCargadas = '1';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.cdmEditable) {
            return;
        }

        var btnAdd = document.getElementById('cdm-agregar-linea');
        var tbody = document.getElementById('cdm-lineas-tbody');
        var tpl = document.getElementById('cdm-template-linea');

        function insertarFila(datos) {
            if (!tbody || !tpl) {
                return null;
            }
            var idx = tbody.querySelectorAll('.cdm-linea-row').length;
            var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
            tbody.insertAdjacentHTML('beforeend', html);
            var row = tbody.lastElementChild;
            if (!row) {
                return null;
            }
            datos = datos || {};
            var tipo = row.querySelector('.cdm-tipo');
            if (tipo && datos.tipo) {
                tipo.value = datos.tipo;
            }
            poner(row, '.articulo_id', datos.articulo_id || '');
            poner(row, '.codigoarticulo', datos.articulo_codigo || '');
            poner(row, '.descripcionarticulo', datos.descripcion || '');
            poner(row, '.cdm-cantidad', datos.cantidad != null ? datos.cantidad : 1);
            poner(row, '.cdm-precio', datos.precio_unitario != null ? datos.precio_unitario : 0);
            poner(row, 'input[name$="[venta_emision_id]"]', datos.venta_emision_id || '');
            poner(row, '.talle_id', datos.talle_id || '');
            poner(row, '.codigotalle', datos.talle_codigo || '');
            poner(row, '.descripciontalle', datos.talle_nombre || '');
            poner(row, '.color_id', datos.color_id || '');
            poner(row, '.codigocolor', datos.color_codigo || '');
            poner(row, '.descripcioncolor', datos.color_nombre || '');
            poner(row, '.combinacion_id', datos.combinacion_id || '');
            poner(row, '.codigocombinacion', datos.combinacion_codigo || '');
            poner(row, '.descripcioncombinacion', datos.combinacion_nombre || '');
            row.dataset.articuloPrevio = datos.articulo_id ? String(datos.articulo_id) : '';
            if (datos.articulo_id) {
                cargarVariantes(row);
            }
            return row;
        }

        if (btnAdd && tbody && tpl) {
            btnAdd.addEventListener('click', function () {
                insertarFila({ tipo: 'reemplazo', cantidad: 1, precio_unitario: 0 });
                reindexLineas();
            });
            tbody.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.cdm-quitar-linea');
                if (!btn) {
                    return;
                }
                var row = btn.closest('tr');
                if (row) {
                    row.remove();
                }
                reindexLineas();
            });
            tbody.addEventListener('keydown', function (ev) {
                var t = ev.target;
                if (!t || !t.closest) {
                    return;
                }
                var esF1 = ev.key === 'F1' || ev.code === 'F1' || ev.keyCode === 112;
                if (esF1 && t.classList.contains('codigoarticulo')) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var lupa = t.closest('.tm-articulo-campo');
                    var btn = lupa ? lupa.querySelector('.consultaarticulo') : null;
                    if (btn) {
                        btn.click();
                    }
                    return;
                }
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    if (t.classList.contains('codigoarticulo') && window.jQuery) {
                        window.jQuery(t).trigger('change');
                    }
                }
            });
        }

        document.querySelectorAll('#cdm-lineas-tbody .cdm-linea-row').forEach(function (row) {
            var art = row.querySelector('.articulo_id');
            if (art && art.value) {
                row.dataset.articuloPrevio = String(art.value);
                cargarVariantes(row);
            }
        });

        window.onArticuloSeleccionado = function (data, ctx) {
            if (!data || !data.id || !ctx || !ctx.row || !ctx.row.jquery) {
                return;
            }
            var campo = ctx.row.get(0);
            var row = campo && campo.closest ? campo.closest('tr') : null;
            if (!row || !row.closest || !row.closest('#cdm-lineas-table')) {
                return;
            }
            var previo = row.dataset.articuloPrevio || '';
            if (String(data.id) !== previo) {
                limpiarCampoVariante(row, 'talle');
                limpiarCampoVariante(row, 'color');
                limpiarCampoVariante(row, 'combinacion');
                row.dataset.articuloPrevio = String(data.id);
            }
            cargarVariantes(row);
            traerPrecioFila(row);
        };

        if (tbody) {
            tbody.addEventListener('change', function (ev) {
                var t = ev.target;
                if (t && t.classList && t.classList.contains('cdm-tipo')) {
                    traerPrecioFila(t.closest('tr'));
                }
            });
        }

        if (window.jQuery) {
            window.jQuery(document).on('talle:seleccionado.cdmPrecio', '#cdm-lineas-table .tm-talle-campo', function () {
                traerPrecioFila(this.closest('tr'));
            });
            window.jQuery(document).on('combinacion:seleccionada.cdmPrecio', '#cdm-lineas-table .tm-combinacion-campo', function () {
                traerPrecioFila(this.closest('tr'));
            });
        }

        function filaCombinacionDesde(el) {
            return el && el.closest ? el.closest('#cdm-lineas-table tr') : null;
        }

        document.addEventListener('click', function (ev) {
            var btn = ev.target && ev.target.closest ? ev.target.closest('.consultacombinacion') : null;
            if (!btn) {
                return;
            }
            var row = filaCombinacionDesde(btn);
            if (!row) {
                return;
            }
            usarListaCombinacionFila(row);
        }, true);

        document.addEventListener('focusin', function (ev) {
            var row = filaCombinacionDesde(ev.target);
            if (!row || !ev.target.classList || !ev.target.classList.contains('codigocombinacion')) {
                return;
            }
            usarListaCombinacionFila(row);
        }, true);

        document.addEventListener('keydown', function (ev) {
            var t = ev.target;
            if (!t || !t.classList || !t.classList.contains('codigocombinacion')) {
                return;
            }
            var row = filaCombinacionDesde(t);
            if (!row) {
                return;
            }
            usarListaCombinacionFila(row);
            if (ev.key !== 'Enter' && ev.which !== 13) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            ev.stopImmediatePropagation();
            resolverCombinacionFila(row, true);
        }, true);

        document.addEventListener('focusout', function (ev) {
            var t = ev.target;
            if (!t || !t.classList || !t.classList.contains('codigocombinacion')) {
                return;
            }
            var row = filaCombinacionDesde(t);
            if (!row) {
                return;
            }
            var modal = document.getElementById('consultacombinacionModal');
            if (modal && modal.classList.contains('show')) {
                return;
            }
            usarListaCombinacionFila(row);
            ev.stopImmediatePropagation();
            resolverCombinacionFila(row, false);
        }, true);

        window.payloadExtraConsultaCombinacion = function () {
            var row = window.cdmFilaCombinacion;
            if (row && row.isConnected) {
                return { lista: combinacionesDeFila(row) };
            }
            return { lista: window.cdmCombinacionesActivas || [] };
        };

        if (window.jQuery) {
            window.jQuery(function () {
                if (typeof window.activa_eventos_consultaarticulo === 'function') {
                    window.activa_eventos_consultaarticulo();
                }
                if (typeof window.activa_eventos_consultatalle === 'function') {
                    window.activa_eventos_consultatalle();
                }
                if (typeof window.activa_eventos_consultacolor === 'function') {
                    window.activa_eventos_consultacolor();
                }
                if (typeof window.activa_eventos_consultacombinacion === 'function') {
                    window.activa_eventos_consultacombinacion();
                }
                ['#consultaarticuloModal', '#consultatalleModal', '#consultacolorModal', '#consultacombinacionModal', '#cdm-modal-ventas'].forEach(function (sel) {
                    var modal = document.querySelector(sel);
                    if (modal && modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }
                });
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
                if (!btn) {
                    return;
                }
                var rows = tbodyArch.querySelectorAll('.item-archivo-cdm');
                var row = btn.closest('tr');
                if (!row) {
                    return;
                }
                if (rows.length <= 1) {
                    var input = row.querySelector('input[type=file]');
                    if (input) {
                        input.value = '';
                    }
                    return;
                }
                row.remove();
            });
        }

        document.querySelectorAll('.cdm-quitar-archivo-existente').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var wrap = btn.closest('.col-md-6') || btn.closest('.col-lg-4');
                if (wrap) {
                    var hidden = wrap.querySelector('.cdm-conservar-archivo');
                    if (hidden) {
                        hidden.remove();
                    }
                    wrap.remove();
                }
            });
        });

        var localSel = document.getElementById('local_venta_id');
        var empresaHidden = document.getElementById('empresa_id');
        if (localSel && empresaHidden) {
            localSel.addEventListener('change', function () {
                var opt = localSel.options[localSel.selectedIndex];
                empresaHidden.value = opt ? (opt.getAttribute('data-empresa') || '') : '';
                document.querySelectorAll('#cdm-lineas-tbody .cdm-linea-row').forEach(function (row) {
                    traerPrecioFila(row);
                });
            });
        }

        var btnBuscar = document.getElementById('btn-buscar-venta-original');
        var codigoInput = document.getElementById('venta_original_codigo');
        var idHidden = document.getElementById('venta_original_id');
        var hint = document.getElementById('venta_original_hint');
        var modalBody = document.getElementById('cdm-modal-ventas-body');

        function textoCampo(id, valor) {
            var el = document.getElementById(id);
            if (el) {
                el.value = valor || '';
            }
        }

        function aplicarLineas(lineas) {
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '';
            (lineas || []).forEach(function (linea) {
                insertarFila(linea);
            });
            insertarFila({ tipo: 'reemplazo', cantidad: 1, precio_unitario: 0 });
            reindexLineas();
        }

        function aplicarVenta(item) {
            if (!item) {
                return;
            }
            if (idHidden) {
                idHidden.value = item.id;
            }
            if (codigoInput) {
                codigoInput.value = item.codigo || String(item.id);
            }
            if (hint) {
                var partes = [];
                if (item.fecha) {
                    partes.push(item.fecha);
                }
                if (item.total != null) {
                    partes.push('Total $ ' + money(item.total));
                }
                if (item.cliente) {
                    partes.push(item.cliente);
                }
                if (item.pedido_numero) {
                    partes.push('Pedido ' + item.pedido_numero);
                }
                if (item.tienda_nombre) {
                    partes.push(item.tienda_nombre);
                }
                hint.textContent = partes.join(' — ');
            }
            textoCampo('tiendanube_pedido_id', item.tiendanube_pedido_id || '');
            textoCampo('pedido_numero', item.pedido_numero || '');
            textoCampo('pedido_numero_hidden', item.pedido_numero || '');
            textoCampo('tienda_nombre', item.tienda_nombre || '');
            textoCampo('tienda_nombre_hidden', item.tienda_nombre || '');

            var clienteId = document.getElementById('cliente_id');
            var receptor = document.getElementById('receptor_nombre');
            var doc = document.getElementById('receptor_documento');
            if (clienteId && item.cliente_id) {
                clienteId.value = item.cliente_id;
            }
            if (receptor && item.cliente) {
                receptor.value = item.cliente;
            }
            if (doc && item.documento) {
                doc.value = item.documento;
            }
            if (localSel && item.local_venta_id) {
                var optLocal = localSel.querySelector('option[value="' + item.local_venta_id + '"]');
                if (optLocal) {
                    localSel.value = String(item.local_venta_id);
                    localSel.dispatchEvent(new Event('change'));
                }
            }
            if (Array.isArray(item.lineas) && item.lineas.length) {
                aplicarLineas(item.lineas);
            }
        }

        function abrirModalVentas(list) {
            if (!modalBody || !window.jQuery) {
                return;
            }
            window.cdmVentasCoincidencias = list;
            modalBody.innerHTML = list.map(function (it, i) {
                return '<tr data-idx="' + i + '">'
                    + '<td>' + esc(it.codigo || it.id) + '</td>'
                    + '<td>' + esc(it.fecha || '') + '</td>'
                    + '<td>' + esc(it.cliente || '') + '</td>'
                    + '<td class="text-right">$ ' + esc(money(it.total)) + '</td>'
                    + '<td>' + esc(it.pedido_numero || '—') + '</td>'
                    + '<td>' + esc(it.tienda_nombre || '—') + '</td>'
                    + '<td><button type="button" class="btn btn-warning btn-sm cdm-elegir-venta">Elegir</button></td>'
                    + '</tr>';
            }).join('');
            window.jQuery('#cdm-modal-ventas').modal('show');
        }

        if (modalBody) {
            modalBody.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.cdm-elegir-venta');
                if (!btn) {
                    return;
                }
                var tr = btn.closest('tr');
                var idx = tr ? parseInt(tr.getAttribute('data-idx'), 10) : -1;
                var item = (window.cdmVentasCoincidencias || [])[idx];
                if (window.jQuery) {
                    window.jQuery('#cdm-modal-ventas').modal('hide');
                }
                aplicarVenta(item);
            });
        }
        var modalVentas = document.getElementById('cdm-modal-ventas');
        function elegirPrimeraVenta() {
            if (!modalVentas || !modalVentas.classList.contains('show')) {
                return;
            }
            var primero = modalVentas.querySelector('.cdm-elegir-venta');
            if (primero) {
                primero.click();
            }
        }
        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' || !modalVentas || !modalVentas.classList.contains('show')) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            elegirPrimeraVenta();
        });

        function buscarVenta() {
            var q = (codigoInput && codigoInput.value || '').trim();
            if (!window.cdmBuscarVentaUrl) {
                return;
            }
            if (q.length < 2) {
                alert('Ingresá al menos 2 caracteres del código de la factura.');
                return;
            }
            fetch(window.cdmBuscarVentaUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json();
            }).then(function (json) {
                var list = (json && json.data) || [];
                if (list.length === 0) {
                    alert('No se encontró la factura.');
                    return;
                }
                if (list.length === 1) {
                    aplicarVenta(list[0]);
                    return;
                }
                abrirModalVentas(list);
            }).catch(function () {
                alert('Error al buscar la factura.');
            });
        }

        if (btnBuscar) {
            btnBuscar.addEventListener('click', buscarVenta);
        }
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
