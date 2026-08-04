(function ($) {
    'use strict';

    var TABLA = '#tabla-recuento-items';
    var FILA = 'tr.recuento-item-row';

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
        $(TABLA + ' .ms-col-color-talle').each(function () {
            if (activo) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        $(TABLA + ' tbody ' + FILA).each(function () {
            var $tr = $(this);
            poblarSelectsFila($tr);
            var $sels = $tr.find('select.ms-color-id, select.ms-talle-id');
            if (!activo) {
                $sels.val('').attr('data-selected', '');
            }
        });
    }

    function limpiarArticuloFila($tr) {
        var tr = $tr[0];
        if (!tr) {
            return;
        }
        (tr.querySelector('.recuento_item_id') || {}).value = '';
        (tr.querySelector('.articulo_id') || {}).value = '';
        (tr.querySelector('.codigoarticulo') || {}).value = '';
        (tr.querySelector('.descripcionarticulo') || {}).value = '';
        (tr.querySelector('.unidadmedida_id') || {}).value = '';
        (tr.querySelector('.saldo_sistema_input') || {}).value = '';
        (tr.querySelector('.input-cantidad-contada') || {}).value = '';
        var um = tr.querySelector('.unidad-medida-label');
        if (um) um.textContent = '—';
        var spanSaldo = tr.querySelector('.saldo-deposito');
        if (spanSaldo) spanSaldo.textContent = '—';
        var td = tr.querySelector('.diferencia-linea');
        if (td) {
            td.textContent = '—';
            td.classList.remove('text-danger');
        }
        $tr.find('select.ms-color-id, select.ms-talle-id').val('').attr('data-selected', '');
        $tr.attr('data-maneja-stock-color-talle', '0');
        var link = tr.querySelector('.btn-link-articulo');
        if (link) link.classList.add('d-none');
    }

    function recalcularModoDesdeLineas() {
        var modo = null;
        $(TABLA + ' tbody ' + FILA).each(function () {
            var $tr = $(this);
            var articuloId = parseInt($tr.find('input.articulo_id').val(), 10) || 0;
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
        if (!$tr || !$tr.length) {
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
                ? 'Este recuento es de stock por color/talle. No puede cargar artículos sin esa gestión.'
                : 'Este recuento no admite artículos con stock por color/talle. Cree otro recuento.');
            limpiarArticuloFila($tr);
            recalcularModoDesdeLineas();
            return false;
        }

        poblarSelectsFila($tr);
        return true;
    };

    window.msRecalcularModoColorTalle = recalcularModoDesdeLineas;
    window.msPoblarSelectsColorTalleFila = poblarSelectsFila;
    window.msAplicarVisibilidadModoColorTalle = aplicarVisibilidadModo;

    $(document).on('change', TABLA + ' select.ms-color-id, ' + TABLA + ' select.ms-talle-id', function () {
        var $sel = $(this);
        $sel.attr('data-selected', $sel.val() || '');
        var tr = $sel.closest(FILA)[0];
        if (tr && typeof window.recuentoRefrescarSaldoFila === 'function') {
            window.recuentoRefrescarSaldoFila(tr);
        }
    });

    $(document).on('click', '#btn-agregar-item-recuento, ' + TABLA + ' .btn-eliminar-item', function () {
        setTimeout(function () {
            recalcularModoDesdeLineas();
            $(TABLA + ' tbody ' + FILA).each(function () {
                poblarSelectsFila($(this));
            });
        }, 100);
    });

    $(function () {
        if (!$(TABLA).length) {
            return;
        }

        $(TABLA + ' tbody ' + FILA).each(function () {
            var $tr = $(this);
            var articuloId = parseInt($tr.find('input.articulo_id').val(), 10) || 0;
            var colorSel = $tr.find('select.ms-color-id').attr('data-selected') || '';
            var talleSel = $tr.find('select.ms-talle-id').attr('data-selected') || '';
            if (articuloId > 0 && (colorSel || talleSel || String($tr.attr('data-maneja-stock-color-talle')) === '1')) {
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
