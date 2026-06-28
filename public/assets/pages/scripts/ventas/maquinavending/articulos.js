$(function () {
    $(document).on('click', '#agrega_renglon_maquinavending_articulo', function (e) {
        e.preventDefault();
        const $template = $('#template-maquinavending-articulo').find('tr').first().clone();
        $('#tbody-maquinavending-articulos').append($template);
    });

    $(document).on('click', '.eliminar_maquinavending_articulo', function () {
        const $tbody = $('#tbody-maquinavending-articulos');
        if ($tbody.find('tr.item-maquinavending-articulo').length <= 1) {
            const $row = $(this).closest('tr');
            $row.find('input').val('');
            return;
        }
        $(this).closest('tr').remove();
    });

    if (typeof activa_eventos_consultadeposito === 'function') {
        activa_eventos_consultadeposito();
    }

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }
});
