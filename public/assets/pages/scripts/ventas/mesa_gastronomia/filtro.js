(function ($) {
    'use strict';
    $(function () {
        var $form = $('#form-filtros-mesa-gastronomia');
        if (!$form.length) {
            return;
        }
        $('#btn-aplicar-filtros-mesa').on('click', function () {
            $('#filtro_modo').val('campo');
            $form.find('input[name="filtro_busqueda_rapida"]').remove();
        });
    });
})(jQuery);
