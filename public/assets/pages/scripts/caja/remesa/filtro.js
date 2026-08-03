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
            setPlaceholderValor('N\u00famero');
        } else {
            setPlaceholderValor('Texto o n\u00famero');
        }
    }

    function repoblarOperadores() {
        var campo = $('#filtro_campo').val();
        var ops = operadoresPorCampo[campo] || {};
        var $sel = $('#filtro_operador');
        var actual = $sel.val();
        $sel.empty();
        $.each(ops, function (key, label) {
            $sel.append($('<option></option>').attr('value', key).text(label));
        });
        if (actual && ops[actual]) {
            $sel.val(actual);
        }
    }

    $(function () {
        parseOperadores();
        actualizarVisibilidad();

        $('#filtro_modo').on('change', function () {
            actualizarVisibilidad();
            if ($(this).val() === MODO_CAMPO) {
                repoblarOperadores();
            }
        });

        $('#filtro_campo').on('change', function () {
            repoblarOperadores();
            actualizarVisibilidad();
        });

        if (LF && typeof LF.initForm === 'function') {
            LF.initForm({
                formId: 'form-filtros-remesa',
                panelId: 'panel-filtros-remesa',
                toggleId: 'btn-toggle-filtros-remesa',
                inputPrincipalId: 'filtro_valor',
                inputPanelId: 'filtro_valor_panel',
                hiddenRapidaId: 'filtro_busqueda_rapida'
            });
        }
    });
}(jQuery));
