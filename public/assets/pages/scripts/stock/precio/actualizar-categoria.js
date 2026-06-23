$(function () {
    var $form = $('#form-actualizar-precios-categoria');
    var $preview = $('#preview-precios-categoria');
    var $resumen = $('#preview-precios-categoria-resumen');
    var $body = $('#preview-precios-categoria-body');
    var $nota = $('#preview-precios-categoria-nota');
    var $btnAplicar = $('#btn-aplicar-precios-categoria');
    var previewUrl = carpetaBase + '/stock/precio/actualizar-por-categoria/preview';
    var aplicarUrl = carpetaBase + '/stock/precio/actualizar-por-categoria';

    function formData() {
        return {
            _token: $('meta[name="csrf-token"]').attr('content') || $form.find('[name="_token"]').val(),
            categoria_id: $('#categoria_id').val(),
            listaprecio_id: $('#listaprecio_id').val(),
            porcentaje: $('#porcentaje').val(),
            fecha_referencia: $('#fecha_referencia').val(),
            nueva_fechavigencia: $('#nueva_fechavigencia').val()
        };
    }

    function formatoMoneda(valor) {
        var n = parseFloat(valor);
        if (isNaN(n)) {
            return '';
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    $('#btn-preview-precios-categoria').on('click', function () {
        $btnAplicar.prop('disabled', true);
        $preview.addClass('d-none');
        $.post(previewUrl, formData())
            .done(function (data) {
                $resumen.html(
                    '<strong>' + data.categoria_nombre + '</strong>'
                    + (data.listaprecio_nombre ? ' · Lista: ' + data.listaprecio_nombre : ' · Todas las listas')
                    + ' · Ajuste: ' + data.porcentaje + '%'
                    + ' · Artículos facturables: ' + data.articulos_facturables
                    + ' · Precios a actualizar: <strong>' + data.precios_a_actualizar + '</strong>'
                );
                $body.empty();
                if (!data.muestra || data.muestra.length === 0) {
                    $body.append('<tr><td colspan="5" class="text-center text-muted">No hay precios vigentes para los criterios indicados.</td></tr>');
                } else {
                    data.muestra.forEach(function (fila) {
                        $body.append(
                            '<tr>'
                            + '<td><small>' + (fila.sku || '') + '</small></td>'
                            + '<td><small>' + (fila.descripcion || '') + '</small></td>'
                            + '<td><small>' + (fila.listaprecio_nombre || '') + '</small></td>'
                            + '<td class="text-right"><small>' + formatoMoneda(fila.precio_actual) + '</small></td>'
                            + '<td class="text-right"><small>' + formatoMoneda(fila.precio_nuevo) + '</small></td>'
                            + '</tr>'
                        );
                    });
                }
                if (data.precios_a_actualizar > data.muestra.length) {
                    $nota.text('Muestra de ' + data.muestra.length + ' registros. Se actualizarán ' + data.precios_a_actualizar + ' en total.');
                } else {
                    $nota.text('');
                }
                $preview.removeClass('d-none');
                if (data.precios_a_actualizar > 0) {
                    $btnAplicar.prop('disabled', false);
                }
            })
            .fail(function (xhr) {
                var msg = 'No se pudo previsualizar.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                alert(msg);
            });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        if ($btnAplicar.prop('disabled')) {
            return;
        }
        if (!confirm('¿Confirma registrar los nuevos precios con la vigencia indicada?')) {
            return;
        }
        var $hiddenForm = $('<form>', {
            method: 'POST',
            action: aplicarUrl
        });
        var data = formData();
        Object.keys(data).forEach(function (key) {
            $hiddenForm.append($('<input>', { type: 'hidden', name: key, value: data[key] }));
        });
        $('body').append($hiddenForm);
        $hiddenForm.submit();
    });

    $form.find('select, input').on('change input', function () {
        $btnAplicar.prop('disabled', true);
        $preview.addClass('d-none');
    });
});
