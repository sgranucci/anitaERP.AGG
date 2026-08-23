(function ($) {
    'use strict';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    $(document).on('click', '.js-ingreso-abrir-rechazo', function () {
        var $btn = $(this);
        var $form = $('#ingreso-rechazo-form');
        if (!$form.length) {
            return;
        }
        $form.attr('action', $btn.data('url'));
        $('#ingreso-motivo-rechazo').val('');
        $('#ingresoRechazoModal').modal('show');
    });

    $(document).on('submit', '#ingreso-rechazo-form', function () {
        var motivo = $.trim($('#ingreso-motivo-rechazo').val() || '');
        if (!motivo) {
            alert('Indique el motivo del rechazo.');
            return false;
        }
        return true;
    });

    window.ingresoAutorizarCsrf = csrfToken;
})(jQuery);
