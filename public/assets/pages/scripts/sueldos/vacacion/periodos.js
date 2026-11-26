$(function () {
    // Anita numera los tramos desde 0 (0-5 años, 5-10, ...). Una línea vacía toma la siguiente libre.
    function renumerarLineasVacias() {
        var usados = [];
        $('#tbody-vacacion-periodos tr.item-vacacion-periodo .nro-linea').each(function () {
            if ($(this).val() !== '') {
                usados.push(parseInt($(this).val(), 10));
            }
        });
        var siguiente = usados.length ? Math.max.apply(null, usados) + 1 : 0;
        $('#tbody-vacacion-periodos tr.item-vacacion-periodo .nro-linea').each(function () {
            if ($(this).val() === '') {
                $(this).val(siguiente);
                siguiente++;
            }
        });
    }

    $(document).on('click', '#agrega_renglon_vacacion_periodo', function (e) {
        e.preventDefault();
        var $template = $('#template-vacacion-periodo').find('tr').first().clone();
        $template.find('input').val('');
        $template.find('select').val('');
        $('#tbody-vacacion-periodos').append($template);
        renumerarLineasVacias();
    });

    $(document).on('click', '.eliminar_vacacion_periodo', function () {
        var $tbody = $('#tbody-vacacion-periodos');
        if ($tbody.find('tr.item-vacacion-periodo').length <= 1) {
            var $row = $(this).closest('tr');
            $row.find('input').val('');
            $row.find('select').val('');
            return;
        }
        $(this).closest('tr').remove();
    });

    renumerarLineasVacias();
});
