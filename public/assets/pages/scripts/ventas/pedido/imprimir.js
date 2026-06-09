(function ($) {
    'use strict';

    var mensajesProceso = [
        'Generando PDF del pedido…',
        'Enviando a la impresora…',
        'Espere un momento…',
    ];

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

    window.imprimirPedidoListado = function (pedidoId) {
        if (!pedidoId) {
            return false;
        }

        if (window.PedidoProcesoOverlay) {
            PedidoProcesoOverlay.iniciar(mensajesProceso, 'Imprimiendo pedido…');
        }

        $.ajax({
            url: carpetaBase + '/ventas/listarpedido/' + encodeURIComponent(pedidoId),
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .done(function (data) {
                if (window.PedidoProcesoOverlay) {
                    PedidoProcesoOverlay.detener();
                }
                if (data && data.ok) {
                    toast(data.mensaje || 'Impresión exitosa.', 'success');
                    return;
                }
                toast((data && data.mensaje) || 'No se pudo imprimir el pedido.', 'warning');
            })
            .fail(function (xhr) {
                if (window.PedidoProcesoOverlay) {
                    PedidoProcesoOverlay.detener();
                }
                var msg = 'No se pudo imprimir el pedido.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                toast(msg, 'warning');
            });

        return false;
    };

    $(function () {
        $(document).on('click', '.btn-imprimir-pedido-listado', function (e) {
            e.preventDefault();
            imprimirPedidoListado($(this).data('pedido-id'));
        });
    });
})(jQuery);
