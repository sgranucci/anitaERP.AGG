(function ($) {
    'use strict';

    var LF = window.ListadoFiltros;

    function $valorPrincipal() {
        return $('#filtro_valor');
    }

    function $valorPanel() {
        return $('#filtro_valor_panel');
    }

    $(function () {
        if (!$('#form-filtros-recepcion-surmar').length) {
            return;
        }

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-recepcion-surmar');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                // Evita que el input vacío del panel pise el valor de la caja superior en el GET.
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-recepcion-surmar').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-recepcion-surmar').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-recepcion-surmar'), {
            selectorPanel: '#panel-filtros-recepcion-surmar'
        });
    });
})(jQuery);
