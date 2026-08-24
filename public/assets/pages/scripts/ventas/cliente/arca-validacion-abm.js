/**
 * ABM cliente: validación impuestos ARCA en background al abrir edición o cambiar CUIT / condición IVA.
 */
$(function () {
    var $cfg = $('.js-cliente-arca-validacion-config, #cliente-arca-validacion-config').first();
    if (!$cfg.length) {
        return;
    }

    var timer = null;

    function dispararValidacionBackground() {
        if (typeof window.ArcaPadronValidacionAsync === 'undefined') {
            return;
        }
        window.ArcaPadronValidacionAsync.encolar({
            $config: $('.js-cliente-arca-validacion-config, #cliente-arca-validacion-config').first(),
            suspenderUi: false,
        });
    }

    function programarValidacion() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(dispararValidacionBackground, 400);
    }

    var clienteId = parseInt($cfg.data('cliente-id') || '0', 10);
    if (clienteId > 0) {
        programarValidacion();
    }

    $('#condicioniva_id, #numerodocumento').on('change.arcaValidacionCliente', programarValidacion);
});
