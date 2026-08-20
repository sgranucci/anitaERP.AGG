(function ($) {
    'use strict';

    var $form = $('#form-pagoproveedor');
    if (!$form.length) {
        return;
    }

    var urlDeuda = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/compras/pagoproveedor/api/deuda-proveedor';
    var urlRet = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/compras/pagoproveedor/api/calcular-retenciones';
    var urlCot = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/compras/comprobante-proveedor/api/cotizacion-moneda-fecha';
    var monedaLocalId = 1;
    var cotDiaPorMoneda = {};

    function fmt(n) {
        return (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cotNorm(v) {
        var n = Number(v) || 0;
        return n > 0 ? n : 1;
    }

    function esLocal(id) {
        return Number(id) <= monedaLocalId;
    }

    function monedaPagoId() {
        return parseInt($('#moneda_id').val() || '1', 10) || 1;
    }

    function modoCot() {
        return String($('#modo_cotizacion').val() || 'factura');
    }

    function cotPagoODia(monedaDeuda, cotDeuda) {
        var modo = modoCot();
        if (modo !== 'dia' && monedaPagoId() === Number(monedaDeuda)) {
            return cotNorm(cotDeuda);
        }
        var midMe = esLocal(monedaDeuda) ? monedaPagoId() : Number(monedaDeuda);
        if (cotDiaPorMoneda[midMe] > 0) {
            return cotDiaPorMoneda[midMe];
        }
        var header = parseFloat($('#cotizacion').val() || '0') || 0;
        if (!esLocal(monedaPagoId()) && header > 1) {
            return header;
        }
        return cotNorm(cotDeuda);
    }

    function liquidarFila($monto) {
        var monto = parseFloat($monto.val() || '0') || 0;
        var monedaDeuda = parseInt($monto.data('moneda') || '1', 10) || 1;
        var cotDeuda = cotNorm($monto.data('cotizacion'));
        var monedaPago = monedaPagoId();
        var cotApl = cotPagoODia(monedaDeuda, cotDeuda);
        var cruzada = monedaDeuda !== monedaPago;
        if (!cruzada) {
            var dcMisma = Math.round((monto * (cotDeuda - cotApl)) * 10000) / 10000;
            return {
                cot_aplicada: cotApl,
                equivalente: monto,
                dc: Math.abs(dcMisma) < 0.01 ? 0 : dcMisma
            };
        }
        var equiv;
        if (esLocal(monedaDeuda) && !esLocal(monedaPago)) {
            equiv = monto / cotApl;
        } else if (!esLocal(monedaDeuda) && esLocal(monedaPago)) {
            equiv = monto * cotApl;
        } else {
            equiv = (monto * cotApl) / cotNorm($('#cotizacion').val());
        }
        equiv = Math.round(equiv * 10000) / 10000;
        var valorDeuda = esLocal(monedaDeuda) ? monto : monto * cotDeuda;
        var valorPago = esLocal(monedaPago) ? equiv : equiv * cotApl;
        var dc = Math.round((valorDeuda - valorPago) * 10000) / 10000;
        return {
            cot_aplicada: cotApl,
            equivalente: equiv,
            dc: Math.abs(dc) < 0.01 ? 0 : dc
        };
    }

    function pintarFila($tr) {
        var $monto = $tr.find('.pp-monto-aplicar');
        if (!$monto.length) {
            return { equivalente: 0 };
        }
        var liq = liquidarFila($monto);
        $tr.find('.pp-cot-liq').text(liq.cot_aplicada.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }));
        $tr.find('.pp-equiv').text(fmt(liq.equivalente));
        var $dc = $tr.find('.pp-dc');
        if (Math.abs(liq.dc) < 0.01) {
            $dc.text('—').removeClass('text-danger text-success');
        } else {
            $dc.text(fmt(Math.abs(liq.dc)) + (liq.dc > 0 ? ' pérdida' : ' ganancia'))
                .toggleClass('text-danger', liq.dc > 0)
                .toggleClass('text-success', liq.dc < 0);
        }
        $monto.data('cot-aplicada', liq.cot_aplicada);
        $monto.data('dc', liq.dc);
        $monto.data('equivalente', liq.equivalente);
        return liq;
    }

    function cargarDeuda() {
        var proveedorId = parseInt($('#proveedor_id').val() || '0', 10);
        var empresaId = parseInt($('#empresa_id').val() || '0', 10);
        var $tb = $('#tabla-deuda-proveedor tbody');
        if (!proveedorId) {
            $tb.html('<tr><td colspan="9" class="text-muted text-center">Seleccione proveedor</td></tr>');
            return;
        }

        $.getJSON(urlDeuda, { proveedor_id: proveedorId, empresa_id: empresaId })
            .done(function (res) {
                var filas = res.filas || [];
                if (!filas.length) {
                    $tb.html('<tr><td colspan="9" class="text-muted text-center">Sin deuda pendiente</td></tr>');
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
                        + '<td class="text-right pp-cot-liq">—</td>'
                        + '<td class="text-right pp-equiv">—</td>'
                        + '<td class="text-right pp-dc">—</td>'
                        + '</tr>';
                });
                $tb.html(html);
                refrescarCotDia(filas);
            });
    }

    function refrescarCotDia(filas) {
        var fecha = $('#fecha').val();
        var ids = {};
        (filas || []).forEach(function (f) {
            if (!esLocal(f.moneda_id)) {
                ids[f.moneda_id] = true;
            }
        });
        if (!esLocal(monedaPagoId())) {
            ids[monedaPagoId()] = true;
        }
        Object.keys(ids).forEach(function (mid) {
            if (!fecha) {
                return;
            }
            $.getJSON(urlCot, { fecha: fecha, moneda_id: mid })
                .done(function (res) {
                    var cot = parseFloat(res && res.cotizacion);
                    if (cot > 0) {
                        cotDiaPorMoneda[mid] = cot;
                        sincronizarCamposAplicacion();
                    }
                });
        });
    }

    function sincronizarCamposAplicacion() {
        $form.find('input[name="idcuentacorrientes[]"],input[name="montoaplicadocomprobantes[]"],input[name="monedacomprobante_ids[]"],input[name="cotizacioncomprobantes[]"],input[name="cotizacion_aplicada_dia[]"],input[name="diferencias_cambio[]"]').remove();
        var totalPago = 0;
        $('#tabla-deuda-proveedor tbody tr').each(function () {
            var $tr = $(this);
            var $chk = $tr.find('.pp-sel-deuda');
            var $monto = $tr.find('.pp-monto-aplicar');
            if (!$monto.length) {
                return;
            }
            var monto = parseFloat($monto.val() || '0') || 0;
            var liq = pintarFila($tr);
            if (!$chk.is(':checked') || monto <= 0) {
                return;
            }
            var ccId = $tr.data('cc-id');
            $form.append($('<input type="hidden" name="idcuentacorrientes[]">').val(ccId));
            $form.append($('<input type="hidden" name="montoaplicadocomprobantes[]">').val(monto));
            $form.append($('<input type="hidden" name="monedacomprobante_ids[]">').val($monto.data('moneda')));
            $form.append($('<input type="hidden" name="cotizacioncomprobantes[]">').val($monto.data('cotizacion')));
            $form.append($('<input type="hidden" name="cotizacion_aplicada_dia[]">').val(liq.cot_aplicada));
            $form.append($('<input type="hidden" name="diferencias_cambio[]">').val(liq.dc));
            totalPago += liq.equivalente;
        });
        $('#monto').val(totalPago.toFixed(2));
        $('#importe_neto_retencion').val(totalPago.toFixed(2));
    }

    $(document).on('change', '.pp-sel-deuda', function () {
        var $tr = $(this).closest('tr');
        var $monto = $tr.find('.pp-monto-aplicar');
        if (this.checked && (!parseFloat($monto.val()) || parseFloat($monto.val()) === 0)) {
            $monto.val($monto.data('saldo'));
        }
        sincronizarCamposAplicacion();
        if (typeof flModificaAsiento !== 'undefined') {
            flModificaAsiento = true;
        }
    });

    $(document).on('input change', '.pp-monto-aplicar', function () {
        var $tr = $(this).closest('tr');
        if (parseFloat($(this).val() || '0') > 0) {
            $tr.find('.pp-sel-deuda').prop('checked', true);
        }
        sincronizarCamposAplicacion();
        if (typeof flModificaAsiento !== 'undefined') {
            flModificaAsiento = true;
        }
    });

    $('#proveedor_id, #empresa_id').on('change', cargarDeuda);
    $(document).on('change.cpProveedorCargado', '#proveedor_id', cargarDeuda);
    $('#moneda_id, #cotizacion, #modo_cotizacion, #fecha').on('change', function () {
        if (this.id === 'fecha' || this.id === 'moneda_id') {
            var filas = [];
            $('#tabla-deuda-proveedor tbody .pp-monto-aplicar').each(function () {
                filas.push({ moneda_id: $(this).data('moneda') });
            });
            refrescarCotDia(filas);
        }
        sincronizarCamposAplicacion();
        if (typeof flModificaAsiento !== 'undefined') {
            flModificaAsiento = true;
        }
    });

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
