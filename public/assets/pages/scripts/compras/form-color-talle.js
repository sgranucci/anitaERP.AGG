(function ($) {
    'use strict';

    var CONTEXTOS = [
        {
            table: '#tabla-articulos-requisicion',
            row: 'tr.item-requisicion-articulo',
            articuloName: 'articulo_ids[]',
            addBtn: '#agrega_renglon_requisicion_articulo',
        },
        {
            table: '#tabla-articulos-ordencompra',
            row: 'tr.item-ordencompra-articulo',
            articuloName: 'articulo_ids[]',
            addBtn: '#agrega_renglon_ordencompra_articulo',
        },
    ];

    function ctxActivo($tr) {
        for (var i = 0; i < CONTEXTOS.length; i++) {
            var c = CONTEXTOS[i];
            if ($tr && $tr.length && $tr.closest(c.table).length) {
                return c;
            }
        }
        return null;
    }

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

        CONTEXTOS.forEach(function (c) {
            var $table = $(c.table);
            if (!$table.length) {
                return;
            }
            $table.find('.ms-col-color-talle').each(function () {
                if (activo) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            $table.find(c.row).each(function () {
                var $tr = $(this);
                poblarSelectsFila($tr);
                var $sels = $tr.find('select.ms-color-id, select.ms-talle-id');
                if (!activo) {
                    $sels.val('').attr('data-selected', '');
                }
            });
        });
    }

    function limpiarArticuloFila($tr, ctx) {
        $tr.find('input.articulo_id[name="' + ctx.articuloName + '"]').val('');
        $tr.find('.codigoarticulo').val('');
        $tr.find('.descripcionarticulo').val('');
        $tr.find('select.ms-color-id, select.ms-talle-id').val('').attr('data-selected', '');
        $tr.attr('data-maneja-stock-color-talle', '0');
    }

    function recalcularModoDesdeLineas() {
        var modo = null;
        CONTEXTOS.forEach(function (c) {
            $(c.table + ' ' + c.row).each(function () {
                var $tr = $(this);
                var articuloId = parseInt($tr.find('input.articulo_id[name="' + c.articuloName + '"]').val(), 10) || 0;
                if (articuloId <= 0) {
                    return;
                }
                var maneja = String($tr.attr('data-maneja-stock-color-talle') || '') === '1';
                if (modo === null) {
                    modo = maneja;
                }
            });
        });
        setModo(modo);
    }

    /**
     * @returns {boolean} false si se rechazó el artículo
     */
    window.msAplicarExclusividadColorTalle = function (dataArticulo, $tr) {
        var ctx = ctxActivo($tr);
        if (!ctx) {
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
                : 'Este comprobante no admite artículos con stock por color/talle. Cree otro comprobante.');
            limpiarArticuloFila($tr, ctx);
            recalcularModoDesdeLineas();
            return false;
        }

        poblarSelectsFila($tr);
        return true;
    };

    window.msRecalcularModoColorTalle = recalcularModoDesdeLineas;
    window.msPoblarSelectsColorTalleFila = poblarSelectsFila;

    $(document).on('change', 'select.ms-color-id, select.ms-talle-id', function () {
        var $sel = $(this);
        $sel.attr('data-selected', $sel.val() || '');
    });

    CONTEXTOS.forEach(function (c) {
        $(document).on('click', c.addBtn + ', ' + c.table + ' .eliminar', function () {
            setTimeout(function () {
                recalcularModoDesdeLineas();
                $(c.table + ' ' + c.row).each(function () {
                    poblarSelectsFila($(this));
                });
            }, 100);
        });
    });

    $(function () {
        var hayTabla = CONTEXTOS.some(function (c) {
            return $(c.table).length > 0;
        });
        if (!hayTabla) {
            return;
        }

        CONTEXTOS.forEach(function (c) {
            $(c.table + ' ' + c.row).each(function () {
                var $tr = $(this);
                var articuloId = parseInt($tr.find('input.articulo_id[name="' + c.articuloName + '"]').val(), 10) || 0;
                var colorSel = $tr.find('select.ms-color-id').attr('data-selected') || '';
                var talleSel = $tr.find('select.ms-talle-id').attr('data-selected') || '';
                if (articuloId > 0 && (colorSel || talleSel)) {
                    $tr.attr('data-maneja-stock-color-talle', '1');
                }
                poblarSelectsFila($tr);
            });
        });

        var modoOld = String($('#modo_stock_color_talle').val() || '').trim();
        if (modoOld === '') {
            recalcularModoDesdeLineas();
        } else {
            aplicarVisibilidadModo();
        }
    });
}(jQuery));
