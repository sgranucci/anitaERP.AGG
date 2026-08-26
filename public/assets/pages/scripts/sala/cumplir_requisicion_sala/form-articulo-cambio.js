(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionSalaConfig || {};

    function puedeCambiarArticulo() {
        return !!cfg.puedeCambiarArticulo;
    }

    function marcarCambioArticulo($tr) {
        var original = parseInt($tr.find('.articulo_id_original').val(), 10) || 0;
        var actual = parseInt($tr.find('.articulo_id').val(), 10) || 0;
        var cambio = original > 0 && actual > 0 && original !== actual;
        $tr.find('.aviso-articulo-cambiado').toggleClass('d-none', !cambio);
        $tr.toggleClass('fila-articulo-cambiado', cambio);
    }

    function aplicarArticuloEnFila($tr, data) {
        if (!$tr.length || !data) {
            return;
        }
        var articuloId = parseInt(data.id, 10) || 0;
        $tr.find('.articulo_id').val(articuloId);
        $tr.find('.codigoarticulo').val(data.sku || data.codigo || '');
        $tr.find('.descripcion-articulo-celda').text(data.descripcion || data.nombre || '');
        $tr.attr('data-articulo-id', articuloId);
        marcarCambioArticulo($tr);
        if (typeof window.crsRefrescarSaldosOrigen === 'function') {
            window.crsRefrescarSaldosOrigen({ ajustarCantidad: false });
        } else if (typeof window.crsCargarSaldoFila === 'function') {
            window.crsCargarSaldoFila($tr, false);
        }
    }

    function htmlCeldaArticulo(linea, idx) {
        var articuloId = parseInt(linea.articulo_id, 10) || 0;
        var articuloOriginalId = parseInt(linea.articulo_id_original, 10) || articuloId;
        var html = '<td class="celda-articulo-cumple align-middle">';
        html += '<input type="hidden" class="articulo_id" name="lineas[' + idx + '][articulo_id]" value="' + articuloId + '">';
        html += '<input type="hidden" class="articulo_id_original" value="' + articuloOriginalId + '">';
        html += '<div class="input-group input-group-sm">';
        html += '<div class="input-group-prepend">';
        html += '<button type="button" class="btn btn-outline-secondary btn-sm consultaarticulo btn-cambio-articulo-cumple" title="Cambiar art\u00edculo"><i class="fa fa-search"></i></button>';
        html += '</div>';
        html += '<input type="text" class="form-control form-control-sm codigoarticulo" value="' + (linea.sku || '') + '" readonly>';
        html += '</div>';
        html += '<small class="text-warning d-none aviso-articulo-cambiado"><i class="fa fa-exchange-alt"></i> Art. modificado</small>';
        html += '</td>';
        return html;
    }

    window.crsHtmlCeldaArticuloCumple = htmlCeldaArticulo;
    window.crsMarcarCambioArticulo = marcarCambioArticulo;

    $(function () {
        if (!puedeCambiarArticulo()) {
            return;
        }

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        var prevOnArticulo = window.onArticuloSeleccionado;
        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (typeof prevOnArticulo === 'function') {
                prevOnArticulo(dataArticulo, ctx);
            }
            if (ctx && ctx.row && $(ctx.row).closest('#tabla-lineas-cumple').length) {
                aplicarArticuloEnFila($(ctx.row), dataArticulo);
            }
        };

        $('#tabla-lineas-cumple tr.fila-cumple-linea').each(function () {
            marcarCambioArticulo($(this));
        });
    });
}(jQuery));
