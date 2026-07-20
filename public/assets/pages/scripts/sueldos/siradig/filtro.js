(function ($) {
    'use strict';

    var LF = window.ListadoFiltros;

    $(function () {
        var $form = $('#form-filtros-siradig');
        if (!$form.length) {
            return;
        }

        if (LF && LF.sincronizarValorPrincipal) {
            LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        }

        // Checkbox "solo vigentes" -> hidden que viaja en el GET.
        $(document).on('change', '#filtro_vigentes_chk', function () {
            $('#filtro_vigentes').val(this.checked ? '1' : '0');
        });

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-siradig');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $('#filtro_valor').val($('#filtro_valor_panel').val());
            } else {
                $('#filtro_valor_panel').val($('#filtro_valor').val());
            }
        }

        $form.on('click', '[data-aplicar-filtros-panel]', function () {
            $('#filtro_valor').val($('#filtro_valor_panel').val());
        });

        $form.on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        if (LF && LF.initSubmitBusquedaRapida) {
            LF.initSubmitBusquedaRapida($form, { selectorPanel: '#panel-filtros-siradig' });
        }
    });
})(jQuery);
