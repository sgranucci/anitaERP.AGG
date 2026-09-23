(function ($) {
    'use strict';

    function esCanje() {
        return typeof window.msOperacionTipoTransaccion === 'function'
            && window.msOperacionTipoTransaccion() === 'C';
    }

    function pintarFila($tr) {
        if (!$tr || !$tr.length) {
            return;
        }
        var sentido = String($tr.find('.ms-sentido-canje').val() || 'E').toUpperCase();
        $tr.removeClass('table-success table-danger ms-canje-entra ms-canje-sale');
        if (!esCanje()) {
            return;
        }
        if (sentido === 'S') {
            $tr.addClass('table-danger ms-canje-sale');
        } else {
            $tr.addClass('table-success ms-canje-entra');
        }
    }

    window.msActualizarModoCanje = function () {
        var activo = esCanje();
        var $tabla = $('#tabla-items-movimientostock');
        $tabla.toggleClass('ms-modo-canje', activo);
        $tabla.find('.ms-col-canje-sentido').toggle(activo);
        $('#ms_aviso_canje').toggle(activo);

        $tabla.find('tr.item-pedido').each(function () {
            pintarFila($(this));
        });

        if (typeof window.actualizarPanelesTransferencia === 'function') {
            // Canje usa depósito simple; el panel T no debe quedar abierto.
        }
    };

    $(document).on('change', '#tipotransaccion_stock_id', function () {
        window.setTimeout(window.msActualizarModoCanje, 0);
    });

    $(document).on('change', '.ms-sentido-canje', function () {
        pintarFila($(this).closest('tr'));
    });

    $(function () {
        window.msActualizarModoCanje();
    });
})(jQuery);
