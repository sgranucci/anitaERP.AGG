(function ($) {
    'use strict';

    var callbackPendiente = null;

    function $modal() {
        return $('#modalAvisoTransferencia');
    }

    /**
     * Pregunta al usuario si la transferencia va con aviso.
     * callback(true)  → enviar aviso (queda pendiente de aprobación)
     * callback(false) → transferir directo (sin aviso)
     * callback(null)  → cancelado
     */
    window.msPreguntarEnvioAviso = function (callback) {
        var $m = $modal();
        if (!$m.length) {
            if (typeof callback === 'function') {
                callback(false);
            }
            return;
        }

        // Si ya está abierto (doble evento submit), ignorar la segunda llamada.
        if ($m.hasClass('show')) {
            return;
        }

        callbackPendiente = callback || null;
        $m.modal('show');
    };

    function resolver(valor) {
        var fn = callbackPendiente;
        callbackPendiente = null;
        $modal().modal('hide');
        if (typeof fn === 'function') {
            fn(valor);
        }
    }

    function cancelar() {
        var fn = callbackPendiente;
        callbackPendiente = null;
        if (typeof fn === 'function') {
            fn(null);
        }
    }

    $(document).on('click', '#btn_aviso_transferencia_si', function () {
        resolver(true);
    });

    $(document).on('click', '#btn_aviso_transferencia_no', function () {
        resolver(false);
    });

    $(document).on('click', '#btn_aviso_transferencia_cancelar', function () {
        cancelar();
    });

    // Cierre por backdrop / X / ESC: tratar como cancelación si quedó callback.
    $(document).on('hidden.bs.modal', '#modalAvisoTransferencia', function () {
        if (callbackPendiente) {
            cancelar();
        }
    });
})(jQuery);
