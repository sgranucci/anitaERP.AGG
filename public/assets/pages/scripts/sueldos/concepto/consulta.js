/* global carpetaBase */
var ptrCampoConceptoSueldos = $();

function esTeclaF1ConceptoSueldos(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaConceptoSueldosAbierto() {
    var $m = $('#consultaconcepto_sueldosModal');
    return $m.length && $m.hasClass('show');
}

function campoConceptoSueldosDesde($el) {
    var $campo = $($el).closest('.tm-concepto-sueldos-campo');
    return $campo.length ? $campo : $();
}

function parsearHtmlConsultaConceptoSueldos(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_concepto_sueldos(consulta) {
    $.ajax({
        url: carpetaBase + '/sueldos/concepto/consultaconcepto',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                ($('input[name="_token"]').first().val() || '')
        },
        data: { consulta: consulta || '' }
    })
        .done(function (respuesta) {
            $('#datosconcepto_sueldos').html(parsearHtmlConsultaConceptoSueldos(respuesta));
        })
        .fail(function () {
            console.log('error consulta concepto sueldos');
        });
}

function actualizarLinkEditarConceptoSueldos($campo, conceptoId) {
    if (!$campo || !$campo.length) {
        return;
    }
    var $link = $campo.find('.btn-link-editar-concepto-sueldos');
    if (!$link.length) {
        return;
    }
    var id = parseInt(conceptoId || '0', 10);
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/sueldos/concepto/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function limpiarConceptoSueldosEnCampo($campo, mantenerCodigo) {
    if (!$campo || !$campo.length) {
        return;
    }
    $campo.find('.concepto_sueldos_id').val('');
    if (!mantenerCodigo) {
        $campo.find('.codigoconcepto_sueldos').val('');
    }
    $campo.find('.nombreconcepto_sueldos').val('');
    actualizarLinkEditarConceptoSueldos($campo, 0);
    $campo.find('.concepto_sueldos_id').trigger('change.conceptoSueldos');
}

function aplicarConceptoSueldosEnCampo($campo, data) {
    if (!$campo || !$campo.length || !data || !data.id) {
        return;
    }
    var codigo = data.codigo != null ? String(data.codigo) : '';
    if (codigo !== '' && /^\d+$/.test(codigo)) {
        codigo = ('0000' + codigo).slice(-4);
    }
    $campo.find('.concepto_sueldos_id').val(data.id);
    $campo.find('.codigoconcepto_sueldos').val(codigo);
    $campo.find('.nombreconcepto_sueldos').val(data.descripcion || data.nombre || '');
    actualizarLinkEditarConceptoSueldos($campo, data.id);
    $campo.find('.concepto_sueldos_id').trigger('change.conceptoSueldos');
}

window.aplicarConceptoSueldosEnCampo = aplicarConceptoSueldosEnCampo;
window.limpiarConceptoSueldosEnCampo = limpiarConceptoSueldosEnCampo;

function leeUnConceptoSueldos(conceptoId, codigoConcepto, $campo) {
    $campo = $campo && $campo.length ? $campo : ptrCampoConceptoSueldos;
    if (!$campo || !$campo.length) {
        return;
    }

    var url;
    if ($.isNumeric(conceptoId) && parseInt(conceptoId, 10) > 0) {
        url = carpetaBase + '/sueldos/concepto/leer/' + parseInt(conceptoId, 10);
    } else if (codigoConcepto !== undefined && codigoConcepto !== null && String(codigoConcepto).trim() !== '') {
        url = carpetaBase + '/sueldos/concepto/leerporcodigo/' + encodeURIComponent(String(codigoConcepto).trim());
    } else {
        limpiarConceptoSueldosEnCampo($campo, false);
        return;
    }

    limpiarConceptoSueldosEnCampo($campo, true);

    $.get(url)
        .done(function (data) {
            if (data && data.id) {
                aplicarConceptoSueldosEnCampo($campo, data);
                return;
            }
            limpiarConceptoSueldosEnCampo($campo, false);
            alert('No se encontr\u00f3 el concepto indicado.');
        })
        .fail(function () {
            limpiarConceptoSueldosEnCampo($campo, false);
            alert('No se pudo cargar el concepto.');
        });
}

function aceptarCodigoConceptoSueldosDesdeInput($input) {
    var $campo = campoConceptoSueldosDesde($input);
    if (!$campo.length) {
        return;
    }
    var codigo = String($input.val() || '').trim();
    if (codigo === '') {
        limpiarConceptoSueldosEnCampo($campo, false);
        return;
    }
    leeUnConceptoSueldos(null, codigo, $campo);
}

function abrirModalConsultaConceptoSueldos($campo) {
    ptrCampoConceptoSueldos = $campo && $campo.length ? $campo : $('.tm-concepto-sueldos-campo').first();
    $('#consultaconcepto_sueldos').val('');
    buscar_datos_concepto_sueldos('');
    $('#consultaconcepto_sueldosModal').modal('show');
}

function leerFilaConceptoSueldosConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.concepto_id').first().text()),
        codigo: $.trim($tr.find('td.codigoconcepto').first().text()),
        descripcion: $.trim($tr.find('td.descripcionconcepto').first().text()),
        tipo: $.trim($tr.find('td.tipoconcepto').first().text())
    };
}

document.addEventListener('keydown', function (e) {
    if (!(e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13)) {
        return;
    }
    var target = e.target;
    if (!target || target.readOnly || target.disabled) {
        return;
    }
    if (!target.classList.contains('codigoconcepto_sueldos')) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    $(target).data('concepto-sueldos-enter-procesado', 1);
    aceptarCodigoConceptoSueldosDesdeInput($(target));
}, true);

$(document)
    .off('keydown.conceptoSueldosCodigoEnter', '.codigoconcepto_sueldos')
    .on('keydown.conceptoSueldosCodigoEnter', '.codigoconcepto_sueldos', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        if ($(this).data('concepto-sueldos-enter-procesado')) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeData('concepto-sueldos-enter-procesado');
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        aceptarCodigoConceptoSueldosDesdeInput($(this));
    });

document.addEventListener('keydown', function (e) {
    if (!esTeclaF1ConceptoSueldos(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList.contains('codigoconcepto_sueldos')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaConceptoSueldosAbierto()) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    abrirModalConsultaConceptoSueldos(campoConceptoSueldosDesde($(target)));
}, true);

$(document).on('keyup', '#consultaconcepto_sueldos', function () {
    buscar_datos_concepto_sueldos(String($(this).val() || '').trim());
});

function activa_eventos_consultaconcepto_sueldos() {
    $(document)
        .off('click.consultaConceptoSueldosAbrir', '.consultaconcepto_sueldos')
        .on('click.consultaConceptoSueldosAbrir', '.consultaconcepto_sueldos', function (event) {
            if ($(this).closest('#datosconcepto_sueldos').length) {
                return;
            }
            event.preventDefault();
            abrirModalConsultaConceptoSueldos(campoConceptoSueldosDesde($(this)));
        });

    $('#consultaconcepto_sueldosModal')
        .off('shown.bs.modal.consultaConceptoSueldos')
        .on('shown.bs.modal.consultaConceptoSueldos', function () {
            $(this).find('#consultaconcepto_sueldos').focus();
        });

    $(document)
        .off('click.eligeConsultaConceptoSueldos', '.eligeconsultaconcepto_sueldos')
        .on('click.eligeConsultaConceptoSueldos', '.eligeconsultaconcepto_sueldos', function (event) {
            event.preventDefault();
            var fila = leerFilaConceptoSueldosConsulta($(this));
            $('#consultaconcepto_sueldosModal').modal('hide');
            if (fila.id) {
                leeUnConceptoSueldos(fila.id, null, ptrCampoConceptoSueldos);
            }
        });
}

window.activa_eventos_consultaconcepto_sueldos = activa_eventos_consultaconcepto_sueldos;

$(function () {
    activa_eventos_consultaconcepto_sueldos();
});
