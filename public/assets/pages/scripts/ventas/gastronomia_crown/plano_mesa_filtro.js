(function ($) {
    'use strict';
    $(function () {
        var $form = $('#form-filtros-plano-mesa');
        if (!$form.length) {
            return;
        }
        $('#btn-aplicar-filtros-plano').on('click', function () {
            $('#filtro_modo').val('campo');
            $form.find('input[name="filtro_busqueda_rapida"]').remove();
        });
    });
})(jQuery);
