(function ($) {
    'use strict';

    function esModoAltaNpu() {
        var meta = typeof msTipoTransaccionMeta === 'function' ? msTipoTransaccionMeta() : {};

        return !!meta.altaNpu;
    }

    function aplicarModoAltaNpuEnTabla() {
        var activo = esModoAltaNpu();
        var $tabla = $('#tabla-items-movimientostock');
        if (!$tabla.length) {
            return;
        }

        $tabla.toggleClass('ms-tabla-alta-npu', activo);
        $('#ms-ayuda-alta-npu').toggle(activo);

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
            });
            if (typeof window.msRefrescarPreciosTodasLasFilas === 'function') {
                window.msRefrescarPreciosTodasLasFilas();
            }
        } else if (typeof window.msEsModoBajaNpu !== 'function' || !window.msEsModoBajaNpu()) {
            $tabla.find('.cant-unidad').prop('readonly', false);
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
