/* global carpetaBase */
var campoAsociadoReporteActivo = $();
var asociadoReporteBusquedaTimer = null;
var asociadoReporteModalAbriendose = false;

function tipoAsociadoReporte($campo) {
    return String($($campo.data('tipo-selector') || '#tipo').val() || '');
}

function limpiarAsociadoReporte($campo, mantenerCodigo) {
    if (!mantenerCodigo) {
        $campo.find('.codigoasociado_reporte').val('').removeData('asociado-invalido');
    }
    $campo.find('.descripcionasociado_reporte').val('');
}

function aplicarAsociadoReporte($campo, data) {
    if (!$campo.length || !data || !data.codigo) {
        return;
    }
    $campo.find('.codigoasociado_reporte')
        .val(data.codigo)
        .removeData('asociado-invalido');
    $campo.find('.descripcionasociado_reporte').val(data.descripcion || '');
}

function modalAsociadoReporteAbierto() {
    return $('#consultaasociado_reporteModal').hasClass('show') ||
        asociadoReporteModalAbriendose;
}

function avisarAsociadoReporte(mensaje, $input) {
    var mostrar = function () {
        window.setTimeout(function () {
            window.alert(mensaje);
            $input.focus();
        }, 0);
    };
    var $modal = $('#consultaasociado_reporteModal');
    if (modalAsociadoReporteAbierto()) {
        $modal.one('hidden.bs.modal', mostrar).modal('hide');
    } else {
        mostrar();
    }
}

function resolverAsociadoReporte($campo, alertar) {
    var tipo = tipoAsociadoReporte($campo);
    var $input = $campo.find('.codigoasociado_reporte');
    var codigo = String($input.val() || '').replace(/\D+/g, '');
    if (tipo !== 'osocial' && tipo !== 'sindicato') {
        limpiarAsociadoReporte($campo, false);
        return;
    }
    if (codigo === '') {
        limpiarAsociadoReporte($campo, false);
        return;
    }

    limpiarAsociadoReporte($campo, true);
    $.get(
        carpetaBase + '/sueldos/reporte-definible/leer-asociado/' +
            encodeURIComponent(tipo) + '/' + encodeURIComponent(codigo)
    ).done(function (data) {
        aplicarAsociadoReporte($campo, data);
    }).fail(function () {
        limpiarAsociadoReporte($campo, false);
        $input.data('asociado-invalido', 1);
        if (alertar) {
            avisarAsociadoReporte(
                tipo === 'osocial'
                    ? 'No se encontró la obra social indicada.'
                    : 'No se encontró el sindicato indicado.',
                $input
            );
        }
    });
}

function actualizarCampoAsociadoReporte($campo) {
    var tipo = tipoAsociadoReporte($campo);
    var anterior = String($campo.data('tipo-asociado-anterior') || '');
    var visible = tipo === 'osocial' || tipo === 'sindicato';

    if (anterior !== '' && anterior !== tipo) {
        limpiarAsociadoReporte($campo, false);
    }
    $campo.data('tipo-asociado-anterior', tipo);
    $campo.toggleClass('d-none', !visible);

    if (!visible) {
        limpiarAsociadoReporte($campo, false);
        return;
    }

    var esObraSocial = tipo === 'osocial';
    $campo.find('.etiqueta-asociado-reporte').text(esObraSocial ? 'Obra social' : 'Sindicato');
    $campo.find('.ayuda-asociado-reporte').text(
        esObraSocial
            ? 'El reporte incluirá solamente empleados de la obra social seleccionada.'
            : 'El reporte incluirá solamente empleados del sindicato seleccionado.'
    );
    if ($campo.find('.codigoasociado_reporte').val() &&
        !$campo.find('.descripcionasociado_reporte').val()) {
        resolverAsociadoReporte($campo, false);
    }
}

function buscarAsociadosReporte(consulta) {
    var tipo = tipoAsociadoReporte(campoAsociadoReporteActivo);
    $.ajax({
        url: carpetaBase + '/sueldos/reporte-definible/consulta-asociado',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                ($('input[name="_token"]').first().val() || '')
        },
        data: { tipo: tipo, consulta: consulta || '' }
    }).done(function (respuesta) {
        $('#datosasociado_reporte').html(respuesta.data || '');
    });
}

function abrirModalAsociadoReporte($campo) {
    var tipo = tipoAsociadoReporte($campo);
    if (tipo !== 'osocial' && tipo !== 'sindicato') {
        return;
    }
    campoAsociadoReporteActivo = $campo;
    asociadoReporteModalAbriendose = true;
    $('#consultaasociado_reporteModalLabel').text(
        tipo === 'osocial' ? 'Consultar obra social' : 'Consultar sindicato'
    );
    $('#consultaasociado_reporte').val('');
    buscarAsociadosReporte('');
    $('#consultaasociado_reporteModal').modal('show');
}

$(document)
    .on('mousedown', '.consultaasociado_reporte', function () {
        asociadoReporteModalAbriendose = true;
    })
    .on('click', '.consultaasociado_reporte', function (e) {
        e.preventDefault();
        abrirModalAsociadoReporte($(this).closest('.tm-asociado-reporte-campo'));
    })
    .on('click', '.eligeconsultaasociado', function (e) {
        e.preventDefault();
        var $fila = $(this).closest('tr');
        aplicarAsociadoReporte(campoAsociadoReporteActivo, {
            codigo: $.trim($fila.find('.codigoasociado').text()),
            descripcion: $.trim($fila.find('.descripcionasociado').text())
        });
        $('#consultaasociado_reporteModal').modal('hide');
    })
    .on('change', '#tipo', function () {
        $('.tm-asociado-reporte-campo').each(function () {
            actualizarCampoAsociadoReporte($(this));
        });
    })
    .on('input', '.codigoasociado_reporte', function () {
        $(this).removeData('asociado-invalido');
        $(this).closest('.tm-asociado-reporte-campo')
            .find('.descripcionasociado_reporte').val('');
    })
    .on('blur', '.codigoasociado_reporte', function () {
        if (!modalAsociadoReporteAbierto() && !$(this).data('asociado-invalido')) {
            resolverAsociadoReporte($(this).closest('.tm-asociado-reporte-campo'), false);
        }
    })
    .on('input', '#consultaasociado_reporte', function () {
        window.clearTimeout(asociadoReporteBusquedaTimer);
        var consulta = $(this).val();
        asociadoReporteBusquedaTimer = window.setTimeout(function () {
            buscarAsociadosReporte(consulta);
        }, 250);
    });

document.addEventListener('keydown', function (e) {
    var $target = $(e.target);
    if ((e.key === 'F1' || e.keyCode === 112) && $target.hasClass('codigoasociado_reporte')) {
        e.preventDefault();
        e.stopPropagation();
        asociadoReporteModalAbriendose = true;
        abrirModalAsociadoReporte($target.closest('.tm-asociado-reporte-campo'));
        return;
    }
    if (!(e.key === 'Enter' || e.keyCode === 13)) {
        return;
    }
    if (e.target.id === 'consultaasociado_reporte') {
        e.preventDefault();
        var $primera = $('#datosasociado_reporte .eligeconsultaasociado').first();
        if ($primera.length) {
            $primera.trigger('click');
        }
        return;
    }
    if ($target.hasClass('codigoasociado_reporte')) {
        e.preventDefault();
        e.stopPropagation();
        resolverAsociadoReporte($target.closest('.tm-asociado-reporte-campo'), true);
    }
}, true);

$('#consultaasociado_reporteModal')
    .on('shown.bs.modal', function () {
        asociadoReporteModalAbriendose = false;
        $('#consultaasociado_reporte').focus();
    })
    .on('hidden.bs.modal', function () {
        asociadoReporteModalAbriendose = false;
    });

$(function () {
    $('.tm-asociado-reporte-campo').each(function () {
        actualizarCampoAsociadoReporte($(this));
    });
});
