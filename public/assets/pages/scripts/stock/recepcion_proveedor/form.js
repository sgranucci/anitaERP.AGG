(function () {
    'use strict';

    var carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    var itemsActuales = [];
    var depositoCabeceraId = parseInt(window.recepcionProveedorDepositoCabeceraId, 10) || 0;
    var cargandoOc = false;
    var ultimoNumeroOcCargado = null;
    var COLSPAN_TABLA_ITEMS = 12;

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function depositoCabeceraActivo() {
        return parseInt($('#recepcion_deposito_id').val(), 10) || depositoCabeceraId || 0;
    }

    function coefEfectivo(item) {
        var coefProv = parseFloat(item.coeficiente_proveedor || item.coeficienteconversion || 1) || 1;
        var coefArt = parseFloat(item.coeficiente_articulo || 1) || 1;
        if (!depositoCabeceraActivo() && item.es_deposito_formula && coefArt > 0) {
            return coefArt;
        }
        return coefProv;
    }

    function depositoLinea(item) {
        var cab = depositoCabeceraActivo();
        if (cab > 0) {
            return cab;
        }
        return parseInt(item.depositoentrega_id || item.deposito_id, 10) || 0;
    }

    function depositoLineaTexto(item) {
        var cab = depositoCabeceraActivo();
        if (cab > 0) {
            var descCab = $('#recepcion_deposito_id_descripcion').val() || ('ID ' + cab);
            return escHtml(descCab);
        }
        if (item.deposito_nombre) {
            return escHtml(item.deposito_nombre);
        }
        var depId = depositoLinea(item);
        return depId > 0 ? ('ID ' + depId) : '—';
    }

    function $lineaPorIdx(idx) {
        return $('#tbody-items-recepcion tr.item-recepcion-linea[data-idx="' + idx + '"]');
    }

    function $comentarioPorIdx(idx) {
        return $('#tbody-items-recepcion tr.item-recepcion-comentario-precio[data-idx="' + idx + '"]');
    }

    function lineaTieneDiferenciaPrecio(precioOc, precioRec) {
        return Math.abs(parseFloat(precioRec || 0) - parseFloat(precioOc || 0)) >= 0.0001;
    }

    function badgesLinea(item) {
        var tipo = item.tipo_linea || 'OC';
        var html = '<span class="badge badge-secondary mr-1">' + escHtml(tipo) + '</span>';
        if (item.maneja_parte_unica) {
            html += '<span class="badge badge-info mr-1" title="Al confirmar se generará un NPU por unidad">NPU</span>';
        }
        if (!depositoCabeceraActivo() && item.es_deposito_formula) {
            var insumo = item.articulo_stock_sku ? (' → ' + escHtml(item.articulo_stock_sku)) : '';
            html += '<span class="badge badge-primary mr-1" title="Conversión a insumo vía SKU alternativo">Fórmula' + insumo + '</span>';
        }
        return html;
    }

    function htmlCeldaArticulo(item, idx) {
        var tipo = item.tipo_linea || 'OC';
        var esExtra = tipo === 'EXTRA';
        var editableArt = esExtra && !window.recepcionProveedorSoloLectura;
        var html = '<div class="celda-articulo-recepcion d-flex align-items-center flex-nowrap mb-0">';
        html += badgesLinea(item);
        html += '<input type="hidden" class="articulo_id" name="items[' + idx + '][articulo_id]" value="' + (item.articulo_id || '') + '">';
        if (editableArt) {
            html += '<button type="button" title="Consulta artículos" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0" style="padding:1;">';
            html += '<i class="fa fa-search text-primary"></i></button>';
        }
        html += '<input type="text" class="codigoarticulo form-control form-control-sm flex-shrink-0" value="' + escHtml(item.sku || '') + '" ';
        html += (editableArt ? '' : 'readonly ') + 'autocomplete="off" placeholder="SKU">';
        html += '</div>';
        return html;
    }

    function htmlFilaComentarioPrecio(item, idx, mostrar) {
        var comentario = escHtml(item.comentario_precio || '');
        var style = mostrar ? '' : ' style="display:none;"';
        var html = '<tr class="item-recepcion-comentario-precio" data-idx="' + idx + '"' + style + '>';
        html += '<td colspan="' + COLSPAN_TABLA_ITEMS + '" class="bg-transparent">';
        html += '<div class="d-flex align-items-center flex-wrap pl-4">';
        html += '<small class="text-muted mr-2 mb-1">Coment. precio (obligatorio si difiere):</small>';
        if (window.recepcionProveedorSoloLectura) {
            html += '<small class="text-body mb-1">' + (comentario || '—') + '</small>';
        } else {
            html += '<input type="text" class="form-control form-control-sm item-comentario-precio mb-1" name="items[' + idx + '][comentario_precio]" value="' + comentario + '" maxlength="255" placeholder="Motivo de la diferencia de precio">';
        }
        html += '</div></td></tr>';
        return html;
    }

    function actualizarComentarioPrecioFila(idx) {
        var $tr = $lineaPorIdx(idx);
        var $sub = $comentarioPorIdx(idx);
        if (!$tr.length || !$sub.length) {
            return;
        }
        var item = itemsActuales[idx];
        var precioOc = parseFloat($tr.find('[name*="[precio_ordencompra]"]').val()) || 0;
        var precioRec = parseFloat($tr.find('.item-precio').val()) || 0;
        var diff = lineaTieneDiferenciaPrecio(precioOc, precioRec);
        if (diff) {
            $sub.show();
            $tr.addClass('table-warning');
        } else {
            $sub.hide();
            if (!$sub.find('.item-comentario-precio').prop('readonly')) {
                $sub.find('.item-comentario-precio').val('');
            }
            if (item && item.tipo_linea !== 'EXTRA' && item.tipo_linea !== 'SUSTITUTO') {
                var cantDiff = item.cantidad_oc > 0 && Math.abs(parseFloat(item.cantidad || 0) - parseFloat(item.cantidad_oc)) >= 0.0001;
                if (!cantDiff) {
                    $tr.removeClass('table-warning');
                }
            }
        }
    }

    function recalcularLinea($tr, item) {
        var cant = parseFloat($tr.find('.item-cantidad').val()) || 0;
        var coef = coefEfectivo(item);
        $tr.find('[name*="[coeficienteconversion]"]').val(coef);
        $tr.find('.item-cant-stock').text((cant * coef).toFixed(6));
    }

    function recalcularTodasLasLineas() {
        itemsActuales.forEach(function (item, idx) {
            var $tr = $lineaPorIdx(idx);
            if ($tr.length) {
                recalcularLinea($tr, item);
                $tr.find('.item-deposito-texto').html(depositoLineaTexto(item));
                $tr.find('[name*="[deposito_id]"]').val(depositoLinea(item));
            }
        });
    }

    function monedaIdItem(item, idx) {
        var id = parseInt(item && item.moneda_id, 10);
        if (id > 0) {
            return id;
        }
        if (idx > 0 && itemsActuales[0]) {
            id = parseInt(itemsActuales[0].moneda_id, 10);
            if (id > 0) {
                return id;
            }
        }

        return 1;
    }

    function cotizacionItem(item, idx) {
        var cot = parseFloat(item && item.cotizacion);
        if (cot > 0) {
            return cot;
        }
        if (typeof idx === 'number' && idx > 0 && itemsActuales[0]) {
            cot = parseFloat(itemsActuales[0].cotizacion);
            if (cot > 0) {
                return cot;
            }
        }

        return 1;
    }

    function abreviaturaMoneda(monedaId) {
        var id = parseInt(monedaId, 10);
        var monedas = window.recepcionProveedorMonedas || [];
        for (var i = 0; i < monedas.length; i++) {
            if (parseInt(monedas[i].id, 10) === id) {
                return monedas[i].abreviatura || String(id);
            }
        }

        return String(id);
    }

    function htmlMonedaCotizacion(item, idx) {
        var monedaId = monedaIdItem(item, idx);
        var cot = cotizacionItem(item, idx);
        item.moneda_id = monedaId;
        item.cotizacion = cot;
        var soloLectura = window.recepcionProveedorSoloLectura;
        var html = '<div class="celda-moneda-cot-recepcion">';
        if (soloLectura) {
            html += '<span class="d-block text-nowrap">' + escHtml(abreviaturaMoneda(monedaId)) + '</span>';
            html += '<span class="d-block text-muted small">' + cot + '</span>';
            html += '<input type="hidden" name="items[' + idx + '][moneda_id]" value="' + monedaId + '">';
            html += '<input type="hidden" name="items[' + idx + '][cotizacion]" value="' + cot + '">';
        } else {
            html += '<select class="form-control form-control-sm item-moneda" name="items[' + idx + '][moneda_id]">';
            (window.recepcionProveedorMonedas || []).forEach(function (m) {
                var mid = parseInt(m.id, 10);
                html += '<option value="' + mid + '"' + (mid === monedaId ? ' selected' : '') + '>' + escHtml(m.abreviatura || mid) + '</option>';
            });
            html += '</select>';
            html += '<input type="number" step="0.000001" min="0.000001" class="form-control form-control-sm item-cotizacion" name="items[' + idx + '][cotizacion]" value="' + cot + '" title="Cotizaci&oacute;n">';
        }
        html += '</div>';

        return html;
    }

    function htmlCamposOcultosLinea(item, idx, depId, tipo) {
        item.moneda_id = monedaIdItem(item, idx);
        item.cotizacion = cotizacionItem(item, idx);
        var html = '';
        html += '<input type="hidden" name="items[' + idx + '][deposito_id]" value="' + depId + '">';
        html += '<input type="hidden" name="items[' + idx + '][tipo_linea]" value="' + escHtml(tipo) + '">';
        html += '<input type="hidden" name="items[' + idx + '][cantidad_oc]" value="' + (item.cantidad_oc != null ? item.cantidad_oc : '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][ordencompra_articulo_id]" value="' + (item.ordencompra_articulo_id || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][ordencompra_articulo_sustituido_id]" value="' + (item.ordencompra_articulo_sustituido_id || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][descuento]" value="' + (item.descuento || 0) + '">';
        html += '<input type="hidden" name="items[' + idx + '][centrocosto_id]" value="' + (item.centrocosto_id || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][penvp_orden]" value="' + (item.orden || idx + 1) + '">';
        if (item.ocr_codigo_proveedor) {
            html += '<input type="hidden" name="items[' + idx + '][ocr_codigo_proveedor]" value="' + escHtml(item.ocr_codigo_proveedor) + '">';
        }
        if (item.ocr_descripcion_proveedor) {
            html += '<input type="hidden" name="items[' + idx + '][ocr_descripcion_proveedor]" value="' + escHtml(item.ocr_descripcion_proveedor) + '">';
        }
        if (item.ocr_codigobarra) {
            html += '<input type="hidden" name="items[' + idx + '][ocr_codigobarra]" value="' + escHtml(item.ocr_codigobarra) + '">';
        }
        if (item.ocr_unidad_compra) {
            html += '<input type="hidden" name="items[' + idx + '][ocr_unidad_compra]" value="' + escHtml(item.ocr_unidad_compra) + '">';
        }

        return html;
    }

    function actualizarLinksArticuloGrilla() {
        if (typeof actualizarLinkEditarArticulo !== 'function') {
            return;
        }
        $('#tbody-items-recepcion tr.item-recepcion-linea').each(function () {
            var articuloId = parseInt($(this).find('.articulo_id').val(), 10) || 0;
            actualizarLinkEditarArticulo($(this), articuloId);
        });
    }

    function renderItems(items) {
        itemsActuales = items || [];
        var $tbody = $('#tbody-items-recepcion');
        $tbody.empty();
        if (!itemsActuales.length) {
            return;
        }
        itemsActuales.forEach(function (item, idx) {
            var precioOc = parseFloat(item.precio_ordencompra != null ? item.precio_ordencompra : item.precio || 0);
            var precioRec = parseFloat(item.precio || 0);
            var precioDiff = lineaTieneDiferenciaPrecio(precioOc, precioRec);
            var cantDiff = item.cantidad_oc > 0 && Math.abs(parseFloat(item.cantidad || 0) - parseFloat(item.cantidad_oc)) >= 0.0001;
            var tipo = item.tipo_linea || 'OC';
            var rowClass = (precioDiff || cantDiff || tipo === 'EXTRA' || tipo === 'SUSTITUTO') ? 'table-warning' : '';
            var extraClass = tipo === 'EXTRA' ? ' item-recepcion-extra' : '';
            var coef = coefEfectivo(item);
            var cantStock = (parseFloat(item.cantidad || 0) * coef).toFixed(6);
            var depId = depositoLinea(item);
            var soloLectura = window.recepcionProveedorSoloLectura;
            var html = '<tr class="item-recepcion-linea' + extraClass + ' ' + rowClass + '" data-idx="' + idx + '">';
            html += '<td class="align-middle">' + (idx + 1) + htmlCamposOcultosLinea(item, idx, depId, tipo) + '</td>';
            html += '<td class="align-middle">' + htmlCeldaArticulo(item, idx) + '</td>';
            html += '<td class="align-middle"><input type="text" class="descripcionarticulo form-control form-control-sm" value="' + escHtml(item.descripcion || '') + '" readonly title="' + escHtml(item.descripcion || '') + '"></td>';
            html += '<td class="text-right align-middle">' + (item.cantidad_oc != null ? item.cantidad_oc : '—') + '</td>';
            html += '<td class="align-middle"><input type="number" step="0.000001" min="0" class="form-control form-control-sm item-cantidad input-qty-recepcion" name="items[' + idx + '][cantidad]" value="' + (item.cantidad || 0) + '" ' + (soloLectura ? 'readonly' : '') + '></td>';
            html += '<td class="align-middle"><input type="number" step="0.000001" class="form-control form-control-sm text-right input-coef-recepcion" name="items[' + idx + '][coeficienteconversion]" value="' + coef + '" readonly tabindex="-1"></td>';
            html += '<td class="text-right align-middle"><span class="item-cant-stock">' + cantStock + '</span></td>';
            html += '<td class="align-middle"><input type="number" step="0.000001" class="form-control form-control-sm text-right" name="items[' + idx + '][precio_ordencompra]" value="' + (item.precio_ordencompra != null ? item.precio_ordencompra : '') + '" readonly tabindex="-1"></td>';
            html += '<td class="align-middle"><input type="number" step="0.000001" min="0" class="form-control form-control-sm text-right item-precio input-precio-recepcion" name="items[' + idx + '][precio]" value="' + (item.precio || 0) + '" ' + (soloLectura ? 'readonly' : '') + '></td>';
            html += '<td class="align-middle">' + htmlMonedaCotizacion(item, idx) + '</td>';
            html += '<td class="align-middle"><span class="item-deposito-texto">' + depositoLineaTexto(item) + '</span></td>';
            if (!soloLectura && tipo === 'EXTRA') {
                html += '<td class="align-middle text-center"><button type="button" class="btn btn-xs btn-danger btn-quitar-linea" data-idx="' + idx + '" title="Quitar línea"><i class="fa fa-trash"></i></button></td>';
            } else {
                html += '<td class="align-middle"></td>';
            }
            html += '</tr>';
            html += htmlFilaComentarioPrecio(item, idx, precioDiff || !!item.comentario_precio);
            $tbody.append(html);
        });
        actualizarLinksArticuloGrilla();
    }

    function cargarOc(mostrarAlertaSiVacio, opciones) {
        opciones = opciones || {};
        var numeroOc = parseInt($('#numero_oc_buscar').val(), 10);
        var ocId = parseInt(opciones.ordencompra_id || $('#ordencompra_id').val(), 10) || 0;
        var forzar = !!opciones.forzar;

        if (!ocId && !numeroOc) {
            if (mostrarAlertaSiVacio !== false) {
                alert('Ingrese número de OC o elíjala desde la búsqueda');
            }
            return;
        }
        if (cargandoOc) {
            return;
        }
        if (!forzar && ultimoNumeroOcCargado === numeroOc && $('#ordencompra_id').val()) {
            return;
        }
        cargandoOc = true;
        var params = ocId ? { ordencompra_id: ocId } : { numero_oc: numeroOc };
        $.getJSON(carpetaBase + '/stock/recepcion-proveedor/api/precarga-oc', params)
            .done(function (data) {
                ultimoNumeroOcCargado = data.numeroordencompra || numeroOc;
                $('#ordencompra_id').val(data.ordencompra_id);
                $('#numero_oc_buscar').val(data.numeroordencompra || numeroOc);
                $('#proveedor_id').val(data.proveedor_id || '');
                $('#proveedor_nombre').val(data.proveedor_nombre);
                if (data.empresa_id) {
                    $('#empresa_id').val(data.empresa_id);
                }
                renderItems(data.lineas);
            })
            .fail(function (xhr) {
                ultimoNumeroOcCargado = null;
                alert(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al cargar OC');
            })
            .always(function () {
                cargandoOc = false;
            });
    }

    window.recepcionProveedorCargarOc = cargarOc;

    function initCargaOcPorTeclado() {
        var $numeroOc = $('#numero_oc_buscar');
        if (!$numeroOc.length || $numeroOc.prop('readonly')) {
            return;
        }

        $numeroOc.on('keydown.recepprovOc', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                cargarOc(true);
                return;
            }
            if (e.key === 'Tab' || e.keyCode === 9) {
                if (!e.shiftKey && parseInt($numeroOc.val(), 10)) {
                    cargarOc(false);
                }
            }
        });
    }

    function sincronizarItemExtraDesdeDom($tr) {
        var idx = parseInt($tr.data('idx'), 10);
        var item = itemsActuales[idx];
        if (!item || item.tipo_linea !== 'EXTRA') {
            return;
        }
        item.articulo_id = parseInt($tr.find('.articulo_id').val(), 10) || null;
        item.sku = ($tr.find('.codigoarticulo').val() || '').trim();
        item.descripcion = $tr.find('.descripcionarticulo').val() || '';
        recalcularLinea($tr, item);
        $tr.find('[name*="[deposito_id]"]').val(depositoLinea(item));
        $tr.find('.item-deposito-texto').html(depositoLineaTexto(item));
    }

    function agregarLineaExtra() {
        if (!$('#ordencompra_id').val()) {
            alert('Cargue primero una orden de compra.');
            return;
        }
        itemsActuales.push({
            tipo_linea: 'EXTRA',
            articulo_id: null,
            sku: '',
            descripcion: '',
            cantidad: 1,
            cantidad_oc: null,
            precio: 0,
            precio_ordencompra: 0,
            coeficienteconversion: 1,
            coeficiente_proveedor: 1,
            coeficiente_articulo: 1,
            deposito_id: depositoCabeceraActivo() || null,
            depositoentrega_id: null,
            es_deposito_formula: false,
            moneda_id: monedaIdItem(itemsActuales[0] || {}, 0),
            cotizacion: cotizacionItem(itemsActuales[0] || {}, 0),
            comentario_precio: '',
            maneja_parte_unica: false,
        });
        renderItems(itemsActuales);
        window.setTimeout(function () {
            $('#tbody-items-recepcion tr.item-recepcion-extra').last().find('.codigoarticulo').trigger('focus');
        }, 0);
    }

    window.onArticuloSeleccionado = function (dataArticulo, ctx) {
        if (!dataArticulo || !ctx || !ctx.row) {
            return;
        }
        var $tr = $(ctx.row);
        if (!$tr.hasClass('item-recepcion-linea')) {
            return;
        }
        var idx = parseInt($tr.data('idx'), 10);
        var item = itemsActuales[idx];
        if (!item || item.tipo_linea !== 'EXTRA') {
            return;
        }
        item.articulo_id = dataArticulo.id;
        item.sku = dataArticulo.sku || '';
        item.descripcion = dataArticulo.descripcion || dataArticulo.nombre || '';
        item.depositoentrega_id = dataArticulo.depositoentrega_id || null;
        item.coeficiente_articulo = parseFloat(dataArticulo.coeficienteconversion || 1) || 1;
        item.maneja_parte_unica = String(dataArticulo.numeroparte || '0') === '1';
        if (!item.moneda_id || parseInt(item.moneda_id, 10) <= 0) {
            item.moneda_id = monedaIdItem(itemsActuales[0] || {}, 0);
        }
        item.cotizacion = cotizacionItem(item, idx);
        $tr.find('[name*="[articulo_id]"]').val(item.articulo_id);
        $tr.find('.item-moneda').val(item.moneda_id);
        $tr.find('.item-cotizacion').val(item.cotizacion);
        $tr.find('.codigoarticulo').val(item.sku);
        $tr.find('.descripcionarticulo').val(item.descripcion);
        recalcularLinea($tr, item);
        $tr.find('[name*="[deposito_id]"]').val(depositoLinea(item));
        $tr.find('.item-deposito-texto').html(depositoLineaTexto(item));
        actualizarComentarioPrecioFila(idx);
    };

    $(function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        window.onDepositoAplicadoEnFormulario = function () {
            depositoCabeceraId = depositoCabeceraActivo();
            renderItems(itemsActuales);
        };

        $('#recepcion_deposito_id').on('change', function () {
            depositoCabeceraId = depositoCabeceraActivo();
            renderItems(itemsActuales);
        });

        if (window.recepcionProveedorItemsInicial && window.recepcionProveedorItemsInicial.length) {
            renderItems(window.recepcionProveedorItemsInicial);
        }

        if (window.recepcionProveedorOrdencompraIdInicial) {
            $('#ordencompra_id').val(window.recepcionProveedorOrdencompraIdInicial);
        }
        if (window.recepcionProveedorNumeroOcInicial) {
            ultimoNumeroOcCargado = window.recepcionProveedorNumeroOcInicial;
        }

        initCargaOcPorTeclado();
        $('#btn-agregar-extra').on('click', agregarLineaExtra);

        $(document).on('click', '.btn-quitar-linea', function () {
            var idx = parseInt($(this).data('idx'), 10);
            itemsActuales.splice(idx, 1);
            renderItems(itemsActuales);
        });

        $(document).on('change.recepprovArtSku', '#tbody-items-recepcion tr.item-recepcion-extra .codigoarticulo', function () {
            sincronizarItemExtraDesdeDom($(this).closest('tr.item-recepcion-linea'));
        });

        $(document).on('keydown.recepprovArtEnter', '#tbody-items-recepcion tr.item-recepcion-extra .codigoarticulo', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $(this).trigger('change');
            }
        });

        $(document).on('input', '.item-cantidad, .item-precio', function () {
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (!item) {
                return;
            }
            recalcularLinea($tr, item);
            actualizarComentarioPrecioFila(idx);
        });

        $(document).on('change', '.item-moneda', function () {
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (item) {
                item.moneda_id = parseInt($(this).val(), 10) || 1;
            }
        });

        $(document).on('input change', '.item-cotizacion', function () {
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (!item) {
                return;
            }
            var cot = parseFloat($(this).val());
            item.cotizacion = cot > 0 ? cot : 1;
        });

        function comprimirImagenParaOcr(file) {
            return new Promise(function (resolve) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                    resolve(file);
                    return;
                }
                var maxAncho = 2400;
                var calidad = 0.88;
                var reader = new FileReader();
                reader.onload = function (ev) {
                    var img = new Image();
                    img.onload = function () {
                        var ancho = img.width;
                        var alto = img.height;
                        if (ancho > maxAncho) {
                            alto = Math.round(alto * (maxAncho / ancho));
                            ancho = maxAncho;
                        }
                        var canvas = document.createElement('canvas');
                        canvas.width = ancho;
                        canvas.height = alto;
                        var ctx = canvas.getContext('2d');
                        if (!ctx) {
                            resolve(file);
                            return;
                        }
                        ctx.drawImage(img, 0, 0, ancho, alto);
                        canvas.toBlob(function (blob) {
                            if (!blob) {
                                resolve(file);
                                return;
                            }
                            var nombre = (file.name || 'ocr.jpg').replace(/\.[^.]+$/, '') + '.jpg';
                            resolve(new File([blob], nombre, { type: 'image/jpeg', lastModified: Date.now() }));
                        }, 'image/jpeg', calidad);
                    };
                    img.onerror = function () {
                        resolve(file);
                    };
                    img.src = ev.target.result;
                };
                reader.onerror = function () {
                    resolve(file);
                };
                reader.readAsDataURL(file);
            });
        }

        function aplicarResultadoOcr(res) {
            if (res.numero_oc_detectado) {
                $('#numero_oc_buscar').val(res.numero_oc_detectado);
                ultimoNumeroOcCargado = res.numero_oc_detectado;
            } else if (res.numeroordencompra) {
                $('#numero_oc_buscar').val(res.numeroordencompra);
                ultimoNumeroOcCargado = res.numeroordencompra;
            }
            if (res.ordencompra_id) {
                $('#ordencompra_id').val(res.ordencompra_id);
            }
            if (res.proveedor_nombre) {
                $('#proveedor_nombre').val(res.proveedor_nombre);
            }
            if (res.proveedor_id) {
                $('#proveedor_id').val(res.proveedor_id);
            }
            if (res.empresa_id) {
                $('#empresa_id').val(res.empresa_id);
            }
            if (res.lineas && res.lineas.length) {
                renderItems(res.lineas);
            }
            var msg = 'OCR ' + (res.ocr_estado || 'OK');
            if (res.numero_oc_detectado) {
                msg += '\nOC detectada: ' + res.numero_oc_detectado;
            }
            if (res.ocr_lineas_detectadas) {
                msg += '\nLíneas detectadas en documento: ' + res.ocr_lineas_detectadas;
            }
            if (res.resumen) {
                msg += '\n' + res.resumen;
            }
            alert(msg);
        }

        function enviarArchivoOcr($input, file) {
            var fd = new FormData();
            fd.append('archivo', file);
            fd.append('_token', $('input[name=_token]').val());

            var url;
            if (window.recepcionProveedorId) {
                url = carpetaBase + '/stock/recepcion-proveedor/' + window.recepcionProveedorId + '/ocr';
            } else {
                url = carpetaBase + '/stock/recepcion-proveedor/ocr-preview';
                var ocId = parseInt($('#ordencompra_id').val(), 10) || 0;
                var numeroOc = parseInt($('#numero_oc_buscar').val(), 10) || 0;
                if (ocId) {
                    fd.append('ordencompra_id', ocId);
                }
                if (numeroOc) {
                    fd.append('numero_oc', numeroOc);
                }
            }

            $input.prop('disabled', true);
            $.ajax({
                url: url,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                timeout: 180000,
            }).done(function (res) {
                aplicarResultadoOcr(res);
            }).fail(function (xhr) {
                var err = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error OCR';
                if (xhr.status === 413) {
                    err = 'El archivo es demasiado grande para el servidor. Intente de nuevo; la imagen se comprime automáticamente.';
                }
                alert(err);
            }).always(function () {
                $input.prop('disabled', false).val('');
            });
        }

        $('#archivo_ocr').on('change', function () {
            if (!this.files || !this.files[0]) {
                return;
            }
            var $input = $(this);
            var fileOriginal = this.files[0];
            comprimirImagenParaOcr(fileOriginal).then(function (file) {
                if (file !== fileOriginal && file.size) {
                    console.log('OCR: imagen comprimida ' + Math.round(fileOriginal.size / 1024) + 'KB → ' + Math.round(file.size / 1024) + 'KB');
                }
                enviarArchivoOcr($input, file);
            });
        });

        var $numeroOc = $('#numero_oc_buscar');
        if ($numeroOc.length && !$numeroOc.prop('readonly')) {
            window.setTimeout(function () {
                $numeroOc.trigger('focus');
            }, 0);
        }
    });
})();
