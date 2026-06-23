(function ($) {
    'use strict';

    function nombreTipoSeleccionado() {
        var $opt = $('#tipotransaccion_stock_id option:selected');
        var text = ($opt.text() || '').trim();

        if (!text || text.indexOf('-- Seleccionar') === 0) {
            return '';
        }

        return text;
    }

    function tituloGrabando() {
        var $opt = $('#tipotransaccion_stock_id option:selected');
        var operacion = String($opt.data('operacion') || '');
        var nombre = nombreTipoSeleccionado();

        if (operacion === 'T') {
            return nombre !== ''
                ? 'Grabando transferencia «' + nombre + '»…'
                : 'Grabando transferencia…';
        }

        return nombre !== ''
            ? 'Grabando movimiento «' + nombre + '»…'
            : 'Grabando movimiento de stock…';
    }

    function mostrarOverlayGrabando() {
        if (!window.PedidoProcesoOverlay || typeof PedidoProcesoOverlay.iniciar !== 'function') {
            return;
        }

        PedidoProcesoOverlay.iniciar(
            [
                'Grabando movimiento de stock…',
                'Registrando líneas de artículos…',
                'Actualizando stock…',
            ],
            tituloGrabando()
        );
        $('#formgeneral button[type="submit"]').prop('disabled', true);
    }

    $(function () {
        var $form = $('#formgeneral');
        if (!$form.length) {
            return;
        }

        $form.on('submit', function () {
            mostrarOverlayGrabando();
        });
    });
}(jQuery));
