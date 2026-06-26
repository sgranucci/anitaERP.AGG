(function ($) {
    'use strict';

    function meta() {
        return typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta()
            : { nombre: '', operacion: '' };
    }

    function nombreTipoSeleccionado() {
        return meta().nombre || '';
    }

    function tituloGrabando() {
        var operacion = meta().operacion || '';
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
