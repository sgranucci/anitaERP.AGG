$(function () {
    $('#agrega_renglon_concepto_usuario').on('click', agregaRenglonUsuario);
    $(document).on('click', '.eliminar_concepto_usuario', borraRenglonUsuario);

    $('#agrega_renglon_concepto_cuenta').on('click', agregaRenglonCuenta);
    $(document).on('click', '.eliminar_concepto_cuenta', borraRenglonCuenta);

    $('.botonsubmit').on('click', function () {
        $('#form-general').submit();
    });

    activaEventos(true);
});

function activaEventos(flInicio) {
    if (!flInicio) {
        $('.consultausuario').off('click');
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
        $('.usuario_codigo_arbol').off('blur change');
    }

    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
}

function agregaRenglonUsuario(event) {
    event.preventDefault();
    var html = $('#template-renglon-concepto-usuario').html();
    $('#tbody-concepto-usuario-table').append(html);
    actualizaRenglonesUsuario();
    activaEventos(false);
}

function borraRenglonUsuario(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
    actualizaRenglonesUsuario();
}

function actualizaRenglonesUsuario() {
    var item = 1;
    $('#tbody-concepto-usuario-table .iiconcepto_usuario').each(function () {
        $(this).val(item++);
    });
}

function agregaRenglonCuenta(event) {
    event.preventDefault();
    var html = $('#template-renglon-concepto-cuenta').html();
    $('#tbody-concepto-cuenta-table').append(html);
    activaEventos(false);
}

function borraRenglonCuenta(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}
