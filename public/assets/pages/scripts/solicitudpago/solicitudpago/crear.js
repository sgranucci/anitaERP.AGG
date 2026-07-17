$(function () {
    $('#agrega_renglon_sp_cuenta').on('click', agregaRenglonCuenta);
    $(document).on('click', '.eliminar_sp_cuenta', borraRenglonCuenta);

    $('#agrega_renglon_sp_cuota').on('click', agregaRenglonCuota);
    $(document).on('click', '.eliminar_sp_cuota', borraRenglonCuota);

    $(document).on('click', '.eliminar_sp_archivo', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $('#tratamiento, #concepto_solicitudpago_id').on('change', actualizarVisibilidadCuotas);

    $('.botonsubmit').on('click', function () {
        $('#form-general').submit();
    });

    actualizarVisibilidadCuotas();
    activaEventos(true);
});

function activaEventos(flInicio) {
    if (!flInicio) {
        $('.consultaproveedor').off('click');
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
    }

    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
}

function agregaRenglonCuenta(event) {
    event.preventDefault();
    var html = $('#template-renglon-sp-cuenta').html();
    $('#tbody-solicitudpago-cuenta-table').append(html);
    activaEventos(false);
}

function borraRenglonCuenta(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}

function agregaRenglonCuota(event) {
    event.preventDefault();
    var html = $('#template-renglon-sp-cuota').html();
    $('#tbody-solicitudpago-cuota-table').append(html);
    renumerarCuotas();
}

function borraRenglonCuota(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
    renumerarCuotas();
}

function renumerarCuotas() {
    var n = 1;
    $('#tbody-solicitudpago-cuota-table .nro-cuota').each(function () {
        $(this).val(n++);
    });
}

function actualizarVisibilidadCuotas() {
    var tratamiento = ($('#tratamiento').val() || '').toUpperCase();
    var formaPago = ($('#concepto_solicitudpago_id option:selected').data('forma-pago') || '').toUpperCase();
    var mostrar = tratamiento === 'PLAN_DE_PAGO' || tratamiento === 'RECURRENTE' || formaPago === 'CUOTAS';
    if (mostrar) {
        $('#bloque-cuotas').show();
        $('#bloque-cuotas-aviso').hide();
    } else {
        $('#bloque-cuotas').hide();
        $('#bloque-cuotas-aviso').show();
    }
}
