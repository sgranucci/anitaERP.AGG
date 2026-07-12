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
                    var texto = (respuesta.resultado && respuesta.resultado.mensaje)
                        ? respuesta.resultado.mensaje
                        : 'Registro revertido correctamente.';
                    if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
                        Biblioteca.notificaciones(texto, 'anitaERP', 'success');
                    }
                    window.location.reload();
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
