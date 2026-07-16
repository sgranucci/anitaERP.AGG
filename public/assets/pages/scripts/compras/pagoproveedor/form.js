(function ($) {
    'use strict';

    var $form = $('#form-pagoproveedor');
    if (!$form.length) {
        return;
    }

    var urlDeuda = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/compras/pagoproveedor/api/deuda-proveedor';
    var urlRet = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/compras/pagoproveedor/api/calcular-retenciones';

    function fmt(n) {
        return (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cargarDeuda() {
        var proveedorId = parseInt($('#proveedor_id').val() || '0', 10);
        var empresaId = parseInt($('#empresa_id').val() || '0', 10);
        var $tb = $('#tabla-deuda-proveedor tbody');
        if (!proveedorId) {
            $tb.html('<tr><td colspan="6" class="text-muted text-center">Seleccione proveedor</td></tr>');
            return;
        }

        $.getJSON(urlDeuda, { proveedor_id: proveedorId, empresa_id: empresaId })
            .done(function (res) {
                var filas = res.filas || [];
                if (!filas.length) {
                    $tb.html('<tr><td colspan="6" class="text-muted text-center">Sin deuda pendiente</td></tr>');
                    return;
                }
                var html = '';
                filas.forEach(function (f) {
                    html += '<tr data-cc-id="' + f.id + '">'
                        + '<td><input type="checkbox" class="pp-sel-deuda"></td>'
                        + '<td>' + $('<div>').text(f.comprobante).html() + '</td>'
                        + '<td>' + (f.vencimiento || '') + '</td>'
                        + '<td>' + (f.moneda || '') + '</td>'
                        + '<td class="text-right">' + fmt(f.saldo) + '</td>'
                        + '<td><input type="number" step="0.01" class="form-control form-control-sm pp-monto-aplicar" value="0"'
                        + ' data-saldo="' + f.saldo + '" data-moneda="' + f.moneda_id + '" data-cotizacion="' + f.cotizacion + '"></td>'
                        + '</tr>';
                });
                $tb.html(html);
            });
    }

    function sincronizarCamposAplicacion() {
        $form.find('input[name="idcuentacorrientes[]"],input[name="montoaplicadocomprobantes[]"],input[name="monedacomprobante_ids[]"],input[name="cotizacioncomprobantes[]"]').remove();
        var total = 0;
        $('#tabla-deuda-proveedor tbody tr').each(function () {
            var $tr = $(this);
            var $chk = $tr.find('.pp-sel-deuda');
            var $monto = $tr.find('.pp-monto-aplicar');
            var monto = parseFloat($monto.val() || '0') || 0;
            if (!$chk.is(':checked') || monto <= 0) {
                return;
            }
            var ccId = $tr.data('cc-id');
            $form.append($('<input type="hidden" name="idcuentacorrientes[]">').val(ccId));
            $form.append($('<input type="hidden" name="montoaplicadocomprobantes[]">').val(monto));
            $form.append($('<input type="hidden" name="monedacomprobante_ids[]">').val($monto.data('moneda')));
            $form.append($('<input type="hidden" name="cotizacioncomprobantes[]">').val($monto.data('cotizacion')));
            total += monto;
        });
        $('#monto').val(total.toFixed(2));
        $('#importe_neto_retencion').val(total.toFixed(2));
    }

    $(document).on('change', '.pp-sel-deuda', function () {
        var $tr = $(this).closest('tr');
        var $monto = $tr.find('.pp-monto-aplicar');
        if (this.checked && (!parseFloat($monto.val()) || parseFloat($monto.val()) === 0)) {
            $monto.val($monto.data('saldo'));
        }
        sincronizarCamposAplicacion();
    });

    $(document).on('input change', '.pp-monto-aplicar', function () {
        var $tr = $(this).closest('tr');
        if (parseFloat($(this).val() || '0') > 0) {
            $tr.find('.pp-sel-deuda').prop('checked', true);
        }
        sincronizarCamposAplicacion();
    });

    $('#proveedor_id, #empresa_id').on('change', cargarDeuda);
    $(document).on('change.cpProveedorCargado', '#proveedor_id', cargarDeuda);

    $('#btn-calcular-retenciones').on('click', function () {
        sincronizarCamposAplicacion();
        $.ajax({
            url: urlRet,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() },
            data: {
                proveedor_id: $('#proveedor_id').val(),
                fecha: $('#fecha').val(),
                importe_neto: $('#importe_neto_retencion').val(),
                importe_iva: $('#importe_iva_retencion').val()
            }
        }).done(function (res) {
            var html = 'Total retenciones: <strong>' + fmt(res.total) + '</strong><ul class="mb-0">';
            ['ganancias', 'iva', 'suss', 'iibb'].forEach(function (k) {
                var r = res[k] || {};
                html += '<li>' + k.toUpperCase() + ': ' + fmt(r.importe) + ' — ' + (r.motivo || '') + '</li>';
            });
            html += '</ul>';
            $('#pp-retenciones-resumen').removeClass('text-muted').html(html);
            $('#pp-retenciones-json').val(JSON.stringify(res));
            if (typeof flModificaAsiento !== 'undefined') {
                flModificaAsiento = true;
            }
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo calcular retenciones');
        });
    });

    $form.on('submit', function () {
        sincronizarCamposAplicacion();
    });

    if ($('#proveedor_id').val()) {
        cargarDeuda();
    }
}(jQuery));
