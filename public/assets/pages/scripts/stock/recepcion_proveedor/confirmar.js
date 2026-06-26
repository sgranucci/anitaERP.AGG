/**
 * Banner y anti-doble-submit al confirmar recepci&oacute;n (formulario editar e index).
 */
(function ($) {
    'use strict';

    var enviandoConfirmacion = false;

    function asegurarBannerConfirmandoRecepcion() {
        if ($('#recepcion-proveedor-banner-confirmando').length) {
            return;
        }
        var html = '<div id="recepcion-proveedor-banner-confirmando" class="recepcion-proveedor-confirmando-overlay" role="status" aria-live="polite" aria-busy="true">';
        html += '<div class="alert alert-warning shadow recepcion-proveedor-confirmando-banner mb-0 px-4 py-3">';
        html += '<div class="recepcion-proveedor-confirmando-spinner-wrap" aria-hidden="true">';
        html += '<div class="spinner-border text-dark" role="status"><span class="sr-only">Cargando&hellip;</span></div>';
        html += '</div>';
        html += '<strong class="d-block mb-2 text-dark">Confirmando recepci&oacute;n&hellip;</strong>';
        html += '<span class="small d-block text-dark">Generando movimiento de stock, asiento contable y registros Anita.<br>Por favor espere; puede tardar varios minutos.</span>';
        html += '</div></div>';
        $('body').append(html);
    }

    function deshabilitarBotonesConfirmacion(selectoresExtra) {
        var selectores = [
            'form.form-confirmar-recepcion button[type="submit"]',
            '#btn-confirmar-recepcion-proveedor',
            '#btn-modal-confirmar-recepcion-aceptar',
            'button[form="form-recepcion-confirmar"]',
        ];
        if (selectoresExtra) {
            selectores = selectores.concat(selectoresExtra);
        }
        $(selectores.join(', ')).prop('disabled', true).addClass('disabled');
    }

    function mostrarBannerConfirmandoRecepcion(selectoresExtra) {
        if (enviandoConfirmacion) {
            return false;
        }
        enviandoConfirmacion = true;
        asegurarBannerConfirmandoRecepcion();
        $('#recepcion-proveedor-banner-confirmando').addClass('is-visible');
        deshabilitarBotonesConfirmacion(selectoresExtra);
        return true;
    }

    function enviarConfirmacionRecepcion($formConfirmar) {
        if (!$formConfirmar || !$formConfirmar.length) {
            return;
        }
        if (!mostrarBannerConfirmandoRecepcion()) {
            return;
        }
        window.setTimeout(function () {
            $formConfirmar.get(0).submit();
        }, 50);
    }

    function initConfirmacionDesdeListado() {
        $(document).on('submit', 'form.form-confirmar-recepcion', function (e) {
            e.preventDefault();
            if (enviandoConfirmacion) {
                return false;
            }
            var $form = $(this);
            var msg = $form.data('confirmMsg') || '¿Confirmar recepción? Generará movimiento de stock y asiento contable.';
            if (!window.confirm(msg)) {
                return false;
            }
            enviarConfirmacionRecepcion($form);
            return false;
        });
    }

    window.RecepcionProveedorConfirmar = {
        enviarConfirmacionRecepcion: enviarConfirmacionRecepcion,
        mostrarBannerConfirmandoRecepcion: mostrarBannerConfirmandoRecepcion,
    };

    $(function () {
        initConfirmacionDesdeListado();
    });
})(jQuery);
