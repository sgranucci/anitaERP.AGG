/**
 * ABM proveedor: consulta WSAPOC (facturas apócrifas) en background.
 */
$(function () {
    if (!$('#proveedor-arca-apoc-config').length) {
        return;
    }

    var timer = null;

    function dispararValidacionBackground() {
        if (typeof window.ArcaApocValidacionAsync === 'undefined') {
            return;
        }
        window.ArcaApocValidacionAsync.encolar({
            $config: $('#proveedor-arca-apoc-config'),
            suspenderUi: true,
        });
    }

    function programarValidacion() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(dispararValidacionBackground, 600);
    }

    var proveedorId = parseInt($('#proveedor-arca-apoc-config').data('proveedor-id') || '0', 10);
    if (proveedorId > 0) {
        programarValidacion();
    }

    $('#nroinscripcion').on('change.arcaApocProveedor', programarValidacion);
});
