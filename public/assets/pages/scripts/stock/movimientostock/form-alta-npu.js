(function ($) {
    'use strict';

    var PLACEHOLDER_ALTA = 'Autom.';
    var TITLE_ALTA = 'NPU a registrar. Vac\u00edo = el sistema genera el siguiente n\u00famero.';

    function esModoAltaNpu() {
        var meta = typeof msTipoTransaccionMeta === 'function' ? msTipoTransaccionMeta() : {};

        return !!meta.altaNpu;
    }

    function npuDeFila($tr) {
        return ($tr.find('.numeroparte-baja-linea').val() || '').toString().trim();
    }

    function normalizarNpuFila($tr) {
        var $npu = $tr.find('.numeroparte-baja-linea');
        var valor = npuDeFila($tr).replace(/\D+/g, '');
        $npu.val(valor);

        return valor;
    }

    function aplicarCantidadSegunNpu($tr) {
        var $cant = $tr.find('.cantidad-stock');
        if (npuDeFila($tr) !== '') {
            $cant.val('1').prop('readonly', true);
            return;
        }

        $cant.prop('readonly', false);
    }

    function aplicarModoAltaNpuEnTabla() {
        var activo = esModoAltaNpu();
        var $tabla = $('#tabla-items-movimientostock');
        if (!$tabla.length) {
            return;
        }

        $tabla.toggleClass('ms-tabla-alta-npu', activo);
        $('#ms-ayuda-alta-npu').toggle(activo);

        if (typeof window.msActualizarVisibilidadColumnaNpu === 'function') {
            window.msActualizarVisibilidadColumnaNpu();
        }

        if (activo) {
            $tabla.find('tr.item-pedido').each(function () {
                var $tr = $(this);
                var $cant = $tr.find('.cantidad-stock');
                var raw = ($cant.val() || '').toString().replace(',', '.');
                var n = parseFloat(raw);
                if (isFinite(n) && n > 0) {
                    $cant.val(String(Math.max(1, Math.round(n))));
                }
                $tr.find('.cant-unidad').val('').prop('readonly', true);
                $tr.find('.numeroparte-baja-linea')
                    .attr('placeholder', PLACEHOLDER_ALTA)
                    .attr('title', TITLE_ALTA);
                $tr.find('.ms-npu-consulta-btn').hide();
                aplicarCantidadSegunNpu($tr);
            });
            if (typeof window.msRefrescarPreciosTodasLasFilas === 'function') {
                window.msRefrescarPreciosTodasLasFilas();
            }
            return;
        }

        $tabla.find('.numeroparte-baja-linea').removeAttr('placeholder').removeAttr('title');
        $tabla.find('.ms-npu-consulta-btn').show();

        if (typeof window.msEsModoBajaNpu !== 'function' || !window.msEsModoBajaNpu()) {
            $tabla.find('.cant-unidad').prop('readonly', false);
            $tabla.find('.cantidad-stock').prop('readonly', false);
        }
    }

    $(document).on('change', '#tipotransaccion_stock_id', function () {
        aplicarModoAltaNpuEnTabla();
    });

    $(document).on('blur', '#tabla-items-movimientostock .cantidad-stock', function () {
        if (!esModoAltaNpu()) {
            return;
        }
        var $cant = $(this);
        var raw = ($cant.val() || '').toString().replace(',', '.');
        var n = parseFloat(raw);
        if (!isFinite(n) || n <= 0) {
            return;
        }
        $cant.val(String(Math.max(1, Math.round(n))));
    });

    $(document).on('blur', '.numeroparte-baja-linea', function () {
        if (!esModoAltaNpu()) {
            return;
        }
        var $tr = $(this).closest('tr.item-pedido');
        normalizarNpuFila($tr);
        aplicarCantidadSegunNpu($tr);
    });

    $(document).on('keydown', '.numeroparte-baja-linea', function (e) {
        if (e.which !== 13 || !esModoAltaNpu()) {
            return;
        }
        e.preventDefault();
        var $tr = $(this).closest('tr.item-pedido');
        normalizarNpuFila($tr);
        aplicarCantidadSegunNpu($tr);

        var $siguiente = $tr.find('.codigoarticulo');
        if ($siguiente.length && !($siguiente.val() || '').trim()) {
            $siguiente.trigger('focus').trigger('select');
        }
    });

    $(document).on('click', '#agrega_renglon', function () {
        setTimeout(aplicarModoAltaNpuEnTabla, 50);
    });

    $(function () {
        if (!$('#tabla-items-movimientostock').length) {
            return;
        }
        setTimeout(aplicarModoAltaNpuEnTabla, 220);
    });

    window.msAplicarModoAltaNpuEnTabla = aplicarModoAltaNpuEnTabla;
    window.msEsModoAltaNpu = esModoAltaNpu;
}(jQuery));
