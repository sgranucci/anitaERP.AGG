/* global carpetaBase */
var ptrCampoMotivoSancion = $();
var _motivoSancionTimer = null;
var _motivoSancionModalAbriendose = false;

function campoMotivoSancionDesde($el) {
    var $c = $($el).closest('.tm-motivo-sancion-campo');
    return $c.length ? $c : $();
}

function parsearHtmlConsultaMotivoSancion(respuesta) {
    if (respuesta && typeof respuesta === 'object') {
        return respuesta.data || '';
    }
    var resp = String(respuesta || '').trim();
    if (resp === '') {
        return '';
    }
    try {
        var parsed = JSON.parse(resp);
        return (parsed && parsed.data) ? parsed.data : '';
    } catch (e) {
        if (resp.indexOf('<tr') >= 0) {
            return resp;
        }
        return '';
    }
}

function buscar_datos_motivo_sancion(consulta) {
    $.ajax({
        url: carpetaBase + '/sueldos/motivo-sancion/consulta',
        type: 'POST',
        dataType: 'HTML',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ($('input[name="_token"]').first().val() || '') },
        data: { consulta: consulta || '' }
    }).done(function (r) { $('#datosmotivo_sancion').html(parsearHtmlConsultaMotivoSancion(r)); });
}

function aplicarMotivoSancionEnCampo($campo, data) {
    if (!$campo.length || !data || !data.id) { return; }
    $campo.find('.motivo_sancion_id').val(data.id);
    $campo.find('.codigomotivo_sancion').val(data.codigo || '').removeData('motivo-sancion-invalido');
    $campo.find('.nombremotivo_sancion').val(data.nombre || '');
    var $link = $campo.find('.btn-link-editar-motivo-sancion');
    if ($link.length) {
        $link.attr('href', carpetaBase + '/sueldos/motivo-sancion/' + data.id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    }
    $campo.find('.motivo_sancion_id').trigger('change.motivoSancion', [data]);
}

function limpiarMotivoSancionEnCampo($campo, mantenerCodigo) {
    $campo.find('.motivo_sancion_id').val('');
    if (!mantenerCodigo) { $campo.find('.codigomotivo_sancion').val(''); }
    $campo.find('.nombremotivo_sancion').val('');
    $campo.find('.btn-link-editar-motivo-sancion').attr('href', '#').addClass('d-none');
}

function resolverMotivoSancionPorCodigo($campo, alertar) {
    var codigo = String($campo.find('.codigomotivo_sancion').val() || '').trim();
    if (codigo === '') { limpiarMotivoSancionEnCampo($campo, true); return; }
    if ($('#consultamotivo_sancionModal').hasClass('show') || _motivoSancionModalAbriendose) { return; }
    $.get(carpetaBase + '/sueldos/motivo-sancion/leerporcodigo/' + encodeURIComponent(codigo))
        .done(function (data) { aplicarMotivoSancionEnCampo($campo, data); })
        .fail(function () {
            $campo.find('.codigomotivo_sancion').data('motivo-sancion-invalido', 1);
            limpiarMotivoSancionEnCampo($campo, true);
            if (alertar) {
                window.setTimeout(function () { window.alert('Motivo de sanción inexistente'); }, 0);
            }
        });
}

function activa_eventos_consultamotivo_sancion() {
    $(document).off('.consultaMotivoSancion');
    $(document).on('click.consultaMotivoSancion', '.consultamotivo_sancion', function (e) {
        e.preventDefault();
        ptrCampoMotivoSancion = campoMotivoSancionDesde(this);
        _motivoSancionModalAbriendose = true;
        buscar_datos_motivo_sancion('');
        $('#consultamotivo_sancion').val('');
        $('#consultamotivo_sancionModal').modal('show');
    });
    $('#consultamotivo_sancionModal').on('shown.bs.modal', function () {
        _motivoSancionModalAbriendose = false;
        $('#consultamotivo_sancion').trigger('focus');
    });
    $(document).on('input.consultaMotivoSancion', '#consultamotivo_sancion', function () {
        if (_motivoSancionTimer) { window.clearTimeout(_motivoSancionTimer); }
        var v = this.value;
        _motivoSancionTimer = window.setTimeout(function () { buscar_datos_motivo_sancion(v); }, 250);
    });
    $(document).on('keydown.consultaMotivoSancion', '#consultamotivo_sancion', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        $('#datosmotivo_sancion .eligeconsultamotivo_sancion').first().trigger('click');
    });
    $(document).on('click.consultaMotivoSancion', '.eligeconsultamotivo_sancion', function () {
        var $tr = $(this).closest('tr');
        aplicarMotivoSancionEnCampo(ptrCampoMotivoSancion, {
            id: $tr.find('.motivo_sancion_id').text(),
            codigo: $tr.find('.codigomotivosancion').text(),
            nombre: $tr.find('.nombremotivosancion').text()
        });
        $('#consultamotivo_sancionModal').modal('hide');
    });
    $(document).on('keydown.consultaMotivoSancion', '.codigomotivo_sancion', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            campoMotivoSancionDesde(this).find('.consultamotivo_sancion').trigger('click');
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            resolverMotivoSancionPorCodigo(campoMotivoSancionDesde(this), true);
        }
    });
    $(document).on('blur.consultaMotivoSancion', '.codigomotivo_sancion', function () {
        resolverMotivoSancionPorCodigo(campoMotivoSancionDesde(this), false);
    });
}

$(function () { activa_eventos_consultamotivo_sancion(); });
