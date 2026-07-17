/**
 * Modal para cambiar la cotización de una recepción de proveedor confirmada desde el listado.
 * Solo edita la cotización; el backend propaga a asiento ERP, ctamov y recepmov Anita.
 */
(function ($) {
    'use strict';

    var enviando = false;

    function init() {
        var $modal = $('#modal-cambiar-cotizacion');
        if (!$modal.length) {
            return;
        }

        var $form = $('#form-cambiar-cotizacion');
        var plantilla = $form.data('actionTemplate') || '';

        $(document).on('click', '.btn-cambiar-cotizacion-recepcion', function () {
            var $btn = $(this);
            var id = $btn.data('id');
            var numero = $btn.data('numero');
            var cotizacion = $btn.data('cotizacion');

            $('#cambiar-cotizacion-numero').text(numero);
            $('#cambiar-cotizacion-actual').text(cotizacion);
            $('#cambiar-cotizacion-valor').val(cotizacion);
            $form.attr('action', plantilla.replace('__ID__', id));

            $modal.modal('show');
            window.setTimeout(function () {
                $('#cambiar-cotizacion-valor').trigger('focus').trigger('select');
            }, 300);
        });

        $form.on('submit', function () {
            if (enviando) {
                return false;
            }
            var valor = parseFloat($('#cambiar-cotizacion-valor').val());
            if (!(valor > 0)) {
                window.alert('Ingrese una cotización mayor a cero.');
                return false;
            }
            enviando = true;
            $('#btn-cambiar-cotizacion-guardar').prop('disabled', true).addClass('disabled');
            return true;
        });
    }

    $(init);
})(jQuery);
