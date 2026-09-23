(function ($) {
    'use strict';

    function msFilaArticuloId($tr) {
        return ($tr.find('input.articulo_id[name="articulos_id[]"]').val() || '').trim();
    }

    function msFilaDescripcion($tr) {
        return ($tr.find('.descripcionarticulo').val() || '').trim();
    }

    function msEsModoFerli() {
        return !!window.movimientoStockModoFerli;
    }

    function msEsLineaSimple($tr) {
        return !!($tr && $tr.length && $tr.hasClass('ms-linea-simple'));
    }

    function msCampoPrecio($tr) {
        return $tr.find('input.precio').first();
    }

    function msPrecioEsManual($tr) {
        return msCampoPrecio($tr).attr('data-ms-precio-manual') === '1';
    }

    function msMarcarPrecioManual($trOInput) {
        var $el = $trOInput && $trOInput.is && $trOInput.is('input.precio')
            ? $trOInput
            : msCampoPrecio($trOInput);
        $el.attr('data-ms-precio-manual', '1');
    }

    function msLimpiarPrecioManual($tr) {
        msCampoPrecio($tr).removeAttr('data-ms-precio-manual');
    }

    window.msPrecioEsManual = msPrecioEsManual;
    window.msMarcarPrecioManual = msMarcarPrecioManual;
    window.msLimpiarPrecioManual = msLimpiarPrecioManual;

    /**
     * Ferli: con combinaciones = calzado (comb/módulo/medidas).
     * Cantidad de calzado sigue viniendo del modal de talles (readonly).
     * Precio queda editable para corregir lista / última compra.
     * Sin combinaciones = no venta → cantidad y precio editables (modo original).
     */
    window.msAplicarModoLineaFerli = function ($tr, esArticuloVenta) {
        if (!$tr || !$tr.length || !msEsModoFerli()) {
            return;
        }

        var $cant = $tr.find('input.cantidad').first();
        var $precio = $tr.find('input.precio').first();
        var $comb = $tr.find('select.combinacion');
        var $mod = $tr.find('select.modulo');
        var $flags = $tr.find('.checkSinFiltro, .checkCombinacion');

        if (esArticuloVenta) {
            $tr.removeClass('ms-linea-simple');
            $cant.removeClass('cantidad-stock').prop('readonly', true);
            // Ferli mov. stock: precio oculto (hidden); no editar en pantalla
            $precio.prop('readonly', true);
            $comb.prop('disabled', false).css('pointer-events', '').attr('tabindex', null);
            $mod.prop('disabled', false).css('pointer-events', '').attr('tabindex', null);
            $flags.prop('disabled', false);
        } else {
            $tr.addClass('ms-linea-simple');
            $cant.addClass('cantidad-stock').prop('readonly', false);
            $precio.prop('readonly', true);
            // No disabled: deben viajar vacíos en el POST (índices de arrays).
            $comb.val('').css('pointer-events', 'none').attr('tabindex', '-1');
            $mod.val('').css('pointer-events', 'none').attr('tabindex', '-1');
            $tr.find('.combinacion_id_previa, .modulo_id_previa, .desc_combinacion, .desc_modulo, .medidas').val('');
            $flags.prop('checked', false);
            // Ferli: no resolver precio de fábrica/lista; el backend completa costo al grabar.
            if (!msEsModoFerli()) {
                var articuloId = msFilaArticuloId($tr);
                if (articuloId && !msPrecioEsManual($tr)) {
                    msResolverPrecioLinea($tr, articuloId);
                }
            } else if (!msPrecioEsManual($tr)) {
                $precio.val('0.00');
            }
            if (typeof window.msEnfocarCantidadFila === 'function') {
                window.msEnfocarCantidadFila($tr);
            }
        }

        if (typeof TotalParesPedido === 'function') {
            TotalParesPedido();
        }
    };

    function msFormatearCantidad(num) {
        if (!isFinite(num) || num === 0) {
            return '';
        }
        return String(parseFloat(num.toFixed(4)));
    }

    window.msFilaArticuloId = msFilaArticuloId;
    window.msFilaDescripcion = msFilaDescripcion;

    window.msEnriquecerUmDesdeArticulo = function ($tr, dataArticulo) {
        if (!$tr.length || !dataArticulo) {
            return;
        }
        var umd = (dataArticulo.unidadesdemedidas && dataArticulo.unidadesdemedidas.abreviatura) || '';
        var umdAlt = (dataArticulo.unidadesdemedidasalternativas && dataArticulo.unidadesdemedidasalternativas.abreviatura) || '';
        var uxenv = parseFloat(dataArticulo.unidadesxenvase) || 0;

        $tr.find('.unidadesxenvase').val(uxenv > 0 ? uxenv : '');
        $tr.find('.abrev-umd').text(umd);
        $tr.find('.abrev-umd-alter').text(umdAlt);
    };

    window.msResolverPrecioLinea = function ($tr, articuloId, opciones) {
        if (!$tr || !$tr.length) {
            return;
        }
        opciones = opciones || {};
        var forzar = !!opciones.forzar;
        // En Ferli solo resuelve precio automático en líneas no venta (sin combinaciones).
        if (msEsModoFerli() && !msEsLineaSimple($tr)) {
            return;
        }
        if (!forzar && msPrecioEsManual($tr)) {
            return;
        }
        var url = window.movimientoStockPrecioLineaUrl || '';
        var tipoId = parseInt($('#tipotransaccion_stock_id').val(), 10) || 0;
        articuloId = parseInt(articuloId, 10) || 0;
        if (!url || articuloId <= 0 || tipoId <= 0) {
            return;
        }
        $.get(url, {
            articulo_id: articuloId,
            tipotransaccion_stock_id: tipoId,
            fecha: $('#fecha').val() || ''
        }).done(function (data) {
            if (String(msFilaArticuloId($tr)) !== String(articuloId)) {
                return;
            }
            if (!forzar && msPrecioEsManual($tr)) {
                return;
            }
            if (!data || data.precio == null) {
                return;
            }
            var precio = parseFloat(data.precio);
            if (!isFinite(precio)) {
                return;
            }
            $tr.find('.precio').val(precio.toFixed(2));
            if (data.listaprecio_id) {
                $tr.find('.listaprecio_id').val(data.listaprecio_id);
            }
            if (data.moneda_id) {
                $tr.find('.moneda_id').val(data.moneda_id);
            }
            if (data.incluyeimpuesto != null && data.incluyeimpuesto !== '') {
                $tr.find('.incluyeimpuesto').val(data.incluyeimpuesto);
            }
            if (typeof window.movStockProgramarPreviewAsiento === 'function') {
                window.movStockProgramarPreviewAsiento();
            }
        });
    };

    function msAplicarTipoTransferenciaContableSiCorresponde($tr, articuloId) {
        var url = window.movimientoStockSugerirTipoTransferenciaContableUrl || '';
        var empresaId = parseInt($('#empresa_id').val(), 10) || 0;
        var meta = typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta()
            : {};
        if ($('#movimientostockid').length
            || !url
            || empresaId <= 0
            || parseInt(articuloId, 10) <= 0
            || String(meta.abreviatura || '').trim().toUpperCase() !== 'TRA') {
            return;
        }

        $.get(url, {
            articulo_id: articuloId,
            empresa_id: empresaId
        }).done(function (resp) {
            if (String(msFilaArticuloId($tr)) !== String(articuloId)
                || !resp
                || !resp.ok
                || !resp.es_contabilizable
                || !resp.tipo_trcont) {
                return;
            }
            if (typeof window.msAplicarTipotransaccionStockEnCampo === 'function') {
                window.msAplicarTipotransaccionStockEnCampo(resp.tipo_trcont);
            }
        });
    }

    window.msRefrescarPreciosTodasLasFilas = function (opciones) {
        opciones = opciones || {};
        $('#tbody-tabla tr.item-pedido').each(function () {
            var $tr = $(this);
            if (msEsModoFerli() && !msEsLineaSimple($tr)) {
                return;
            }
            var articuloId = msFilaArticuloId($tr);
            if (articuloId) {
                msResolverPrecioLinea($tr, articuloId, opciones);
            }
        });
    };

    window.msRecalcularCantidadesStandard = function ($tr, origen) {
        var uxenv = parseFloat($tr.find('.unidadesxenvase').val()) || 0;
        var $cant = $tr.find('.cantidad-stock');
        var $alt = $tr.find('.cant-unidad');
        var cant = parseFloat($cant.val()) || 0;
        var alt = parseFloat($alt.val()) || 0;

        if (origen === 'cantidad' && cant !== 0 && uxenv !== 0) {
            $alt.val(msFormatearCantidad(cant * uxenv));
        } else if (origen === 'cant_unidad' && alt !== 0 && uxenv !== 0) {
            $cant.val(msFormatearCantidad(alt / uxenv));
        }

        if (typeof window.movStockProgramarPreviewAsiento === 'function') {
            window.movStockProgramarPreviewAsiento();
        }
    };

    window.onArticuloSeleccionado = function (dataArticulo, ctx) {
        if (!dataArticulo || !ctx || !ctx.row) {
            return;
        }
        var $tr = $(ctx.row);
        if (!$tr.closest('#tabla-items-movimientostock').length) {
            return;
        }

        var articuloId = parseInt(dataArticulo.id, 10) || 0;
        $tr.find('input.articulo_id[name="articulos_id[]"]').val(articuloId > 0 ? articuloId : '');
        $tr.find('.codigoarticulo').val(dataArticulo.sku || '');
        $tr.find('.descripcionarticulo').val(dataArticulo.descripcion || dataArticulo.nombre || '');
        $tr.find('.articulo_id_previo').val(articuloId > 0 ? articuloId : '');
        msLimpiarPrecioManual($tr);
        $tr.find('.precio').val('');
        msAplicarTipoTransferenciaContableSiCorresponde($tr, articuloId);

        if (typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo($tr, articuloId);
        }

        if (msEsModoFerli()) {
            // Mientras llegan combinaciones, no forzar modo venta.
            $tr.removeClass('ms-linea-simple');
            if (typeof completarCombinaciones === 'function') {
                completarCombinaciones($tr, 0, false);
            }
            if (typeof completarModulos === 'function') {
                completarModulos($tr, 0);
            }
            // El foco a cantidad lo aplica msAplicarModoLineaFerli si es no-venta.
        } else {
            if (typeof window.msAplicarExclusividadColorTalle === 'function') {
                if (!window.msAplicarExclusividadColorTalle(dataArticulo, $tr)) {
                    return;
                }
            }
            msEnriquecerUmDesdeArticulo($tr, dataArticulo);
            msResolverPrecioLinea($tr, articuloId, { forzar: true });
            $tr.find('.cantidad-stock').val('');
            $tr.find('.cant-unidad').val('');
            msEnfocarCantidadFila($tr);
        }

        if (typeof window.movStockProgramarPreviewAsiento === 'function') {
            window.movStockProgramarPreviewAsiento();
        }
    };

    function msEnfocarCampoFila($el) {
        if (!$el || !$el.length) {
            return false;
        }
        setTimeout(function () {
            $el.trigger('focus');
            if ($el[0] && typeof $el[0].select === 'function') {
                $el[0].select();
            }
        }, 30);
        return true;
    }

    function msEnfocarCantidadFila($tr) {
        if (!$tr || !$tr.length) {
            return false;
        }
        var $target = $tr.find('.cantidad-stock, .cantidad')
            .filter(':visible:not([readonly])')
            .first();
        if (!$target.length) {
            $target = $tr.find('.cantidad-stock, .cantidad').first();
        }
        if (!$target.length) {
            return false;
        }
        return msEnfocarCampoFila($target);
    }

    window.msEnfocarCantidadFila = msEnfocarCantidadFila;

    function msEnfocarCantidadAlternativaFila($tr) {
        if (!$tr || !$tr.length || msEsModoFerli()) {
            return false;
        }
        var $alt = $tr.find('.cant-unidad')
            .filter(':visible:not([readonly])')
            .first();
        if (!$alt.length) {
            $alt = $tr.find('.cant-unidad').first();
        }
        if (!$alt.length) {
            return false;
        }
        return msEnfocarCampoFila($alt);
    }

    function msValidarSkuFilaConEnter(input) {
        if (!input || !input.classList || !input.classList.contains('codigoarticulo')) {
            return false;
        }
        if (!input.closest || !input.closest('#tabla-items-movimientostock')) {
            return false;
        }

        var $input = $(input);
        var $tr = $input.closest('tr');
        var sku = ($input.val() || '').trim();

        if (!sku) {
            return true;
        }

        // Ya resuelto: solo pasar el foco a cantidad (no re-disparar change ni borrar cantidad)
        if (msFilaArticuloId($tr) && msFilaDescripcion($tr)) {
            msEnfocarCantidadFila($tr);
            return true;
        }

        $input.trigger('change');
        return true;
    }

    function msAvanzarCantidadAAlternativaConEnter(input) {
        if (!input || !input.classList || !input.classList.contains('cantidad-stock')) {
            return false;
        }
        if (!input.closest || !input.closest('#tabla-items-movimientostock')) {
            return false;
        }
        var $tr = $(input).closest('tr');
        // Ferli no-venta: Enter en cantidad → precio (no hay UM alt.).
        if (msEsModoFerli()) {
            if (!msEsLineaSimple($tr)) {
                return false;
            }
            if (typeof TotalParesPedido === 'function') {
                TotalParesPedido();
            }
            if (typeof window.movStockProgramarPreviewAsiento === 'function') {
                window.movStockProgramarPreviewAsiento();
            }
            return msEnfocarCampoFila($tr.find('input.precio').first());
        }

        msRecalcularCantidadesStandard($tr, 'cantidad');
        if (typeof window.movStockProgramarPreviewAsiento === 'function') {
            window.movStockProgramarPreviewAsiento();
        }
        msEnfocarCantidadAlternativaFila($tr);
        return true;
    }

    $(function () {
        if (!$('#tabla-items-movimientostock').length) {
            return;
        }

        // Capture: gana a bloqueos legacy de Enter en $('input').keydown (depmae, etc.)
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.which !== 13 && e.keyCode !== 13) {
                return;
            }
            var handled = msValidarSkuFilaConEnter(e.target)
                || msAvanzarCantidadAAlternativaConEnter(e.target);
            if (!handled) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
        }, true);

        $(document).on('input.msArtSkuClear', '#tabla-items-movimientostock .codigoarticulo', function () {
            var $tr = $(this).closest('tr');
            if (!msFilaArticuloId($tr)) {
                return;
            }
            // Al editar el SKU invalidamos la línea para forzar revalidación con Enter/blur
            $tr.find('input.articulo_id[name="articulos_id[]"]').val('');
            $tr.find('.descripcionarticulo').val('');
            if (typeof actualizarLinkEditarArticulo === 'function') {
                actualizarLinkEditarArticulo($tr, 0);
            }
        });

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        $('#tbody-tabla tr.item-pedido').each(function () {
            var $tr = $(this);
            if (!msFilaArticuloId($tr)) {
                return;
            }
            if (msEsModoFerli()) {
                var combinacionId = $tr.find('.combinacion_id_previa').val();
                var moduloId = $tr.find('.modulo_id_previa').val();
                if (typeof completarCombinaciones === 'function') {
                    completarCombinaciones($tr, combinacionId, true);
                }
                if (typeof completarModulos === 'function') {
                    completarModulos($tr, moduloId);
                }
            }
            if (typeof actualizarLinkEditarArticulo === 'function') {
                actualizarLinkEditarArticulo($tr, msFilaArticuloId($tr));
            }
        });

        $(document).on('input.msPrecioManual', '#tabla-items-movimientostock input.precio', function () {
            msMarcarPrecioManual($(this));
            if (typeof window.movStockProgramarPreviewAsiento === 'function') {
                window.movStockProgramarPreviewAsiento();
            }
        });

        if ($('#movimientostockid').length) {
            $('#tabla-items-movimientostock input.precio').each(function () {
                if (($(this).val() || '').trim() !== '') {
                    msMarcarPrecioManual($(this));
                }
            });
        }

        if (msEsModoFerli()) {
            $(document).on('change.msCheckSinFiltro', '.checkSinFiltro', function () {
                var $tr = $(this).closest('tr');
                if (msFilaArticuloId($tr) && typeof completarCombinaciones === 'function') {
                    var idPrev = $tr.find('.combinacion').val() || $tr.find('.combinacion_id_previa').val() || 0;
                    completarCombinaciones($tr, idPrev, false);
                }
            });

            $(document).on('change.msCheckComb', '.checkCombinacion', function () {
                var $tr = $(this).closest('tr');
                if (msFilaArticuloId($tr) && typeof completarCombinaciones === 'function') {
                    var idPrev = $tr.find('.combinacion').val() || $tr.find('.combinacion_id_previa').val() || 0;
                    completarCombinaciones($tr, idPrev, false);
                }
            });
        } else {
            $(document).on('input change', '#tabla-items-movimientostock .cantidad-stock', function () {
                msRecalcularCantidadesStandard($(this).closest('tr'), 'cantidad');
            });
            $(document).on('input change', '#tabla-items-movimientostock .cant-unidad', function () {
                msRecalcularCantidadesStandard($(this).closest('tr'), 'cant_unidad');
            });
            $(document).on('change input', '#tabla-items-movimientostock .cantidad-stock', function () {
                if (typeof window.movStockProgramarPreviewAsiento === 'function') {
                    window.movStockProgramarPreviewAsiento();
                }
            });

            $(document).on('change', '#tipotransaccion_stock_id, #fecha', function () {
                msRefrescarPreciosTodasLasFilas();
            });
        }
    });
}(jQuery));
