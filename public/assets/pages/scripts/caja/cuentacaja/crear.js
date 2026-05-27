function empresaIdParaConsultaCuentaContable() {
    let empresaId = $('#empresa_id').val();

    if (!empresaId || empresaId === '0') {
        return 1;
    }

    return empresaId;
}

function limpiarCuentaContable() {
    $('#cuentacontable_id').val('');
    $('#codigocuentacontable').val('');
    $('#nombrecuentacontable').val('');
}

function actualizarRequeridoCbu() {
    let tieneBanco = $('#banco_id').val() !== '' && $('#banco_id').val() != null;

    if (tieneBanco) {
        $('#cbu_label').addClass('requerido');
        $('#cbu').prop('required', true);
    } else {
        $('#cbu_label').removeClass('requerido');
        $('#cbu').prop('required', false).css('borderColor', '');
    }
}

$(function () {
    activaEventosConsultaCuentaContableCuentacaja();
    actualizarRequeridoCbu();

    $('#empresa_id').on('change', function () {
        limpiarCuentaContable();
    });

    $('#banco_id').on('change', function () {
        if (!$('#banco_id').val()) {
            $('#cbu').val('').css('borderColor', '');
        }
        actualizarRequeridoCbu();
    });
});

function activaEventosConsultaCuentaContableCuentacaja() {
    $('#codigocuentacontable').off('change.cuentacaja').on('change.cuentacaja', function (event) {
        event.preventDefault();

        let codigoNuevo = $(this).val().trim();
        let empresaId = empresaIdParaConsultaCuentaContable();

        if (!codigoNuevo) {
            limpiarCuentaContable();
            return;
        }

        let urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + codigoNuevo;

        $.get(urlCta, function (data) {
            if (data.id > 0) {
                $('#cuentacontable_id').val(data.id);
                $('#nombrecuentacontable').val(data.nombre);
            } else {
                alert('No existe la cuenta');
                limpiarCuentaContable();
            }
        });
    });

    $('.consultacuentacontable').off('click.cuentacaja').on('click.cuentacaja', function (event) {
        event.preventDefault();

        let empresaId = empresaIdParaConsultaCuentaContable();

        $('#consultaempresa_id').val(empresaId);
        $('#consultacuentaModal').modal('show');
    });

    $('#consultacuentaModal').off('shown.bs.modal.cuentacaja').on('shown.bs.modal.cuentacaja', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultacuentaModal').off('click.cuentacaja').on('click.cuentacaja', function () {
        $('#consultacuentaModal').modal('hide');
    });

    $(document).off('click.cuentacaja', '.eligeconsultacuentacontable').on('click.cuentacaja', '.eligeconsultacuentacontable', function () {
        let seleccion = $(this).parents('tr').children().html();
        let nombre = $(this).parents('tr').find('.nombrecuentacontable').html();
        let codigo = $(this).parents('tr').find('.codigocuentacontable').html();

        $('#cuentacontable_id').val(seleccion);
        $('#nombrecuentacontable').val(nombre);
        $('#codigocuentacontable').val(codigo);

        $('#consultacuentaModal').modal('hide');
    });
}
