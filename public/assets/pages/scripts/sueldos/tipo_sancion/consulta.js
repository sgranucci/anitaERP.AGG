/* global carpetaBase */
var ptrCampoTipoSancion = $();
var _tipoSancionTimer = null;
var _tipoSancionModalAbriendose = false;

function campoTipoSancionDesde($el) {
    var $c = $($el).closest('.tm-tipo-sancion-campo');
    return $c.length ? $c : $();
}

function parsearHtmlConsultaTipoSancion(respuesta) {
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

function buscar_datos_tipo_sancion(consulta) {
    $.ajax({
        url: carpetaBase + '/sueldos/tipo-sancion/consulta',
        type: 'POST',
        dataType: 'HTML',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ($('input[name="_token"]').first().val() || '') },
        data: { consulta: consulta || '' }
    }).done(function (r) { $('#datostipo_sancion').html(parsearHtmlConsultaTipoSancion(r)); });
}

function aplicarTipoSancionEnCampo($campo, data) {
    if (!$campo.length || !data || !data.id) { return; }
    $campo.find('.tipo_sancion_id').val(data.id);
    $campo.find('.codigotipo_sancion').val(data.codigo || '').removeData('tipo-sancion-invalido');
    $campo.find('.nombretipo_sancion').val(data.nombre || '');
    var $link = $campo.find('.btn-link-editar-tipo-sancion');
    if ($link.length) {
        $link.attr('href', carpetaBase + '/sueldos/tipo-sancion/' + data.id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    }
    $campo.find('.tipo_sancion_id').trigger('change.tipoSancion', [data]);
}

function limpiarTipoSancionEnCampo($campo, mantenerCodigo) {
    $campo.find('.tipo_sancion_id').val('');
    if (!mantenerCodigo) { $campo.find('.codigotipo_sancion').val(''); }
    $campo.find('.nombretipo_sancion').val('');
    $campo.find('.btn-link-editar-tipo-sancion').attr('href', '#').addClass('d-none');
    $campo.find('.tipo_sancion_id').trigger('change.tipoSancion', [null]);
}
window.limpiarTipoSancionEnCampo = limpiarTipoSancionEnCampo;

function resolverTipoSancionPorCodigo($campo, alertar) {
    var codigo = String($campo.find('.codigotipo_sancion').val() || '').trim();
    if (codigo === '') { limpiarTipoSancionEnCampo($campo, true); return; }
    if ($('#consultatipo_sancionModal').hasClass('show') || _tipoSancionModalAbriendose) { return; }
    $.get(carpetaBase + '/sueldos/tipo-sancion/leerporcodigo/' + encodeURIComponent(codigo))
        .done(function (data) { aplicarTipoSancionEnCampo($campo, data); })
        .fail(function () {
            $campo.find('.codigotipo_sancion').data('tipo-sancion-invalido', 1);
            limpiarTipoSancionEnCampo($campo, true);
            if (alertar) {
                window.setTimeout(function () { window.alert('Tipo de sanción inexistente'); }, 0);
            }
        });
}

function activa_eventos_consultatipo_sancion() {
    $(document).off('.consultaTipoSancion');
    $(document).on('click.consultaTipoSancion', '.consultatipo_sancion', function (e) {
        e.preventDefault();
        ptrCampoTipoSancion = campoTipoSancionDesde(this);
        _tipoSancionModalAbriendose = true;
        buscar_datos_tipo_sancion('');
        $('#consultatipo_sancion').val('');
        $('#consultatipo_sancionModal').modal('show');
    });
    $('#consultatipo_sancionModal').on('shown.bs.modal', function () {
        _tipoSancionModalAbriendose = false;
        $('#consultatipo_sancion').trigger('focus');
    });
    $(document).on('input.consultaTipoSancion', '#consultatipo_sancion', function () {
        if (_tipoSancionTimer) { window.clearTimeout(_tipoSancionTimer); }
        var v = this.value;
        _tipoSancionTimer = window.setTimeout(function () { buscar_datos_tipo_sancion(v); }, 250);
    });
    $(document).on('keydown.consultaTipoSancion', '#consultatipo_sancion', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        var $btn = $('#datostipo_sancion .eligeconsultatipo_sancion').first();
        if ($btn.length) { $btn.trigger('click'); }
    });
    $(document).on('click.consultaTipoSancion', '.eligeconsultatipo_sancion', function () {
        var $tr = $(this).closest('tr');
        aplicarTipoSancionEnCampo(ptrCampoTipoSancion, {
            id: $tr.find('.tipo_sancion_id').text(),
            codigo: $tr.find('.codigotiposancion').text(),
            nombre: $tr.find('.nombretiposancion').text()
        });
        $('#consultatipo_sancionModal').modal('hide');
    });
    $(document).on('keydown.consultaTipoSancion', '.codigotipo_sancion', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            campoTipoSancionDesde(this).find('.consultatipo_sancion').trigger('click');
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            resolverTipoSancionPorCodigo(campoTipoSancionDesde(this), true);
        }
    });
    $(document).on('blur.consultaTipoSancion', '.codigotipo_sancion', function () {
        resolverTipoSancionPorCodigo(campoTipoSancionDesde(this), false);
    });
    $(document).on('input.consultaTipoSancion', '.codigotipo_sancion', function () {
        $(this).removeData('tipo-sancion-invalido');
    });
}

$(function () { activa_eventos_consultatipo_sancion(); });
