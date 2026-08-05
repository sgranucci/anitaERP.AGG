(function ($) {
    'use strict';

    function reindexTolerancias() {
        $('#tolerancia-cp-table tbody tr.item-tolerancia-cp').each(function (idx) {
            $(this).find('[name^="tolerancias["]').each(function () {
                var name = $(this).attr('name');
                if (!name) {
                    return;
                }
                $(this).attr('name', name.replace(/tolerancias\[\d+]/, 'tolerancias[' + idx + ']'));
            });
        });
    }

    $(function () {
        $('#empresa_id').on('change', function () {
            var id = $(this).val();
            if (id) {
                window.location = (window.carpetaBase || '') + '/compras/configuracion-comprobante-proveedor?empresa_id=' + id;
            }
        });

        $('#btn-agregar-tolerancia-cp').on('click', function () {
            var tpl = document.getElementById('template-tolerancia-cp');
            if (!tpl) {
                return;
            }
            var idx = $('#tolerancia-cp-table tbody tr.item-tolerancia-cp').length;
            var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
            $('#tolerancia-cp-table tbody').append(html);
        });

        $(document).on('click', '.js-quitar-tolerancia-cp', function () {
            $(this).closest('tr').remove();
            reindexTolerancias();
        });
    });
})(jQuery);
