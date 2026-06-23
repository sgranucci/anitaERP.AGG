function rsReporteNormalizarValor(valor) {
    return String(valor || '').trim();
}

function rsReporteExpandirRangoRequisicion($campo) {
    var $desde = $campo.find('.codigonumero-desde');
    var $hasta = $campo.find('.codigonumero-hasta');
    var desde = rsReporteNormalizarValor($desde.val());

    if (desde.indexOf('/') < 0) {
        return;
    }

    var partes = desde.split('/');
    var valorDesde = rsReporteNormalizarValor(partes[0]);
    var valorHasta = rsReporteNormalizarValor(partes[1] || '');

    if (valorDesde !== '') {
        $desde.val(valorDesde);
    }

    if (valorHasta !== '') {
        $hasta.val(valorHasta);
    }
}

function rsReporteResolverUsuarios($campo) {
    var valor = rsReporteNormalizarValor($campo.find('.codigousuario').val());
    var $meta = $campo.find('.metausuario');

    if (valor === '') {
        $meta.val('Todos los usuarios');
        return;
    }

    if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
        var ids = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        $meta.val(ids.length > 1 ? 'Lista de usuarios (' + ids.length + ')' : 'Lista de usuarios');
        return;
    }

    if (valor.indexOf('/') >= 0) {
        var partes = valor.split('/');
        $meta.val('Rango ' + rsReporteNormalizarValor(partes[0]) + ' al ' + rsReporteNormalizarValor(partes[1] || ''));
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', { valor: valor })
        .done(function (data) {
            if (data && data.ok) {
                $campo.find('.codigousuario').val(String(data.id));
                $meta.val(data.nombre || '');
            } else {
                $meta.val('');
            }
        })
        .fail(function () {
            $meta.val('');
        });
}

function rsReporteAgregarUsuario(id, nombre) {
    var $campo = $('#rs-reporte-usuario-campo');
    var $inp = $campo.find('.codigousuario');
    var actual = rsReporteNormalizarValor($inp.val());
    var ids = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var idStr = String(id);

    if (ids.indexOf(idStr) < 0) {
        ids.push(idStr);
    }

    $inp.val(ids.join(','));
    rsReporteResolverUsuarios($campo);
}

function rsReporteBuscarUsuariosModal(consulta) {
    $.ajax({
        url: carpetaBase + '/configuracion/consultausuario',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var resp = respuesta.replace(/\\/g, '');
            $('#datosusuario').html(resp);
        })
        .fail(function () {
            $('#datosusuario').html('<tr><td colspan="4">Error al consultar usuarios</td></tr>');
        });
}

function activaEventosRequisicionSalaReporteFiltro() {
    var $form = $('#form-requisicion-sala-reporte');
    if (!$form.length) {
        return;
    }

    rsReporteExpandirRangoRequisicion($('#rs-reporte-requisicion-campo'));
    rsReporteResolverUsuarios($('#rs-reporte-usuario-campo'));

    $form.on('keydown', 'input:not([type="submit"])', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            var $campoReq = $(this).closest('#rs-reporte-requisicion-campo');
            if ($campoReq.length) {
                rsReporteExpandirRangoRequisicion($campoReq);
                return false;
            }
            var $campoUsr = $(this).closest('#rs-reporte-usuario-campo');
            if ($campoUsr.length) {
                rsReporteResolverUsuarios($campoUsr);
                return false;
            }
        }
    });

    $(document)
        .off('change.rsreporte', '#rs-reporte-requisicion-campo .codigonumero-desde')
        .on('change.rsreporte', '#rs-reporte-requisicion-campo .codigonumero-desde', function () {
            rsReporteExpandirRangoRequisicion($('#rs-reporte-requisicion-campo'));
        });

    $(document)
        .off('blur.rsreporte', '#rs-reporte-requisicion-campo .codigonumero-desde')
        .on('blur.rsreporte', '#rs-reporte-requisicion-campo .codigonumero-desde', function () {
            rsReporteExpandirRangoRequisicion($('#rs-reporte-requisicion-campo'));
        });

    $(document)
        .off('change.rsreporte', '#rs-reporte-usuario-campo .codigousuario')
        .on('change.rsreporte', '#rs-reporte-usuario-campo .codigousuario', function () {
            rsReporteResolverUsuarios($('#rs-reporte-usuario-campo'));
        });

    $(document)
        .off('click.rsreporte', '#rs-reporte-usuario-campo .consultausuario-rs')
        .on('click.rsreporte', '#rs-reporte-usuario-campo .consultausuario-rs', function (e) {
            e.preventDefault();
            rsReporteBuscarUsuariosModal($('#rs-reporte-usuario-campo .codigousuario').val().trim());
            $('#consultausuarioModal').modal('show');
        });

    $('#consultausuarioModal')
        .off('shown.bs.modal.rsreporte')
        .on('shown.bs.modal.rsreporte', function () {
            if (!$('#form-requisicion-sala-reporte').length) {
                return;
            }
            var valor = $('#rs-reporte-usuario-campo .codigousuario').val().trim();
            $('#consultausuario').val(valor);
            rsReporteBuscarUsuariosModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.rsreporte', '#consultausuario')
        .on('keyup.rsreporte', '#consultausuario', function () {
            if (!$('#form-requisicion-sala-reporte').length) {
                return;
            }
            rsReporteBuscarUsuariosModal($(this).val().trim());
        });

    $(document)
        .off('click.rsreporte', '.eligeconsultausuario')
        .on('click.rsreporte', '.eligeconsultausuario', function (e) {
            if (!$('#form-requisicion-sala-reporte').length) {
                return;
            }
            e.stopImmediatePropagation();

            var $trModal = $(this).closest('tr');
            var id = $trModal.find('.id').first().text().trim();
            var nombre = $trModal.find('.nombre').first().text().trim();

            if (id !== '') {
                rsReporteAgregarUsuario(id, nombre);
            }

            $('#consultausuarioModal').modal('hide');
            return false;
        });
}

$(function () {
    activaEventosRequisicionSalaReporteFiltro();
});
