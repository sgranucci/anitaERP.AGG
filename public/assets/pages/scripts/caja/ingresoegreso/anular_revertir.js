(function ($) {
    'use strict';

    var OVERLAY_ID = 'ie-anular-revertir-procesando-overlay';
    var enCurso = false;

    function mensajeError(respuesta, xhr, fallback) {
        if (respuesta && respuesta.mensaje && respuesta.mensaje !== 'ok') {
            return respuesta.mensaje;
        }
        if (xhr && xhr.responseJSON) {
            if (xhr.responseJSON.mensaje) {
                return xhr.responseJSON.mensaje;
            }
            if (xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
        }
        return fallback || 'No se pudo completar la operación.';
    }

    function notificar(texto, tipo) {
        if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(texto, 'anitaERP', tipo || 'success');
        } else {
            alert(texto);
        }
    }

    function asegurarOverlay() {
        var $ov = $('#' + OVERLAY_ID);
        if ($ov.length) {
            return $ov;
        }

        $ov = $(
            '<div id="' + OVERLAY_ID + '" class="d-none" aria-hidden="true" role="alert"' +
            ' style="position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;' +
            'background:rgba(23,32,42,0.55);">' +
            '<div style="max-width:420px;margin:1rem;padding:1.25rem 1.5rem;border-radius:6px;' +
            'background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.25);text-align:center;">' +
            '<div class="mb-2"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i></div>' +
            '<div class="font-weight-bold ie-ar-titulo">Procesando…</div>' +
            '<div class="small text-muted mt-2 ie-ar-detalle">No cierre ni recargue la pantalla.</div>' +
            '</div></div>'
        );
        $('body').append($ov);
        return $ov;
    }

    function mostrarProcesando(titulo, detalle) {
        var $ov = asegurarOverlay();
        $ov.find('.ie-ar-titulo').text(titulo || 'Procesando…');
        $ov.find('.ie-ar-detalle').text(
            detalle || 'Puede demorar unos segundos (ERP + Anita). No cierre ni recargue la pantalla.'
        );
        $ov.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
        $('body').css('cursor', 'wait');
    }

    function ocultarProcesando() {
        var $ov = $('#' + OVERLAY_ID);
        if ($ov.length) {
            $ov.addClass('d-none').css('display', 'none').attr('aria-hidden', 'true');
        }
        $('body').css('cursor', '');
    }

    function setBotonesForm($form, deshabilitar) {
        $form.find('button[type="submit"], input[type="submit"]').prop('disabled', !!deshabilitar);
    }

    function postForm($form, confirmMsg, tituloProceso, okMsg) {
        if (enCurso) {
            return;
        }
        if (!window.confirm(confirmMsg)) {
            return;
        }

        enCurso = true;
        setBotonesForm($form, true);
        mostrarProcesando(tituloProceso);

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            timeout: 180000,
            success: function (respuesta) {
                if (respuesta && respuesta.mensaje === 'ok') {
                    var texto = okMsg;
                    if (respuesta.resultado && respuesta.resultado.numerotransaccion) {
                        texto += ' Anulación N° ' + respuesta.resultado.numerotransaccion + '.';
                    }
                    // Evitar toast amarillo/verde que “cuelga” mientras recarga: el overlay ya informa.
                    mostrarProcesando('Listo', texto + ' Actualizando el listado…');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 400);
                    return;
                }
                enCurso = false;
                setBotonesForm($form, false);
                ocultarProcesando();
                notificar(mensajeError(respuesta, null), 'error');
            },
            error: function (xhr) {
                enCurso = false;
                setBotonesForm($form, false);
                ocultarProcesando();
                notificar(mensajeError(null, xhr), 'error');
            }
        });
    }

    $(document).on('submit', '.form-anular-fisico-ie', function (event) {
        event.preventDefault();
        postForm(
            $(this),
            '¿Anular físicamente esta OP? Se borra en ERP y Anita, y la solicitud vuelve a AUTORIZADA.',
            'Anulando OP…',
            'OP anulada físicamente.'
        );
    });

    $(document).on('submit', '.form-revertir-ie', function (event) {
        event.preventDefault();
        postForm(
            $(this),
            '¿Revertir esta OP? Se genera compensatorio con asiento y Anita invertidos; la SP vuelve a AUTORIZADA. La OP original no se borra.',
            'Revirtiendo OP…',
            'OP revertida.'
        );
    });

    // API para el modal de editar (crear.js)
    window.IngresoEgresoAnularRevertirUi = {
        mostrarProcesando: mostrarProcesando,
        ocultarProcesando: ocultarProcesando,
        estaEnCurso: function () {
            return enCurso;
        },
        setEnCurso: function (v) {
            enCurso = !!v;
        }
    };
})(jQuery);
