(function ($) {
    'use strict';

    /**
     * Primer control editable preferido por solapa.
     * Si no hay match, cae al primer input/select/textarea visible de la solapa.
     */
    var FOCUS_POR_SOLAPA = {
        '#tab-datos': '#cuil, #nombre, #legajo',
        '#tab-laborales': '#fecha_ingreso, #categoria_id',
        '#tab-bases': '#nombrebase_id, #host-bases input:not([type=hidden]):not([type=checkbox]), #host-bases select',
        '#tab-leyendas': '#leyendas',
        '#tab-foto': '#foto_archivo',
        '#tab-archivos': '#empleado-tbody-tabla-archivo input[type=file]',
        '#tab-ausencias': '#ausencia_tipo, #ausencia_desde',
        '#tab-indumentaria': '#form-talles-indumentaria input:not([type=hidden]), #form-entrega-indumentaria input:not([type=hidden])',
        '#tab-historia': '#host-historia input[type=date], #host-historia select',
        '#tab-familiares': '#form-familiar-nuevo select[name=tipo], #form-familiar-nuevo input:not([type=hidden])',
        '#tab-planes-cuota': '#concepto_sueldos_id_codigo, #tab-planes-cuota .codigoconcepto_sueldos',
        '#tab-siradig': '#host-siradig input:not([type=hidden]):not([type=checkbox]), #host-siradig select'
    };

    function focusEnSolapa(tabHref) {
        var href = tabHref || '';
        var $pane = $(href);
        if (!$pane.length || !$pane.hasClass('active')) {
            return;
        }

        var preferidos = FOCUS_POR_SOLAPA[href] || '';
        var $target = $();
        if (preferidos) {
            $target = $pane.find(preferidos).filter(':visible:enabled').first();
        }
        if (!$target.length) {
            $target = $pane
                .find('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([readonly]), select, textarea')
                .filter(':visible:enabled')
                .first();
        }
        if (!$target.length) {
            return;
        }

        setTimeout(function () {
            try {
                $target.trigger('focus');
                if ($target.is('input[type=text], input[type=number], input:not([type])')) {
                    $target.trigger('select');
                }
            } catch (e) {
                // ignore
            }
        }, 60);
    }

    window.focusSolapaEmpleado = focusEnSolapa;

    function agregarRenglonArchivoEmpleado() {
        var tpl = document.getElementById('empleado-template-renglon-archivo');
        var tbody = document.getElementById('empleado-tbody-tabla-archivo');
        if (!tpl || !tbody || !tpl.content) {
            return;
        }
        tbody.appendChild(document.importNode(tpl.content, true));
    }

    $(function () {
        $(document).on('click', '.eliminar-archivo-empleado', function () {
            $(this).closest('.empleado-archivo-item').remove();
        });

        $('#empleado-agrega-renglon-archivo').on('click', function (e) {
            e.preventDefault();
            agregarRenglonArchivoEmpleado();
        });

        $(document).on('click', '.empleado-eliminararchivo', function (e) {
            e.preventDefault();
            var $tbody = $('#empleado-tbody-tabla-archivo');
            var $fila = $(this).closest('tr.item-archivo-empleado');
            if ($tbody.find('tr.item-archivo-empleado').length <= 1) {
                $fila.find('input[type=file]').val('');
                return;
            }
            $fila.remove();
        });

        $(document).on('shown.bs.tab', '#tabs-empleado a[data-toggle="tab"]', function () {
            focusEnSolapa($(this).attr('href'));
        });

        // Solapa inicial (datos personales)
        if ($('#tab-datos').hasClass('active')) {
            focusEnSolapa('#tab-datos');
        }
    });
})(jQuery);
