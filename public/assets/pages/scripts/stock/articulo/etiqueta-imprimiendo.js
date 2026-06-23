(function ($) {
    'use strict';

    var overlayTimer = null;
    var overlayId = 'articulo-etiqueta-imprimiendo-overlay';
    var ajaxImpresionActiva = null;

    function toast(msg, type) {
        var t = type || 'info';
        if (window.toastr) {
            var opts =
                t === 'success'
                    ? { timeOut: 4500, progressBar: true }
                    : { timeOut: 9000, extendedTimeOut: 4000, closeButton: true, progressBar: true };
            toastr[t](msg, '', opts);
        } else {
            alert(msg);
        }
    }

    function mostrarOverlay(titulo, subtitulo) {
        var $ov = $('#' + overlayId);
        if (!$ov.length) {
            return;
        }
        $('#articulo-etiqueta-imprimiendo-titulo').text(titulo || 'Imprimiendo etiqueta…');
        $('#articulo-etiqueta-imprimiendo-subtitulo').text(
            subtitulo || 'Por favor espere. Se está enviando la etiqueta a la impresora.',
        );
        $ov.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function ocultarOverlay() {
        if (overlayTimer) {
            clearInterval(overlayTimer);
            overlayTimer = null;
        }
        var $ov = $('#' + overlayId);
        if ($ov.length) {
            $ov.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
        }
        $('body').css('overflow', '');
    }

    function iniciarOverlayAnimado(tituloDefault) {
        ocultarOverlay();
        var mensajes = [
            tituloDefault || 'Imprimiendo etiqueta…',
            'Generando código ZPL…',
            'Enviando a la impresora…',
            'Espere un momento…',
        ];
        var idx = 0;
        mostrarOverlay(mensajes[0]);
        overlayTimer = setInterval(function () {
            idx = (idx + 1) % mensajes.length;
            $('#articulo-etiqueta-imprimiendo-titulo').text(mensajes[idx]);
        }, 2200);
    }

    function imprimirEtiquetaArticulo(url) {
        if (!url) {
            return false;
        }

        if (ajaxImpresionActiva) {
            ajaxImpresionActiva.abort();
        }

        iniciarOverlayAnimado('Imprimiendo etiqueta…');

        ajaxImpresionActiva = $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            timeout: 90000,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .always(function () {
                ajaxImpresionActiva = null;
                ocultarOverlay();
            })
            .done(function (data) {
                if (data && data.ok) {
                    toast(data.mensaje || 'Etiqueta impresa con éxito.', 'success');
                    return;
                }
                var err =
                    data && data.errores && data.errores.length
                        ? data.errores.join(' ')
                        : (data && data.mensaje) || 'No se pudo imprimir la etiqueta.';
                toast(err, 'warning');
            })
            .fail(function (xhr, textStatus) {
                if (textStatus === 'abort') {
                    return;
                }
                var msg = 'No se pudo imprimir la etiqueta.';
                if (textStatus === 'timeout') {
                    msg =
                        'La impresión tardó demasiado en responder. Verifique si la etiqueta salió en la impresora.';
                } else if (xhr.responseJSON) {
                    if (xhr.responseJSON.errores && xhr.responseJSON.errores.length) {
                        msg = xhr.responseJSON.errores.join(' ');
                    } else if (xhr.responseJSON.mensaje) {
                        msg = xhr.responseJSON.mensaje;
                    }
                }
                toast(msg, 'warning');
            });

        return false;
    }

    window.imprimirEtiquetaArticulo = imprimirEtiquetaArticulo;
})(jQuery);
