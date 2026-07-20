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
        var tipo = tipoCampoActivo();

        if (modo === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
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

    // Si arranca por "desde" y "hasta" queda vacío, completar con hoy.
    function completarHastaConHoy() {
        var $desde = $('#fecha_desde');
        var $hasta = $('#fecha_hasta');
        if ($desde.val() && !$hasta.val()) {
            var hoy = new Date();
            var mes = String(hoy.getMonth() + 1).padStart(2, '0');
            var dia = String(hoy.getDate()).padStart(2, '0');
            $hasta.val(hoy.getFullYear() + '-' + mes + '-' + dia);
        }
    }

    $(function () {
        if (!$('#form-filtros-factura').length) {
            return;
        }

        parseOperadores();

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-factura');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-factura').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-factura').on('submit.listadoFiltrosSync', function () {
            completarHastaConHoy();
            sincronizarValorAntesDeEnviar();
        });

        // Al fijar "desde", precargar "hasta" con hoy si está vacío.
        $('#fecha_desde').on('change', function () {
            completarHastaConHoy();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-factura'), {
            selectorPanel: '#panel-filtros-factura'
        });

        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        $('#filtro_operador').on('change', function () {
            actualizarVisibilidad();
        });

        actualizarOperadores(true);
    });
})(jQuery);
