/**
 * Confirmación al marcar requisición APROBADA / GENERO ORDEN COMPRA como CUMPLIDA.
 */
(function ($) {
    'use strict';

    $(function () {
        $(document).on('submit', 'form.form-marcar-cumplida-requisicion', function (e) {
            e.preventDefault();
            var $form = $(this);
            var msg = $form.data('confirmMsg')
                || '¿Marcar la requisición como CUMPLIDA? Se cerrarán los ítems pendientes sin OC.';
            if (!window.confirm(msg)) {
                return false;
            }
            $form.find('button[type="submit"]').prop('disabled', true);
            $form.get(0).submit();
            return false;
        });
    });
})(jQuery);
