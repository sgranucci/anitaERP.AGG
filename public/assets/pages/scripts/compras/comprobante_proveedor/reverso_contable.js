(function ($) {
    'use strict';

    function formatoMonto(n) {
        var v = Number(n || 0);
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function mostrarError($err, texto) {
        if (!texto) {
            $err.addClass('d-none').text('');
            return;
        }
        $err.removeClass('d-none').text(texto);
    }

    $(function () {
        var $modal = $('#modal-reverso-contable-cp');
        if (!$modal.length) {
            return;
        }

        var $form = $('#form-reverso-contable-cp');
        var $resumen = $('#reverso-contable-cp-resumen');
        var $fecha = $('#reverso-contable-cp-fecha');
        var $error = $('#reverso-contable-cp-error');
        var $btn = $('#reverso-contable-cp-confirmar');

        $(document).on('click', '.js-reverso-contable-cp', function (e) {
            e.preventDefault();
            var $el = $(this);
            var previewUrl = $el.data('preview-url');
            var actionUrl = $el.data('action-url');
            if (!previewUrl || !actionUrl) {
                return;
            }

            $form.attr('action', actionUrl);
            $resumen.text('Cargando…');
            $btn.prop('disabled', true);
            mostrarError($error, '');

            $.getJSON(previewUrl)
                .done(function (data) {
                    if (!data || !data.ok) {
                        $resumen.text('No se pudo preparar el reverso.');
                        mostrarError($error, (data && data.mensaje) || 'Error al previsualizar.');
                        return;
                    }
                    if (data.fecha_contable) {
                        $fecha.val(data.fecha_contable);
                    }
                    $resumen.html(
                        '<div><strong>Factura:</strong> ' + (data.etiqueta || ('#' + data.origen_id)) + '</div>'
                        + '<div><strong>Total:</strong> ' + formatoMonto(data.total) + '</div>'
                        + '<div><strong>Conceptos a clonar:</strong> ' + (data.conceptos || 0) + '</div>'
                        + '<div><strong>Tipo NC:</strong> ' + (data.tipo_nc || '—') + '</div>'
                    );
                    $btn.prop('disabled', false);
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.mensaje)
                        || 'No se puede revertir este comprobante.';
                    $resumen.text('No disponible.');
                    mostrarError($error, msg);
                });

            $modal.modal('show');
        });

        $form.on('submit', function () {
            $btn.prop('disabled', true).text('Procesando…');
        });
    });
})(jQuery);
