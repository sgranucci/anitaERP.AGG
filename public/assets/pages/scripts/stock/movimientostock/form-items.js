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

        if (dataArticulo.ppp != null && dataArticulo.ppp !== '') {
            $tr.find('.precio').val(parseFloat(dataArticulo.ppp).toFixed(2));
        }
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
        } else {
            msEnriquecerUmDesdeArticulo($tr, dataArticulo);
            $tr.find('.cantidad-stock').val('').trigger('focus');
            $tr.find('.cant-unidad').val('');
        }

        if (typeof window.movStockProgramarPreviewAsiento === 'function') {
            window.movStockProgramarPreviewAsiento();
        }
    };

    $(function () {
        if (!$('#tabla-items-movimientostock').length) {
            return;
        }

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
        }
    });
}(jQuery));
