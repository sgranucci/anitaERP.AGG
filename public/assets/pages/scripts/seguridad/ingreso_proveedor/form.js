(function ($) {
    'use strict';

    function agregarPersona() {
        var tpl = document.getElementById('ingreso-template-persona');
        if (!tpl) {
            return;
        }
        var $item = $(tpl.content.cloneNode(true));
        $('#ingreso-personas #ingreso-agregar-persona').before($item);
    }

    function agregarArchivo() {
        var tpl = document.getElementById('ingreso-template-renglon-archivo');
        if (!tpl) {
            return;
        }
        $('#ingreso-tbody-tabla-archivo').append(tpl.content.cloneNode(true));
    }

    function sincronizarVisitante() {
        var visitante = $('#es_visitante').is(':checked');
        $('.ingreso-campo-proveedor').toggle(!visitante);
        $('.ingreso-campo-visitante').toggle(visitante);
        if (visitante) {
            $('#proveedor_id').val('');
            $('#codigoproveedor').val('');
            $('#nombreproveedor').val('');
            $('#ordencompra_id').val('');
        }
    }

    $(function () {
        $('#es_visitante').on('change', sincronizarVisitante);
        sincronizarVisitante();

        $('#ingreso-agregar-persona').on('click', function () {
            agregarPersona();
        });

        $('#ingreso-agrega-renglon-archivo').on('click', function () {
            agregarArchivo();
        });

        $(document).on('click', '.ingreso-eliminararchivo', function () {
            var $filas = $('#ingreso-tbody-tabla-archivo tr.item-archivo-ingreso');
            if ($filas.length <= 1) {
                $filas.find('input[type=file]').val('');
                return;
            }
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.ingreso-quitar-archivo', function () {
            $(this).closest('.ingreso-archivo-item').remove();
        });

        if (typeof window.activa_eventos_consultaproveedor === 'function') {
            window.activa_eventos_consultaproveedor();
        }
    });
})(jQuery);
