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

    function tipoCampoActivo() {
        var modo = $('#filtro_modo').val();
        if (modo !== MODO_CAMPO) {
            return 'texto';
        }
        var $opt = $('#filtro_campo option:selected');
        return $opt.data('type') || 'texto';
    }

    function setPlaceholderValor(texto) {
        $valorPrincipal().attr('placeholder', texto);
        $valorPanel().attr('placeholder', texto);
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        var operador = $('#filtro_operador').val();
        var tipo = tipoCampoActivo();

        if (modo === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
        }

        if (operador === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }

        if (tipo === 'entero') {
            setPlaceholderValor('Número entero');
        } else {
            setPlaceholderValor('Texto o número');
        }
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa;
        var $op = $('#filtro_operador');

        if (modo === MODO_CAMPO) {
            var campo = $('#filtro_campo').val();
            mapa = operadoresPorCampo[campo] || LF.operadoresModoTodos();
        } else {
            mapa = LF.operadoresModoTodos();
        }

        LF.rellenarSelectOperadores($op, mapa, valorActual);
        actualizarVisibilidad();
    }

    $(function () {
        if (!$('#form-filtros-lugartrabajo-sueldos').length) {
            return;
        }

        parseOperadores();

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-lugartrabajo-sueldos');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-lugartrabajo-sueldos').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-lugartrabajo-sueldos').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-lugartrabajo-sueldos'), {
            selectorPanel: '#panel-filtros-lugartrabajo-sueldos'
        });

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
