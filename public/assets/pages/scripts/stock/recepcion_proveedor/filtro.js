(function ($) {
    'use strict';

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

    function actualizarOperadores(mantenerSeleccion) {
        var campo = $('#filtro_campo').val() || 'numerorecepcion';
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa = operadoresPorCampo[campo] || LF.operadoresModoTodos();
        var $op = $('#filtro_operador');

        LF.rellenarSelectOperadores($op, mapa, valorActual);
    }

    $(function () {
        if (!$('#form-filtros-recepcion').length) {
            return;
        }

        parseOperadores();

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-recepcion');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-recepcion').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-recepcion').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-recepcion'), {
            selectorPanel: '#panel-filtros-recepcion'
        });

        $('#filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        actualizarOperadores(true);
    });
})(jQuery);
