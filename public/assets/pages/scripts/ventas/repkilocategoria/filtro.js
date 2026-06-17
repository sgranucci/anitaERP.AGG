var kiloCategoriaRepartoCampoActivo = null;

function kiloCategoriaNormalizarCodigoReparto(valor) {
    return String(valor || '').trim();
}

function kiloCategoriaLimpiarCampoReparto($campo) {
    $campo.find('.codigoreparto').val('');
    $campo.find('.nombrereparto').val('');
}

function kiloCategoriaResolverReparto($campo) {
    var codigo = kiloCategoriaNormalizarCodigoReparto($campo.find('.codigoreparto').val());

    if (codigo === '') {
        kiloCategoriaLimpiarCampoReparto($campo);
        return;
    }

    if (codigo.indexOf(',') >= 0 || codigo.indexOf(';') >= 0) {
        $campo.find('.nombrereparto').val('Lista de repartos');
        return;
    }

    if (codigo.indexOf('/') >= 0) {
        var partes = codigo.split('/');
        var desde = kiloCategoriaNormalizarCodigoReparto(partes[0]);
        var hasta = kiloCategoriaNormalizarCodigoReparto(partes[1] || '');
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
            kiloCategoriaLimpiarCampoReparto($campo);
        }
    }).fail(function () {
        alert('No se pudo validar el reparto');
    });
}

function kiloCategoriaBuscarTransportesModal(consulta) {
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
            $('#datostransporte').html('<tr><td colspan="4">Error al consultar repartos</td></tr>');
        });
}

function kiloCategoriaAplicarDefaultsReparto() {
    // Sin defaults: vacío en ambos = todos los repartos.
}

function activaEventosKiloCategoriaFiltro() {
    $('#form-kilo-categoria').on('submit', function () {
        kiloCategoriaAplicarDefaultsReparto();
    });

    $(document)
        .off('keydown.kc', '#form-kilo-categoria input')
        .on('keydown.kc', '#form-kilo-categoria input', function (e) {
            if (e.which === 13 && !$(this).is('[type="submit"]')) {
                e.preventDefault();
                var $campo = $(this).closest('.kilo-categoria-reparto-campo');
                if ($campo.length) {
                    kiloCategoriaResolverReparto($campo);
                }
                return false;
            }
        });

    $(document)
        .off('change.kc', '.kilo-categoria-reparto-campo .codigoreparto')
        .on('change.kc', '.kilo-categoria-reparto-campo .codigoreparto', function (e) {
            e.preventDefault();
            kiloCategoriaResolverReparto($(this).closest('.kilo-categoria-reparto-campo'));
        });

    $(document)
        .off('click.kc', '.kilo-categoria-reparto-campo .consultareparto')
        .on('click.kc', '.kilo-categoria-reparto-campo .consultareparto', function (e) {
            e.preventDefault();
            kiloCategoriaRepartoCampoActivo = $(this).closest('.kilo-categoria-reparto-campo');
            $('#consultatransporteModal').modal('show');
        });

    $('#consultatransporteModal')
        .off('shown.bs.modal.kc')
        .on('shown.bs.modal.kc', function () {
            var valor = '';
            if (kiloCategoriaRepartoCampoActivo && kiloCategoriaRepartoCampoActivo.length) {
                valor = kiloCategoriaRepartoCampoActivo.find('.codigoreparto').val().trim();
            }
            $('#consultatransporte').val(valor);
            kiloCategoriaBuscarTransportesModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.kc', '#consultatransporte')
        .on('keyup.kc', '#consultatransporte', function () {
            kiloCategoriaBuscarTransportesModal($(this).val().trim());
        });

    $(document)
        .off('click.kc', '.eligeconsultatransporte')
        .on('click.kc', '.eligeconsultatransporte', function () {
            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigo').first().text().trim();
            var nombre = $tr.find('.nombre').first().text().trim();

            if (kiloCategoriaRepartoCampoActivo && kiloCategoriaRepartoCampoActivo.length) {
                kiloCategoriaRepartoCampoActivo.find('.codigoreparto').val(codigo);
                kiloCategoriaRepartoCampoActivo.find('.nombrereparto').val(nombre);
            }

            $('#consultatransporteModal').modal('hide');
        });

    $('#aceptaconsultatransporteModal')
        .off('click.kc')
        .on('click.kc', function () {
            $('#consultatransporteModal').modal('hide');
        });
}

$(function () {
    activaEventosKiloCategoriaFiltro();
});
