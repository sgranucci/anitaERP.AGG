var ptrDeposito_id;
var ptrCodigoDeposito_id;
var ptrDescripcionDeposito;

function esFormularioDepmaeAbm() {
    return $('#form-general').length && $('#codigo[name="codigo"]').length;
}

function descripcionDepositoConEmpresa(descripcion, empresaNombre) {
    var desc = (descripcion || '').trim();
    var emp = (empresaNombre || '').trim();
    if ($('#consultadeposito-mostrar-empresa').val() === '1' && emp) {
        return desc + ' (' + emp + ')';
    }
    return desc;
}

/** Resuelve hidden/código/descripción según convención del partial campo_consulta_deposito ({inputId}_codigo). */
window.depositoCampoInputs = function (hiddenInputId) {
    hiddenInputId = (hiddenInputId || '').replace(/_codigo$|_descripcion$/, '');
    return {
        $id: $('#' + hiddenInputId),
        $codigo: $('#' + hiddenInputId + '_codigo'),
        $descripcion: $('#' + hiddenInputId + '_descripcion'),
    };
};

function copiarDepositoCampo(origenHiddenId, destinoHiddenId) {
    var origen = window.depositoCampoInputs(origenHiddenId);
    var destino = window.depositoCampoInputs(destinoHiddenId);
    destino.$id.val(origen.$id.val() || '');
    destino.$codigo.val(origen.$codigo.val() || '');
    destino.$descripcion.val(origen.$descripcion.val() || '');
    return destino;
}

window.copiarDepositoCampo = copiarDepositoCampo;

function limpiarDepositoCampo(hiddenInputId) {
    var campos = window.depositoCampoInputs(hiddenInputId);
    campos.$id.val('').trigger('change');
    campos.$codigo.val('');
    campos.$descripcion.val('');
    return campos;
}

window.limpiarDepositoCampo = limpiarDepositoCampo;

function empresaSelectParaConsultaDeposito() {
    if (esFormularioDepmaeAbm()) {
        return $();
    }

    var $emp = $();
    if (ptrDeposito_id && ptrDeposito_id.length) {
        var $form = ptrDeposito_id.closest('form');
        if ($form.length) {
            $emp = $form.find('#empresa_id').first();
        }
        if (!$emp.length) {
            $emp = ptrDeposito_id.closest('.card, .modal-content, .row, body').find('#empresa_id').first();
        }
    }
    if (!$emp.length) {
        $emp = $('#empresa_id').first();
    }

    return $emp;
}

function empresaIdParaConsultaDeposito() {
    if (esFormularioDepmaeAbm()) {
        return '';
    }
    var $emp = empresaSelectParaConsultaDeposito();
    if (!$emp.length) {
        return '';
    }
    return String($emp.val() || '').trim();
}

function empresaRequeridaPendienteEnFormulario() {
    var $emp = empresaSelectParaConsultaDeposito();
    if (!$emp.length || !$emp.is('select')) {
        return false;
    }

    return $emp.prop('required') && String($emp.val() || '').trim() === '';
}

function enfocarEmpresaFormularioDeposito() {
    var $emp = empresaSelectParaConsultaDeposito();
    if ($emp.length) {
        $emp.trigger('focus');
    }
}

function actualizarLinkEditarDeposito($ctx, depositoId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-deposito');
    if (!$link.length) {
        return;
    }
    var id = parseInt(depositoId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/depmae/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function limpiarCamposDepositoEnFormulario($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.deposito_id').val('').trigger('change');
    $ctx.find('.codigodeposito').val('');
    $ctx.find('.descripciondeposito').val('');
    actualizarLinkEditarDeposito($ctx, 0);
}

/**
 * Si el formulario tiene select #empresa_id vacío, precarga la empresa del depósito elegido.
 */
function aplicarEmpresaDesdeDeposito(empresaId) {
    if (window.recepcionProveedorEmpresaDesdeOc) {
        return;
    }
    var $emp = $('#empresa_id');
    if (!$emp.length || !$emp.is('select')) {
        return;
    }
    var empId = String(empresaId || '').trim();
    if (!empId) {
        return;
    }
    var actual = String($emp.val() || '').trim();
    if (actual !== '') {
        return;
    }
    window._omitirLimpiarDepositoAlCambiarEmpresa = true;
    $emp.val(empId).trigger('change');
    window._omitirLimpiarDepositoAlCambiarEmpresa = false;
}

function contextoCampoConsultaDeposito($fallback) {
    if ($fallback && $fallback.length) {
        return $fallback;
    }
    if (ptrDeposito_id && ptrDeposito_id.length) {
        return ptrDeposito_id.closest('.tm-deposito-campo, .depmae-campo-consulta, tr');
    }

    return $();
}

function buscar_datos_deposito(consulta) {
    var payload = {
        consulta: consulta || '',
    };
    if (typeof window.payloadExtraConsultaDeposito === 'function') {
        $.extend(payload, window.payloadExtraConsultaDeposito(contextoCampoConsultaDeposito()) || {});
    }
    var empresaId = empresaIdParaConsultaDeposito();
    if (empresaId && !payload.empresa_ids && !payload.intercompany) {
        payload.empresa_id = empresaId;
    }

    $.ajax({
        url: carpetaBase + '/stock/depmae/consultadeposito',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: payload,
    })
        .done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            } else if (respuesta && typeof respuesta.data === 'string') {
                html = respuesta.data;
            }
            $('#datosdeposito').html(html);
        })
        .fail(function () {
            $('#datosdeposito').html('<tr><td colspan="5">Error al consultar depósitos</td></tr>');
        });
}

function notificarDepositoAplicado($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (data && data.tipodeposito !== undefined) {
        $ctx.attr('data-tipodeposito', String(data.tipodeposito || ''));
        $ctx.find('.deposito_id').attr('data-tipodeposito', String(data.tipodeposito || ''));
    }
    $ctx.find('.deposito_id').trigger('change');
    if (typeof window.onDepositoAplicadoEnFormulario === 'function') {
        window.onDepositoAplicadoEnFormulario(data, $ctx);
    }
}

$('input').keydown(function (e) {
    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }
    if ($(this).is('.codigodeposito, #consultadeposito')) {
        return;
    }
    if ($(this).is('#filtro_valor, #filtro_valor_panel') || $(this).attr('name') === 'filtro_valor') {
        return;
    }
    // Grillas que validan SKU / cantidades con Enter (no bloquear)
    if ($(this).closest('#tabla-recuento-items, #tabla-items-movimientostock, #tabla-items-recepcion').length) {
        return;
    }
    // Recepción Surmar: Enter navega entre campos (crear / workbench / modal etiqueta)
    if ($(this).closest('#form-recepcion-surmar, #form-encabezado-surmar, #modalEtiquetaProveedorSurmar, #surmar-nuevo-item-campos').length) {
        return;
    }
    if ($(this).is('.surmar-enc-nav, .surmar-item-nav, .surmar-etiq-nav, #numero_oc_buscar, #certificado_senasa')) {
        return;
    }
    e.preventDefault();
    return false;
});

$(document)
    .off('keydown.leerDepositoEnter', '.codigodeposito')
    .on('keydown.leerDepositoEnter', '.codigodeposito', function (e) {
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.tm-deposito-campo, .depmae-campo-consulta, tr').find('.consultadeposito').first().trigger('click');
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        if (esFormularioDepmaeAbm()) {
            return;
        }
        if (empresaRequeridaPendienteEnFormulario()) {
            alert('Seleccione la empresa del formulario antes de consultar dep\u00f3sitos.');
            enfocarEmpresaFormularioDeposito();
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        leerDepositoPorCodigo($(this).val(), this);
    });

function esTeclaF1Deposito(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function manejarF1CodigoDepositoCapture(e) {
    if (!esTeclaF1Deposito(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigodeposito')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    $(target).closest('.tm-deposito-campo, .depmae-campo-consulta, tr').find('.consultadeposito').first().trigger('click');
}

if (!window.__depositoF1CaptureActivo) {
    document.addEventListener('keydown', manejarF1CodigoDepositoCapture, true);
    window.__depositoF1CaptureActivo = true;
}

$(document).on('keyup', '#consultadeposito', function () {
    buscar_datos_deposito($(this).val());
});

function activa_eventos_consultadeposito() {
    $('.consultadeposito')
        .off('click.consultaDeposito')
        .on('click.consultaDeposito', function () {
            if (empresaRequeridaPendienteEnFormulario()) {
                alert('Seleccione la empresa del formulario antes de consultar dep\u00f3sitos.');
                enfocarEmpresaFormularioDeposito();
                return;
            }
            var $btn = $(this);
            var $ctx = $btn.closest('.tm-deposito-campo, .depmae-campo-consulta, tr');

            ptrDeposito_id = $ctx.find('.deposito_id');
            if (!ptrDeposito_id.length) {
                ptrDeposito_id = $btn.parents('tr').find('.deposito_id');
            }

            ptrCodigoDeposito_id = $ctx.find('.codigodeposito');
            if (!ptrCodigoDeposito_id.length) {
                ptrCodigoDeposito_id = $btn.parents('tr').find('.codigodeposito');
            }

            ptrDescripcionDeposito = $ctx.find('.descripciondeposito');
            if (!ptrDescripcionDeposito.length) {
                ptrDescripcionDeposito = $btn.parents('tr').find('.descripciondeposito');
            }

            $('#consultadepositoModal')
                .removeAttr('inert')
                .css('display', '')
                .modal('show');
        });

    $('#consultadepositoModal')
        .off('shown.bs.modal.consultaDeposito')
        .on('shown.bs.modal.consultaDeposito', function () {
            $(this).removeAttr('inert');
            var $input = $('#consultadeposito');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
            buscar_datos_deposito($input.val());
        });

    $('#aceptaconsultadepositoModal')
        .off('click.consultaDeposito')
        .on('click.consultaDeposito', function () {
            $('#consultadepositoModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultadeposito')
        .on('click', '.eligeconsultadeposito', function () {
            var $tr = $(this).parents('tr');
            var id = $tr.find('.id').html();
            var codigo = $tr.find('.codigo').html();
            var descripcion = $tr.find('.descripcion').html();
            var tipodeposito = $tr.find('.tipodeposito').html() || '';
            var empresaIdDep = $tr.find('.empresa-id').html() || '';
            var empresaNombreDep = $tr.find('.empresa-nombre').html() || '';
            descripcion = descripcionDepositoConEmpresa(descripcion, empresaNombreDep);

            if ($('#form-general').length && $('#codigo[name="codigo"]').length
                && typeof window.aplicarDepositoEnFormularioAbm === 'function') {
                if (window.aplicarDepositoEnFormularioAbm({
                    id: id,
                    codigo: codigo,
                    descripcion: descripcion,
                    tipodeposito: tipodeposito,
                })) {
                    return;
                }
                $('#consultadepositoModal').modal('hide');
                return;
            }

            if (ptrDeposito_id && ptrDeposito_id.length && ptrDeposito_id.closest('#tbody-usuario-deposito-table').length) {
                var depId = String(id || '').trim();
                var $trTabla = ptrDeposito_id.closest('tr');
                var duplicado = false;
                $('#tbody-usuario-deposito-table .deposito_id').each(function () {
                    if ($(this).closest('tr').is($trTabla)) {
                        return;
                    }
                    if (String($(this).val() || '') === depId) {
                        duplicado = true;
                    }
                });
                if (duplicado) {
                    alert('Depósito ya cargado');
                    $('#consultadepositoModal').modal('hide');
                    return;
                }
            }

            if (ptrDeposito_id && ptrDeposito_id.length) {
                ptrDeposito_id.val(id);
            }
            if (ptrCodigoDeposito_id && ptrCodigoDeposito_id.length) {
                ptrCodigoDeposito_id.val(codigo);
            }
            if (ptrDescripcionDeposito && ptrDescripcionDeposito.length) {
                ptrDescripcionDeposito.val(descripcion);
            }

            var $ctxDep = ptrDeposito_id && ptrDeposito_id.length
                ? ptrDeposito_id.closest('.tm-deposito-campo, .depmae-campo-consulta, tr')
                : $();
            actualizarLinkEditarDeposito($ctxDep, id);
            aplicarEmpresaDesdeDeposito(empresaIdDep);

            if (ptrDeposito_id && ptrDeposito_id.length && ptrDeposito_id.closest('#tbody-usuario-deposito-table').length) {
                ptrDeposito_id.closest('tr').find('.empresa-deposito-nombre').val($tr.find('.empresa-nombre').html() || '');
            }

            var payloadDep = {
                id: id,
                codigo: codigo,
                descripcion: descripcion,
                tipodeposito: tipodeposito,
                empresa_id: parseInt(empresaIdDep, 10) || 0,
                empresa_nombre: empresaNombreDep,
            };
            $('#consultadepositoModal').one('hidden.bs.modal.depAplicadoRecuento', function () {
                notificarDepositoAplicado($ctxDep, payloadDep);
            });
            $('#consultadepositoModal').modal('hide');
        });

    $(document)
        .off('change.leerDepositoCod', '.codigodeposito')
        .on('change.leerDepositoCod', '.codigodeposito', function (e) {
        if (esFormularioDepmaeAbm()) {
            return;
        }
        if (empresaRequeridaPendienteEnFormulario()) {
            alert('Seleccione la empresa del formulario antes de consultar dep\u00f3sitos.');
            enfocarEmpresaFormularioDeposito();
            if (typeof onDone === 'function') {
                onDone(null);
            }
            return;
        }
        e.preventDefault();
        leerDepositoPorCodigo($(this).val(), this);
        });
}

function leerDepositoPorCodigo(codigo, ptrrenglon, onDone) {
    var cod = (codigo || '').trim();
    if (!cod) {
        if (typeof onDone === 'function') {
            onDone(null);
        }
        return;
    }

    var $ctx = $(ptrrenglon).closest('.tm-deposito-campo, .depmae-campo-consulta, tr');
    var codOriginal = cod;
    if ($ctx.length) {
        $ctx.find('.deposito_id').val('');
        $ctx.find('.descripciondeposito').val('');
    }

    var leerUrl = carpetaBase + '/stock/depmae/leer/' + encodeURIComponent(cod);
    var extraPayload = typeof window.payloadExtraConsultaDeposito === 'function'
        ? (window.payloadExtraConsultaDeposito(contextoCampoConsultaDeposito($ctx)) || {})
        : {};
    var empresaId = empresaIdParaConsultaDeposito();
    if (extraPayload.empresa_ids && extraPayload.empresa_ids.length) {
        extraPayload.empresa_ids.forEach(function (id, idx) {
            leerUrl += (leerUrl.indexOf('?') >= 0 ? '&' : '?') + 'empresa_ids[' + idx + ']=' + encodeURIComponent(id);
        });
    }
    if (extraPayload.omitir_filtro_usuario) {
        leerUrl += (leerUrl.indexOf('?') >= 0 ? '&' : '?') + 'omitir_filtro_usuario=1';
    }
    if (extraPayload.intercompany) {
        leerUrl += (leerUrl.indexOf('?') >= 0 ? '&' : '?') + 'intercompany=1';
    } else if (empresaId && !(extraPayload.empresa_ids && extraPayload.empresa_ids.length)) {
        leerUrl += (leerUrl.indexOf('?') >= 0 ? '&' : '?') + 'empresa_id=' + encodeURIComponent(empresaId);
    }

    $.get(leerUrl)
        .done(function (data) {
            if (!data || !data.id) {
                if ($ctx.length) {
                    $ctx.find('.codigodeposito').val(codOriginal);
                }
                alert('Dep\u00f3sito no encontrado');
                if (typeof onDone === 'function') {
                    onDone(null);
                }
                return;
            }

            if ($('#form-general').length && $('#codigo[name="codigo"]').length
                && typeof window.aplicarDepositoEnFormularioAbm === 'function') {
                if (window.aplicarDepositoEnFormularioAbm(data)) {
                    return;
                }
                if (typeof onDone === 'function') {
                    onDone(data);
                }
                return;
            }

            if ($ctx.length) {
                $ctx.find('.deposito_id').val(data.id);
                $ctx.find('.codigodeposito').val(data.codigo);
                $ctx.find('.descripciondeposito').val(
                    descripcionDepositoConEmpresa(data.descripcion, data.empresa_nombre)
                );
                actualizarLinkEditarDeposito($ctx, data.id);
                aplicarEmpresaDesdeDeposito(data.empresa_id);
                if ($ctx.closest('#tbody-usuario-deposito-table').length) {
                    $ctx.find('.empresa-deposito-nombre').val(data.empresa_nombre || '');
                }
                notificarDepositoAplicado($ctx, data);
            }
            if (typeof onDone === 'function') {
                onDone(data);
            }
        })
        .fail(function (xhr) {
            if ($ctx.length) {
                $ctx.find('.codigodeposito').val(codOriginal);
            }
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error)
                ? xhr.responseJSON.error
                : 'No se pudo leer el depósito (sin autorización o no existe).';
            if (typeof onDone !== 'function') {
                alert(msg);
            }
            if (typeof onDone === 'function') {
                onDone(null);
            }
        });
}

$(document).on('change', '#empresa_id', function () {
    if ($('.tm-deposito-campo').length && typeof buscar_datos_deposito === 'function') {
        buscar_datos_deposito($('#consultadeposito').val());
    }
    if (window._omitirLimpiarDepositoAlCambiarEmpresa) {
        return;
    }
    $('.tm-deposito-campo').each(function () {
        var $ctx = $(this);
        if ($ctx.closest('#tbody-usuario-deposito-table').length) {
            return;
        }
        limpiarCamposDepositoEnFormulario($ctx);
    });
});

$(function () {
    if (typeof activa_eventos_consultadeposito === 'function') {
        activa_eventos_consultadeposito();
    }
    $('.tm-deposito-campo').each(function () {
        var $ctx = $(this);
        actualizarLinkEditarDeposito($ctx, parseInt($ctx.find('.deposito_id').val(), 10) || 0);
    });
});
