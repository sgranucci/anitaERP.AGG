$(function () {
    'use strict';

    function renumerar() {
        var n = 1;
        $('#tbody-antiguedad-tramos tr.item-antiguedad-tramo').each(function () {
            var $nro = $(this).find('.nro-linea');
            if (!$nro.val()) {
                $nro.val(n);
            }
            // Reindex name attributes
            $(this).find('input').each(function () {
                var name = $(this).attr('name') || '';
                $(this).attr('name', name.replace(/tramos\[\d+]/, 'tramos[' + (n - 1) + ']'));
            });
            n++;
        });
    }

    $(document).on('click', '#agrega_renglon_antiguedad_tramo', function (e) {
        e.preventDefault();
        var html = $('#template-antiguedad-tramo').html() || '';
        var idx = $('#tbody-antiguedad-tramos tr.item-antiguedad-tramo').length;
        html = html.replace(/__IDX__/g, String(idx));
        $('#tbody-antiguedad-tramos').append(html);
        renumerar();
    });

    $(document).on('click', '.eliminar_antiguedad_tramo', function (e) {
        e.preventDefault();
        var $tbody = $('#tbody-antiguedad-tramos');
        if ($tbody.find('tr.item-antiguedad-tramo').length <= 1) {
            var $row = $(this).closest('tr');
            $row.find('input').val('');
            return;
        }
        $(this).closest('tr').remove();
        renumerar();
    });

    renumerar();
});
