$(function () {
    $('#agrega_renglon_cuentacontable').on('click', function () {
        var tpl = document.getElementById('template-cuenta-iibb');
        if (!tpl || !tpl.content) {
            return;
        }
        var clone = document.importNode(tpl.content, true);
        $('#tbody-cuentacontable-table').append(clone);
        if (typeof activa_eventos_consultacuentacontable === 'function') {
            activa_eventos_consultacuentacontable();
        }
    });

    $(document).on('click', '.eliminar_cuentacontable', function () {
        var $rows = $('#tbody-cuentacontable-table tr.item-cuentacontable');
        if ($rows.length <= 1) {
            $(this).closest('tr').find('input').val('');
            return;
        }
        $(this).closest('tr').remove();
    });

    if (typeof activa_eventos_consultacuentacontable === 'function') {
        activa_eventos_consultacuentacontable();
    }
    if (typeof activa_eventos_consultaprovincia === 'function') {
        activa_eventos_consultaprovincia();
    }
});
