(function ($) {
    'use strict';

    $(function () {
        var $modal = $('#modal-replicar-vianda-tipo-menu');
        if (!$modal.length) {
            return;
        }

        var $form = $('#form-replicar-vianda-tipo-menu');
        var $checks = $form.find('.vianda-empresa-destino-check');

        $(document).on('click', '.btn-replicar-vianda-tipo-menu', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var nombre = $(this).data('nombre') || '';
            var empresaId = parseInt($(this).data('empresa-id'), 10) || 0;
            var empresaNombre = $(this).data('empresa-nombre') || '';
            var url = $(this).data('url') || '';

            if (!url) {
                alert('No se pudo determinar la ruta de replicación.');
                return;
            }

            $form.attr('action', url);
            $('#replicar-menu-origen-nombre').text(nombre);
            $('#replicar-menu-origen-empresa').text(empresaNombre || ('ID ' + empresaId));
            $checks.prop('checked', false).closest('.custom-control').show();
            $checks.filter('[value="' + empresaId + '"]').prop('checked', false).closest('.custom-control').hide();
            $modal.modal('show');
        });

        $form.on('submit', function () {
            var seleccionados = $checks.filter(':visible:checked').length;
            if (seleccionados < 1) {
                alert('Seleccione al menos una empresa destino.');
                return false;
            }
            return confirm('Se pisarán los artículos del menú destino en las empresas seleccionadas. ¿Continuar?');
        });
    });
})(jQuery);
