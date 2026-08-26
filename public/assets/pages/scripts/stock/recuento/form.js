(function () {
    'use strict';

    function fmt(n) {
        if (n === null || n === undefined || Number.isNaN(Number(n))) return '—';
        const num = Number(n);
        if (Math.abs(num - Math.trunc(num)) < 1e-9) return String(Math.trunc(num));
        return num.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
    }

    function carpetaBase() {
        return typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('tbody-recuento-items');
        if (!tbody) return;

        const saldoUrl = (document.getElementById('recuento-saldo-articulo-url') || {}).value || '';
        const aleatorioUrl = (document.getElementById('recuento-aleatorio-url') || {}).value || '';
        const csrf = (document.getElementById('recuento-csrf') || {}).value || '';
        const selDeposito = document.getElementById('recuento_deposito_id')
            || document.querySelector('.tm-deposito-campo .deposito_id');
        const template = document.getElementById('template-recuento-item-row');

        function depositoId() {
            return selDeposito ? parseInt(selDeposito.value, 10) || 0 : 0;
        }

        function actualizarLinkArticulo(tr, articuloId) {
            const link = tr.querySelector('.btn-link-articulo') || tr.querySelector('a[href*="editar_articulo"]');
            if (!link) return;
            if (articuloId > 0) {
                const urlFn = typeof urlEditarArticuloConsulta === 'function'
                    ? urlEditarArticuloConsulta
                    : function (id) { return carpetaBase() + '/stock/articulo/' + id + '/editar?origen=modal_consulta&vista=consulta'; };
                link.href = urlFn(articuloId);
                link.classList.remove('d-none');
            } else {
                link.classList.add('d-none');
            }
        }

        function colorTalleFila(tr) {
            const colorSel = tr.querySelector('select.ms-color-id');
            const talleSel = tr.querySelector('select.ms-talle-id');
            return {
                colorId: parseInt((colorSel && colorSel.value) || 0, 10) || 0,
                talleId: parseInt((talleSel && talleSel.value) || 0, 10) || 0
            };
        }

        function claveVarianteFila(tr) {
            const aid = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
            const ct = colorTalleFila(tr);
            return aid + '|' + ct.colorId + '|' + ct.talleId;
        }

        function cargarSaldo(tr, articuloId) {
            const depId = depositoId();
            if (!articuloId || !depId || !saldoUrl) return;
            const ct = colorTalleFila(tr);
            let url = saldoUrl + '?articulo_id=' + articuloId + '&deposito_id=' + depId;
            if (ct.colorId > 0) url += '&color_id=' + ct.colorId;
            if (ct.talleId > 0) url += '&talle_id=' + ct.talleId;
            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) return;
                    const saldo = data.saldo;
                    const inp = tr.querySelector('.saldo_sistema_input');
                    const span = tr.querySelector('.saldo-deposito');
                    if (inp) inp.value = saldo;
                    if (span) span.textContent = fmt(saldo);
                    pintarDiferencia(tr);
                })
                .catch(function () {});
        }

        window.recuentoRefrescarSaldoFila = function (tr) {
            const aid = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
            if (aid) cargarSaldo(tr, aid);
        };

        function pintarDiferencia(tr) {
            const saldo = parseFloat((tr.querySelector('.saldo_sistema_input') || {}).value || 0);
            const contado = parseFloat((tr.querySelector('.input-cantidad-contada') || {}).value || 0);
            const td = tr.querySelector('.diferencia-linea');
            if (!td || Number.isNaN(saldo) || Number.isNaN(contado)) return;
            const dif = contado - saldo;
            td.textContent = fmt(dif);
            td.classList.toggle('text-danger', Math.abs(dif) > 1e-9);
        }

        function limpiarFilaArticulo(tr) {
            if (!tr) return;
            (tr.querySelector('.recuento_item_id') || {}).value = '';
            (tr.querySelector('.articulo_id') || {}).value = '';
            (tr.querySelector('.codigoarticulo') || {}).value = '';
            (tr.querySelector('.descripcionarticulo') || {}).value = '';
            (tr.querySelector('.unidadmedida_id') || {}).value = '';
            (tr.querySelector('.saldo_sistema_input') || {}).value = '';
            (tr.querySelector('.input-cantidad-contada') || {}).value = '';
            const um = tr.querySelector('.unidad-medida-label');
            if (um) um.textContent = '—';
            const spanSaldo = tr.querySelector('.saldo-deposito');
            if (spanSaldo) spanSaldo.textContent = '—';
            const td = tr.querySelector('.diferencia-linea');
            if (td) {
                td.textContent = '—';
                td.classList.remove('text-danger');
            }
            const colorSel = tr.querySelector('select.ms-color-id');
            const talleSel = tr.querySelector('select.ms-talle-id');
            if (colorSel) {
                colorSel.value = '';
                colorSel.setAttribute('data-selected', '');
            }
            if (talleSel) {
                talleSel.value = '';
                talleSel.setAttribute('data-selected', '');
            }
            tr.setAttribute('data-maneja-stock-color-talle', '0');
            actualizarLinkArticulo(tr, 0);
            if (typeof window.actualizarBotonMovimientosRecuentoFila === 'function') {
                window.actualizarBotonMovimientosRecuentoFila(tr);
            }
            if (typeof window.msRecalcularModoColorTalle === 'function') {
                window.msRecalcularModoColorTalle();
            }
        }

        function filaConVariante(articuloId, colorId, talleId, excluirTr) {
            const id = parseInt(articuloId, 10);
            if (!id) return null;
            const c = parseInt(colorId, 10) || 0;
            const t = parseInt(talleId, 10) || 0;
            const rows = tbody.querySelectorAll('tr.recuento-item-row');
            for (let i = 0; i < rows.length; i++) {
                const tr = rows[i];
                if (excluirTr && tr === excluirTr) continue;
                const aid = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
                if (aid !== id) continue;
                const ct = colorTalleFila(tr);
                if (ct.colorId === c && ct.talleId === t) return tr;
            }
            return null;
        }

        function rechazarVarianteDuplicada(tr, articuloId, colorId, talleId, etiqueta) {
            const existente = filaConVariante(articuloId, colorId, talleId, tr);
            if (!existente) return false;

            limpiarFilaArticulo(tr);
            avisarArticuloDuplicadoEnGrilla(existente, etiqueta || articuloId);
            return true;
        }

        function avisarArticuloDuplicadoEnGrilla(filaExistente, etiqueta) {
            const skuExistente = (filaExistente.querySelector('.codigoarticulo') || {}).value || '';
            const ref = etiqueta || skuExistente || '';
            const msg = 'La variante del artículo «' + ref + '» ya está cargada en otra línea del recuento. '
                + 'Cada combinación artículo/color/talle solo puede figurar una vez; modifique la cantidad contada en la línea existente.';

            if (typeof Biblioteca !== 'undefined' && typeof Biblioteca.notificaciones === 'function') {
                Biblioteca.notificaciones(msg, 'Recuento', 'warning');
            } else {
                alert(msg);
            }

            filaExistente.classList.add('recuento-linea-duplicada-aviso');
            setTimeout(function () {
                filaExistente.classList.remove('recuento-linea-duplicada-aviso');
            }, 2500);

            enfocarSkuFila(filaExistente);
            const cant = filaExistente.querySelector('.input-cantidad-contada');
            if (cant) {
                setTimeout(function () {
                    cant.focus();
                    if (typeof cant.select === 'function') cant.select();
                }, 120);
            }
        }

        function agregarFila(data) {
            if (!template) return null;
            if (data && data.articulo_id) {
                const cId = parseInt(data.color_id, 10) || 0;
                const tId = parseInt(data.talle_id, 10) || 0;
                const existente = filaConVariante(data.articulo_id, cId, tId, null);
                if (existente) {
                    avisarArticuloDuplicadoEnGrilla(existente, data.sku || data.descripcion || data.articulo_id);
                    return existente;
                }
            }
            const tr = template.content.firstElementChild.cloneNode(true);
            tbody.appendChild(tr);
            if (data) {
                (tr.querySelector('.recuento_item_id') || {}).value = data.recuento_item_id || '';
                (tr.querySelector('.articulo_id') || {}).value = data.articulo_id || '';
                (tr.querySelector('.codigoarticulo') || {}).value = data.sku || '';
                (tr.querySelector('.descripcionarticulo') || {}).value = data.detalle || data.descripcion || '';
                (tr.querySelector('.unidadmedida_id') || {}).value = data.unidadmedida_id || '';
                (tr.querySelector('.saldo_sistema_input') || {}).value = data.saldo_sistema ?? '';
                (tr.querySelector('.input-cantidad-contada') || {}).value = data.cantidad_contada ?? 0;
                const um = tr.querySelector('.unidad-medida-label');
                if (um) um.textContent = data.unidadmedida || '—';
                const spanSaldo = tr.querySelector('.saldo-deposito');
                if (spanSaldo) spanSaldo.textContent = fmt(data.saldo_sistema);
                const maneja = !!(data.maneja_stock_color_talle === true
                    || data.maneja_stock_color_talle === 1
                    || data.maneja_stock_color_talle === '1'
                    || (parseInt(data.color_id, 10) || 0) > 0
                    || (parseInt(data.talle_id, 10) || 0) > 0);
                tr.setAttribute('data-maneja-stock-color-talle', maneja ? '1' : '0');
                const colorSel = tr.querySelector('select.ms-color-id');
                const talleSel = tr.querySelector('select.ms-talle-id');
                const cId = parseInt(data.color_id, 10) || 0;
                const tId = parseInt(data.talle_id, 10) || 0;
                if (colorSel) colorSel.setAttribute('data-selected', cId > 0 ? String(cId) : '');
                if (talleSel) talleSel.setAttribute('data-selected', tId > 0 ? String(tId) : '');
                if (typeof window.msPoblarSelectsColorTalleFila === 'function' && typeof $ !== 'undefined') {
                    window.msPoblarSelectsColorTalleFila($(tr));
                }
                if (typeof window.msAplicarExclusividadColorTalle === 'function' && typeof $ !== 'undefined') {
                    window.msAplicarExclusividadColorTalle({ maneja_stock_color_talle: maneja }, $(tr));
                }
                actualizarLinkArticulo(tr, parseInt(data.articulo_id, 10) || 0);
            }
            pintarDiferencia(tr);
            if (typeof window.actualizarBotonMovimientosRecuentoFila === 'function') {
                window.actualizarBotonMovimientosRecuentoFila(tr);
            }
            if (typeof window.msRecalcularModoColorTalle === 'function') {
                window.msRecalcularModoColorTalle();
            }
            return tr;
        }

        function enfocarSkuFila(tr) {
            if (!tr) return;
            const sku = tr.querySelector('.codigoarticulo');
            if (!sku) return;
            setTimeout(function () {
                sku.focus();
                if (typeof sku.select === 'function') {
                    sku.select();
                }
            }, 0);
        }

        function enfocarPrimerSkuRecuento() {
            const tr = tbody.querySelector('tr.recuento-item-row');
            enfocarSkuFila(tr);
        }

        function empresaRecuentoDefinida() {
            const emp = document.getElementById('empresa_id');
            if (!emp) {
                return false;
            }
            return parseInt(String(emp.value || '').trim(), 10) > 0;
        }

        function enfocarDepositoRecuento() {
            const inp = document.getElementById('recuento_deposito_id_codigo')
                || document.querySelector('#tm_deposito_recuento .codigodeposito');
            if (!inp || inp.readOnly || inp.disabled) {
                return;
            }
            setTimeout(function () {
                inp.focus();
                if (typeof inp.select === 'function') {
                    inp.select();
                }
            }, 150);
        }

        function aplicarFocoInicialRecuento() {
            if (!empresaRecuentoDefinida()) {
                return;
            }
            if (depositoId() > 0) {
                enfocarPrimerSkuRecuento();
                return;
            }
            enfocarDepositoRecuento();
        }

        function validarSkuRecuento(input) {
            if (!input || !input.classList.contains('codigoarticulo')) {
                return;
            }
            if (!input.closest('#tabla-recuento-items')) {
                return;
            }
            if (typeof $ !== 'undefined') {
                $(input).trigger('change');
            } else {
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function agregarFilaNuevaConFocoSku() {
            const tr = agregarFila(null);
            enfocarSkuFila(tr);
            return tr;
        }

        tbody.querySelectorAll('tr.recuento-item-row').forEach(function (tr) {
            pintarDiferencia(tr);
        });

        document.getElementById('btn-agregar-item-recuento')?.addEventListener('click', function () {
            agregarFilaNuevaConFocoSku();
        });

        tbody.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.which !== 13) return;

            if (e.target.classList.contains('codigoarticulo')) {
                e.preventDefault();
                e.stopPropagation();
                validarSkuRecuento(e.target);
                return;
            }

            if (!e.target.classList.contains('input-cantidad-contada')) return;

            const tr = e.target.closest('tr');
            const articuloId = parseInt((tr && tr.querySelector('.articulo_id') || {}).value, 10) || 0;
            if (!articuloId) return;

            e.preventDefault();
            e.stopPropagation();
            agregarFilaNuevaConFocoSku();
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.closest('.btn-eliminar-item')) {
                const rows = tbody.querySelectorAll('tr.recuento-item-row');
                if (rows.length <= 1) return;
                e.target.closest('tr').remove();
            }
        });

        tbody.addEventListener('input', function (e) {
            if (e.target.classList.contains('input-cantidad-contada')) {
                pintarDiferencia(e.target.closest('tr'));
            }
        });

        if (selDeposito && typeof $ !== 'undefined') {
            $(selDeposito).on('change.recuentoDep', function () {
                tbody.querySelectorAll('tr.recuento-item-row').forEach(function (tr) {
                    const aid = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
                    if (aid) cargarSaldo(tr, aid);
                });
            });
        }

        window.enfocarPrimerSkuRecuento = enfocarPrimerSkuRecuento;
        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if (!document.getElementById('form-recuento') || !$ctx || !$ctx.length) {
                return;
            }
            if (!$ctx.closest('#form-recuento').length) {
                return;
            }
            var delay = $('#consultadepositoModal').hasClass('show') ? 280 : 80;
            setTimeout(enfocarPrimerSkuRecuento, delay);
        };

        function aplicarArticuloEnFila(tr, art) {
            (tr.querySelector('.articulo_id') || {}).value = art.id;
            (tr.querySelector('.codigoarticulo') || {}).value = art.sku || '';
            (tr.querySelector('.descripcionarticulo') || {}).value = art.descripcion || '';
            (tr.querySelector('.unidadmedida_id') || {}).value = art.unidadmedida_id || '';
            const um = tr.querySelector('.unidad-medida-label');
            if (um) um.textContent = art.unidadmedida_abreviatura || art.um || '—';
            actualizarLinkArticulo(tr, parseInt(art.id, 10));
            cargarSaldo(tr, parseInt(art.id, 10));
            if (typeof window.actualizarBotonMovimientosRecuentoFila === 'function') {
                window.actualizarBotonMovimientosRecuentoFila(tr);
            }
        }

        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (!ctx || !ctx.row) return;
            const tr = ctx.row.jquery ? ctx.row[0] : ctx.row;
            if (!tr || !tr.closest('#tabla-recuento-items')) return;
            const artId = parseInt(dataArticulo.id, 10) || 0;
            if (typeof window.msAplicarExclusividadColorTalle === 'function' && typeof $ !== 'undefined') {
                if (!window.msAplicarExclusividadColorTalle(dataArticulo, $(tr))) {
                    return;
                }
            }
            const ct = colorTalleFila(tr);
            if (artId && rechazarVarianteDuplicada(tr, artId, ct.colorId, ct.talleId, dataArticulo.sku || dataArticulo.descripcion)) {
                return;
            }
            aplicarArticuloEnFila(tr, {
                id: dataArticulo.id,
                sku: dataArticulo.sku,
                descripcion: dataArticulo.descripcion,
                unidadmedida_id: dataArticulo.unidadmedida_id,
                unidadmedida_abreviatura: dataArticulo.unidadesdemedidas ? dataArticulo.unidadesdemedidas.abreviatura : ''
            });
        };

        const btnAleatorio = document.getElementById('btn-recuento-aleatorio');
        if (btnAleatorio && aleatorioUrl) {
            btnAleatorio.addEventListener('click', function () {
                const depId = depositoId();
                const cantidad = parseInt(prompt('¿Cuántos artículos sortear al azar?', '10'), 10);
                if (!depId || !cantidad || cantidad <= 0) return;

                fetch(aleatorioUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ deposito_id: depId, cantidad: cantidad })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        resetearModoColorTalle();
                        tbody.innerHTML = '';
                        (data.lineas || []).forEach(function (ln) { agregarFila(ln); });
                        if (!data.lineas || !data.lineas.length) agregarFila(null);
                        if (typeof window.msRecalcularModoColorTalle === 'function') {
                            window.msRecalcularModoColorTalle();
                        }
                    })
                    .catch(function () { alert('No se pudo generar el recuento aleatorio.'); });
            });
        }

        function resetearModoColorTalle() {
            var modo = document.getElementById('modo_stock_color_talle');
            if (modo) modo.value = '';
        }

        function repoblarLineas(lineas) {
            resetearModoColorTalle();
            tbody.innerHTML = '';
            (lineas || []).forEach(function (ln) { agregarFila(ln); });
            if (!lineas || !lineas.length) agregarFila(null);
            if (typeof window.msRecalcularModoColorTalle === 'function') {
                window.msRecalcularModoColorTalle();
            }
        }

        const formImportExcel = document.getElementById('form-importar-recuento-excel');
        if (formImportExcel) {
            let previewTimer = null;
            let previewAbort = null;
            let ultimoPreview = null;
            let ultimasHojas = null;

            const errBox = document.getElementById('importar-recuento-excel-error');
            const btnSubmit = document.getElementById('btn-importar-recuento-excel-submit');
            const btnPreview = document.getElementById('btn-preview-recuento-excel');
            const inputArchivo = document.getElementById('importar-recuento-archivo');
            const previewUrl = formImportExcel.getAttribute('data-preview-url') || formImportExcel.action;
            const $panelPreview = $('#panel-preview-import-recuento');
            const $contenidoPreview = $('#preview-import-recuento-contenido');
            const $estadoPreview = $('#preview-import-recuento-estado');
            const $panelHoja = $('#panel-hoja-recuento-excel');
            const $selectHoja = $('#importar-recuento-hoja-select');
            const $hiddenHoja = $('#importar-recuento-hoja-indice');

            function escHtml(texto) {
                return $('<div/>').text(texto == null ? '' : String(texto)).html();
            }

            function mostrarErrorImport(msg) {
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.classList.remove('d-none');
                } else {
                    alert(msg);
                }
            }

            function limpiarErrorImport() {
                if (!errBox) return;
                errBox.classList.add('d-none');
                errBox.textContent = '';
            }

            function archivoSeleccionado() {
                return inputArchivo && inputArchivo.files && inputArchivo.files.length > 0;
            }

            function depositoImportId() {
                const fijo = document.getElementById('importar-recuento-deposito-id');
                if (fijo && parseInt(fijo.value, 10) > 0) {
                    return parseInt(fijo.value, 10);
                }
                return depositoId();
            }

            function badgeColumna(col) {
                if (!col) {
                    return '<span class="badge badge-danger">No encontrada</span>';
                }
                if (col.encontrada) {
                    return '<span class="badge badge-success">«' + escHtml(col.titulo) + '»</span>';
                }
                return '<span class="badge badge-danger">No encontrada' + (col.requerida ? ' (requerida)' : '') + '</span>';
            }

            function actualizarSelectorHojas(data) {
                const hojas = (data && data.hojas && data.hojas.length) ? data.hojas : ultimasHojas;
                if (!hojas || hojas.length <= 1) {
                    $panelHoja.addClass('d-none');
                    return;
                }
                ultimasHojas = hojas;
                const seleccionada = parseInt((data && data.hoja_seleccionada) || $hiddenHoja.val() || 1, 10);
                $selectHoja.empty();
                hojas.forEach(function (hoja) {
                    $selectHoja.append(
                        $('<option></option>').val(hoja.indice).text(hoja.indice + ' — ' + hoja.nombre)
                            .prop('selected', parseInt(hoja.indice, 10) === seleccionada)
                    );
                });
                $hiddenHoja.val(String(seleccionada));
                $('#importar-recuento-hoja-ayuda').text('Este archivo tiene ' + hojas.length + ' hojas.');
                $panelHoja.removeClass('d-none');
            }

            function renderPreview(data) {
                $panelPreview.show();
                actualizarSelectorHojas(data);
                ultimoPreview = data && data.resumen ? data : null;

                if (!data || (data.mensaje && !data.resumen)) {
                    $estadoPreview.removeClass().addClass('badge badge-danger').text('Error');
                    $contenidoPreview.html('<p class="text-danger small mb-0">' + escHtml(data && data.mensaje ? data.mensaje : 'No se pudo analizar el archivo.') + '</p>');
                    if (btnSubmit) btnSubmit.disabled = true;
                    return;
                }

                if (data.ok) {
                    $estadoPreview.removeClass().addClass('badge badge-success').text('Listo para cargar');
                } else {
                    $estadoPreview.removeClass().addClass('badge badge-warning').text('Revisar');
                }

                if (btnSubmit) {
                    btnSubmit.disabled = !(data.ok && data.lineas && data.lineas.length && depositoImportId());
                }

                let html = '';
                if (data.hoja_nombre) {
                    html += '<p class="small mb-2">Hoja: <strong>' + escHtml(data.hoja_seleccionada) + ' — ' + escHtml(data.hoja_nombre) + '</strong></p>';
                }
                html += '<p class="small mb-2">Encabezado detectado en fila <strong>' + escHtml(data.fila_encabezado) + '</strong>';
                if (data.fila_encabezado_automatica) {
                    html += ' (automático)';
                }
                html += '.</p>';

                if (data.columnas) {
                    html += '<div class="row small mb-2">';
                    html += '<div class="col-md-4"><strong>SKU</strong> (' + escHtml(data.columnas.sku.configurado) + '): ' + badgeColumna(data.columnas.sku) + '</div>';
                    html += '<div class="col-md-4"><strong>Cantidad</strong> (' + escHtml(data.columnas.cantidad.configurado) + '): ' + badgeColumna(data.columnas.cantidad) + '</div>';
                    html += '<div class="col-md-4"><strong>Detalle</strong> (' + escHtml(data.columnas.detalle.configurado) + '): ' + badgeColumna(data.columnas.detalle) + '</div>';
                    html += '<div class="col-md-4"><strong>Color</strong> (' + escHtml(data.columnas.color.configurado) + '): ' + badgeColumna(data.columnas.color) + '</div>';
                    html += '<div class="col-md-4"><strong>Talle</strong> (' + escHtml(data.columnas.talle.configurado) + '): ' + badgeColumna(data.columnas.talle) + '</div>';
                    html += '</div>';
                }

                if (data.advertencias && data.advertencias.length) {
                    html += '<div class="alert alert-warning py-2 small mb-2"><ul class="mb-0 pl-3">';
                    data.advertencias.forEach(function (msg) {
                        html += '<li>' + escHtml(msg) + '</li>';
                    });
                    html += '</ul></div>';
                }

                if (data.resumen) {
                    html += '<p class="small mb-2">';
                    html += 'Filas: <strong>' + data.resumen.total_filas_datos + '</strong> · ';
                    html += 'Importables: <strong class="text-success">' + data.resumen.importables + '</strong> · ';
                    html += 'Omitidas: <strong class="text-muted">' + data.resumen.omitidas + '</strong>';
                    html += '</p>';
                }

                if (data.filas && data.filas.length) {
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
                    html += '<thead style="background-color:#85C1E9;color:#17202A;"><tr>';
                    html += '<th>Fila</th><th>SKU</th><th>Artículo</th><th>Color</th><th>Talle</th><th class="text-right">Cantidad</th><th>Resultado</th>';
                    html += '</tr></thead><tbody>';
                    data.filas.forEach(function (fila) {
                        const cls = fila.estado === 'ok' ? 'table-success' : '';
                        html += '<tr class="' + cls + '">';
                        html += '<td>' + escHtml(fila.fila_excel) + '</td>';
                        html += '<td>' + escHtml(fila.sku) + '</td>';
                        html += '<td><small>' + escHtml(fila.articulo_descripcion || fila.detalle || '—') + '</small></td>';
                        html += '<td>' + escHtml(fila.color || '—') + '</td>';
                        html += '<td>' + escHtml(fila.talle || '—') + '</td>';
                        html += '<td class="text-right">' + escHtml(fila.cantidad_texto || fila.cantidad_contada || '') + '</td>';
                        html += '<td><small>' + escHtml(fila.mensaje) + '</small></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    if (data.hay_mas_filas) {
                        html += '<p class="text-muted small mt-2 mb-0">Mostrando las primeras ' + data.filas.length + ' filas de datos.</p>';
                    }
                }

                $contenidoPreview.html(html);
            }

            function solicitarPreview() {
                if (!archivoSeleccionado()) {
                    return;
                }
                if (previewAbort) {
                    previewAbort.abort();
                }
                previewAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

                limpiarErrorImport();
                $panelPreview.show();
                $estadoPreview.removeClass().addClass('badge badge-secondary').text('Analizando…');
                $contenidoPreview.html('<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Leyendo archivo…</p>');
                if (btnSubmit) btnSubmit.disabled = true;
                ultimoPreview = null;

                const formData = new FormData(formImportExcel);
                const depId = depositoImportId();
                if (depId) {
                    formData.set('deposito_id', String(depId));
                } else {
                    formData.delete('deposito_id');
                }

                const fetchOpts = {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                };
                if (previewAbort) {
                    fetchOpts.signal = previewAbort.signal;
                }

                fetch(previewUrl, fetchOpts)
                    .then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok) {
                                let msg = data.message || data.mensaje;
                                if (!msg && data.errors) {
                                    msg = Object.values(data.errors).flat().join(' ');
                                }
                                throw { message: msg || 'Error al analizar el archivo.' };
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        renderPreview(data);
                    })
                    .catch(function (err) {
                        if (err && err.name === 'AbortError') {
                            return;
                        }
                        const msg = (err && (err.message || err.mensaje)) || 'Error al analizar el archivo.';
                        renderPreview({ mensaje: msg });
                        mostrarErrorImport(msg);
                    })
                    .finally(function () {
                        previewAbort = null;
                    });
            }

            function programarPreview() {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(solicitarPreview, 450);
            }

            function aplicarPreviewAGrilla() {
                if (!ultimoPreview || !ultimoPreview.lineas || !ultimoPreview.lineas.length) {
                    mostrarErrorImport('No hay líneas importables. Revise la vista previa.');
                    return;
                }
                const depId = depositoImportId();
                if (!depId) {
                    mostrarErrorImport('Seleccione el depósito antes de cargar las líneas.');
                    return;
                }
                repoblarLineas(ultimoPreview.lineas);
                const tipoInput = document.getElementById('recuento-tipo');
                if (tipoInput) tipoInput.value = 'IMPORTADO';
                if (typeof $ !== 'undefined') {
                    $('#modal-importar-recuento-excel').modal('hide');
                }
                if (ultimoPreview.mensaje && typeof window.mostrarMensaje === 'function') {
                    window.mostrarMensaje(ultimoPreview.mensaje, 'success');
                } else if (ultimoPreview.mensaje) {
                    alert(ultimoPreview.mensaje);
                }
            }

            if (inputArchivo) {
                inputArchivo.addEventListener('change', function () {
                    const tiene = archivoSeleccionado();
                    if (btnPreview) btnPreview.disabled = !tiene;
                    if (tiene) {
                        $hiddenHoja.val(1);
                        ultimasHojas = null;
                        $panelHoja.removeClass('d-none');
                        $selectHoja.prop('disabled', true).html('<option value="">Detectando hojas…</option>');
                        programarPreview();
                    } else {
                        $panelPreview.hide();
                        $panelHoja.addClass('d-none');
                        ultimoPreview = null;
                        if (btnSubmit) btnSubmit.disabled = true;
                    }
                });
            }

            if (btnPreview) {
                btnPreview.addEventListener('click', function () {
                    solicitarPreview();
                });
            }

            $selectHoja.on('change', function () {
                $hiddenHoja.val($(this).val());
                if (archivoSeleccionado()) {
                    programarPreview();
                }
            });

            $(formImportExcel).on('change input', 'input[name="col_sku"], input[name="col_cantidad"], input[name="col_detalle"], input[name="col_color"], input[name="col_talle"], #importar-recuento-fila-encabezado', function () {
                if (archivoSeleccionado()) {
                    programarPreview();
                }
            });

            if (selDeposito) {
                selDeposito.addEventListener('change', function () {
                    if (archivoSeleccionado()) {
                        programarPreview();
                    }
                });
            }

            formImportExcel.addEventListener('submit', function (e) {
                e.preventDefault();
                aplicarPreviewAGrilla();
            });
        }

        function activarSolapa(num) {
            document.querySelectorAll('.form1, .form2, .form3').forEach(function (el) {
                el.style.display = 'none';
            });
            const panel = document.querySelector('.form' + num);
            if (panel) panel.style.display = '';

            document.querySelectorAll('#botonform1, #botonform2, #botonform3').forEach(function (b) {
                b.classList.remove('btn-primary');
                b.classList.add('btn-info');
            });
            const btn = document.getElementById('botonform' + num);
            if (btn) {
                btn.classList.remove('btn-info');
                btn.classList.add('btn-primary');
            }
        }

        function validarRecuentoAntesDeEnviar(form) {
            var resultado = typeof validarCamposObligatoriosFormulario === 'function'
                ? validarCamposObligatoriosFormulario(form)
                : { valido: true, primerInvalido: null, cantidadInvalidos: 0 };

            if (!depositoId()) {
                resultado.valido = false;
                resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                var depCod = document.getElementById('recuento_deposito_id_codigo')
                    || form.querySelector('.codigodeposito');
                if (depCod) {
                    if (typeof marcarCampoObligatorio === 'function') {
                        marcarCampoObligatorio(depCod, true);
                    }
                    resultado.primerInvalido = depCod;
                }
            }

            var filasConArticulo = 0;
            var clavesVistas = {};
            var filaDuplicada = null;
            var faltaColorTalle = null;
            var modoCt = String((document.getElementById('modo_stock_color_talle') || {}).value || '').trim() === '1';
            tbody.querySelectorAll('tr.recuento-item-row').forEach(function (tr) {
                var aid = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
                if (aid <= 0) return;
                filasConArticulo++;
                var ct = colorTalleFila(tr);
                if (modoCt && (ct.colorId <= 0 || ct.talleId <= 0)) {
                    resultado.valido = false;
                    faltaColorTalle = faltaColorTalle || tr;
                }
                var clave = claveVarianteFila(tr);
                if (clavesVistas[clave]) {
                    resultado.valido = false;
                    filaDuplicada = filaDuplicada || tr;
                    var skuInp = tr.querySelector('.codigoarticulo');
                    if (skuInp && typeof marcarCampoObligatorio === 'function') {
                        marcarCampoObligatorio(skuInp, true);
                    }
                    return;
                }
                clavesVistas[clave] = tr;
            });
            if (filasConArticulo === 0) {
                resultado.valido = false;
                resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                if (!resultado.primerInvalido) {
                    resultado.primerInvalido = tbody.querySelector('.codigoarticulo');
                }
            } else if (filaDuplicada) {
                resultado.valido = false;
                resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                resultado.articuloDuplicado = true;
                if (!resultado.primerInvalido) {
                    resultado.primerInvalido = filaDuplicada.querySelector('.codigoarticulo');
                }
            } else if (faltaColorTalle) {
                resultado.valido = false;
                resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                resultado.faltaColorTalle = true;
                if (!resultado.primerInvalido) {
                    resultado.primerInvalido = faltaColorTalle.querySelector('select.ms-color-id')
                        || faltaColorTalle.querySelector('.codigoarticulo');
                }
            }

            return resultado;
        }

        const formRecuento = document.getElementById('form-recuento');
        if (formRecuento) {
            formRecuento.setAttribute('novalidate', 'novalidate');
            formRecuento.addEventListener('submit', function (e) {
                var resultado = validarRecuentoAntesDeEnviar(formRecuento);
                if (resultado.valido) {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                if (typeof mostrarSolapaDelPrimerCampoInvalido === 'function') {
                    mostrarSolapaDelPrimerCampoInvalido(resultado.primerInvalido);
                } else {
                    activarSolapa(1);
                }
                var mensajeExtra = '';
                if (!depositoId()) {
                    mensajeExtra = ' Valide el dep\u00f3sito (c\u00f3digo + Enter o modal).';
                } else if (resultado.articuloDuplicado) {
                    mensajeExtra = ' Hay variantes repetidas en la grilla. Cada combinaci\u00f3n art\u00edculo/color/talle debe figurar una sola vez.';
                } else if (resultado.faltaColorTalle) {
                    mensajeExtra = ' Complete color y talle en todas las l\u00edneas de este recuento.';
                } else if (resultado.cantidadInvalidos === 1 && resultado.primerInvalido
                    && resultado.primerInvalido.classList.contains('codigoarticulo')) {
                    mensajeExtra = ' Agregue al menos un art\u00edculo.';
                }
                if (typeof notificarCamposObligatoriosPendientes === 'function') {
                    notificarCamposObligatoriosPendientes(resultado.primerInvalido, resultado.cantidadInvalidos);
                    if (mensajeExtra && typeof Biblioteca !== 'undefined') {
                        Biblioteca.notificaciones(mensajeExtra.trim(), 'Recuento', 'info');
                    }
                } else {
                    alert('Complete los campos obligatorios antes de guardar.' + mensajeExtra);
                }
                if (resultado.primerInvalido && resultado.primerInvalido.classList.contains('codigoarticulo')) {
                    enfocarSkuFila(resultado.primerInvalido.closest('tr'));
                } else if (resultado.primerInvalido && resultado.primerInvalido.classList.contains('codigodeposito')) {
                    enfocarDepositoRecuento();
                } else if (typeof enfocarCampoInvalido === 'function') {
                    enfocarCampoInvalido(resultado.primerInvalido);
                }
            });
        }

        document.getElementById('botonform1')?.addEventListener('click', function () { activarSolapa(1); });
        document.getElementById('botonform2')?.addEventListener('click', function () { activarSolapa(2); });
        document.getElementById('botonform3')?.addEventListener('click', function () { activarSolapa(3); });

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        const empEl = document.getElementById('empresa_id');
        if (empEl && empEl.tagName === 'SELECT') {
            empEl.addEventListener('change', function () {
                if (empresaRecuentoDefinida()) {
                    enfocarDepositoRecuento();
                }
            });
        }

        aplicarFocoInicialRecuento();
    });
})();
