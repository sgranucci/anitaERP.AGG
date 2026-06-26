$(function () {
    if (!$('#form-general[data-admin-usuario-depositos="1"]').length) {
        return;
    }

    $('#agrega_renglon_usuario_deposito').on('click', agregaRenglonDeposito);
    $(document).on('click', '.eliminar_usuario_deposito', borraRenglonDeposito);

    $('#botonform1').on('click', function () {
        $('.form1').show();
        $('.form2').hide();
        $('.form3').hide();
        $(this).removeClass('btn-info').addClass('btn-primary');
        $('#botonform2').removeClass('btn-primary').addClass('btn-info');
        $('#botonform3').removeClass('btn-primary').addClass('btn-info');
    });

    $('#botonform2').on('click', function () {
        if (!empresasUsuarioSeleccionadas().length) {
            alert('Seleccione al menos una empresa antes de asignar depósitos.');
            return;
        }
        $('.form1').hide();
        $('.form2').show();
        $('.form3').hide();
        $(this).removeClass('btn-info').addClass('btn-primary');
        $('#botonform1').removeClass('btn-primary').addClass('btn-info');
        $('#botonform3').removeClass('btn-primary').addClass('btn-info');
    });

    if (typeof activa_eventos_consultadeposito === 'function') {
        activa_eventos_consultadeposito();
    }

    $(document).on('change', '#tbody-usuario-deposito-table .codigodeposito', function () {
        var $tr = $(this).closest('tr');
        var codigo = String($(this).val() || '').trim();
        if (!codigo) {
            $tr.find('.deposito_id').val('');
            $tr.find('.descripciondeposito').val('');
            $tr.find('.empresa-deposito-nombre').val('');
            return;
        }
        if (depositoDuplicadoEnTabla($tr, codigo)) {
            alert('Depósito ya cargado');
            $tr.find('.deposito_id').val('');
            $(this).val('');
            $tr.find('.descripciondeposito').val('');
            $tr.find('.empresa-deposito-nombre').val('');
        }
    });
});

function empresasUsuarioSeleccionadas() {
    if (typeof window.empresasUsuarioSeleccionadas === 'function') {
        return window.empresasUsuarioSeleccionadas();
    }
    var ids = $('#empresa_id').val();
    if (!ids) {
        $('#empresas_asignadas_hidden input[name="empresa_ids[]"]').each(function () {
            ids = ids || [];
            if (!Array.isArray(ids)) {
                ids = [ids];
            }
            ids.push(String($(this).val()));
        });
    }
    if (!ids) {
        return [];
    }
    return Array.isArray(ids) ? ids : [String(ids)];
}

function agregaRenglonDeposito(event) {
    event.preventDefault();
    if (!empresasUsuarioSeleccionadas().length) {
        alert('Seleccione al menos una empresa antes de asignar depósitos.');
        return;
    }
    var renglon = $('#template-renglon-usuario-deposito').html();
    $('#tbody-usuario-deposito-table').append(renglon);
    if (typeof activa_eventos_consultadeposito === 'function') {
        activa_eventos_consultadeposito();
    }
    $('#usuario-deposito-table').find('tr').last().find('.codigodeposito').focus();
}

function borraRenglonDeposito(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}

function depositoDuplicadoEnTabla($trActual, codigo) {
    var duplicado = false;
    $('#tbody-usuario-deposito-table .codigodeposito').each(function () {
        if ($(this).closest('tr').is($trActual)) {
            return;
        }
        if (String($(this).val() || '').trim() === codigo) {
            duplicado = true;
        }
    });
    return duplicado;
}

function depositoDuplicadoPorId($trActual, id) {
    var duplicado = false;
    $('#tbody-usuario-deposito-table .deposito_id').each(function () {
        if ($(this).closest('tr').is($trActual)) {
            return;
        }
        if (String($(this).val() || '') === String(id)) {
            duplicado = true;
        }
    });
    return duplicado;
}

window.esConsultaDepositoAdminUsuario = function () {
    return $('#form-general[data-admin-usuario-depositos="1"]').length > 0;
};

window.payloadExtraConsultaDeposito = function () {
    if (!window.esConsultaDepositoAdminUsuario()) {
        return {};
    }
    return {
        omitir_filtro_usuario: 1,
        empresa_ids: empresasUsuarioSeleccionadas(),
    };
};
