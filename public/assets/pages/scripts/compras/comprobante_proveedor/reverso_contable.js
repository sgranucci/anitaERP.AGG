(function ($) {
    'use strict';

    function formatoMonto(n) {
        var v = Number(n || 0);
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapar(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
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
        var $modo = $('#reverso-contable-cp-modo');

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
            $modo.addClass('d-none').text('');
            $btn.prop('disabled', true).text('Crear NC y aplicar');
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
                    var aCuenta = data.modo === 'a_cuenta';
                    var filas = '';
                    (data.lineas || []).forEach(function (l) {
                        filas += '<tr>'
                            + '<td>' + escapar(l.cuenta_codigo) + ' ' + escapar(l.cuenta_nombre) + '</td>'
                            + '<td class="text-right">' + (Number(l.debe) ? formatoMonto(l.debe) : '') + '</td>'
                            + '<td class="text-right">' + (Number(l.haber) ? formatoMonto(l.haber) : '') + '</td>'
                            + '</tr>';
                    });
                    $resumen.html(
                        '<div><strong>Factura:</strong> ' + escapar(data.etiqueta || ('#' + data.origen_id)) + '</div>'
                        + '<div><strong>Total:</strong> ' + formatoMonto(data.total) + '</div>'
                        + '<div><strong>Tipo NC:</strong> ' + escapar(data.tipo_nc || '—') + '</div>'
                        + '<div class="mt-2"><strong>Asiento de la NC (espejo de la factura):</strong></div>'
                        + '<table class="table table-sm table-bordered mb-0">'
                        + '<thead style="background:#85C1E9;color:#17202A;"><tr>'
                        + '<th>Cuenta</th><th class="text-right">Debe</th><th class="text-right">Haber</th>'
                        + '</tr></thead><tbody>' + filas + '</tbody></table>'
                    );
                    $modo.removeClass('d-none text-info text-warning')
                        .addClass(aCuenta ? 'text-warning' : 'text-info')
                        .text(data.modo_texto || '');
                    $btn.text(aCuenta ? 'Crear NC y dejar a cuenta' : 'Crear NC y aplicar');
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
