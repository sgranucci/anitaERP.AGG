$(function () {
    $('#cv-agrega_renglon_cuentacontable').on('click', agregaRenglonCuentacontableConceptoVenta);
    $(document).on('click', '.eliminar_cv_cuentacontable', borraRenglonCuentacontableConceptoVenta);
    $('#cv-agrega_renglon_precio').on('click', agregaRenglonPrecioConceptoVenta);
    $(document).on('click', '.eliminar_cv_precio', borraRenglonPrecioConceptoVenta);
    activa_eventos_concepto_venta(true);
});

function activa_eventos_concepto_venta(flInicio) {
    if (!flInicio) {
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
    }
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
    if (typeof activa_eventos_consultacentrocosto === 'function') {
        activa_eventos_consultacentrocosto();
    }
}

function agregaRenglonCuentacontableConceptoVenta(event) {
    event.preventDefault();
    var renglon = $('#cv-template-renglon-cuentacontable').html();
    $('#tbody-cv-cuentacontable-table').append(renglon);
    actualizaRenglonesCuentacontableConceptoVenta();
    activa_eventos_concepto_venta(false);
}

function borraRenglonCuentacontableConceptoVenta(event) {
    event.preventDefault();
    $(this).parents('tr').remove();
    actualizaRenglonesCuentacontableConceptoVenta();
}

function actualizaRenglonesCuentacontableConceptoVenta() {
    var item = 1;
    $('#tbody-cv-cuentacontable-table .cv-iicuenta').each(function () {
        $(this).val(item++);
    });
}

function agregaRenglonPrecioConceptoVenta(event) {
    event.preventDefault();
    var renglon = $('#cv-template-renglon-precio').html();
    $('#tbody-cv-precio-table').append(renglon);
}

function borraRenglonPrecioConceptoVenta(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}
