(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var FORM = '#form-filtros-tracking-facturas';
    var PANEL = '#panel-filtros-tracking-facturas';
    var EJE_CARGA = 'carga';
    var SEGMENTO_CARGADOS = 'cargados_entre_fechas';

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
        if ($('#filtro_modo').val() !== MODO_CAMPO) {
            return 'texto';
        }
        return $('#filtro_campo option:selected').data('type') || 'texto';
    }

    function setPlaceholderValor(texto) {
        $valorPrincipal().attr('placeholder', texto);
        $valorPanel().attr('placeholder', texto);
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        var operador = $('#filtro_operador').val();
        var tipo = tipoCampoActivo();

        $('.filtro-campo-wrap').toggle(modo === MODO_CAMPO);
        $('.filtro-valor-hasta-wrap').toggle(modo === MODO_CAMPO && operador === 'entre' && tipo === 'fecha');

        if (operador === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }

        if (tipo === 'entero') {
            setPlaceholderValor('Número entero');
        } else if (tipo === 'fecha') {
            setPlaceholderValor('Fecha (dd/mm/aaaa)');
        } else {
            setPlaceholderValor('Número, proveedor, CUIT…');
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

    /**
     * El segmento «cargados entre fechas» pregunta por la fecha de carga.
     * Si el usuario cambia el eje a otra cosa, el chip dejaría de significar lo
     * que dice, así que se vuelve al segmento «todos».
     */
    function sincronizarSegmentoConEje() {
        var $segmento = $('input[name="segmento"]', FORM);
        if (!$segmento.length || $segmento.val() !== SEGMENTO_CARGADOS) {
            return;
        }
        if ($('#eje_fecha').val() !== EJE_CARGA) {
            $segmento.val('todos');
        }
    }

    function sincronizarValorAntesDeEnviar() {
        var $panel = $(PANEL);
        var abierto = $panel.hasClass('show') || $panel.hasClass('in');
        if (abierto) {
            $valorPrincipal().val($valorPanel().val());
        } else {
            $valorPanel().val($valorPrincipal().val());
        }
    }

    $(function () {
        var $form = $(FORM);
        if (!$form.length) {
            return;
        }

        parseOperadores();
        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        $form.on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $form.on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
            sincronizarSegmentoConEje();
        });

        LF.initSubmitBusquedaRapida($form, { selectorPanel: PANEL });

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

        // Cambiar el eje de fechas reconsulta directamente: es el filtro que más
        // cambia el resultado y no tiene sentido pedir un click extra.
        $('#eje_fecha').on('change', function () {
            sincronizarSegmentoConEje();
            $form.trigger('submit');
        });

        actualizarOperadores(true);
    });
})(jQuery);
