/**
 * ABM cliente: validación impuestos ARCA en background al abrir edición o cambiar CUIT / condición IVA.
 */
$(function () {
    if (!$('#cliente-arca-validacion-config').length) {
        return;
    }

    var timer = null;

    function dispararValidacionBackground() {
        if (typeof window.ArcaPadronValidacionAsync === 'undefined') {
            return;
        }
        window.ArcaPadronValidacionAsync.encolar({
            $config: $('#cliente-arca-validacion-config'),
            suspenderUi: false,
        });
    }

    function programarValidacion() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(dispararValidacionBackground, 400);
    }

    var clienteId = parseInt($('#cliente-arca-validacion-config').data('cliente-id') || '0', 10);
    if (clienteId > 0) {
        programarValidacion();
    }

    $('#condicioniva_id, #numerodocumento').on('change.arcaValidacionCliente', programarValidacion);
});
