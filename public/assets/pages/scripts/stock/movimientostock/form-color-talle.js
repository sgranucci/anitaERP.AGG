(function ($) {
    'use strict';

    function opcionesHtml(lista, placeholder) {
        var html = '<option value="">' + placeholder + '</option>';
        (lista || []).forEach(function (item) {
            html += '<option value="' + item.id + '">' + $('<div>').text(item.nombre || '').html() + '</option>';
        });
        return html;
    }

    function poblarSelectsFila($tr) {
        var $color = $tr.find('select.ms-color-id');
        var $talle = $tr.find('select.ms-talle-id');
        if (!$color.length || !$talle.length) {
            return;
        }

        var colorSel = $color.attr('data-selected') || $color.val() || '';
        var talleSel = $talle.attr('data-selected') || $talle.val() || '';

        if ($color.find('option').length <= 1) {
            $color.html(opcionesHtml(window.msColoresOpciones || [], '— Color —'));
        }
        if ($talle.find('option').length <= 1) {
            $talle.html(opcionesHtml(window.msTallesOpciones || [], '— Talle —'));
        }

        if (colorSel) {
            $color.val(String(colorSel));
        }
        if (talleSel) {
            $talle.val(String(talleSel));
        }
    }

    function modoActual() {
        var v = String($('#modo_stock_color_talle').val() || '').trim();
        if (v === '1') {
            return true;
        }
        if (v === '0') {
            return false;
        }
        return null;
    }

    function setModo(modo) {
        if (modo === null) {
            $('#modo_stock_color_talle').val('');
        } else {
            $('#modo_stock_color_talle').val(modo ? '1' : '0');
        }
        aplicarVisibilidadModo();
    }

    function aplicarVisibilidadModo() {
        var modo = modoActual();
        var activo = modo === true;

        $('#ms-ayuda-color-talle').toggle(activo);
        $('#tabla-items-movimientostock .ms-col-color-talle').each(function () {
            if (activo) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
            var $tr = $(this);
            poblarSelectsFila($tr);
            var $sels = $tr.find('select.ms-color-id, select.ms-talle-id');
            if (!activo) {
                $sels.val('').attr('data-selected', '');
            }
        });
    }

    function limpiarArticuloFila($tr) {
        $tr.find('input.articulo_id[name="articulos_id[]"]').val('');
        $tr.find('.codigoarticulo').val('');
        $tr.find('.descripcionarticulo').val('');
        $tr.find('.articulo_id_previo').val('');
        $tr.find('select.ms-color-id, select.ms-talle-id').val('').attr('data-selected', '');
        if (typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo($tr, 0);
        }
    }

    function recalcularModoDesdeLineas() {
        var modo = null;
        $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
            var $tr = $(this);
            var articuloId = parseInt($tr.find('input.articulo_id[name="articulos_id[]"]').val(), 10) || 0;
            if (articuloId <= 0) {
                return;
            }
            var maneja = String($tr.attr('data-maneja-stock-color-talle') || '') === '1';
            if (modo === null) {
                modo = maneja;
            }
        });
        setModo(modo);
    }

    /**
     * @returns {boolean} false si se rechazó el artículo
     */
    window.msAplicarExclusividadColorTalle = function (dataArticulo, $tr) {
        if (!$tr || !$tr.length || (window.movimientoStockModoFerli === true)) {
            return true;
        }

        var maneja = !!(dataArticulo && (dataArticulo.maneja_stock_color_talle === true
            || dataArticulo.maneja_stock_color_talle === 1
            || dataArticulo.maneja_stock_color_talle === '1'));

        $tr.attr('data-maneja-stock-color-talle', maneja ? '1' : '0');

        var modo = modoActual();
        if (modo === null) {
            setModo(maneja);
            poblarSelectsFila($tr);
            return true;
        }

        if (modo !== maneja) {
            alert(modo
                ? 'Este comprobante es de stock por color/talle. No puede cargar artículos sin esa gestión.'
                : 'Este comprobante no admite artículos con stock por color/talle. Cree otro movimiento.');
            limpiarArticuloFila($tr);
            recalcularModoDesdeLineas();
            return false;
        }

        poblarSelectsFila($tr);
        return true;
    };

    window.msRecalcularModoColorTalle = recalcularModoDesdeLineas;
    window.msPoblarSelectsColorTalleFila = poblarSelectsFila;

    $(document).on('change', '#tabla-items-movimientostock select.ms-color-id, #tabla-items-movimientostock select.ms-talle-id', function () {
        var $sel = $(this);
        $sel.attr('data-selected', $sel.val() || '');
        if (typeof window.msRefrescarSaldosOrigen === 'function') {
            window.msRefrescarSaldosOrigen();
        }
    });

    $(document).on('click', '#agrega_renglon, #tabla-items-movimientostock .eliminar', function () {
        setTimeout(function () {
            recalcularModoDesdeLineas();
            $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
                poblarSelectsFila($(this));
            });
        }, 100);
    });

    $(function () {
        if (window.movimientoStockModoFerli === true) {
            return;
        }

        $('#tabla-items-movimientostock tbody tr.item-pedido').each(function () {
            var $tr = $(this);
            var articuloId = parseInt($tr.find('input.articulo_id[name="articulos_id[]"]').val(), 10) || 0;
            var colorSel = $tr.find('select.ms-color-id').attr('data-selected') || '';
            var talleSel = $tr.find('select.ms-talle-id').attr('data-selected') || '';
            if (articuloId > 0 && (colorSel || talleSel)) {
                $tr.attr('data-maneja-stock-color-talle', '1');
            }
            poblarSelectsFila($tr);
        });

        var modoOld = String($('#modo_stock_color_talle').val() || '').trim();
        if (modoOld === '') {
            recalcularModoDesdeLineas();
        } else {
            aplicarVisibilidadModo();
        }
    });
}(jQuery));
