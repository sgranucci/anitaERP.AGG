(function () {
    'use strict';

    var carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    var itemsActuales = [];
    var depositoCabeceraId = parseInt(window.recepcionProveedorDepositoCabeceraId, 10) || 0;
    var depositoCabeceraEmpresaId = parseInt(window.recepcionProveedorDepositoCabeceraEmpresaId, 10) || 0;
    var depositoCabeceraTipo = window.recepcionProveedorDepositoCabeceraTipo || '';
    var cargandoOc = false;
    var ultimoNumeroOcCargado = null;
    var COLSPAN_TABLA_ITEMS = 12;

    function umCompraLinea(item) {
        var um = String((item && (item.ocr_unidad_compra || item.um_compra)) || '').trim();
        return um || 'bulto';
    }

    function umStockLinea(item) {
        var um = String((item && item.um_stock) || '').trim();
        return um || 'UN';
    }

    function formatearCantidadStock(n) {
        var s = (parseFloat(n) || 0).toFixed(6);
        return s.replace(/\.?0+$/, '') || '0';
    }

    function htmlCeldaConversion(item, cant, coef) {
        coef = coef > 0 ? coef : 1;
        cant = parseFloat(cant) || 0;
        var umC = escHtml(umCompraLinea(item));
        var umS = escHtml(umStockLinea(item));
        var stock = formatearCantidadStock(cant * coef);
        var title = 'Cantidades en ' + umCompraLinea(item) + ' (remito). Al confirmar: stock = cantidad × '
            + coef + ' → ' + umStockLinea(item);
        var html = '<div class="celda-conversion-recepcion text-right" title="' + escHtml(title) + '">';
        html += '<span class="conv-compra d-block"><span class="conv-compra-um text-muted">' + umC + '</span> <strong class="conv-coef">×' + coef + '</strong></span>';
        html += '<span class="conv-stock d-block text-primary">→ <span class="conv-stock-um">' + umS + '</span> <span class="item-cant-stock">' + stock + '</span></span>';
        html += '</div>';
        return html;
    }

    function htmlInputCantidadConUm(claseInput, name, value, um, soloLectura) {
        var title = 'Unidad del remito: ' + um;
        var html = '<div class="input-group input-group-sm input-qty-um-recepcion" title="' + escHtml(title) + '">';
        html += '<input type="number" step="0.000001" min="0" class="form-control form-control-sm ' + claseInput + '" name="' + name + '" value="' + value + '" ' + (soloLectura ? 'readonly' : '') + '>';
        html += '<div class="input-group-append"><span class="input-group-text um-compra-suffix">' + escHtml(um) + '</span></div>';
        html += '</div>';
        return html;
    }

    function cantidadTotalRecibida(item) {
        return (parseFloat(item.cantidad || 0) || 0) + (parseFloat(item.cantidad_rechazada || 0) || 0);
    }

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function empresaIdRecepcion() {
        return parseInt($('#empresa_id').val(), 10) || 0;
    }

    function esTipoDepositoFormula(tipo) {
        var t = String(tipo || '').trim();
        return t === 'Formulas' || t === 'Fórmulas' || t === 'F' || t === '8';
    }

    function esDepositoCabeceraFormula() {
        if (!depositoCabeceraActivo()) {
            return false;
        }
        return esTipoDepositoFormula(depositoCabeceraTipo);
    }

    function lineaUsaConversionFormula(item) {
        return !!item.es_deposito_formula || (depositoCabeceraActivo() && esDepositoCabeceraFormula());
    }

    function aplicarMetadatosDepositoCabecera(data) {
        var $dep = $('#recepcion_deposito_id');
        if (!$dep.length) {
            return;
        }
        depositoCabeceraTipo = data && data.tipodeposito ? String(data.tipodeposito) : '';
        depositoCabeceraEmpresaId = parseInt(data && data.empresa_id, 10) || 0;
        $dep.data('tipodeposito', depositoCabeceraTipo);
        $dep.data('empresa-id', depositoCabeceraEmpresaId || '');
    }

    function limpiarDepositoCabeceraRecepcion() {
        var $ctx = $('#tm_deposito_entrada');
        if ($ctx.length && typeof limpiarCamposDepositoEnFormulario === 'function') {
            limpiarCamposDepositoEnFormulario($ctx);
        }
        depositoCabeceraId = 0;
        depositoCabeceraEmpresaId = 0;
        depositoCabeceraTipo = '';
        aplicarMetadatosDepositoCabecera(null);
    }

    function depositoCabeceraValidoParaEmpresa(empresaId) {
        var depId = depositoCabeceraActivo();
        if (!depId || !empresaId) {
            return true;
        }
        if (depositoCabeceraEmpresaId > 0) {
            return depositoCabeceraEmpresaId === empresaId;
        }
        return true;
    }

    function sincronizarEmpresaDesdeOc(data) {
        var empresaNueva = parseInt(data.empresa_id, 10) || 0;
        var empresaAnterior = empresaIdRecepcion();
        if (empresaNueva > 0) {
            window._omitirLimpiarDepositoAlCambiarEmpresa = true;
            $('#empresa_id').val(empresaNueva);
            window._omitirLimpiarDepositoAlCambiarEmpresa = false;
            window.recepcionProveedorEmpresaDesdeOc = true;
        } else {
            window.recepcionProveedorEmpresaDesdeOc = false;
        }
        if (empresaNueva > 0 && !depositoCabeceraValidoParaEmpresa(empresaNueva)) {
            limpiarDepositoCabeceraRecepcion();
        } else if (empresaNueva > 0 && empresaAnterior > 0 && empresaNueva !== empresaAnterior) {
            limpiarDepositoCabeceraRecepcion();
        }
    }

    function depositoCabeceraActivo() {
        return parseInt($('#recepcion_deposito_id').val(), 10) || depositoCabeceraId || 0;
    }

    function coefEfectivo(item) {
        var coefProv = parseFloat(item.coeficiente_proveedor || item.coeficienteconversion || 1) || 1;
        var coefArt = parseFloat(item.coeficiente_articulo || 1) || 1;
        if (lineaUsaConversionFormula(item) && coefArt > 0) {
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

    function $motivoRechazoPorIdx(idx) {
        return $('#tbody-items-recepcion tr.item-recepcion-motivo-rechazo[data-idx="' + idx + '"]');
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
        if (lineaUsaConversionFormula(item)) {
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
        html += '<small class="text-warning mr-2 mb-1 font-weight-bold"><i class="fa fa-exclamation-triangle"></i> Diferencia de precio — comentario obligatorio:</small>';
        if (window.recepcionProveedorSoloLectura) {
            html += '<small class="text-body mb-1">' + (comentario || '—') + '</small>';
        } else {
            html += '<input type="text" class="form-control form-control-sm item-comentario-precio mb-1" name="items[' + idx + '][comentario_precio]" value="' + comentario + '" maxlength="255" placeholder="Motivo de la diferencia de precio">';
        }
        html += '</div></td></tr>';
        return html;
    }

    function htmlFilaMotivoRechazo(item, idx, mostrar) {
        var motivo = escHtml(item.motivorechazo || '');
        var style = mostrar ? '' : ' style="display:none;"';
        var html = '<tr class="item-recepcion-motivo-rechazo" data-idx="' + idx + '"' + style + '>';
        html += '<td colspan="' + COLSPAN_TABLA_ITEMS + '" class="bg-transparent">';
        html += '<div class="d-flex align-items-center flex-wrap pl-4">';
        html += '<small class="text-muted mr-2 mb-1">Motivo rechazo (obligatorio si C.rech. &gt; 0):</small>';
        if (window.recepcionProveedorSoloLectura) {
            html += '<small class="text-body mb-1">' + (motivo || '—') + '</small>';
        } else {
            html += '<input type="text" class="form-control form-control-sm item-motivo-rechazo mb-1" name="items[' + idx + '][motivorechazo]" value="' + motivo + '" maxlength="255" placeholder="Motivo del rechazo">';
        }
        html += '</div></td></tr>';
        return html;
    }

    function actualizarMotivoRechazoFila(idx) {
        var $tr = $lineaPorIdx(idx);
        var $sub = $motivoRechazoPorIdx(idx);
        if (!$tr.length || !$sub.length) {
            return;
        }
        var rech = parseFloat($tr.find('.item-cant-rechazada').val()) || 0;
        if (rech > 0.000001) {
            $sub.show();
            $tr.addClass('table-danger');
        } else {
            $sub.hide();
            if (!$sub.find('.item-motivo-rechazo').prop('readonly')) {
                $sub.find('.item-motivo-rechazo').val('');
            }
            if (itemsActuales[idx]) {
                itemsActuales[idx].motivorechazo = '';
            }
            var item = itemsActuales[idx];
            var precioOc = precioOcItem(item);
            var precioRec = parseFloat(item.precio || 0);
            var cantDiff = item && item.cantidad_oc > 0
                && Math.abs(cantidadTotalRecibida(item) - parseFloat(item.cantidad_oc)) >= 0.0001;
            if (!lineaTieneDiferenciaPrecio(precioOc, precioRec) && !cantDiff
                && item && item.tipo_linea !== 'EXTRA' && item.tipo_linea !== 'SUSTITUTO') {
                $tr.removeClass('table-danger table-warning');
            } else if (lineaTieneDiferenciaPrecio(precioOc, precioRec) || cantDiff) {
                $tr.removeClass('table-danger').addClass('table-warning');
            }
        }
    }

    function actualizarComentarioPrecioFila(idx) {
        var $tr = $lineaPorIdx(idx);
        var $sub = $comentarioPorIdx(idx);
        if (!$tr.length || !$sub.length) {
            return;
        }
        var item = itemsActuales[idx];
        var precioOc = precioOcItem(item);
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
                var cantDiff = item.cantidad_oc > 0 && Math.abs(cantidadTotalRecibida(item) - parseFloat(item.cantidad_oc)) >= 0.0001;
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
        $tr.find('.conv-coef').text('×' + coef);
        $tr.find('.conv-compra-um').text(umCompraLinea(item));
        $tr.find('.conv-stock-um').text(umStockLinea(item));
        $tr.find('.item-cant-stock').text(formatearCantidadStock(cant * coef));
        $tr.find('.um-compra-suffix').text(umCompraLinea(item));
        $tr.find('.input-qty-um-recepcion').attr('title', 'Unidad del remito: ' + umCompraLinea(item));
        $tr.find('.celda-conversion-recepcion').attr('title',
            'Cantidades en ' + umCompraLinea(item) + ' (remito). Al confirmar: stock = cantidad × '
            + coef + ' → ' + umStockLinea(item));
        actualizarImporteLineaEnFila($tr, item);
    }

    window.recepcionProveedorRefrescarLinea = function (idx) {
        var item = itemsActuales[idx];
        var $tr = $lineaPorIdx(idx);
        if (item && $tr.length) {
            recalcularLinea($tr, item);
        }
    };

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

    var sincronizandoImporteLinea = false;
    var sincronizandoModalLineaPrecio = false;

    function precioOcItem(item) {
        if (!item) {
            return 0;
        }
        if (item.precio_ordencompra != null && item.precio_ordencompra !== '') {
            return parseFloat(item.precio_ordencompra) || 0;
        }

        return parseFloat(item.precio || 0) || 0;
    }

    function redondearImporteLinea(n) {
        return Math.round((parseFloat(n) || 0) * 100) / 100;
    }

    function redondearPrecioUnitario(n) {
        return Math.round((parseFloat(n) || 0) * 1000000) / 1000000;
    }

    function actualizarImporteLineaEnFila($tr, item) {
        if (!$tr || !$tr.length || !item) {
            return;
        }
        var importe = importeLineaRecepcion(item);
        sincronizandoImporteLinea = true;
        $tr.find('.item-importe-linea').val(importe.toFixed(2));
        $tr.find('.item-importe-linea-text').text(formatearImporteRecepcion(importe));
        sincronizandoImporteLinea = false;
    }

    function sincronizarPrecioDesdeImporte(item, importe, cantidad) {
        cantidad = parseFloat(cantidad) || 0;
        importe = redondearImporteLinea(importe);
        if (cantidad > 0.000001) {
            item.precio = redondearPrecioUnitario(importe / cantidad);
        } else {
            item.precio = 0;
        }

        return importe;
    }

    function aplicarPreciosLinea(idx, cantidad, precio, importeOpcional, comentarioPrecio) {
        var item = itemsActuales[idx];
        var $tr = $lineaPorIdx(idx);
        if (!item || !$tr.length) {
            return;
        }

        item.cantidad = parseFloat(cantidad) || 0;
        if (importeOpcional !== undefined && importeOpcional !== null && !isNaN(importeOpcional)) {
            sincronizarPrecioDesdeImporte(item, importeOpcional, item.cantidad);
        } else {
            item.precio = redondearPrecioUnitario(precio);
        }

        if (comentarioPrecio !== undefined) {
            item.comentario_precio = comentarioPrecio;
        }

        $tr.find('.item-cantidad').val(item.cantidad);
        $tr.find('.item-precio').val(item.precio);
        var $subComent = $comentarioPorIdx(idx);
        if ($subComent.length) {
            $subComent.find('.item-comentario-precio').val(item.comentario_precio || '');
        }
        actualizarImporteLineaEnFila($tr, item);
        recalcularLinea($tr, item);
        actualizarComentarioPrecioFila(idx);
        actualizarMotivoRechazoFila(idx);
        actualizarTotalRecepcion();
    }

    function htmlCeldaPrecioUnitario(item, idx, soloLectura) {
        var precio = parseFloat(item.precio || 0) || 0;
        if (soloLectura) {
            return '<span class="text-right d-block item-precio-text">' + formatearImporteRecepcion(precio) + '</span>'
                + '<input type="hidden" class="item-precio" name="items[' + idx + '][precio]" value="' + precio + '">';
        }

        return '<input type="number" step="0.000001" min="0" class="form-control form-control-sm text-right item-precio input-precio-recepcion" name="items[' + idx + '][precio]" value="' + precio + '">';
    }

    function htmlCeldaImporteLinea(item, idx, soloLectura) {
        var importe = importeLineaRecepcion(item);
        var html = '<div class="celda-importe-linea">';
        if (soloLectura) {
            html += '<div class="d-flex align-items-center justify-content-end">';
            html += '<span class="item-importe-linea-text font-weight-bold mr-1">' + formatearImporteRecepcion(importe) + '</span>';
            html += '<button type="button" class="btn btn-sm btn-info btn-linea-precio-modal" data-idx="' + idx + '" title="Ver detalle de precios">';
            html += '<span class="fa fa-calculator"></span></button>';
            html += '</div>';
        } else {
            html += '<div class="input-group input-group-sm input-importe-grupo-recepcion">';
            html += '<input type="number" step="0.01" min="0" class="form-control text-right item-importe-linea input-importe-linea-recepcion font-weight-bold" value="' + importe.toFixed(2) + '" title="Total línea (doble clic para detalle)">';
            html += '<div class="input-group-append">';
            html += '<button type="button" class="btn btn-info btn-linea-precio-modal" data-idx="' + idx + '" title="Detalle de precios">';
            html += '<span class="fa fa-calculator"></span></button>';
            html += '</div></div>';
        }
        html += '</div>';

        return html;
    }

    function actualizarAvisoDiffModalLineaPrecio(precioOc, precioRec) {
        var $aviso = $('#modal-linea-precio-diff-aviso');
        var $wrapComent = $('#modal-linea-precio-comentario-wrap');
        var soloLectura = !!window.recepcionProveedorSoloLectura;
        var diff = lineaTieneDiferenciaPrecio(precioOc, precioRec);
        if ($aviso.length) {
            if (diff) {
                $aviso.removeClass('d-none').text('El precio de recepción difiere del precio de la OC. Indique el motivo antes de aplicar.');
            } else {
                $aviso.addClass('d-none').text('');
            }
        }
        if ($wrapComent.length) {
            if (diff && !soloLectura) {
                $wrapComent.removeClass('d-none');
            } else {
                $wrapComent.addClass('d-none');
                if (!diff && !soloLectura) {
                    $('#modal-linea-comentario-precio').val('');
                }
            }
        }
    }

    function abrirModalLineaPrecio(idx) {
        var item = itemsActuales[idx];
        if (!item || !$('#modalRecepcionLineaPrecio').length) {
            return;
        }

        var soloLectura = !!window.recepcionProveedorSoloLectura;
        var precioOc = precioOcItem(item);
        $('#modal-linea-precio-idx').val(idx);
        $('#modalRecepcionLineaPrecioTitulo').text('Precios de la línea ' + (idx + 1));
        $('#modal-linea-precio-subtitulo').text(
            (item.sku || '') + (item.descripcion ? ' — ' + item.descripcion : '')
        );
        $('#modalRecepcionLineaPrecio').data('precio-oc', precioOc);
        $('#modal-linea-precio-oc').val(formatearImporteRecepcion(precioOc));
        $('#modal-linea-um-compra').text(umCompraLinea(item));
        $('#modal-linea-cantidad').val(parseFloat(item.cantidad || 0) || 0).prop('readonly', soloLectura);
        $('#modal-linea-precio-unit').val(parseFloat(item.precio || 0) || 0).prop('readonly', soloLectura);
        $('#modal-linea-importe').val(importeLineaRecepcion(item).toFixed(2)).prop('readonly', soloLectura);
        $('#modal-linea-comentario-precio').val(item.comentario_precio || '').prop('readonly', soloLectura);
        $('#btn-modal-linea-precio-aplicar').toggle(!soloLectura);
        actualizarAvisoDiffModalLineaPrecio(precioOc, parseFloat(item.precio || 0) || 0);
        $('#modalRecepcionLineaPrecio').modal('show');
    }

    function aplicarModalLineaPrecio() {
        if (window.recepcionProveedorSoloLectura) {
            return;
        }
        var idx = parseInt($('#modal-linea-precio-idx').val(), 10);
        if (isNaN(idx)) {
            return;
        }
        var cant = parseFloat($('#modal-linea-cantidad').val()) || 0;
        var precio = parseFloat($('#modal-linea-precio-unit').val()) || 0;
        var importe = parseFloat($('#modal-linea-importe').val());
        var precioOc = parseFloat($('#modalRecepcionLineaPrecio').data('precio-oc')) || 0;
        var comentario = $.trim($('#modal-linea-comentario-precio').val() || '');
        if (lineaTieneDiferenciaPrecio(precioOc, precio) && comentario === '') {
            alert('Indique el motivo de la diferencia de precio respecto a la OC.');
            $('#modal-linea-comentario-precio').trigger('focus');
            return;
        }
        aplicarPreciosLinea(idx, cant, precio, importe, comentario);
        $('#modalRecepcionLineaPrecio').modal('hide');
    }

    function importeLineaRecepcion(item) {
        var cant = parseFloat(item.cantidad || 0) || 0;
        var precio = parseFloat(item.precio || 0) || 0;

        return Math.round(cant * precio * 100) / 100;
    }

    function formatearImporteRecepcion(n) {
        return (parseFloat(n) || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function actualizarTotalRecepcion() {
        var $total = $('#recepcion-total-recepcion');
        if (!$total.length) {
            return;
        }

        var totalesPorMoneda = {};
        (itemsActuales || []).forEach(function (item) {
            var importe = importeLineaRecepcion(item);
            if (Math.abs(importe) < 0.000001) {
                return;
            }
            var monedaId = parseInt(item.moneda_id, 10) || monedaIdItem(item, 0);
            totalesPorMoneda[monedaId] = (totalesPorMoneda[monedaId] || 0) + importe;
        });

        var claves = Object.keys(totalesPorMoneda);
        if (!claves.length) {
            $total.text('—');
            return;
        }

        var partes = claves.map(function (monedaId) {
            var total = Math.round(totalesPorMoneda[monedaId] * 100) / 100;
            return abreviaturaMoneda(monedaId) + ' ' + formatearImporteRecepcion(total);
        });

        $total.text(partes.join(' · '));
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
        html += '<input type="hidden" name="items[' + idx + '][penvp_orden]" value="' + (item.penvp_orden || item.orden || idx + 1) + '">';
        html += '<input type="hidden" name="items[' + idx + '][penvp_nro_interno]" value="' + (item.penvp_nro_interno || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][ocr_codigo_proveedor]" value="' + escHtml(item.ocr_codigo_proveedor || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][ocr_descripcion_proveedor]" value="' + escHtml(item.ocr_descripcion_proveedor || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][ocr_codigobarra]" value="' + escHtml(item.ocr_codigobarra || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][ocr_unidad_compra]" value="' + escHtml(item.ocr_unidad_compra || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][unidadmedida_id]" value="' + (parseInt(item.unidadmedida_id, 10) || '') + '">';
        html += '<input type="hidden" name="items[' + idx + '][coeficienteconversion]" value="' + coefEfectivo(item) + '">';
        html += '<input type="hidden" name="items[' + idx + '][precio_ordencompra]" value="' + (item.precio_ordencompra != null ? item.precio_ordencompra : '') + '">';

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
        window.itemsActualesRecepcion = itemsActuales;
        var $tbody = $('#tbody-items-recepcion');
        $tbody.empty();
        if (!itemsActuales.length) {
            actualizarTotalRecepcion();
            return;
        }
        itemsActuales.forEach(function (item, idx) {
            var precioOc = parseFloat(item.precio_ordencompra != null ? item.precio_ordencompra : item.precio || 0);
            var precioRec = parseFloat(item.precio || 0);
            var precioDiff = lineaTieneDiferenciaPrecio(precioOc, precioRec);
            var cantDiff = item.cantidad_oc > 0 && Math.abs(cantidadTotalRecibida(item) - parseFloat(item.cantidad_oc)) >= 0.0001;
            var cantRech = parseFloat(item.cantidad_rechazada || 0) || 0;
            var tipo = item.tipo_linea || 'OC';
            var rowClass = (precioDiff || cantDiff || tipo === 'EXTRA' || tipo === 'SUSTITUTO') ? 'table-warning' : '';
            if (cantRech > 0.000001) {
                rowClass = 'table-danger';
            }
            var extraClass = tipo === 'EXTRA' ? ' item-recepcion-extra' : '';
            var coef = coefEfectivo(item);
            var umCompra = umCompraLinea(item);
            var depId = depositoLinea(item);
            var soloLectura = window.recepcionProveedorSoloLectura;
            var html = '<tr class="item-recepcion-linea' + extraClass + ' ' + rowClass + '" data-idx="' + idx + '">';
            html += '<td class="align-middle">' + (idx + 1) + htmlCamposOcultosLinea(item, idx, depId, tipo) + '</td>';
            html += '<td class="align-middle">' + htmlCeldaArticulo(item, idx) + '</td>';
            html += '<td class="align-middle"><input type="text" class="descripcionarticulo form-control form-control-sm" value="' + escHtml(item.descripcion || '') + '" readonly title="' + escHtml(item.descripcion || '') + '"></td>';
            html += '<td class="text-right align-middle">' + (item.cantidad_oc != null ? item.cantidad_oc : '—') + '</td>';
            html += '<td class="align-middle">' + htmlInputCantidadConUm('item-cantidad input-qty-recepcion', 'items[' + idx + '][cantidad]', (item.cantidad || 0), umCompra, soloLectura) + '</td>';
            html += '<td class="align-middle">' + htmlInputCantidadConUm('item-cant-rechazada input-qty-rech-recepcion', 'items[' + idx + '][cantidad_rechazada]', (item.cantidad_rechazada || 0), umCompra, soloLectura) + '</td>';
            html += '<td class="align-middle">' + htmlCeldaConversion(item, item.cantidad || 0, coef) + '</td>';
            html += '<td class="align-middle">' + htmlCeldaPrecioUnitario(item, idx, soloLectura) + '</td>';
            html += '<td class="align-middle">' + htmlCeldaImporteLinea(item, idx, soloLectura) + '</td>';
            html += '<td class="align-middle">' + htmlMonedaCotizacion(item, idx) + '</td>';
            html += '<td class="align-middle"><span class="item-deposito-texto">' + depositoLineaTexto(item) + '</span></td>';
            if (!soloLectura && tipo === 'EXTRA') {
                html += '<td class="align-middle text-center"><button type="button" class="btn btn-xs btn-danger btn-quitar-linea" data-idx="' + idx + '" title="Quitar línea"><i class="fa fa-trash"></i></button></td>';
            } else {
                html += '<td class="align-middle"></td>';
            }
            html += '</tr>';
            html += htmlFilaComentarioPrecio(item, idx, precioDiff || !!item.comentario_precio);
            html += htmlFilaMotivoRechazo(item, idx, cantRech > 0.000001 || !!item.motivorechazo);
            $tbody.append(html);
        });
        actualizarLinksArticuloGrilla();
        actualizarTotalRecepcion();
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
                sincronizarEmpresaDesdeOc(data);
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

    function enfocarNumeroOcRecepcion(seleccionar) {
        var $numeroOc = $('#numero_oc_buscar');
        if (!$numeroOc.length || $numeroOc.prop('readonly') || window.recepcionProveedorSoloLectura) {
            return false;
        }
        var el = $numeroOc[0];
        if (!el || typeof el.focus !== 'function') {
            return false;
        }
        el.focus();
        if (seleccionar && typeof el.select === 'function') {
            el.select();
        }
        return true;
    }

    function debeEnfocarNumeroOcAlInicio(paramsUrl) {
        if (window.recepcionProveedorSoloLectura) {
            return false;
        }
        if (paramsUrl.get('solapa')) {
            return false;
        }
        if (paramsUrl.get('enfocar_oc') === '1') {
            return true;
        }

        return !window.recepcionProveedorId;
    }

    function programarFocoInicialNumeroOc(paramsUrl) {
        if (!debeEnfocarNumeroOcAlInicio(paramsUrl)) {
            return;
        }
        var seleccionar = paramsUrl.get('enfocar_oc') === '1';
        var intentar = function () {
            enfocarNumeroOcRecepcion(seleccionar);
        };
        window.setTimeout(intentar, 150);
        $(window).one('load.recepProvFocoOc', intentar);
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
            cantidad_rechazada: 0,
            motivorechazo: '',
            cantidad_oc: null,
            precio: 0,
            precio_ordencompra: 0,
            coeficienteconversion: 1,
            coeficiente_proveedor: 1,
            coeficiente_articulo: 1,
            um_compra: 'bulto',
            um_stock: 'UN',
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
        if (dataArticulo.unidadesdemedidas) {
            item.um_stock = dataArticulo.unidadesdemedidas.abreviatura
                || dataArticulo.unidadesdemedidas.nombre
                || item.um_stock
                || 'UN';
        }
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

        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if ($ctx && $ctx.attr('id') === 'tm_deposito_entrada') {
                var ocEmpresa = empresaIdRecepcion();
                var depEmpresa = parseInt(data && data.empresa_id, 10) || 0;
                if (window.recepcionProveedorEmpresaDesdeOc && ocEmpresa > 0 && depEmpresa > 0 && depEmpresa !== ocEmpresa) {
                    alert('El depósito debe pertenecer a la empresa de la orden de compra.');
                    limpiarDepositoCabeceraRecepcion();
                    return;
                }
                aplicarMetadatosDepositoCabecera(data || null);
            }
            depositoCabeceraId = depositoCabeceraActivo();
            renderItems(itemsActuales);
        };

        $('#recepcion_deposito_id').on('change', function () {
            depositoCabeceraId = depositoCabeceraActivo();
            if (!depositoCabeceraId) {
                depositoCabeceraEmpresaId = 0;
                depositoCabeceraTipo = '';
                aplicarMetadatosDepositoCabecera(null);
            }
            renderItems(itemsActuales);
        });

        window.recepcionProveedorEmpresaDesdeOc = !!(window.recepcionProveedorOrdencompraIdInicial && window.recepcionProveedorEmpresaIdInicial);
        if (depositoCabeceraId > 0 && depositoCabeceraEmpresaId > 0) {
            aplicarMetadatosDepositoCabecera({
                tipodeposito: $('#recepcion_deposito_id').data('tipodeposito') || '',
                empresa_id: depositoCabeceraEmpresaId,
            });
        }

        if (window.recepcionProveedorItemsInicial && window.recepcionProveedorItemsInicial.length) {
            renderItems(window.recepcionProveedorItemsInicial);
        }

        if (window.recepcionProveedorOrdencompraIdInicial) {
            $('#ordencompra_id').val(window.recepcionProveedorOrdencompraIdInicial);
        }
        if (window.recepcionProveedorNumeroOcInicial) {
            ultimoNumeroOcCargado = window.recepcionProveedorNumeroOcInicial;
        }

        var $formRecepcion = $('#form-recepcion-proveedor');
        if ($formRecepcion.length && $formRecepcion.find('[name="tipo"]').val() === 'DEVOLUCION') {
            $formRecepcion.on('submit.recepcionDevolucionConfirm', function (e) {
                if (window.recepcionProveedorEnviandoTrasModal) {
                    return true;
                }
                if (window.recepcionProveedorModalCatalogoHabilitado) {
                    return true;
                }
                if (!window.confirm('¿Confirmar devolución? Generará salida de stock.')) {
                    e.preventDefault();
                }
            });
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

        $(document).on('input', '.item-cantidad, .item-precio, .item-cant-rechazada', function () {
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (!item) {
                return;
            }
            if ($(this).hasClass('item-cant-rechazada')) {
                item.cantidad_rechazada = parseFloat($(this).val()) || 0;
                actualizarMotivoRechazoFila(idx);
            } else if ($(this).hasClass('item-cantidad')) {
                item.cantidad = parseFloat($(this).val()) || 0;
            } else if ($(this).hasClass('item-precio')) {
                item.precio = parseFloat($(this).val()) || 0;
            }
            recalcularLinea($tr, item);
            actualizarComentarioPrecioFila(idx);
            actualizarTotalRecepcion();
        });

        $(document).on('input', '.item-importe-linea', function () {
            if (sincronizandoImporteLinea) {
                return;
            }
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (!item) {
                return;
            }
            item.cantidad = parseFloat($tr.find('.item-cantidad').val()) || 0;
            var importe = parseFloat($(this).val()) || 0;
            sincronizarPrecioDesdeImporte(item, importe, item.cantidad);
            $tr.find('.item-precio').val(item.precio);
            actualizarComentarioPrecioFila(idx);
            actualizarTotalRecepcion();
        });

        $(document).on('click', '.btn-linea-precio-modal', function (e) {
            e.preventDefault();
            abrirModalLineaPrecio(parseInt($(this).data('idx'), 10));
        });

        $(document).on('dblclick', '.item-importe-linea, .item-importe-linea-text', function () {
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            if (!isNaN(idx)) {
                abrirModalLineaPrecio(idx);
            }
        });

        $('#btn-modal-linea-precio-aplicar').on('click', aplicarModalLineaPrecio);

        $('#modal-linea-precio-unit').on('input', function () {
            if (sincronizandoModalLineaPrecio) {
                return;
            }
            sincronizandoModalLineaPrecio = true;
            var cant = parseFloat($('#modal-linea-cantidad').val()) || 0;
            var precio = parseFloat($(this).val()) || 0;
            var importe = redondearImporteLinea(cant * precio);
            $('#modal-linea-importe').val(importe.toFixed(2));
            var precioOc = parseFloat($('#modalRecepcionLineaPrecio').data('precio-oc')) || 0;
            actualizarAvisoDiffModalLineaPrecio(precioOc, precio);
            sincronizandoModalLineaPrecio = false;
        });

        $('#modal-linea-importe').on('input', function () {
            if (sincronizandoModalLineaPrecio) {
                return;
            }
            sincronizandoModalLineaPrecio = true;
            var cant = parseFloat($('#modal-linea-cantidad').val()) || 0;
            var importe = parseFloat($(this).val()) || 0;
            var precio = cant > 0.000001 ? redondearPrecioUnitario(importe / cant) : 0;
            $('#modal-linea-precio-unit').val(precio);
            var precioOc = parseFloat($('#modalRecepcionLineaPrecio').data('precio-oc')) || 0;
            actualizarAvisoDiffModalLineaPrecio(precioOc, precio);
            sincronizandoModalLineaPrecio = false;
        });

        $('#modal-linea-cantidad').on('input', function () {
            if (sincronizandoModalLineaPrecio) {
                return;
            }
            sincronizandoModalLineaPrecio = true;
            var cant = parseFloat($(this).val()) || 0;
            var precio = parseFloat($('#modal-linea-precio-unit').val()) || 0;
            $('#modal-linea-importe').val(redondearImporteLinea(cant * precio).toFixed(2));
            sincronizandoModalLineaPrecio = false;
        });

        $(document).on('change', '.item-moneda', function () {
            var $tr = $(this).closest('tr.item-recepcion-linea');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (item) {
                item.moneda_id = parseInt($(this).val(), 10) || 1;
            }
            actualizarTotalRecepcion();
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

        function mostrarPanelOcrDebug(res) {
            var $wrap = $('#ocr-debug-wrap');
            var $pre = $('#ocr_debug_json');
            if (!$wrap.length || !$pre.length) {
                return;
            }
            var texto = res && res.ocr_texto_puro ? String(res.ocr_texto_puro).trim() : '';
            var lineasParseadas = res && res.ocr_lineas_parseadas ? res.ocr_lineas_parseadas : null;
            if (!texto && (!lineasParseadas || !lineasParseadas.length)) {
                $wrap.addClass('d-none');
                $pre.text('');
                return;
            }
            var debug = {
                ocr_estado: res.ocr_estado || null,
                numero_oc_detectado: res.numero_oc_detectado || null,
                ocr_lineas_detectadas: res.ocr_lineas_detectadas || 0,
                resumen: res.resumen || null,
                ocr_texto_puro: res.ocr_texto_puro || '',
                ocr_lineas_parseadas: lineasParseadas || [],
            };
            $pre.text(JSON.stringify(debug, null, 2));
            $wrap.removeClass('d-none');
            $('#ocr-debug-panel').collapse('hide');
            $('#ocr-debug-toggle').addClass('collapsed').attr('aria-expanded', 'false');
        }

        $('#ocr-debug-panel').on('show.bs.collapse', function () {
            $('#ocr-debug-toggle .ocr-debug-chevron')
                .removeClass('fa-chevron-right')
                .addClass('fa-chevron-down');
        }).on('hide.bs.collapse', function () {
            $('#ocr-debug-toggle .ocr-debug-chevron')
                .removeClass('fa-chevron-down')
                .addClass('fa-chevron-right');
        });

        function aplicarResultadoOcr(res) {
            mostrarPanelOcrDebug(res);
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

        function rpMostrarSolapa(sel, btnId) {
            $('.rp-solapa').hide();
            $(sel).show();
            $('.rp-tab-solapa').removeClass('font-weight-bold btn-primary').addClass('btn-info');
            var $btn = $('#' + btnId);
            if ($btn.length) {
                $btn.removeClass('btn-info').addClass('btn-primary font-weight-bold');
            }
        }

        $('#rp-boton-principal').on('click', function () {
            rpMostrarSolapa('#rp-solapa-principal', 'rp-boton-principal');
        });
        $('#rp-boton-archivos').on('click', function () {
            rpMostrarSolapa('#rp-solapa-archivos', 'rp-boton-archivos');
        });
        $('#rp-boton-historia-estados').on('click', function () {
            rpMostrarSolapa('#rp-solapa-historia-estados', 'rp-boton-historia-estados');
        });
        $('#rp-boton-asiento-contable').on('click', function () {
            rpMostrarSolapa('#rp-solapa-asiento-contable', 'rp-boton-asiento-contable');
        });

        $('#btn-cambiar-oc-recepcion').on('click', function () {
            rpMostrarSolapa('#rp-solapa-principal', 'rp-boton-principal');
            $('#btn-consulta-oc-recepcion-modal').trigger('click');
        });

        var paramsUrl = new URLSearchParams(window.location.search);
        if (paramsUrl.get('enfocar_oc') === '1') {
            rpMostrarSolapa('#rp-solapa-principal', 'rp-boton-principal');
        } else if (paramsUrl.get('solapa') === 'archivos') {
            rpMostrarSolapa('#rp-solapa-archivos', 'rp-boton-archivos');
        } else if (paramsUrl.get('solapa') === 'estados') {
            rpMostrarSolapa('#rp-solapa-historia-estados', 'rp-boton-historia-estados');
        } else if (paramsUrl.get('solapa') === 'asiento') {
            rpMostrarSolapa('#rp-solapa-asiento-contable', 'rp-boton-asiento-contable');
        }

        programarFocoInicialNumeroOc(paramsUrl);

        $('#rp-agrega-renglon-archivo').on('click', function (event) {
            event.preventDefault();
            var tpl = $('#rp-template-renglon-archivo').html();
            if (!tpl) {
                return;
            }
            $('#rp-tbody-tabla-archivo').append(tpl);
        });

        $(document).on('click', '#rp-tbody-tabla-archivo .rp-eliminararchivo', function (event) {
            event.preventDefault();
            $(this).closest('tr.item-archivo-recepcion').remove();
        });

        $(document).on('click', '.eliminar-archivo-recepcion', function (event) {
            event.preventDefault();
            $(this).closest('.recepcion-archivo-item').remove();
        });
    });
})();
