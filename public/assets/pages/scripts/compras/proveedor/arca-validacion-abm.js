/**
 * ABM proveedor: validación impuestos ARCA en background al abrir edición o cambiar CUIT / condición IVA.
 */
$(function () {
    if (!$('#proveedor-arca-validacion-config').length) {
        return;
    }

    var timer = null;

    function dispararValidacionBackground() {
        if (typeof window.ArcaPadronValidacionAsync === 'undefined') {
            return;
        }
        window.ArcaPadronValidacionAsync.encolar({
            $config: $('#proveedor-arca-validacion-config'),
            suspenderUi: true,
        });
    }

    function programarValidacion() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(dispararValidacionBackground, 400);
    }

    var proveedorId = parseInt($('#proveedor-arca-validacion-config').data('proveedor-id') || '0', 10);
    if (proveedorId > 0) {
        programarValidacion();
    }

    $('#condicioniva_id, #nroinscripcion').on('change.arcaValidacionProveedor', programarValidacion);
});
