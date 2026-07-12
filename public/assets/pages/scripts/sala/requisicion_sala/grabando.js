/**
 * Banner centrado al grabar / aprobar requisici&oacute;n de sala.
 */
(function ($) {
    'use strict';

    var enviandoGrabacion = false;

    function asegurarBannerGrabandoRequisicionSala() {
        if ($('#requisicion-sala-banner-grabando').length) {
            return;
        }
        var html = '<div id="requisicion-sala-banner-grabando" class="requisicion-sala-grabando-overlay" role="status" aria-live="polite" aria-busy="true">';
        html += '<div class="alert alert-warning shadow requisicion-sala-grabando-banner mb-0 px-4 py-3">';
        html += '<div class="requisicion-sala-grabando-spinner-wrap" aria-hidden="true">';
        html += '<div class="spinner-border text-dark" role="status"><span class="sr-only">Cargando&hellip;</span></div>';
        html += '</div>';
        html += '<strong id="requisicion-sala-banner-grabando-titulo" class="d-block mb-2 text-dark">Grabando requisici&oacute;n de sala&hellip;</strong>';
        html += '<span id="requisicion-sala-banner-grabando-subtitulo" class="small d-block text-dark">';
        html += 'Validaci&oacute;n del &aacute;rbol de aprobaci&oacute;n y env&iacute;o de avisos.<br>Por favor espere; puede tardar varios minutos.';
        html += '</span></div></div>';
        $('body').append(html);
    }

    function mostrarBannerGrabandoRequisicionSala(opciones) {
        if (enviandoGrabacion) {
            return false;
        }
        enviandoGrabacion = true;
        var opts = opciones || {};
        asegurarBannerGrabandoRequisicionSala();
        if (opts.titulo) {
            $('#requisicion-sala-banner-grabando-titulo').text(opts.titulo);
        }
        if (opts.subtitulo) {
            $('#requisicion-sala-banner-grabando-subtitulo').html(opts.subtitulo);
        }
        $('#requisicion-sala-banner-grabando').addClass('is-visible');
        return true;
    }

    function ocultarBannerGrabandoRequisicionSala() {
        enviandoGrabacion = false;
        $('#requisicion-sala-banner-grabando').removeClass('is-visible');
    }

    window.RequisicionSalaGrabando = {
        mostrar: mostrarBannerGrabandoRequisicionSala,
        ocultar: ocultarBannerGrabandoRequisicionSala,
    };

    $(window).on('pageshow', function (event) {
        if (event.originalEvent && event.originalEvent.persisted) {
            ocultarBannerGrabandoRequisicionSala();
        }
    });
}(jQuery));
