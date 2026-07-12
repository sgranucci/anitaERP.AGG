/**
 * Confirmaci&oacute;n al devolver requisici&oacute;n de EN ARBOL APROBACION a EN COMPRAS.
 */
(function ($) {
    'use strict';

    $(function () {
        $(document).on('submit', 'form.form-volver-compras-requisicion', function (e) {
            e.preventDefault();
            var $form = $(this);
            var msg = $form.data('confirmMsg') || '¿Devolver la requisición a compras? Se anularán las autorizaciones pendientes del árbol.';
            if (!window.confirm(msg)) {
                return false;
            }
            $form.find('button[type="submit"]').prop('disabled', true);
            $form.get(0).submit();
            return false;
        });
    });
})(jQuery);
