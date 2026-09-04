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

    function hoyYmd() {
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function calcularResumenDeuda() {
        var saldo = 0;
        var aplicado = 0;
        var equiv = 0;
        var dc = 0;
        var n = 0;
        $('#tabla-deuda-proveedor tbody tr').each(function () {
            var $tr = $(this);
            var $monto = $tr.find('.pp-monto-aplicar');
            if (!$monto.length) {
                return;
            }
            saldo += parseFloat($monto.data('saldo') || '0') || 0;
            var liq = pintarFila($tr);
            var monto = parseFloat($monto.val() || '0') || 0;
            var chk = $tr.find('.pp-sel-deuda').is(':checked');
            if (chk && monto > 0) {
                aplicado += monto;
                equiv += liq.equivalente || 0;
                dc += liq.dc || 0;
                n += 1;
            }
        });
        return { saldo: saldo, aplicado: aplicado, equiv: equiv, dc: dc, n: n };
    }

    function textoDc(dc) {
        if (Math.abs(dc) < 0.01) {
            return '—';
        }
        return fmt(Math.abs(dc)) + (dc > 0 ? ' pérdida' : ' ganancia');
    }

    function pintarResumenDesembolso() {
        var d = calcularResumenDeuda();
        var medios = typeof window.totalMediosPagoproveedor === 'function'
            ? window.totalMediosPagoproveedor()
            : Number(window.ppTotalMedios || 0);
        window.ppTotalMedios = medios;
        var dif = Math.round((medios - d.equiv) * 100) / 100;
        $('#pp-card-saldo, #pp-tfoot-saldo').text(fmt(d.saldo));
        $('#pp-card-aplicado, #pp-tfoot-aplicado').text(fmt(d.aplicado));
        $('#pp-card-equiv, #pp-tfoot-equiv').text(fmt(d.equiv));
        $('#pp-card-dc, #pp-tfoot-dc').text(textoDc(d.dc));
        $('#pp-bar-aplicado').text(fmt(d.equiv));
        $('#pp-bar-medios').text(fmt(medios));
        $('#pp-bar-dif').text(fmt(dif));
        $('#pp-bar-dc').text(textoDc(d.dc));
        $('#pp-ref-aplicado-txt').text(fmt(d.equiv));
        $('#pp-ref-cuentas-txt').text(fmt(medios));
        $('#pp-ref-falta-txt').text(fmt(dif));
        var $dif = $('#pp-bar-dif, #pp-ref-falta-txt');
        $dif.toggleClass('text-danger', Math.abs(dif) >= 0.01 && dif < 0);
        $dif.toggleClass('text-success', Math.abs(dif) >= 0.01 && dif > 0);
        var hayFilas = $('#tabla-deuda-proveedor tbody .pp-monto-aplicar').length > 0;
        $('.pp-deuda-tfoot').toggle(hayFilas);
        window.ppResumenDeuda = d;
        $('#tbody-cuenta-table .monto').attr('placeholder', d.equiv > 0 ? d.equiv.toFixed(2) : '');
        return d;
    }

    window.pintarResumenDesembolso = pintarResumenDesembolso;

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

    function mensajeDeuda(texto) {
        $('#tabla-deuda-proveedor tbody').html(
            '<tr><td colspan="9" class="text-muted text-center">' + $('<div>').text(texto).html() + '</td></tr>'
        );
        pintarResumenDesembolso();
    }

    function cargarDeuda() {
        var proveedorId = parseInt($('#proveedor_id').val() || '0', 10);
        var empresaId = parseInt($('#empresa_id').val() || '0', 10);
        var pagoId = parseInt($('#pagoproveedor_id').val() || '0', 10);
        var $tb = $('#tabla-deuda-proveedor tbody');
        if (!empresaId) {
            mensajeDeuda(proveedorId ? 'Seleccione empresa para ver la deuda' : 'Seleccione empresa y proveedor');
            return;
        }
        if (!proveedorId) {
            mensajeDeuda('Seleccione proveedor');
            return;
        }

        $tb.html('<tr><td colspan="9" class="text-muted text-center">Cargando deuda…</td></tr>');
        var params = { proveedor_id: proveedorId, empresa_id: empresaId };
        if (pagoId > 0) {
            params.pagoproveedor_id = pagoId;
        }
        $.getJSON(urlDeuda, params)
            .done(function (res) {
                var filas = res.filas || [];
                if (!filas.length) {
                    mensajeDeuda(res.aviso || 'Sin deuda pendiente');
                    return;
                }
                var html = '';
                var hoy = hoyYmd();
                filas.forEach(function (f) {
                    var vencida = f.vencimiento && String(f.vencimiento) < hoy;
                    var aplicadoOp = parseFloat(f.aplicado_op || 0) || 0;
                    var montoIni = aplicadoOp > 0 ? aplicadoOp : 0;
                    var checked = aplicadoOp > 0 ? ' checked' : '';
                    var linkComp = '';
                    if (f.comprobante_url) {
                        linkComp = ' <a class="btn-accion-tabla tooltipsC text-primary" href="'
                            + $('<div>').text(f.comprobante_url).html()
                            + '" target="_blank" rel="noopener" title="Ver factura">'
                            + '<i class="fa fa-edit"></i></a>';
                    }
                    html += '<tr data-cc-id="' + f.id + '"' + (vencida ? ' class="table-danger"' : '') + '>'
                        + '<td class="text-center"><input type="checkbox" class="pp-sel-deuda"' + checked + '></td>'
                        + '<td>' + $('<div>').text(f.comprobante).html() + linkComp + '</td>'
                        + '<td>' + (f.vencimiento || '') + '</td>'
                        + '<td>' + (f.moneda || '') + '</td>'
                        + '<td class="text-right text-nowrap">' + fmt(f.saldo) + '</td>'
                        + '<td class="text-right pp-col-aplicar">'
                        + '<input type="number" step="0.01" min="0" class="form-control form-control-sm text-right pp-monto-aplicar" value="'
                        + montoIni + '"'
                        + ' data-saldo="' + f.saldo + '" data-moneda="' + f.moneda_id + '" data-cotizacion="' + f.cotizacion + '">'
                        + '</td>'
                        + '<td class="text-right pp-cot-liq">—</td>'
                        + '<td class="text-right pp-equiv">—</td>'
                        + '<td class="text-right pp-dc">—</td>'
                        + '</tr>';
                });
                $tb.html(html);
                refrescarCotDia(filas);
                sincronizarCamposAplicacion();
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
        pintarResumenDesembolso();
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

    $('#proveedor_id').on('change', cargarDeuda);
    $(document).on('change.cpProveedorCargado', '#proveedor_id', cargarDeuda);
    $(document).on('change.ppEmpresaDeuda', '#empresa_id', function () {
        cargarDeuda();
        if (typeof window.calcularRetencionesPagoproveedor === 'function') {
            window.calcularRetencionesPagoproveedor();
        }
    });
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

    function pintarResumenRetenciones(res) {
        var html = 'Total retenciones: <strong>' + fmt(res.total) + '</strong><ul class="mb-0">';
        ['ganancias', 'iva', 'suss', 'iibb'].forEach(function (k) {
            var r = res[k] || {};
            html += '<li>' + k.toUpperCase() + ': ' + fmt(r.importe) + ' — ' + (r.motivo || '') + '</li>';
        });
        html += '</ul>';
        $('#pp-retenciones-resumen').removeClass('text-muted').html(html);
        $('#pp-retenciones-json').val(JSON.stringify(res));
        window.ppTotalRetenciones = Number(res.total) || 0;
        if (typeof flModificaAsiento !== 'undefined') {
            flModificaAsiento = true;
        }
        if (typeof window.pintarResumenDesembolso === 'function') {
            window.pintarResumenDesembolso();
        }
    }

    function calcularRetencionesPagoproveedor() {
        var proveedorId = parseInt($('#proveedor_id').val() || '0', 10);
        if (!proveedorId) {
            return $.Deferred().resolve().promise();
        }
        sincronizarCamposAplicacion();
        return $.ajax({
            url: urlRet,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() },
            data: {
                proveedor_id: proveedorId,
                empresa_id: $('#empresa_id').val(),
                fecha: $('#fecha').val(),
                importe_neto: $('#importe_neto_retencion').val(),
                importe_iva: $('#importe_iva_retencion').val()
            }
        }).done(function (res) {
            pintarResumenRetenciones(res);
        }).fail(function (xhr) {
            $('#pp-retenciones-resumen').addClass('text-muted').text(
                (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudieron calcular retenciones'
            );
        });
    }

    window.calcularRetencionesPagoproveedor = calcularRetencionesPagoproveedor;

    var timerRetenciones = null;
    function programarCalculoRetenciones() {
        if (timerRetenciones) {
            clearTimeout(timerRetenciones);
        }
        timerRetenciones = setTimeout(function () {
            timerRetenciones = null;
            calcularRetencionesPagoproveedor();
        }, 350);
    }

    $('#btn-calcular-retenciones').on('click', function () {
        calcularRetencionesPagoproveedor().fail(function (xhr) {
            if (xhr && xhr.responseJSON) {
                alert(xhr.responseJSON.error || 'No se pudo calcular retenciones');
            }
        });
    });

    $(document).on('change.ppRetenciones input.ppRetenciones', '.pp-monto-aplicar, .pp-sel-deuda', programarCalculoRetenciones);

    $form.on('submit', function () {
        sincronizarCamposAplicacion();
    });

    if ($('#proveedor_id').val()) {
        cargarDeuda();
    }
}(jQuery));
