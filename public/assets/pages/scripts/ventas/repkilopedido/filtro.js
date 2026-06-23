var kiloPedidoRepartoCampoActivo = null;

function kiloPedidoNormalizarCodigoReparto(valor) {
    return String(valor || '').trim();
}

function kiloPedidoLimpiarCampoReparto($campo) {
    $campo.find('.codigoreparto').val('');
    $campo.find('.nombrereparto').val('');
}

function kiloPedidoResolverReparto($campo) {
    var codigo = kiloPedidoNormalizarCodigoReparto($campo.find('.codigoreparto').val());

    if (codigo === '') {
        kiloPedidoLimpiarCampoReparto($campo);
        return;
    }

    if (codigo.indexOf(',') >= 0 || codigo.indexOf(';') >= 0) {
        $campo.find('.nombrereparto').val('Lista de repartos');
        return;
    }

    if (codigo.indexOf('/') >= 0) {
        var partes = codigo.split('/');
        var desde = kiloPedidoNormalizarCodigoReparto(partes[0]);
        var hasta = kiloPedidoNormalizarCodigoReparto(partes[1] || '');
        if (desde !== '' && hasta !== '') {
            $campo.find('.nombrereparto').val('Rango ' + desde + ' al ' + hasta);
            return;
        }
        if (desde !== '') {
            codigo = desde;
        }
    }

    $.get(carpetaBase + '/ventas/leertransporte/' + encodeURIComponent(codigo), function (data) {
        if (data && data.id) {
            $campo.find('.codigoreparto').val(data.codigo || codigo);
            $campo.find('.nombrereparto').val(data.nombre || '');
        } else {
            alert('No existe el reparto indicado');
            kiloPedidoLimpiarCampoReparto($campo);
        }
    }).fail(function () {
        alert('No se pudo validar el reparto');
    });
}

function kiloPedidoBuscarTransportesModal(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/transporte/consultatransporte',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var resp = respuesta.replace(/\\/g, '');
            $('#datostransporte').html(resp);
        })
        .fail(function () {
            $('#datostransporte').html('<tr><td colspan="8">Error al consultar repartos</td></tr>');
        });
}

function kiloPedidoAplicarDefaultsReparto() {
    // Sin defaults: vacío en ambos = todos los repartos.
}

function activaEventosKiloPedidoFiltro() {
    $('#form-kilo-pedido').on('submit', function () {
        kiloPedidoAplicarDefaultsReparto();
    });

    $(document)
        .off('keydown.kp', '#form-kilo-pedido input')
        .on('keydown.kp', '#form-kilo-pedido input', function (e) {
            if (e.which === 13 && !$(this).is('[type="submit"]')) {
                e.preventDefault();
                var $campo = $(this).closest('.kilo-pedido-reparto-campo');
                if ($campo.length) {
                    kiloPedidoResolverReparto($campo);
                }
                return false;
            }
        });

    $(document)
        .off('change.kp', '.kilo-pedido-reparto-campo .codigoreparto')
        .on('change.kp', '.kilo-pedido-reparto-campo .codigoreparto', function (e) {
            e.preventDefault();
            kiloPedidoResolverReparto($(this).closest('.kilo-pedido-reparto-campo'));
        });

    $(document)
        .off('click.kp', '.kilo-pedido-reparto-campo .consultareparto')
        .on('click.kp', '.kilo-pedido-reparto-campo .consultareparto', function (e) {
            e.preventDefault();
            kiloPedidoRepartoCampoActivo = $(this).closest('.kilo-pedido-reparto-campo');
            $('#consultatransporteModal').modal('show');
        });

    $('#consultatransporteModal')
        .off('shown.bs.modal.kp')
        .on('shown.bs.modal.kp', function () {
            var valor = '';
            if (kiloPedidoRepartoCampoActivo && kiloPedidoRepartoCampoActivo.length) {
                valor = kiloPedidoRepartoCampoActivo.find('.codigoreparto').val().trim();
            }
            $('#consultatransporte').val(valor);
            kiloPedidoBuscarTransportesModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.kp', '#consultatransporte')
        .on('keyup.kp', '#consultatransporte', function () {
            kiloPedidoBuscarTransportesModal($(this).val().trim());
        });

    $(document)
        .off('click.kp', '.eligeconsultatransporte')
        .on('click.kp', '.eligeconsultatransporte', function () {
            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigo').first().text().trim();
            var nombre = $tr.find('.nombre').first().text().trim();

            if (kiloPedidoRepartoCampoActivo && kiloPedidoRepartoCampoActivo.length) {
                kiloPedidoRepartoCampoActivo.find('.codigoreparto').val(codigo);
                kiloPedidoRepartoCampoActivo.find('.nombrereparto').val(nombre);
            }

            $('#consultatransporteModal').modal('hide');
        });

    $('#aceptaconsultatransporteModal')
        .off('click.kp')
        .on('click.kp', function () {
            $('#consultatransporteModal').modal('hide');
        });
}

$(function () {
    activaEventosKiloPedidoFiltro();
});
