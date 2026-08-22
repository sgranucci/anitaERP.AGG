(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;

    function $valorPrincipal() {
        return $('#filtro_valor');
    }

    function $valorPanel() {
        return $('#filtro_valor_panel');
    }

    function parseOperadores() {
        var $sel = $('#filtro_operador');
        if (!$sel.length) {
            return;
        }
        try {
            operadoresPorCampo = JSON.parse($sel.attr('data-operadores') || '{}');
        } catch (e) {
            operadoresPorCampo = {};
        }
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        $('.filtro-campo-wrap').toggle(modo === MODO_CAMPO);
        if ($('#filtro_operador').val() === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }
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
        if (!$('#form-filtros-ingreso-catalogo').length) {
            return;
        }
        parseOperadores();
        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        $('#form-filtros-ingreso-catalogo').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });
        $('#form-filtros-ingreso-catalogo').on('submit.listadoFiltrosSync', function () {
            var $panel = $('#panel-filtros-ingreso-catalogo');
            if ($panel.hasClass('show') || $panel.hasClass('in')) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        });
        LF.initSubmitBusquedaRapida($('#form-filtros-ingreso-catalogo'), {
            selectorPanel: '#panel-filtros-ingreso-catalogo'
        });
        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });
        $('#filtro_operador').on('change', actualizarVisibilidad);
        actualizarOperadores(true);
    });
})(jQuery);
