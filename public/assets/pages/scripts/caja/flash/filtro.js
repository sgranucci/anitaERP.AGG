(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;

    function $valorPrincipal() { return $('#filtro_valor'); }
    function $valorPanel() { return $('#filtro_valor_panel'); }

    function parseOperadores() {
        var $sel = $('#filtro_operador');
        if (!$sel.length) { return; }
        try {
            operadoresPorCampo = JSON.parse($sel.attr('data-operadores') || '{}');
        } catch (e) {
            operadoresPorCampo = {};
        }
    }

    function tipoCampoActivo() {
        if ($('#filtro_modo').val() !== MODO_CAMPO) { return 'texto'; }
        return $('#filtro_campo option:selected').data('type') || 'texto';
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        var operador = $('#filtro_operador').val();
        var tipo = tipoCampoActivo();
        $('.filtro-campo-wrap').toggle(modo === MODO_CAMPO);
        if (operador === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }
        var ph = (tipo === 'entero' || tipo === 'decimal') ? 'N\u00famero o fecha' : 'Fecha, empresa o comentario';
        $valorPrincipal().attr('placeholder', ph);
        $valorPanel().attr('placeholder', ph);
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa = (modo === MODO_CAMPO)
            ? (operadoresPorCampo[$('#filtro_campo').val()] || LF.operadoresModoTodos())
            : LF.operadoresModoTodos();
        LF.rellenarSelectOperadores($('#filtro_operador'), mapa, valorActual);
        actualizarVisibilidad();
    }

    $(function () {
        if (!$('#form-filtros-flash-caja').length) { return; }
        parseOperadores();
        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        $('#form-filtros-flash-caja').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });
        $('#form-filtros-flash-caja').on('submit.listadoFiltrosSync', function () {
            var $panel = $('#panel-filtros-flash-caja');
            var abierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (abierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        });
        LF.initSubmitBusquedaRapida($('#form-filtros-flash-caja'), { selectorPanel: '#panel-filtros-flash-caja' });
        $('#filtro_modo, #filtro_campo').on('change', function () { actualizarOperadores(false); });
        $('#filtro_operador').on('change', actualizarVisibilidad);
        actualizarOperadores(true);
    });
})(jQuery);
