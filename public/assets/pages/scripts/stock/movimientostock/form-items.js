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

    window.msResolverPrecioLinea = function ($tr, articuloId) {
        if (msEsModoFerli() || !$tr || !$tr.length) {
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

    window.msRefrescarPreciosTodasLasFilas = function () {
        if (msEsModoFerli()) {
            return;
        }
        $('#tbody-tabla tr.item-pedido').each(function () {
            var $tr = $(this);
            var articuloId = msFilaArticuloId($tr);
            if (articuloId) {
                msResolverPrecioLinea($tr, articuloId);
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

        if (typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo($tr, articuloId);
        }

        if (msEsModoFerli()) {
            if (typeof completarCombinaciones === 'function') {
                completarCombinaciones($tr, 0, false);
            }
            if (typeof completarModulos === 'function') {
                completarModulos($tr, 0);
            }
            msEnfocarCantidadFila($tr);
        } else {
            if (typeof window.msAplicarExclusividadColorTalle === 'function') {
                if (!window.msAplicarExclusividadColorTalle(dataArticulo, $tr)) {
                    return;
                }
            }
            msEnriquecerUmDesdeArticulo($tr, dataArticulo);
            msResolverPrecioLinea($tr, articuloId);
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
        if (msEsModoFerli()) {
            return false;
        }

        var $tr = $(input).closest('tr');
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

        if (msEsModoFerli()) {
            $(document).on('change.msCheckSinFiltro', '.checkSinFiltro', function () {
                var $tr = $(this).closest('tr');
                if (msFilaArticuloId($tr) && typeof completarCombinaciones === 'function') {
                    completarCombinaciones($tr, $tr.find('.combinacion').val() || 0, false);
                }
            });

            $(document).on('change.msCheckComb', '.checkCombinacion', function () {
                var $tr = $(this).closest('tr');
                if (msFilaArticuloId($tr) && typeof completarCombinaciones === 'function') {
                    completarCombinaciones($tr, $tr.find('.combinacion').val() || 0, false);
                }
            });
        } else {
            $(document).on('input change', '#tabla-items-movimientostock .cantidad-stock', function () {
                msRecalcularCantidadesStandard($(this).closest('tr'), 'cantidad');
            });
            $(document).on('input change', '#tabla-items-movimientostock .cant-unidad', function () {
                msRecalcularCantidadesStandard($(this).closest('tr'), 'cant_unidad');
            });
            $(document).on('change input', '#tabla-items-movimientostock .cantidad-stock, #tabla-items-movimientostock .precio', function () {
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
