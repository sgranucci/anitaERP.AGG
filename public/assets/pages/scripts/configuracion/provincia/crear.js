$(function () {
    $('#agrega_renglon_tasaiibb').on('click', agregaRenglonTasaiibb);
    $(document).on('click', '.eliminar_tasaiibb', borraRenglonTasaiibb);
    $('#agrega_renglon_cuentacontableiibb').on('click', agregaRenglonCuentacontableiibb);
    $(document).on('click', '.eliminar_cuentacontableiibb', borraRenglonCuentacontableiibb);

    $('.botonsubmit').click(function () {
        $('#form-general').submit();
    });

    activa_eventos(true);
});

function activa_eventos(flInicio) {
    if (!flInicio) {
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
    }
    activa_eventos_consulta_cuentacontable();
}

function agregaRenglonTasaiibb(event) {
    event.preventDefault();
    var renglon = $('#template-renglon-tasaiibb').html();

    $('#tbody-tasaiibb-table').append(renglon);
    actualizaRenglonesTasaiibb();
}

function borraRenglonTasaiibb(event) {
    event.preventDefault();
    $(this).parents('tr').remove();
    actualizaRenglonesTasaiibb();
}

function actualizaRenglonesTasaiibb() {
    var item = 1;

    $('#tbody-tasaiibb-table .iitasaiibb').each(function () {
        $(this).val(item++);
    });
}

function agregaRenglonCuentacontableiibb(event) {
    event.preventDefault();
    var renglon = $('#template-renglon-cuentacontableiibb').html();

    $('#tbody-cuentacontableiibb-table').append(renglon);
    actualizaRenglonesCuentacontableiibb();

    activa_eventos(false);
}

function borraRenglonCuentacontableiibb(event) {
    event.preventDefault();
    $(this).parents('tr').remove();
    actualizaRenglonesCuentacontableiibb();
}

function actualizaRenglonesCuentacontableiibb() {
    var item = 1;

    $('#tbody-cuentacontableiibb-table .iicuenta').each(function () {
        $(this).val(item++);
    });
}
