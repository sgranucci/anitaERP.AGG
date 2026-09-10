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
    var monedaRefMeId = 2; // DOL: TC de referencia para expresar movimientos MN en ME
    var cotDiaPorMoneda = {};
    var deudaXhr = null;
    var deudaReqSeq = 0;

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
        // Misma MN: para modo día usamos DOL de referencia (header / cot del día).
        var midMe;
        if (esLocal(monedaDeuda) && esLocal(monedaPagoId())) {
            midMe = monedaRefMeId;
        } else {
            midMe = esLocal(monedaDeuda) ? monedaPagoId() : Number(monedaDeuda);
        }
        if (cotDiaPorMoneda[midMe] > 0) {
            return cotDiaPorMoneda[midMe];
        }
        var header = parseFloat($('#cotizacion').val() || '0') || 0;
        if (header > 1) {
            return header;
        }
        return cotNorm(cotDeuda);
    }

    function refrescarCotizacionHeader(forzar) {
        var fecha = $('#fecha').val();
        var $cot = $('#cotizacion');
        if (!fecha || !$cot.length) {
            return;
        }
        var actual = parseFloat($cot.val() || '0') || 0;
        if (!forzar && actual > 1) {
            return;
        }
        var mid = esLocal(monedaPagoId()) ? monedaRefMeId : monedaPagoId();
        $.getJSON(urlCot, { fecha: fecha, moneda_id: mid })
            .done(function (res) {
                var cot = parseFloat(res && res.cotizacion);
                if (cot > 1) {
                    $cot.val(cot);
                    cotDiaPorMoneda[mid] = cot;
                    sincronizarCamposAplicacion();
                }
            });
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

    function actualizarCheckTodas() {
        var $todas = $('#pp-sel-deuda-todas');
        if (!$todas.length) {
            return;
        }
        var $checks = $('#tabla-deuda-proveedor tbody .pp-sel-deuda');
        var n = $checks.length;
        var nChecked = $checks.filter(':checked').length;
        $todas.prop('disabled', n === 0);
        $todas.prop('indeterminate', n > 0 && nChecked > 0 && nChecked < n);
        $todas.prop('checked', n > 0 && nChecked === n);
    }

    function aplicarInclusionFila($tr, checked) {
        var $chk = $tr.find('.pp-sel-deuda');
        var $monto = $tr.find('.pp-monto-aplicar');
        if (!$chk.length) {
            return;
        }
        $chk.prop('checked', checked);
        if (checked && $monto.length) {
            var actual = parseFloat($monto.val() || '0') || 0;
            if (!actual) {
                $monto.val($monto.data('saldo'));
            }
        }
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
        var retenciones = Number(window.ppTotalRetenciones || 0);
        var desembolsar = Math.round((d.equiv - retenciones) * 100) / 100;
        if (desembolsar < 0) {
            desembolsar = 0;
        }
        var dif = Math.round((medios - desembolsar) * 100) / 100;
        window.ppADesembolsar = desembolsar;
        $('#pp-card-saldo, #pp-tfoot-saldo').text(fmt(d.saldo));
        $('#pp-card-aplicado, #pp-tfoot-aplicado').text(fmt(d.aplicado));
        $('#pp-card-equiv, #pp-tfoot-equiv').text(fmt(d.equiv));
        $('#pp-card-dc, #pp-tfoot-dc').text(textoDc(d.dc));
        $('#pp-bar-aplicado').text(fmt(d.equiv));
        $('#pp-bar-retenciones').text(fmt(retenciones));
        $('#pp-bar-desembolsar').text(fmt(desembolsar));
        $('#pp-bar-medios').text(fmt(medios));
        $('#pp-bar-dif').text(fmt(dif));
        $('#pp-bar-dc').text(textoDc(d.dc));
        $('#pp-ref-aplicado-txt').text(fmt(d.equiv));
        $('#pp-ref-retenciones-txt').text(fmt(retenciones));
        $('#pp-ref-desembolsar-txt').text(fmt(desembolsar));
        $('#pp-ref-cuentas-txt').text(fmt(medios));
        $('#pp-ref-falta-txt').text(fmt(dif));
        var $dif = $('#pp-bar-dif, #pp-ref-falta-txt');
        $dif.toggleClass('text-danger', Math.abs(dif) >= 0.01 && dif < 0);
        $dif.toggleClass('text-success', Math.abs(dif) >= 0.01 && dif > 0);
        var hayFilas = $('#tabla-deuda-proveedor tbody .pp-monto-aplicar').length > 0;
        $('.pp-deuda-tfoot').toggle(hayFilas);
        actualizarCheckTodas();
        window.ppResumenDeuda = d;
        var ph = desembolsar > 0 ? desembolsar.toFixed(2) : '';
        $('#tbody-cuenta-table .monto').attr('placeholder', ph);
        $('#tbody-cheque-emitido-table .montocheque_emitido').attr('placeholder', ph);
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

    /** Snapshot de lo que el usuario ya tildó/escribió, para no perderlo si la deuda se recarga. */
    function capturarSeleccionDeuda() {
        var mapa = {};
        $('#tabla-deuda-proveedor tbody tr').each(function () {
            var $tr = $(this);
            var ccId = parseInt($tr.data('cc-id') || '0', 10);
            if (!ccId) {
                return;
            }
            var $monto = $tr.find('.pp-monto-aplicar');
            mapa[ccId] = {
                checked: $tr.find('.pp-sel-deuda').is(':checked'),
                monto: parseFloat($monto.val() || '0') || 0
            };
        });
        return mapa;
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

        var seleccionPrevia = capturarSeleccionDeuda();
        // Tras error de grabación: old() de aplicaciones (page reload pierde la grilla).
        // No se descarta hasta pintar, por si hay dos cargas en paralelo.
        if (Object.keys(seleccionPrevia).length === 0 && window.ppOldAplicaciones && window.ppOldAplicaciones.length) {
            window.ppOldAplicaciones.forEach(function (a) {
                var id = parseInt(a.id || a.cc_id || '0', 10);
                var monto = parseFloat(a.monto || '0') || 0;
                if (id > 0 && monto > 0) {
                    seleccionPrevia[id] = { checked: true, monto: monto };
                }
            });
        }
        var reqId = ++deudaReqSeq;
        if (deudaXhr && deudaXhr.readyState !== 4) {
            try {
                deudaXhr.abort();
            } catch (e) { /* ignore */ }
        }

        $tb.html('<tr><td colspan="9" class="text-muted text-center">Cargando deuda…</td></tr>');
        var params = { proveedor_id: proveedorId, empresa_id: empresaId };
        if (pagoId > 0) {
            params.pagoproveedor_id = pagoId;
        }
        deudaXhr = $.getJSON(urlDeuda, params)
            .done(function (res) {
                if (reqId !== deudaReqSeq) {
                    return;
                }
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
                    var prev = seleccionPrevia[f.id];
                    var montoIni = aplicadoOp > 0 ? aplicadoOp : 0;
                    var checked = aplicadoOp > 0;
                    if (prev) {
                        if (prev.checked || prev.monto > 0) {
                            checked = true;
                            montoIni = prev.monto > 0 ? prev.monto : (aplicadoOp > 0 ? aplicadoOp : (parseFloat(f.saldo) || 0));
                        }
                    }
                    var linkComp = '';
                    if (f.comprobante_url) {
                        linkComp = ' <a class="btn-accion-tabla tooltipsC text-primary" href="'
                            + $('<div>').text(f.comprobante_url).html()
                            + '" target="_blank" rel="noopener" title="Ver factura">'
                            + '<i class="fa fa-edit"></i></a>';
                    }
                    html += '<tr data-cc-id="' + f.id + '"' + (vencida ? ' class="table-danger"' : '') + '>'
                        + '<td class="text-center"><input type="checkbox" class="pp-sel-deuda"' + (checked ? ' checked' : '') + '></td>'
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
                window.ppOldAplicaciones = null;
                if (window.ppOldRetencionesJson) {
                    try {
                        pintarResumenRetenciones(JSON.parse(window.ppOldRetencionesJson));
                    } catch (e) { /* ignore */ }
                    window.ppOldRetencionesJson = null;
                } else if (typeof window.calcularRetencionesPagoproveedor === 'function') {
                    window.calcularRetencionesPagoproveedor();
                }
            })
            .fail(function (xhr, status) {
                if (status === 'abort' || reqId !== deudaReqSeq) {
                    return;
                }
                mensajeDeuda('No se pudo cargar la deuda del proveedor');
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
        } else {
            // Pago MN: igual necesitamos DOL del día para el header / modo día.
            ids[monedaRefMeId] = true;
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
                        if (Number(mid) === monedaRefMeId || Number(mid) === monedaPagoId()) {
                            var actual = parseFloat($('#cotizacion').val() || '0') || 0;
                            if (actual <= 1 && cot > 1) {
                                $('#cotizacion').val(cot);
                            }
                        }
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

    $(document).on('change', '#pp-sel-deuda-todas', function () {
        var checked = this.checked;
        $('#tabla-deuda-proveedor tbody tr').each(function () {
            aplicarInclusionFila($(this), checked);
        });
        sincronizarCamposAplicacion();
        programarCalculoRetenciones();
        if (typeof flModificaAsiento !== 'undefined') {
            flModificaAsiento = true;
        }
    });

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

    $('#proveedor_id')
        .off('change.ppDeudaProv')
        .on('change.ppDeudaProv', cargarDeuda);
    $(document)
        .off('change.cpProveedorCargado.ppDeuda', '#proveedor_id')
        .on('change.cpProveedorCargado.ppDeuda', '#proveedor_id', cargarDeuda);
    $(document)
        .off('change.ppEmpresaDeuda', '#empresa_id')
        .on('change.ppEmpresaDeuda', '#empresa_id', function () {
            cargarDeuda();
            if (typeof window.calcularRetencionesPagoproveedor === 'function') {
                window.calcularRetencionesPagoproveedor();
            }
        });
    $('#moneda_id, #cotizacion, #modo_cotizacion, #fecha').on('change', function () {
        if (this.id === 'fecha' || this.id === 'moneda_id') {
            refrescarCotizacionHeader(true);
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

    // Alta en PES: precargar TC DOL del día (no dejar el header en 1).
    refrescarCotizacionHeader(false);

    function filaDetalle(label, valor) {
        return '<tr><th class="text-muted" style="width:42%;font-weight:normal;">'
            + $('<div>').text(label).html()
            + '</th><td class="text-right text-nowrap">'
            + valor
            + '</td></tr>';
    }

    function etiquetaMotivo(motivo) {
        var map = {
            ok: 'Aplica',
            ok_manual: 'Aplica (manual)',
            no_retiene: 'No retiene',
            sin_regimen: 'Sin régimen',
            bajo_minimo_no_sujeto: 'Bajo mínimo no sujeto',
            bajo_minimo_retencion: 'Bajo mínimo de retención',
            manual_requerido: 'Requiere monto manual',
            no_agente: 'Empresa no es agente',
            sin_tasa: 'Sin tasa',
            bajo_minimo_imponible: 'Bajo mínimo imponible'
        };
        return map[motivo] || String(motivo || '—');
    }

    function origenTasaLabel(origen) {
        var map = { padron: 'Padrón', fallback: 'Condición / paramétrica', override: 'Override manual' };
        return map[origen] || String(origen || '—');
    }

    var RET_META = {
        ganancias: { label: 'Ganancias', short: 'Gan.' },
        iva: { label: 'IVA', short: 'IVA' },
        suss: { label: 'SUSS', short: 'SUSS' },
        iibb: { label: 'IIBB', short: 'IIBB' }
    };

    function baseTxtRetencion(k, r) {
        if (k === 'iva') {
            return 'Neto ' + fmt(r.base_neto) + ' / IVA ' + fmt(r.base_iva);
        }
        if (r.base != null) {
            return fmt(r.base);
        }
        return '—';
    }

    function htmlDetalleTipo(tipo, res) {
        var r = (res && res[tipo]) || {};
        var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>';
        if (tipo === 'ganancias') {
            var gd = r.detalle || {};
            var acum = res.acumulado_ganancias || null;
            html += filaDetalle('Estado', etiquetaMotivo(r.motivo));
            html += filaDetalle('Importe a retener', '<strong>' + fmt(r.importe) + '</strong>');
            html += filaDetalle('Régimen', (gd.codigo ? gd.codigo + ' — ' : '') + (gd.regimen || '—'));
            html += filaDetalle('Forma / modo', (gd.forma_calculo || '—') + (gd.modo ? ' / ' + gd.modo : ''));
            html += filaDetalle('Inscripto', gd.inscripto === true ? 'Sí' : (gd.inscripto === false ? 'No' : '—'));
            html += filaDetalle('Neto este pago', fmt(gd.neto_pago != null ? gd.neto_pago : r.base));
            html += filaDetalle('Neto acum. previo', fmt(gd.neto_acumulado_previo));
            html += filaDetalle('Neto del período', fmt(gd.neto_periodo != null ? gd.neto_periodo : r.base_periodo));
            html += filaDetalle('Mínimo no sujeto (excedente)', fmt(gd.monto_excedente));
            html += filaDetalle('Base retenible', fmt(gd.base_retenible != null ? gd.base_retenible : r.base_retenible));
            html += filaDetalle('Alícuota %', fmt(r.alicuota));
            html += filaDetalle('Retención del período', fmt(gd.retencion_periodo));
            html += filaDetalle('Ya retenido (previo)', fmt(gd.retenido_previo));
            html += filaDetalle('Mínimo retención', fmt(gd.minimo_retencion));
            if (gd.retencion_calculada != null) {
                html += filaDetalle('Calculada (bajo mínimo)', fmt(gd.retencion_calculada));
            }
            html += '</tbody></table></div>';
            if (acum) {
                html += '<div class="mt-3 small"><strong>Acumulado '
                    + (acum.desde || '') + ' → ' + (acum.hasta || '')
                    + ': neto ' + fmt(acum.neto) + ' / retenido ' + fmt(acum.retenido)
                    + ' (' + (acum.pagos || 0) + ' OP'
                    + ((acum.pagos_anita > 0) ? ', ' + acum.pagos_anita + ' desde Anita' : '')
                    + ')</strong></div>';
                var lista = acum.detalle_pagos || [];
                if (lista.length) {
                    html += '<div class="table-responsive mt-2"><table class="table table-sm table-bordered mb-0"><thead><tr>'
                        + '<th>Fecha</th><th>OP</th><th class="text-right">Neto</th><th class="text-right">Retenido</th>'
                        + '</tr></thead><tbody>';
                    lista.forEach(function (p) {
                        var etiquetaOp = p.nro || ('#' + p.pagoproveedor_id);
                        if (p.origen === 'anita') {
                            etiquetaOp += ' (Anita)';
                        }
                        html += '<tr><td>' + (p.fecha || '—') + '</td><td>'
                            + $('<div>').text(etiquetaOp).html()
                            + '</td><td class="text-right">' + fmt(p.neto)
                            + '</td><td class="text-right">' + fmt(p.retenido) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<div class="text-muted small mt-1">Sin OPs con Ganancias en el período.</div>';
                }
            } else {
                html += '<div class="text-muted small mt-2">El régimen no acumula período (o no hay fecha/régimen).</div>';
            }
            return html;
        }
        if (tipo === 'iibb') {
            var idet = r.detalle || {};
            var jur = r.provincia_nombre || idet.provincia_nombre || idet.jurisdiccion || '—';
            html += filaDetalle('Estado', etiquetaMotivo(r.motivo));
            html += filaDetalle('Importe a retener', '<strong>' + fmt(r.importe) + '</strong>');
            html += filaDetalle('Jurisdicción', $('<div>').text(jur).html()
                + (idet.jurisdiccion ? ' <span class="text-muted">(cod. ' + idet.jurisdiccion + ')</span>' : ''));
            html += filaDetalle('Origen de tasa', origenTasaLabel(idet.origen_tasa));
            html += filaDetalle('Base (neto)', fmt(r.base != null ? r.base : idet.neto));
            html += filaDetalle('Alícuota %', fmt(r.alicuota));
            html += filaDetalle('Mínimo imponible', fmt(idet.minimo_imponible));
            html += filaDetalle('Mínimo retención', fmt(idet.minimo_retencion));
            if (idet.retencion_calculada != null) {
                html += filaDetalle('Calculada (bajo mínimo)', fmt(idet.retencion_calculada));
            }
            html += '</tbody></table></div>';
            return html;
        }
        // IVA / SUSS
        html += filaDetalle('Estado', etiquetaMotivo(r.motivo));
        html += filaDetalle('Importe a retener', '<strong>' + fmt(r.importe) + '</strong>');
        html += filaDetalle('Alícuota %', fmt(r.alicuota));
        if (tipo === 'iva') {
            html += filaDetalle('Base neto', fmt(r.base_neto));
            html += filaDetalle('Base IVA', fmt(r.base_iva));
        } else {
            html += filaDetalle('Base', fmt(r.base));
        }
        html += '</tbody></table></div>';
        return html;
    }

    function abrirModalCalculoRetencion(tipo) {
        var res = window.ppUltimoCalculoRetenciones;
        if (!res) {
            return;
        }
        var meta = RET_META[tipo] || { label: String(tipo || '').toUpperCase() };
        $('#pp-modal-retencion-titulo').text('Cálculo — ' + meta.label);
        $('#pp-modal-retencion-body').html(htmlDetalleTipo(tipo, res));
        $('#pp-modal-calculo-retencion').modal('show');
    }

    function pintarResumenRetenciones(res) {
        window.ppUltimoCalculoRetenciones = res;
        var $tb = $('#pp-retenciones-grilla tbody').empty();
        ['ganancias', 'iva', 'suss', 'iibb'].forEach(function (k) {
            var r = res[k] || {};
            var meta = RET_META[k];
            var importe = Number(r.importe) || 0;
            var aplica = !!r.aplica && importe > 0;
            var $tr = $('<tr/>').toggleClass('table-success', aplica);
            $tr.append($('<td/>').text(meta.label));
            $tr.append($('<td/>').text(etiquetaMotivo(r.motivo)));
            $tr.append($('<td class="text-right text-nowrap"/>').text(baseTxtRetencion(k, r)));
            $tr.append($('<td class="text-right text-nowrap"/>').text(fmt(r.alicuota) + '%'));
            $tr.append($('<td class="text-right text-nowrap font-weight-bold"/>').text(fmt(importe)));
            var $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary pp-btn-ver-calculo-ret"/>')
                .attr('data-tipo', k)
                .attr('title', 'Ver cálculo de ' + meta.label)
                .html('<i class="fa fa-calculator"></i> Ver cálculo');
            $tr.append($('<td class="text-nowrap text-center"/>').append($btn));
            $tb.append($tr);
        });
        $('#pp-retenciones-total').text(fmt(res.total));
        var pie = '';
        if (res.bases && res.bases.origen) {
            pie = 'Bases: ' + res.bases.origen;
        if (res.acumulado_ganancias && res.acumulado_ganancias.neto > 0) {
                pie += ' · Acum. Gan. mes neto ' + fmt(res.acumulado_ganancias.neto)
                    + ' / ret. ' + fmt(res.acumulado_ganancias.retenido);
                if (res.acumulado_ganancias.pagos_anita > 0) {
                    pie += ' (incl. Anita)';
                }
            }
        }
        $('#pp-retenciones-pie').text(pie || '');
        $('#pp-retenciones-resumen').removeClass('text-muted').html(
            'Total retenciones: <strong>' + fmt(res.total) + '</strong>'
        );
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
                moneda_id: $('#moneda_id').val(),
                cotizacion: $('#cotizacion').val(),
                pagoproveedor_id: $('input[name="id"]').val() || $('#id').val() || '',
                importe_neto: $('#importe_neto_retencion').val(),
                importe_iva: $('#importe_iva_retencion').val(),
                idcuentacorrientes: $form.find('input[name="idcuentacorrientes[]"]').map(function () { return $(this).val(); }).get(),
                montoaplicadocomprobantes: $form.find('input[name="montoaplicadocomprobantes[]"]').map(function () { return $(this).val(); }).get(),
                cotizacion_aplicada_dia: $form.find('input[name="cotizacion_aplicada_dia[]"]').map(function () { return $(this).val(); }).get(),
                cotizacioncomprobantes: $form.find('input[name="cotizacioncomprobantes[]"]').map(function () { return $(this).val(); }).get(),
                monedacomprobante_ids: $form.find('input[name="monedacomprobante_ids[]"]').map(function () { return $(this).val(); }).get()
            }
        }).done(function (res) {
            pintarResumenRetenciones(res);
            if (res && res.bases) {
                if (res.bases.neto_ganancias != null) {
                    $('#importe_neto_retencion').val(Number(res.bases.neto_documental || res.bases.neto_ganancias).toFixed(2));
                }
                if (res.bases.importe_iva != null) {
                    $('#importe_iva_retencion').val(Number(res.bases.importe_iva).toFixed(2));
                }
            }
        }).fail(function (xhr) {
            $('#pp-retenciones-resumen').addClass('text-muted').text(
                (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudieron calcular retenciones'
            );
            $('#pp-retenciones-grilla tbody').html(
                '<tr><td colspan="6" class="text-muted text-center">Sin cálculo</td></tr>'
            );
            $('#pp-retenciones-total').text('0,00');
            $('#pp-retenciones-pie').text('');
            window.ppUltimoCalculoRetenciones = null;
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

    $(document).on('click', '.pp-btn-ver-calculo-ret', function () {
        abrirModalCalculoRetencion($(this).data('tipo'));
    });

    $(document).on('change.ppRetenciones input.ppRetenciones', '.pp-monto-aplicar, .pp-sel-deuda', programarCalculoRetenciones);

    $form.on('submit', function () {
        sincronizarCamposAplicacion();
    });

    actualizarCheckTodas();
    if ($('#proveedor_id').val()) {
        cargarDeuda();
    }
}(jQuery));
