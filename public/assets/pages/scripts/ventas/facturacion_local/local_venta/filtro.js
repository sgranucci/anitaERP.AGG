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

    function refrescarOperadores() {
        var campo = $('#filtro_campo').val() || 'nombre';
        var ops = operadoresPorCampo[campo] || {};
        var $sel = $('#filtro_operador');
        var actual = $sel.val();
        $sel.empty();
        $.each(ops, function (k, label) {
            $sel.append($('<option>', { value: k, text: label }));
        });
        if (ops[actual]) {
            $sel.val(actual);
        }
        setPlaceholderValor(
            tipoCampoActivo() === 'entero'
                ? 'Número…'
                : 'Texto (tolera errores de tipeo)…'
        );
    }

    function syncPanelVisibilidad() {
        var modo = $('#filtro_modo').val();
        $('.filtro-campo-wrap').toggle(modo === MODO_CAMPO);
        refrescarOperadores();
    }

    $(function () {
        parseOperadores();
        syncPanelVisibilidad();

        $('#filtro_modo').on('change', syncPanelVisibilidad);
        $('#filtro_campo').on('change', refrescarOperadores);

        if (LF && typeof LF.init === 'function') {
            LF.init({
                formId: 'form-filtros-local-venta',
                valorPrincipal: $valorPrincipal,
                valorPanel: $valorPanel,
            });
        }
    });
})(jQuery);
