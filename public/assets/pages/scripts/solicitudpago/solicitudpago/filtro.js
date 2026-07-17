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
        var campo = $('#filtro_campo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa = campo && operadoresPorCampo[campo]
            ? operadoresPorCampo[campo]
            : (LF && LF.operadoresModoTodos ? LF.operadoresModoTodos() : {
                contiene: 'Contiene (en cualquier parte)',
                igual: 'Igual a',
                empieza: 'Empieza con',
                termina: 'Termina con'
            });
        if (LF && LF.rellenarSelectOperadores) {
            LF.rellenarSelectOperadores($('#filtro_operador'), mapa, valorActual);
        }
    }

    $(function () {
        if (!$('#form-filtros-solicitudpago').length) {
            return;
        }

        parseOperadores();
        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-solicitudpago');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-solicitudpago').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-solicitudpago').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-solicitudpago'), {
            selectorPanel: '#panel-filtros-solicitudpago'
        });

        $('#filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        actualizarOperadores(true);
    });
})(jQuery);
