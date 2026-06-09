(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;
    var $desde;
    var $hasta;
    var hastaTocado = false;

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

    function sincronizarHastaDesdeDesde() {
        if (!$desde || !$desde.length || !$hasta || !$hasta.length) {
            return;
        }
        var desdeVal = ($desde.val() || '').trim();
        if (desdeVal === '') {
            return;
        }
        if (hastaTocado && ($hasta.val() || '').trim() !== '') {
            return;
        }
        $hasta.val(desdeVal);
    }

    $(function () {
        if (!$('#form-filtros-cierres-turno').length) {
            return;
        }

        $desde = $('#fecha_desde');
        $hasta = $('#fecha_hasta');
        hastaTocado = ($hasta.val() || '').trim() !== '';

        parseOperadores();

        if (LF && LF.sincronizarValorPrincipal) {
            LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        }

        $desde.on('change input', sincronizarHastaDesdeDesde);
        $hasta.on('change input', function () {
            hastaTocado = ($hasta.val() || '').trim() !== '';
        });

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-cierres-turno');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-cierres-turno').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-cierres-turno').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        if (LF && LF.initSubmitBusquedaRapida) {
            LF.initSubmitBusquedaRapida($('#form-filtros-cierres-turno'), {
                selectorPanel: '#panel-filtros-cierres-turno'
            });
        }

        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        $('#filtro_operador').on('change', actualizarVisibilidad);

        actualizarOperadores(true);
    });
})(jQuery);
