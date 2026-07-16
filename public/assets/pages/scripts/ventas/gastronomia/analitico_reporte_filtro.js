(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var PERIODO_MES = 'mes';
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

    function actualizarPeriodo() {
        var modo = $('#modo_periodo').val();
        if (modo === PERIODO_MES) {
            $('.js-periodo-mes').show();
            $('.js-periodo-rango').hide();
        } else {
            $('.js-periodo-mes').hide();
            $('.js-periodo-rango').show();
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

    function sincronizarValorAntesDeEnviar() {
        var $panel = $('#panel-filtros-analitico-gastro');
        var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
        if (panelAbierto) {
            $valorPrincipal().val($valorPanel().val());
        } else {
            $valorPanel().val($valorPrincipal().val());
        }
    }

    $(function () {
        parseOperadores();
        actualizarPeriodo();
        actualizarOperadores(true);

        $('#modo_periodo').on('change', actualizarPeriodo);
        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });
        $('#filtro_operador').on('change', actualizarVisibilidad);

        $valorPrincipal().on('input', function () {
            $valorPanel().val($(this).val());
        });
        $valorPanel().on('input', function () {
            $valorPrincipal().val($(this).val());
        });

        $('#form-analitico-gastro-reporte').on('submit', function (e) {
            sincronizarValorAntesDeEnviar();
            var $panel = $('#panel-filtros-analitico-gastro');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (!panelAbierto && ($valorPrincipal().val() || '').trim() !== '') {
                $('#filtro_busqueda_rapida').val('1');
            } else {
                $('#filtro_busqueda_rapida').val('');
            }

            var $dual = $(this).find('.reporte-empresas-dual');
            if ($dual.length && String($dual.data('empresa-unica')) !== '1') {
                var hayEmpresas = $dual.find('input[name="empresa_ids[]"]').length > 0;
                if (!hayEmpresas) {
                    e.preventDefault();
                    window.alert('Seleccione al menos una empresa.');
                    return false;
                }
            }
        });
    });
})(jQuery);
