(function ($) {
    'use strict';

    $(function () {
        $('#btn-agregar-leyenda').on('click', function () {
            var n = $('#leyendas-empleado .leyenda-fila').length + 1;
            var html = '<div class="form-group row leyenda-fila">'
                + '<label class="col-lg-2 control-label">Línea ' + n + '</label>'
                + '<div class="col-lg-8"><input type="text" name="leyendas[]" class="form-control" maxlength="80"></div>'
                + '<div class="col-lg-2"><button type="button" class="btn btn-outline-danger btn-sm btn-quitar-leyenda"><i class="fa fa-times"></i></button></div>'
                + '</div>';
            $('#leyendas-empleado').append(html);
        });

        $(document).on('click', '.btn-quitar-leyenda', function () {
            var $filas = $('#leyendas-empleado .leyenda-fila');
            if ($filas.length <= 1) {
                $(this).closest('.leyenda-fila').find('input').val('');
                return;
            }
            $(this).closest('.leyenda-fila').remove();
        });

        $(document).on('click', '.eliminar-archivo-empleado', function () {
            $(this).closest('.empleado-archivo-item').remove();
        });
    });
})(jQuery);
