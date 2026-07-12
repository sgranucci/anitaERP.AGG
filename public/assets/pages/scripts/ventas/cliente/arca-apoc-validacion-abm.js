/**
 * ABM cliente: consulta WSAPOC (facturas apócrifas) en background.
 */
$(function () {
    if (!$('#cliente-arca-apoc-config').length) {
        return;
    }

    var timer = null;

    function dispararValidacionBackground() {
        if (typeof window.ArcaApocValidacionAsync === 'undefined') {
            return;
        }
        window.ArcaApocValidacionAsync.encolar({
            $config: $('#cliente-arca-apoc-config'),
            suspenderUi: true,
        });
    }

    function programarValidacion() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(dispararValidacionBackground, 600);
    }

    var clienteId = parseInt($('#cliente-arca-apoc-config').data('cliente-id') || '0', 10);
    if (clienteId > 0) {
        programarValidacion();
    }

    $('#numerodocumento').on('change.arcaApocCliente', programarValidacion);
});
