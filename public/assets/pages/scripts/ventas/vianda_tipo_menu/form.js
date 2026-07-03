$(function () {
    function clonarFilaDia(dia) {
        const html = $('#template-vianda-articulo-dia').html().replace(/__DIA__/g, String(dia));
        return $(html.trim());
    }

    $(document).on('click', '.agrega-articulo-dia', function (e) {
        e.preventDefault();
        const dia = $(this).data('dia');
        const $contenedor = $('#vianda-dia-items-' + dia);
        $contenedor.append(clonarFilaDia(dia));
    });

    $(document).on('click', '.eliminar-articulo-dia', function () {
        const $contenedor = $(this).closest('.vianda-dia-items');
        const $filas = $contenedor.find('.item-vianda-articulo-dia');
        if ($filas.length <= 1) {
            const $row = $(this).closest('.item-vianda-articulo-dia');
            $row.find('input').val('');
            return;
        }
        $(this).closest('.item-vianda-articulo-dia').remove();
    });

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }
});
