$(document).ready(function () {
    $('#form-importar-facturas').on('submit', function (e) {
        e.preventDefault();

        var token = $("meta[name='csrf-token']").attr('content');
        var url = $(this).data('action');
        var desdefecha = $('#desdefecha').val();
        var hastafecha = $('#hastafecha').val();
        var datosfacturas = [];
        var filasSinMedioPago = 0;

        $('#tabla-factura .filafactura').each(function () {
            var fila = $(this);
            var mediopago = fila.find('.mediopago').val();

            if (!mediopago) {
                filasSinMedioPago++;
            }

            datosfacturas.push({
                tipocomprobante: fila.find('.tipocomprobante').val(),
                prefijo: fila.find('.prefijo').val(),
                numero: fila.find('.numero').val(),
                condicionventa: fila.find('.condicionventa').val(),
                fechahora: fila.find('.fechahora').val(),
                total: fila.find('.total').val(),
                totalneto: fila.find('.totalneto').val(),
                iva1: fila.find('.iva1').val(),
                iva2: fila.find('.iva2').val(),
                subtotalnoalcanzado: fila.find('.subnoalc').val(),
                subtotalexcento: fila.find('.subexcento').val(),
                totalpercepcioniibb: fila.find('.totalprecepcioniibb').val(),
                item: fila.find('.factura-items').val(),
                cae: fila.find('.factura-cae').val(),
                fechavencimientocae: fila.find('.fechavencimientocae').val(),
                cliente: fila.find('.factura-cliente').val(),
                mediopago: mediopago
            });
        });

        if (datosfacturas.length === 0) {
            alert('No hay facturas para importar.');
            return;
        }

        if (filasSinMedioPago > 0) {
            alert('Seleccione el medio de pago en todas las facturas antes de importar.');
            return;
        }

        var $boton = $('#btn-importar-facturas');
        $boton.prop('disabled', true);
        $('#loading').show();

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: {
                _token: token,
                datos: JSON.stringify(datosfacturas),
                desdefecha: desdefecha,
                hastafecha: hastafecha
            }
        }).done(function (data) {
            if (data.error && data.error !== 'Success') {
                alert(data.error);
                return;
            }

            var mensaje = data.mensaje || 'Importacion finalizada';
            if (data.verificacion) {
                mensaje += '\n\nVerificacion del periodo:\n' + data.verificacion;
            }
            alert(mensaje);
            window.location.reload();
        }).fail(function (xhr) {
            var mensaje = 'No se pudo completar la importacion.';

            if (xhr.responseJSON && xhr.responseJSON.error) {
                mensaje = xhr.responseJSON.error;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                mensaje = xhr.responseJSON.message;
            } else if (xhr.status === 419) {
                mensaje = 'La sesion expiro. Vuelva a iniciar sesion e intente nuevamente.';
            } else if (xhr.status === 0) {
                mensaje = 'No hubo respuesta del servidor. Verifique la conexion.';
            } else if (xhr.responseText) {
                mensaje += ' Codigo HTTP: ' + xhr.status;
            }

            alert(mensaje);
        }).always(function () {
            $('#loading').hide();
            $boton.prop('disabled', false);
        });
    });
});
