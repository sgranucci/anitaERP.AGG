function empresaIdConsultaCuentacaja() {
    var $emp = $('#empresa_id');
    if (!$emp.length || String($emp.val() || '').trim() === '') {
        $emp = $('#wz_empresa_id');
    }
    if (typeof window.GASTRONOMIA !== 'undefined' && window.GASTRONOMIA.empresaId) {
        return String(window.GASTRONOMIA.empresaId);
    }
    if (typeof window.ESTACIONAMIENTO !== 'undefined' && window.ESTACIONAMIENTO.empresaId) {
        return String(window.ESTACIONAMIENTO.empresaId);
    }
    if (!$emp.length) {
        return '';
    }
    var v = parseInt(String($emp.val() || '0'), 10);
    return v > 0 ? String(v) : '';
}

function htmlFilasConsultaCuentacaja(respuesta) {
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

function buscar_datos_cuentacaja(consulta) {
    var empresa_id = empresaIdConsultaCuentacaja();
    var usocuentacaja_id = '';
    if (typeof window.GASTRONOMIA !== 'undefined' && parseInt(window.GASTRONOMIA.usocuentacajaGastronomiaId, 10) > 0) {
        usocuentacaja_id = parseInt(window.GASTRONOMIA.usocuentacajaGastronomiaId, 10);
    }
    if (typeof window.ESTACIONAMIENTO !== 'undefined' && parseInt(window.ESTACIONAMIENTO.usocuentacajaEstacionamientoId, 10) > 0) {
        usocuentacaja_id = parseInt(window.ESTACIONAMIENTO.usocuentacajaEstacionamientoId, 10);
    }

    $('#datoscuentacaja').html('<tr><td colspan="10" class="text-muted">Buscando…</td></tr>');
    $.ajax({
        url: carpetaBase + '/caja/cuentacaja/consultacuentacaja',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || '',
            empresa_id: empresa_id,
            usocuentacaja_id: usocuentacaja_id,
            excluir_cuentas_solo_automaticas:
                typeof window.GASTRONOMIA !== 'undefined' || typeof window.ESTACIONAMIENTO !== 'undefined' ? 1 : 0,
        },
    })
        .done(function (respuesta) {
            var html = htmlFilasConsultaCuentacaja(respuesta);
            if (!html) {
                html = '<tr><td colspan="10" class="text-muted">Sin resultados'
                    + (empresa_id ? ' para la empresa seleccionada' : '')
                    + '</td></tr>';
            }
            $('#datoscuentacaja').html(html);
        })
        .fail(function () {
            $('#datoscuentacaja').html('<tr><td colspan="10" class="text-danger">Error al consultar cuentas de caja</td></tr>');
        });
}

$('input').keydown(function (e) {
    var keyCode = e.which;
    if (keyCode == 13) {
        if ($(this).closest('#cuenta-table').length || $(this).is('#consultacuentacaja')) {
            return;
        }
        e.preventDefault();
        return false;
    }
});

function elegirPrimeraCuentacajaDelModal() {
    var $btn = $('#datoscuentacaja .eligeconsultacuentacaja').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

$(document).on('keyup', '#consultacuentacaja', function (e) {
    if (e.which === 13 || e.key === 'Enter') {
        return;
    }
    buscar_datos_cuentacaja(String($(this).val() || '').trim());
});

$(document).on('keydown', '#consultacuentacaja', function (e) {
    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    if (!elegirPrimeraCuentacajaDelModal()) {
        buscar_datos_cuentacaja(String($(this).val() || '').trim());
    }
});

$(document).on('submit', '#consultacuentacajaModal form', function (e) {
    e.preventDefault();
    elegirPrimeraCuentacajaDelModal();
});

$(document).on('dblclick', '#datoscuentacaja tr', function () {
    $(this).find('.eligeconsultacuentacaja').trigger('click');
});

$('#consultacuentacajaModal').on('shown.bs.modal', function () {
    var $input = $(this).find('#consultacuentacaja');
    $input.trigger('focus');
    buscar_datos_cuentacaja(String($input.val() || '').trim());
});

$('#aceptaconsultacuentacajaModal').on('click', function () {
    if (!elegirPrimeraCuentacajaDelModal()) {
        $('#consultacuentacajaModal').modal('hide');
    }
});
