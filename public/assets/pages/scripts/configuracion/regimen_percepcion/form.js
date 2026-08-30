$(function () {
    $('#rp-agrega_renglon_cuentacontable').on('click', agregaRenglonCuentacontableRegimen);
    $(document).on('click', '.eliminar_rp_cuentacontable', borraRenglonCuentacontableRegimen);
    activa_eventos_regimen(true);
});

function activa_eventos_regimen(flInicio) {
    if (!flInicio) {
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
    }
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
}

function agregaRenglonCuentacontableRegimen(event) {
    event.preventDefault();
    var renglon = $('#rp-template-renglon-cuentacontable').html();
    $('#tbody-rp-cuentacontable-table').append(renglon);
    actualizaRenglonesCuentacontableRegimen();
    activa_eventos_regimen(false);
}

function borraRenglonCuentacontableRegimen(event) {
    event.preventDefault();
    $(this).parents('tr').remove();
    actualizaRenglonesCuentacontableRegimen();
}

function actualizaRenglonesCuentacontableRegimen() {
    var item = 1;
    $('#tbody-rp-cuentacontable-table .rp-iicuenta').each(function () {
        $(this).val(item++);
    });
}
