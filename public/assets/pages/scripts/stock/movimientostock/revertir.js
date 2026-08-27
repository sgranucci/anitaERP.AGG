(function ($) {
    'use strict';

    function mensajeErrorRevertir(respuesta, xhr) {
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
        return 'No se pudo revertir el registro.';
    }

    function ejecutarRevertir($form) {
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function (respuesta) {
                if (respuesta && respuesta.mensaje === 'ok') {
                    irTrasRevertirOk(respuesta.redirect || $form.data('url-index') || '');
                    return;
                }
                if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
                    Biblioteca.notificaciones(mensajeErrorRevertir(respuesta), 'anitaERP', 'error');
                }
            },
            error: function (xhr) {
                if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
                    Biblioteca.notificaciones(mensajeErrorRevertir(null, xhr), 'anitaERP', 'error');
                }
            }
        });
    }

    function paginaListadoEsPrimera() {
        var page = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);

        return !page || page <= 1;
    }

    function estaEnIndexListado() {
        return $('#form-filtros-movimientostock').length > 0;
    }

    function mismaUrlActual(destino) {
        if (!destino) {
            return true;
        }
        try {
            var actual = new URL(window.location.href);
            var dest = new URL(destino, window.location.origin);
            return actual.pathname === dest.pathname && actual.search === dest.search;
        } catch (e) {
            return destino === window.location.href
                || destino === (window.location.pathname + window.location.search);
        }
    }

    function irTrasRevertirOk(destino) {
        if (estaEnIndexListado() && (paginaListadoEsPrimera() || mismaUrlActual(destino))) {
            window.location.reload();
            return;
        }
        if (destino) {
            window.location.href = destino;
            return;
        }
        window.location.reload();
    }

    function confirmarRevertir(event) {
        event.preventDefault();
        var $form = $(this);

        swal({
            title: '¿Revertir este registro?',
            text: 'Se generarán movimientos inversos. Si es contable, también un asiento al revés. El original quedará inactivo.',
            icon: 'warning',
            buttons: {
                cancel: 'Cancelar',
                confirm: 'Aceptar'
            }
        }).then(function (value) {
            if (value) {
                ejecutarRevertir($form);
            }
        });
    }

    $(function () {
        $(document).on('submit', '.form-revertir-movstock', confirmarRevertir);
    });
}(jQuery));
