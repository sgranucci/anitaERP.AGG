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
        if (modo === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
        }

        if ($('#filtro_operador').val() === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa;
        var $op = $('#filtro_operador');

        if (modo === MODO_CAMPO) {
            mapa = operadoresPorCampo[$('#filtro_campo').val()] || (LF ? LF.operadoresModoTodos() : {});
        } else {
            mapa = LF ? LF.operadoresModoTodos() : {};
        }

        if (LF && LF.rellenarSelectOperadores) {
            LF.rellenarSelectOperadores($op, mapa, valorActual);
        }
        actualizarVisibilidad();
    }

    $(function () {
        if (!$('#form-filtros-administracion-ticket').length) {
            return;
        }

        $('#ver_todos_tickets').on('change', function () {
            $('#form-filtros-administracion-ticket').trigger('submit');
        });

        parseOperadores();

        if (LF && LF.sincronizarValorPrincipal) {
            LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        }

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-administracion-ticket');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-administracion-ticket').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-administracion-ticket').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        if (LF && LF.initSubmitBusquedaRapida) {
            LF.initSubmitBusquedaRapida($('#form-filtros-administracion-ticket'), {
                selectorPanel: '#panel-filtros-administracion-ticket'
            });
        }

        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        $('#filtro_operador').on('change', function () {
            if ($(this).val() === 'vacio') {
                $valorPrincipal().val('');
                $valorPanel().val('');
            }
            actualizarVisibilidad();
        });

        actualizarOperadores(true);
    });
})(jQuery);
