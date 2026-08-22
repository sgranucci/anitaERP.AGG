(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;
    var FORM = '#form-filtros-motivo-sancion-sueldos';
    var PANEL = '#panel-filtros-motivo-sancion-sueldos';

    function $valorPrincipal() { return $('#filtro_valor'); }
    function $valorPanel() { return $('#filtro_valor_panel'); }

    function parseOperadores() {
        var $sel = $('#filtro_operador');
        if (!$sel.length) { return; }
        try { operadoresPorCampo = JSON.parse($sel.attr('data-operadores') || '{}'); } catch (e) { operadoresPorCampo = {}; }
    }

    function tipoCampoActivo() {
        if ($('#filtro_modo').val() !== MODO_CAMPO) { return 'texto'; }
        return $('#filtro_campo option:selected').data('type') || 'texto';
    }

    function setPlaceholderValor(texto) {
        $valorPrincipal().attr('placeholder', texto);
        $valorPanel().attr('placeholder', texto);
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        var operador = $('#filtro_operador').val();
        $('.filtro-campo-wrap').toggle(modo === MODO_CAMPO);
        if (operador === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }
        setPlaceholderValor(tipoCampoActivo() === 'entero' ? 'Número entero' : 'Texto o número');
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa = modo === MODO_CAMPO
            ? (operadoresPorCampo[$('#filtro_campo').val()] || LF.operadoresModoTodos())
            : LF.operadoresModoTodos();
        LF.rellenarSelectOperadores($('#filtro_operador'), mapa, valorActual);
        actualizarVisibilidad();
    }

    $(function () {
        if (!$(FORM).length) { return; }
        parseOperadores();
        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        $(FORM).on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });
        $(FORM).on('submit.listadoFiltrosSync', function () {
            var abierto = $(PANEL).hasClass('show') || $(PANEL).hasClass('in');
            if (abierto) { $valorPrincipal().val($valorPanel().val()); }
            else { $valorPanel().val($valorPrincipal().val()); }
        });
        LF.initSubmitBusquedaRapida($(FORM), { selectorPanel: PANEL });
        $('#filtro_modo, #filtro_campo').on('change', function () { actualizarOperadores(false); });
        $('#filtro_operador').on('change', function () {
            if ($(this).val() === 'vacio') { $valorPrincipal().val(''); $valorPanel().val(''); }
            actualizarVisibilidad();
        });
        actualizarOperadores(true);
    });
})(jQuery);
