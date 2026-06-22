$(function () {
    var $form = $('#form-cp-desde-oc');
    if (!$form.length) {
        return;
    }

    var urlResolver = String($form.data('url-resolver') || '');
    var csrf = $('meta[name="csrf-token"]').attr('content') || '';

    function mostrarError(msg) {
        $('#cp-oc-error').removeClass('d-none').text(msg || 'Error');
    }

    function limpiarError() {
        $('#cp-oc-error').addClass('d-none').empty();
    }

    $form.on('submit', function (e) {
        e.preventDefault();
        limpiarError();

        var raw = String($('#cp-numero-oc').val() || '').replace(/\D/g, '');
        if (raw.length === 0) {
            mostrarError('Ingrese el número de OC (6 dígitos).');
            return;
        }

        if (raw.length > 6) {
            mostrarError('El número de OC debe tener como máximo 6 dígitos.');
            return;
        }

        var numeroOc = raw.padStart(6, '0');

        $.ajax({
            url: urlResolver,
            method: 'GET',
            data: { numero_oc: numeroOc },
            dataType: 'json'
        }).done(function (data) {
            if (data && data.ok && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            mostrarError((data && data.message) || 'No se pudo resolver la OC.');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'OC no encontrada o sin permiso.';
            mostrarError(msg);
        });
    });
});
