(function ($) {
    'use strict';

    var timersPorFila = {};
    var pendingPickCallback = null;

    function previewConversionUrl() {
        return window.movimientoStockPreviewConversionFormulaUrl || '';
    }

    function esTipoDepositoFormula(tipo) {
        var t = String(tipo || '').trim();
        if (!t) {
            return false;
        }
        var upper = t.toUpperCase();
        return upper === 'FORMULAS' || upper === 'FÓRMULAS' || upper === 'F' || t === '8';
    }

    function operacionTipoSeleccionada() {
        return typeof window.msOperacionTipoTransaccion === 'function'
            ? window.msOperacionTipoTransaccion()
            : '';
    }

    function tipodepositoCampo($ctx, $depId) {
        if (!$ctx || !$ctx.length) {
            return '';
        }
        var tipo = String($ctx.attr('data-tipodeposito') || '').trim();
        if (tipo) {
            return tipo;
        }
        if ($depId && $depId.length) {
            tipo = String($depId.attr('data-tipodeposito') || '').trim();
        }
        return tipo;
    }

    function depositoEsFormula(depId, tipo) {
        if ((parseInt(depId, 10) || 0) <= 0) {
            return false;
        }
        return esTipoDepositoFormula(tipo);
    }

    function contextoFormulaConversion() {
        var operacion = operacionTipoSeleccionada();

        if (operacion === 'T') {
            var $ctxDest = $('#tm_deposito_entrada');
            var $ctxOrig = $('#tm_deposito_salida');
            var destId = parseInt($('#deposito_entrada_id').val(), 10) || 0;
            var origId = parseInt($('#deposito_salida_id').val(), 10) || 0;
            var destTipo = tipodepositoCampo($ctxDest, $('#deposito_entrada_id'));
            var origTipo = tipodepositoCampo($ctxOrig, $('#deposito_salida_id'));

            if (depositoEsFormula(destId, destTipo)) {
                return { activo: true, sentido: 'entrada', depositoId: destId };
            }
            if (depositoEsFormula(origId, origTipo)) {
                return { activo: true, sentido: 'salida', depositoId: origId };
            }

            return { activo: false };
        }

        var $ctx = $('#tm_deposito_movimientostock');
        var depId = parseInt($('#deposito_id').val(), 10) || 0;
        var depTipo = tipodepositoCampo($ctx, $('#deposito_id'));
        if (!depositoEsFormula(depId, depTipo)) {
            return { activo: false };
        }

        if (operacion === 'E') {
            return { activo: true, sentido: 'entrada', depositoId: depId };
        }
        if (operacion === 'S') {
            return { activo: true, sentido: 'salida', depositoId: depId };
        }

        return { activo: false };
    }

    function articuloIdFila($tr) {
        if (typeof window.msFilaArticuloId === 'function') {
            var id = parseInt(window.msFilaArticuloId($tr), 10) || 0;
            if (id > 0) {
                return id;
            }
        }
        return parseInt($tr.find('input.articulo_id[name="articulos_id[]"], .articulo_id').first().val(), 10) || 0;
    }

    function cantidadLinea($tr) {
        var $cant = $tr.find('.cantidad-stock');
        if ($cant.length) {
            return Math.abs(parseFloat($cant.val()) || 0);
        }
        var $cantFerli = $tr.find('.cantidad');
        if ($cantFerli.length) {
            return Math.abs(parseFloat($cantFerli.val()) || 0);
        }
        return 0;
    }

    function formatearCantidadDestino(num) {
        if (!isFinite(num) || num === 0) {
            return '';
        }
        return String(parseFloat(parseFloat(num).toFixed(6)));
    }

    function truncarTexto(texto, max) {
        var t = String(texto || '').trim();
        if (!t || t.length <= max) {
            return t;
        }
        return t.substring(0, Math.max(1, max - 1)) + '…';
    }

    function textoCompletoInsumoDestino(sku, desc) {
        sku = String(sku || '').trim();
        desc = String(desc || '').trim();
        if (sku && desc) {
            return sku + ' — ' + desc;
        }
        if (desc) {
            return '— ' + desc;
        }
        return sku;
    }

    function textoVisibleInsumoDestino(sku, desc) {
        return truncarTexto(textoCompletoInsumoDestino(sku, desc), 42);
    }

    function conversionTransferenciaFormulaActiva() {
        if (operacionTipoSeleccionada() !== 'T') {
            return false;
        }
        return contextoFormulaConversion().activo;
    }

    function articulosSinInsumoFormula() {
        var list = [];
        $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
            var $tr = $(this);
            var $hint = $tr.find('.ms-conversion-formula.text-danger').filter(function () {
                return !$(this).hasClass('d-none') && $.trim($(this).text()) !== '';
            });
            if (!$hint.length) {
                return;
            }
            var sku = $.trim(String($tr.find('.codigoarticulo').val() || ''));
            var texto = $.trim($hint.first().text());
            list.push(sku ? (sku + ': ' + texto) : texto);
        });
        return list;
    }

    function actualizarAvisoFormulaInsumo() {
        var $aviso = $('#ms_aviso_formula_insumo');
        var $lista = $('#ms_aviso_formula_insumo_lista');
        if (!$aviso.length) {
            return;
        }
        var visible = conversionTransferenciaFormulaActiva();
        $aviso.toggleClass('d-none', !visible);
        if (!visible) {
            $lista.empty();
            return;
        }
        var faltan = articulosSinInsumoFormula();
        $lista.empty();
        faltan.forEach(function (linea) {
            $lista.append($('<li></li>').text(linea));
        });
        $aviso.toggleClass('alert-danger', faltan.length > 0);
        $aviso.toggleClass('alert-warning', faltan.length === 0);
    }

    function actualizarVisibilidadColumnasConversion() {
        var visible = conversionTransferenciaFormulaActiva();
        var $tabla = $('#tabla-items-movimientostock');
        $tabla.toggleClass('ms-tabla-conversion-formula', visible);
        if (!visible) {
            $tabla.find('tbody tr.item-pedido').each(function () {
                limpiarConversionFila($(this));
            });
        }
        actualizarAvisoFormulaInsumo();
    }

    function limpiarColumnasDestinoFila($tr) {
        $tr.find('.ms-insumo-destino-sku').val('').attr('title', '').attr('placeholder', '—');
        $tr.find('.ms-cantidad-destino').val('').attr('placeholder', '—');
        $tr.find('.ms-um-destino').text('');
    }

    function aplicarColumnasDestinoFila($tr, data, cantidadReal) {
        var sku = (data.articulo_convertido_sku || '').trim();
        var desc = (data.articulo_convertido_descripcion || '').trim();
        var um = (data.um_convertida || '').trim();
        var cantConv = parseFloat(data.cantidad_convertida);
        var visible = textoVisibleInsumoDestino(sku, desc);
        var titleFull = textoCompletoInsumoDestino(sku, desc);

        $tr.find('.ms-insumo-destino-sku')
            .val(visible)
            .attr('title', titleFull || visible)
            .attr('placeholder', visible ? '' : '—');

        $tr.find('.ms-um-destino').text(um);

        if (cantidadReal > 0 && isFinite(cantConv)) {
            $tr.find('.ms-cantidad-destino').val(formatearCantidadDestino(cantConv));
        } else {
            $tr.find('.ms-cantidad-destino').val('').attr('placeholder', cantidadReal > 0 ? '—' : '');
        }
    }

    function limpiarConversionFila($tr) {
        limpiarColumnasDestinoFila($tr);
        $tr.find('.ms-conversion-formula').addClass('d-none').removeClass('text-danger').text('');
        $tr.find('.ms-articulo-compra-elegido').val('');
    }

    function mostrarConversionFila($tr, texto, esError) {
        var $hint = $tr.find('.ms-conversion-formula');
        if (!texto) {
            $hint.addClass('d-none').removeClass('text-danger text-primary').text('');
            return;
        }
        $hint.text(texto)
            .toggleClass('text-danger', !!esError)
            .toggleClass('text-primary', !esError)
            .removeClass('d-none');
    }

    function abrirSelectorArticuloCompra(opciones, onPick) {
        var $lista = $('#ms-lista-articulos-compra').empty();
        if (!opciones || !opciones.length) {
            return;
        }
        opciones.forEach(function (opt) {
            var $btn = $('<button type="button" class="list-group-item list-group-item-action"></button>');
            $btn.text((opt.sku || '') + (opt.descripcion ? (' — ' + opt.descripcion) : ''));
            $btn.on('click', function () {
                $('#msModalElegirArticuloCompra').modal('hide');
                if (typeof onPick === 'function') {
                    onPick(parseInt(opt.id, 10) || 0);
                }
            });
            $lista.append($btn);
        });
        pendingPickCallback = onPick;
        $('#msModalElegirArticuloCompra').modal('show');
    }

    function solicitarPreviewFila($tr, forzarCompraId) {
        var previewUrl = previewConversionUrl();
        if (!previewUrl || !$tr.length) {
            return;
        }

        var ctx = contextoFormulaConversion();
        if (!conversionTransferenciaFormulaActiva()) {
            limpiarConversionFila($tr);
            return;
        }

        var articuloId = articuloIdFila($tr);
        if (articuloId <= 0) {
            limpiarConversionFila($tr);
            return;
        }

        var cantidadReal = cantidadLinea($tr);
        var cantidadApi = cantidadReal > 0 ? cantidadReal : 1;

        var compraElegido = forzarCompraId > 0
            ? forzarCompraId
            : (parseInt($tr.find('.ms-articulo-compra-elegido').val(), 10) || 0);

        $.get(previewUrl, {
            articulo_id: articuloId,
            deposito_id: ctx.depositoId,
            sentido: ctx.sentido,
            cantidad: cantidadApi,
            empresa_id: parseInt($('#empresa_id').val(), 10) || 0,
            articulo_compra_id: compraElegido > 0 ? compraElegido : undefined,
        }).done(function (data) {
            if (!data) {
                limpiarConversionFila($tr);
                return;
            }

            if (data.requiere_elegir_compra && data.opciones_compra && data.opciones_compra.length) {
                abrirSelectorArticuloCompra(data.opciones_compra, function (idCompra) {
                    $tr.find('.ms-articulo-compra-elegido').val(idCompra > 0 ? idCompra : '');
                    solicitarPreviewFila($tr, idCompra);
                });
                limpiarColumnasDestinoFila($tr);
                mostrarConversionFila($tr, 'Elija artículo de compra…', false);
                return;
            }

            if (!data.ok) {
                limpiarColumnasDestinoFila($tr);
                mostrarConversionFila($tr, data.mensaje || 'Falta SKU alternativo (insumo)', true);
                actualizarAvisoFormulaInsumo();
                return;
            }

            if (!data.activo || !data.fl_conversion_formula) {
                limpiarConversionFila($tr);
                return;
            }

            if (data.articulo_compra_id) {
                $tr.find('.ms-articulo-compra-elegido').val(data.articulo_compra_id);
            }

            aplicarColumnasDestinoFila($tr, data, cantidadReal);
            mostrarConversionFila($tr, '', false);
            actualizarAvisoFormulaInsumo();
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje)
                ? xhr.responseJSON.mensaje
                : 'No se pudo calcular la conversión.';
            mostrarConversionFila($tr, msg, true);
            actualizarAvisoFormulaInsumo();
        });
    }

    function programarPreviewFila($tr) {
        var key = $tr.index();
        clearTimeout(timersPorFila[key]);
        timersPorFila[key] = setTimeout(function () {
            solicitarPreviewFila($tr);
        }, 250);
    }

    function refrescarTodasLasFilas() {
        actualizarVisibilidadColumnasConversion();
        $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
            programarPreviewFila($(this));
        });
    }

    window.msRefrescarConversionFormulaFilas = refrescarTodasLasFilas;
    window.msArticulosSinInsumoFormula = articulosSinInsumoFormula;

    function activarInterceptorGrabadoSinInsumo() {
        var form = document.getElementById('formgeneral');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (e) {
            if (!conversionTransferenciaFormulaActiva()) {
                return;
            }
            var faltan = articulosSinInsumoFormula();
            if (!faltan.length) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            actualizarAvisoFormulaInsumo();
            alert(
                'No se puede grabar la transferencia al depósito Fórmulas.\n'
                + 'Falta SKU alternativo (insumo) en:\n\n'
                + faltan.join('\n')
                + '\n\nCargue el SKU alt./insumo en el maestro de artículos.'
            );
            var $aviso = $('#ms_aviso_formula_insumo');
            if ($aviso.length && $aviso[0].scrollIntoView) {
                $aviso[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, true);
    }

    $(document).on('input change', '#tabla-items-movimientostock .cantidad-stock, #tabla-items-movimientostock .cantidad', function () {
        programarPreviewFila($(this).closest('tr'));
    });

    $(document).on('change input', '#tabla-items-movimientostock .articulo_id, #tabla-items-movimientostock .codigoarticulo', function () {
        programarPreviewFila($(this).closest('tr'));
    });

    $(document).on('change', '#tipotransaccion_stock_id, #deposito_id, #deposito_salida_id, #deposito_entrada_id, #empresa_id', refrescarTodasLasFilas);

    $(document).on('click', '#agrega_renglon, #tabla-items-movimientostock .eliminar', function () {
        setTimeout(refrescarTodasLasFilas, 150);
    });

    $(function () {
        var prevOnDeposito = window.onDepositoAplicadoEnFormulario;
        window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
            if (typeof prevOnDeposito === 'function') {
                prevOnDeposito(data, $ctx);
            }
            refrescarTodasLasFilas();
        };

        var prevOnArticulo = window.onArticuloSeleccionado;
        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (typeof prevOnArticulo === 'function') {
                prevOnArticulo(dataArticulo, ctx);
            }
            if (ctx && ctx.row) {
                var $tr = $(ctx.row);
                if ($tr.closest('#tabla-items-movimientostock').length) {
                    $tr.find('.ms-articulo-compra-elegido').val('');
                    programarPreviewFila($tr);
                }
            }
        };

        $('#msModalElegirArticuloCompra').on('hidden.bs.modal', function () {
            pendingPickCallback = null;
        });

        activarInterceptorGrabadoSinInsumo();
        refrescarTodasLasFilas();
    });
}(jQuery));
