/**
 * Init barra Filtrar resultado (listado-filtros + modo/campo).
 * window.REPORTE_FILTROS_RESULTADO = { formId: 'form-…', panelId: 'panel-…' }
 */
(function ($) {
    'use strict';

    function init() {
        var cfg = window.REPORTE_FILTROS_RESULTADO || {};
        var formId = cfg.formId || 'form-reporte';
        var panelId = cfg.panelId || 'panel-filtros-resultado';
        var $form = $('#' + formId);
        if (!$form.length) {
            return;
        }

        if (window.ListadoFiltros) {
            window.ListadoFiltros.init();
            window.ListadoFiltros.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
            window.ListadoFiltros.initSubmitBusquedaRapida($form, {
                hiddenRapida: '#filtro_busqueda_rapida',
                selectorRapidaBtn: '[data-busqueda-rapida]',
                selectorAplicarBtn: '[data-aplicar-filtros-panel]',
                selectorInputRapida: '#filtro_valor',
                selectorPanel: '#' + panelId
            });
        }

        function syncCampoWrap() {
            var modo = $('#filtro_modo').val() || 'todos';
            $('.filtro-campo-wrap').toggle(modo === 'campo');
        }

        $('#filtro_modo').off('change.reporteFiltros').on('change.reporteFiltros', syncCampoWrap);
        syncCampoWrap();

        $('#filtro_campo').off('change.reporteFiltros').on('change.reporteFiltros', function () {
            var $op = $('#filtro_operador');
            var mapa = $op.data('operadores') || {};
            var campo = $(this).val();
            var ops = mapa[campo] || (window.ListadoFiltros && window.ListadoFiltros.operadoresModoTodos
                ? window.ListadoFiltros.operadoresModoTodos()
                : {});
            if (window.ListadoFiltros && window.ListadoFiltros.rellenarSelectOperadores) {
                window.ListadoFiltros.rellenarSelectOperadores($op, ops, $op.val());
            }
        });

        $form.on('submit.reporteFiltrosValor', function () {
            var $panelValor = $('#filtro_valor_panel');
            var $toolbarValor = $('#filtro_valor');
            if ($panelValor.length && $toolbarValor.length && document.activeElement === $panelValor[0]) {
                $toolbarValor.val($panelValor.val());
            }
        });
    }

    $(init);
})(jQuery);
