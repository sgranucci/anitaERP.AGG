/**
 * Banner y anti-doble-submit al confirmar requisici&oacute;n provisoria.
 */
(function ($) {
    'use strict';

    var enviandoConfirmacion = false;

    function asegurarBannerConfirmandoRequisicion() {
        if ($('#requisicion-banner-confirmando').length) {
            return;
        }
        var html = '<div id="requisicion-banner-confirmando" class="requisicion-confirmando-overlay" role="status" aria-live="polite" aria-busy="true">';
        html += '<div class="alert alert-warning shadow requisicion-confirmando-banner mb-0 px-4 py-3">';
        html += '<div class="requisicion-confirmando-spinner-wrap" aria-hidden="true">';
        html += '<div class="spinner-border text-dark" role="status"><span class="sr-only">Cargando&hellip;</span></div>';
        html += '</div>';
        html += '<strong class="d-block mb-2 text-dark">Confirmando requisici&oacute;n&hellip;</strong>';
        html += '<span class="small d-block text-dark">Validando datos, enviando al &aacute;rbol de aprobaci&oacute;n y sincronizando con Anita.<br>Por favor espere; puede tardar varios minutos.</span>';
        html += '</div></div>';
        $('body').append(html);
    }

    function deshabilitarBotonesConfirmacion() {
        $([
            'form.form-confirmar-requisicion button[type="submit"]',
            '#btn-confirmar-requisicion-provisorio',
            'button[form="form-requisicion-confirmar"]',
        ].join(', ')).prop('disabled', true).addClass('disabled');
    }

    function mostrarBannerConfirmandoRequisicion() {
        if (enviandoConfirmacion) {
            return false;
        }
        enviandoConfirmacion = true;
        asegurarBannerConfirmandoRequisicion();
        $('#requisicion-banner-confirmando').addClass('is-visible');
        deshabilitarBotonesConfirmacion();
        return true;
    }

    function enviarConfirmacionRequisicion($formConfirmar) {
        if (!$formConfirmar || !$formConfirmar.length) {
            return;
        }
        if (!mostrarBannerConfirmandoRequisicion()) {
            return;
        }
        window.setTimeout(function () {
            $formConfirmar.get(0).submit();
        }, 50);
    }

    function initConfirmacionDesdeListado() {
        $(document).on('submit', 'form.form-confirmar-requisicion', function (e) {
            e.preventDefault();
            if (enviandoConfirmacion) {
                return false;
            }
            var $form = $(this);
            var msg = $form.data('confirmMsg') || '¿Confirmar requisición? Enviará al árbol de aprobación y sincronizará con Anita.';
            if (!window.confirm(msg)) {
                return false;
            }
            enviarConfirmacionRequisicion($form);
            return false;
        });
    }

    window.RequisicionConfirmar = {
        enviarConfirmacionRequisicion: enviarConfirmacionRequisicion,
        mostrarBannerConfirmandoRequisicion: mostrarBannerConfirmandoRequisicion,
    };

    $(function () {
        initConfirmacionDesdeListado();

        $(document).on('click', '#btn-confirmar-requisicion-provisorio, button[form="form-requisicion-confirmar"]', function (e) {
            e.preventDefault();
            if (enviandoConfirmacion) {
                return false;
            }
            var msg = '¿Confirmar requisición? Enviará al árbol de aprobación y sincronizará con Anita.';
            if (!window.confirm(msg)) {
                return false;
            }
            var $form = $('#form-requisicion-confirmar');
            enviarConfirmacionRequisicion($form);
            return false;
        });
    });
})(jQuery);
