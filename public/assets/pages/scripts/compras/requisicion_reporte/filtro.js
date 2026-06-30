function rcReporteNormalizarValor(valor) {
    return String(valor || '').trim();
}

function rcReporteEsTeclaF1(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function rcReporteModalAbierto(selector) {
    var $m = $(selector);
    return $m.length && $m.hasClass('show');
}

function rcReporteEsPantallaActiva() {
    return $('#form-requisicion-compras-reporte').length > 0;
}

function rcReporteAbrirModalUsuario() {
    var valor = $('#rc-reporte-usuario-campo .codigousuario').val().trim();
    rcReporteBuscarUsuariosModal(valor);
    $('#consultausuarioModal').modal('show');
}

function rcReporteAbrirModalCentrocosto() {
    var valor = $('#rc-reporte-centrocosto-campo .codigocentrocosto').val().trim();
    rcReporteBuscarCentrocostosModal(valor);
    $('#consultacentrocostoModal').modal('show');
}

function rcReporteExpandirRangoRequisicion($campo) {
    var $desde = $campo.find('.codigonumero-desde');
    var $hasta = $campo.find('.codigonumero-hasta');
    var desde = rcReporteNormalizarValor($desde.val());

    if (desde.indexOf('/') < 0) {
        return;
    }

    var partes = desde.split('/');
    var valorDesde = rcReporteNormalizarValor(partes[0]);
    var valorHasta = rcReporteNormalizarValor(partes[1] || '');

    if (valorDesde !== '') {
        $desde.val(valorDesde);
    }

    if (valorHasta !== '') {
        $hasta.val(valorHasta);
    }
}

function rcReporteResolverCentrocostos($campo) {
    var valor = rcReporteNormalizarValor($campo.find('.codigocentrocosto').val());
    var $meta = $campo.find('.metacentrocosto');

    if (valor === '') {
        $meta.val('Todos los centros de costo');
        return;
    }

    if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
        var codigos = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        $meta.val(codigos.length > 1 ? 'Lista CC (' + codigos.length + '): ' + codigos.join(', ') : 'Lista CC');
        return;
    }

    $.getJSON(carpetaBase + '/contable/centrocosto/resolvercentrocosto', { valor: valor })
        .done(function (data) {
            if (data && data.ok) {
                $campo.find('.codigocentrocosto').val(String(data.codigo));
                $meta.val((data.codigo || '') + ' — ' + (data.nombre || ''));
            } else {
                $meta.val('');
            }
        })
        .fail(function () {
            $meta.val('');
        });
}

function rcReporteAgregarCentrocosto(codigo, nombre) {
    var $campo = $('#rc-reporte-centrocosto-campo');
    var $inp = $campo.find('.codigocentrocosto');
    var actual = rcReporteNormalizarValor($inp.val());
    var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var codigoStr = String(codigo).trim();

    if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
        codigos.push(codigoStr);
    }

    $inp.val(codigos.join(','));
    rcReporteResolverCentrocostos($campo);
}

function rcReporteBuscarCentrocostosModal(consulta) {
    $.ajax({
        url: carpetaBase + '/contable/centrocosto/consultacentrocosto',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $('#datoscentrocosto').html(html);
        })
        .fail(function () {
            $('#datoscentrocosto').html('<tr><td colspan="5">Error al consultar centros de costo</td></tr>');
        });
}

function rcReporteResolverUsuarios($campo) {
    var valor = rcReporteNormalizarValor($campo.find('.codigousuario').val());
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
        $meta.val('Rango ' + rcReporteNormalizarValor(partes[0]) + ' al ' + rcReporteNormalizarValor(partes[1] || ''));
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

function rcReporteAgregarUsuario(id, nombre) {
    var $campo = $('#rc-reporte-usuario-campo');
    var $inp = $campo.find('.codigousuario');
    var actual = rcReporteNormalizarValor($inp.val());
    var ids = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var idStr = String(id);

    if (ids.indexOf(idStr) < 0) {
        ids.push(idStr);
    }

    $inp.val(ids.join(','));
    rcReporteResolverUsuarios($campo);
}

function rcReporteBuscarUsuariosModal(consulta) {
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
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $('#datosusuario').html(html);
        })
        .fail(function () {
            $('#datosusuario').html('<tr><td colspan="4">Error al consultar usuarios</td></tr>');
        });
}

function rcReporteToggleGrupo(grupoId, colapsar) {
    var $detalle = $('.req-reporte-grupo-' + grupoId);
    var $cabecera = $('.req-reporte-grupo-cabecera[data-grupo-id="' + grupoId + '"]');

    if (colapsar === undefined) {
        colapsar = !$cabecera.hasClass('req-reporte-colapsado');
    }

    if (colapsar) {
        $cabecera.addClass('req-reporte-colapsado');
        $detalle.addClass('req-reporte-colapsado');
        $cabecera.find('.req-reporte-grupo-icon')
            .removeClass('fa-chevron-down')
            .addClass('fa-chevron-right');
    } else {
        $cabecera.removeClass('req-reporte-colapsado');
        $detalle.removeClass('req-reporte-colapsado');
        $cabecera.find('.req-reporte-grupo-icon')
            .removeClass('fa-chevron-right')
            .addClass('fa-chevron-down');
    }
}

function rcReporteToggleTodosGrupos() {
    var $cabeceras = $('#tabla-requisicion-compras-reporte .req-reporte-grupo-cabecera');
    if (!$cabeceras.length) {
        return;
    }

    var algunoExpandido = $cabeceras.filter(':not(.req-reporte-colapsado)').length > 0;
    $cabeceras.each(function () {
        rcReporteToggleGrupo($(this).data('grupo-id'), algunoExpandido);
    });
}

function activaEventosRequisicionComprasReporteFiltro() {
    var $form = $('#form-requisicion-compras-reporte');
    if (!$form.length) {
        return;
    }

    rcReporteExpandirRangoRequisicion($('#rc-reporte-requisicion-campo'));
    rcReporteResolverUsuarios($('#rc-reporte-usuario-campo'));
    rcReporteResolverCentrocostos($('#rc-reporte-centrocosto-campo'));

    $form.on('keydown', 'input:not([type="submit"])', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            var $campoReq = $(this).closest('#rc-reporte-requisicion-campo');
            if ($campoReq.length) {
                rcReporteExpandirRangoRequisicion($campoReq);
                return false;
            }
            var $campoUsr = $(this).closest('#rc-reporte-usuario-campo');
            if ($campoUsr.length) {
                rcReporteResolverUsuarios($campoUsr);
                return false;
            }
            var $campoCc = $(this).closest('#rc-reporte-centrocosto-campo');
            if ($campoCc.length) {
                rcReporteResolverCentrocostos($campoCc);
                return false;
            }
        }
    });

    $(document)
        .off('change.rcreporte', '#rc-reporte-requisicion-campo .codigonumero-desde')
        .on('change.rcreporte', '#rc-reporte-requisicion-campo .codigonumero-desde', function () {
            rcReporteExpandirRangoRequisicion($('#rc-reporte-requisicion-campo'));
        });

    $(document)
        .off('blur.rcreporte', '#rc-reporte-requisicion-campo .codigonumero-desde')
        .on('blur.rcreporte', '#rc-reporte-requisicion-campo .codigonumero-desde', function () {
            rcReporteExpandirRangoRequisicion($('#rc-reporte-requisicion-campo'));
        });

    $(document)
        .off('change.rcreporte', '#rc-reporte-usuario-campo .codigousuario')
        .on('change.rcreporte', '#rc-reporte-usuario-campo .codigousuario', function () {
            rcReporteResolverUsuarios($('#rc-reporte-usuario-campo'));
        });

    $(document)
        .off('change.rcreporte', '#rc-reporte-centrocosto-campo .codigocentrocosto')
        .on('change.rcreporte', '#rc-reporte-centrocosto-campo .codigocentrocosto', function () {
            rcReporteResolverCentrocostos($('#rc-reporte-centrocosto-campo'));
        });

    $(document)
        .off('blur.rcreporte', '#rc-reporte-centrocosto-campo .codigocentrocosto')
        .on('blur.rcreporte', '#rc-reporte-centrocosto-campo .codigocentrocosto', function () {
            rcReporteResolverCentrocostos($('#rc-reporte-centrocosto-campo'));
        });

    $(document)
        .off('click.rcreporte', '#rc-reporte-centrocosto-campo .consultacentrocosto-rc')
        .on('click.rcreporte', '#rc-reporte-centrocosto-campo .consultacentrocosto-rc', function (e) {
            e.preventDefault();
            rcReporteAbrirModalCentrocosto();
        });

    $('#consultacentrocostoModal')
        .off('shown.bs.modal.rcreporte')
        .on('shown.bs.modal.rcreporte', function () {
            if (!$('#form-requisicion-compras-reporte').length) {
                return;
            }
            var valor = $('#rc-reporte-centrocosto-campo .codigocentrocosto').val().trim();
            $('#consultacentrocosto').val(valor);
            rcReporteBuscarCentrocostosModal(valor);
            $(this).find('#consultacentrocosto').focus();
        });

    $(document)
        .off('keyup.rcreporte', '#consultacentrocosto')
        .on('keyup.rcreporte', '#consultacentrocosto', function () {
            if (!$('#form-requisicion-compras-reporte').length) {
                return;
            }
            rcReporteBuscarCentrocostosModal($(this).val().trim());
        });

    $(document)
        .off('click.rcreporte', '.eligeconsultacentrocosto')
        .on('click.rcreporte', '.eligeconsultacentrocosto', function (e) {
            if (!$('#form-requisicion-compras-reporte').length) {
                return;
            }
            e.stopImmediatePropagation();

            var $trModal = $(this).closest('tr');
            var codigo = $trModal.find('.codigo').first().text().trim();
            var nombre = $trModal.find('.nombre').first().text().trim();

            if (codigo !== '') {
                rcReporteAgregarCentrocosto(codigo, nombre);
            }

            $('#consultacentrocostoModal').modal('hide');
            return false;
        });

    $(document)
        .off('click.rcreporte', '#rc-reporte-usuario-campo .consultausuario-rc')
        .on('click.rcreporte', '#rc-reporte-usuario-campo .consultausuario-rc', function (e) {
            e.preventDefault();
            rcReporteAbrirModalUsuario();
        });

    $('#consultausuarioModal')
        .off('shown.bs.modal.rcreporte')
        .on('shown.bs.modal.rcreporte', function () {
            if (!$('#form-requisicion-compras-reporte').length) {
                return;
            }
            var valor = $('#rc-reporte-usuario-campo .codigousuario').val().trim();
            $('#consultausuario').val(valor);
            rcReporteBuscarUsuariosModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.rcreporte', '#consultausuario')
        .on('keyup.rcreporte', '#consultausuario', function () {
            if (!$('#form-requisicion-compras-reporte').length) {
                return;
            }
            rcReporteBuscarUsuariosModal($(this).val().trim());
        });

    $(document)
        .off('click.rcreporte', '.eligeconsultausuario')
        .on('click.rcreporte', '.eligeconsultausuario', function (e) {
            if (!$('#form-requisicion-compras-reporte').length) {
                return;
            }
            e.stopImmediatePropagation();

            var $trModal = $(this).closest('tr');
            var id = $trModal.find('.id').first().text().trim();
            var nombre = $trModal.find('.nombre').first().text().trim();

            if (id !== '') {
                rcReporteAgregarUsuario(id, nombre);
            }

            $('#consultausuarioModal').modal('hide');
            return false;
        });

    $(document)
        .off('click.rcreporte', '#tabla-requisicion-compras-reporte .req-reporte-grupo-cabecera')
        .on('click.rcreporte', '#tabla-requisicion-compras-reporte .req-reporte-grupo-cabecera', function () {
            rcReporteToggleGrupo($(this).data('grupo-id'));
        });

    $(document)
        .off('click.rcreporte', '#rc-reporte-toggle-grupos')
        .on('click.rcreporte', '#rc-reporte-toggle-grupos', function () {
            rcReporteToggleTodosGrupos();
        });

    document.removeEventListener('keydown', rcReporteAtajoF1Handler, true);
    document.addEventListener('keydown', rcReporteAtajoF1Handler, true);
}

function rcReporteAtajoF1Handler(e) {
    if (!rcReporteEsTeclaF1(e) || !rcReporteEsPantallaActiva()) {
        return;
    }

    var target = e.target;
    if (!target || !target.closest('#form-requisicion-compras-reporte')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }

    if (target.classList.contains('codigousuario')) {
        if (rcReporteModalAbierto('#consultausuarioModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        rcReporteAbrirModalUsuario();
        return;
    }

    if (target.classList.contains('codigocentrocosto')) {
        if (rcReporteModalAbierto('#consultacentrocostoModal')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        rcReporteAbrirModalCentrocosto();
    }
}

$(function () {
    activaEventosRequisicionComprasReporteFiltro();
});
