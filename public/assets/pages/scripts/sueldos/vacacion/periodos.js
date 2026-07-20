$(function () {
    function renumerarLineasVacias() {
        var nro = 1;
        $('#tbody-vacacion-periodos tr.item-vacacion-periodo').each(function () {
            var $nro = $(this).find('.nro-linea');
            if (!$nro.val()) {
                $nro.val(nro);
            }
            nro++;
        });
    }

    function diasEntre(desde, hasta) {
        if (!desde || !hasta) {
            return null;
        }
        var d1 = new Date(desde + 'T00:00:00');
        var d2 = new Date(hasta + 'T00:00:00');
        if (isNaN(d1.getTime()) || isNaN(d2.getTime()) || d2 < d1) {
            return null;
        }
        return Math.floor((d2 - d1) / 86400000) + 1;
    }

    function sugerirCantidad($row) {
        var $cant = $row.find('.cantidad-dias');
        if ($cant.val() !== '') {
            return;
        }
        var dias = diasEntre($row.find('.fecha-desde').val(), $row.find('.fecha-hasta').val());
        if (dias !== null) {
            $cant.val(dias);
        }
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

    $(document).on('change', '.fecha-desde, .fecha-hasta', function () {
        sugerirCantidad($(this).closest('tr'));
    });

    renumerarLineasVacias();
});
