(function ($) {
    'use strict';
    $(function () {
        var $form = $('#form-filtros-mozo-gastronomia');
        if (!$form.length) {
            return;
        }
        $('#btn-aplicar-filtros-mozo').on('click', function () {
            $('#filtro_modo').val('campo');
            $form.find('input[name="filtro_busqueda_rapida"]').remove();
        });
    });
})(jQuery);
