(function ($) {
    'use strict';

    /** @type {Object.<string, Array>} uid → etiquetas */
    var porLinea = {};
    /** @type {Object.<string, Object>} uid → emisión AP {separa_unidadmedida_id, cant_unid_separa, unidades[]} */
    var emisionPorLinea = {};
    var uidActivo = null;
    var uidSeq = 0;
    var apModalUid = null;

    function $panel() {
        return $('#ms-panel-etiquetas-surmar');
    }

    function $workbench() {
        return $('#ms-surmar-workbench');
    }

    function tiposPiqueo() {
        var raw = String($panel().data('tipos') || 'AP,DES,TRA');
        return raw.split(',').map(function (t) { return String(t).trim().toUpperCase(); }).filter(Boolean);
    }

    function empresaEsSurmar() {
        if (String($panel().data('surmar-activo') || '') !== '1') {
            return false;
        }
        var empSurmar = parseInt($panel().data('empresa-surmar'), 10) || 3;
        var emp = parseInt($('#empresa_id').val(), 10) || 0;
        return emp === empSurmar;
    }

    function abreviaturaTipo() {
        if (typeof window.msTipoTransaccionMeta === 'function') {
            return String(window.msTipoTransaccionMeta().abreviatura || '').trim().toUpperCase();
        }
        return String($('#tipotransaccion_stock_id_abreviatura').val() || '').trim().toUpperCase();
    }

    function debeMostrar() {
        return empresaEsSurmar() && tiposPiqueo().indexOf(abreviaturaTipo()) !== -1;
    }

    function setMsg(text, ok) {
        var $m = $('#ms_etiqueta_msg');
        $m.text(text || '');
        $m.removeClass('text-danger text-success');
        if (text) {
            $m.addClass(ok ? 'text-success' : 'text-danger');
        }
    }

    function filasItems() {
        return $('#tabla-items-movimientostock tbody tr.item-pedido');
    }

    function ensureUid($tr) {
        var uid = String($tr.attr('data-ms-uid') || '');
        if (!uid) {
            uidSeq += 1;
            uid = 'msl-n-' + uidSeq + '-' + Date.now();
            $tr.attr('data-ms-uid', uid);
        }
        if (!porLinea[uid]) {
            porLinea[uid] = [];
        }
        return uid;
    }

    function ensureAllUids() {
        filasItems().each(function () {
            ensureUid($(this));
        });
    }

    function etiquetasDe(uid) {
        if (!uid) {
            return [];
        }
        if (!porLinea[uid]) {
            porLinea[uid] = [];
        }
        return porLinea[uid];
    }

    function etiquetasActivas() {
        return etiquetasDe(uidActivo);
    }

    function idsUsadosGlobal() {
        var ids = {};
        Object.keys(porLinea).forEach(function (uid) {
            (porLinea[uid] || []).forEach(function (e) {
                ids[e.etiqueta_id] = true;
            });
        });
        return ids;
    }

    function actualizarBadges() {
        filasItems().each(function () {
            var $tr = $(this);
            var uid = ensureUid($tr);
            var n = etiquetasDe(uid).length;
            $tr.find('.ms-etiq-badge').text(String(n));
            if (n > 0) {
                $tr.find('.ms-etiq-badge').removeClass('badge-secondary').addClass('badge-info');
            } else {
                $tr.find('.ms-etiq-badge').removeClass('badge-info').addClass('badge-secondary');
            }
        });
    }

    function actualizarCtx() {
        var $ctx = $('#ms_etiqueta_linea_ctx');
        if (!uidActivo) {
            $ctx.html('Seleccioná un renglón a la izquierda para piquear sus etiquetas.');
            return;
        }
        var $tr = filasItems().filter('[data-ms-uid="' + uidActivo + '"]').first();
        var nro = $tr.find('.item').val() || '?';
        var sku = String($tr.find('.codigoarticulo').val() || '').trim();
        var desc = String($tr.find('.descripcionarticulo').val() || '').trim();
        var txt = 'Renglón <strong>#' + $('<div>').text(nro).html() + '</strong>';
        if (sku) {
            txt += ' — SKU <code>' + $('<div>').text(sku).html() + '</code>';
        }
        if (desc) {
            txt += ' <span class="text-muted">' + $('<div>').text(desc).html() + '</span>';
        }
        $ctx.html(txt);
    }

    function renderPanel() {
        var $tb = $('#ms-tbody-etiquetas-surmar');
        $tb.empty();
        var etiquetas = etiquetasActivas();
        var total = 0;
        if (!uidActivo) {
            $tb.append('<tr class="ms-etiq-empty"><td colspan="5" class="text-center text-muted">Seleccioná un renglón.</td></tr>');
        } else if (!etiquetas.length) {
            $tb.append('<tr class="ms-etiq-empty"><td colspan="5" class="text-center text-muted">Sin etiquetas en este renglón.</td></tr>');
        } else {
            etiquetas.forEach(function (e) {
                total += parseFloat(e.peso_neto) || 0;
                $tb.append(
                    '<tr data-id="' + e.etiqueta_id + '">' +
                    '<td>' + e.etiqueta_id + '</td>' +
                    '<td>' + $('<div>').text(e.sku || '').html() + '</td>' +
                    '<td class="text-right">' + (parseFloat(e.peso_neto) || 0).toFixed(2) + '</td>' +
                    '<td>' + $('<div>').text(e.lote_proveedor || '—').html() + '</td>' +
                    '<td class="text-center text-nowrap">' +
                    '<button type="button" class="btn-accion-tabla ms-etiq-reimprimir" title="Reimprimir" data-id="' + e.etiqueta_id + '">' +
                    '<i class="fa fa-print"></i></button>' +
                    '<button type="button" class="btn-accion-tabla ms-etiq-quitar" title="Quitar" data-id="' + e.etiqueta_id + '">' +
                    '<i class="fa fa-times-circle text-danger"></i></button></td></tr>'
                );
            });
        }
        $('#ms_etiqueta_total_neto').text(total.toFixed(2));
        actualizarBadges();
        actualizarCtx();
    }

    function syncHiddens() {
        var $h = $('#ms-etiquetas-consumo-hiddens');
        $h.empty();
        if (!debeMostrar()) {
            syncEmisionHiddens();
            return;
        }
        ensureAllUids();
        filasItems().each(function (idx) {
            var uid = ensureUid($(this));
            etiquetasDe(uid).forEach(function (e) {
                $h.append(
                    '<input type="hidden" name="etiquetas_consumo_linea[' + idx + '][]" value="' + e.etiqueta_id + '">'
                );
            });
        });
        syncEmisionHiddens();
    }

    function setScanEnabled(on) {
        $('#ms_etiqueta_scan, #ms_etiqueta_agregar').prop('disabled', !on || !uidActivo);
    }

    function seleccionar($tr, opts) {
        if (!$tr || !$tr.length || !debeMostrar()) {
            return;
        }
        opts = opts || {};
        ensureAllUids();
        uidActivo = ensureUid($tr);
        filasItems().removeClass('ms-linea-activa');
        $tr.addClass('ms-linea-activa');
        setScanEnabled(true);
        renderPanel();
        actualizarEmisionUi();
        setMsg('');
        // El foco del renglón (SKU, kilos, F1) no se mueve al lector.
        // Solo al hacer clic en la fila, fuera de un campo, se prepara el escaneo.
        if (opts.enfocarScan) {
            $('#ms_etiqueta_scan').trigger('focus');
        }
    }

    function seleccionarPrimeraSiHaceFalta() {
        if (!debeMostrar()) {
            return;
        }
        ensureAllUids();
        var $activo = filasItems().filter('.ms-linea-activa').first();
        if ($activo.length) {
            seleccionar($activo);
            return;
        }
        if (uidActivo && filasItems().filter('[data-ms-uid="' + uidActivo + '"]').length) {
            seleccionar(filasItems().filter('[data-ms-uid="' + uidActivo + '"]').first());
            return;
        }
        var $first = filasItems().first();
        if ($first.length) {
            seleccionar($first);
        } else {
            uidActivo = null;
            setScanEnabled(false);
            renderPanel();
        }
    }

    function esAp() {
        return abreviaturaTipo() === 'AP';
    }

    function toggleLayout(show) {
        var $wb = $workbench();
        var $colItems = $('#ms-surmar-col-items');
        var $colEtiq = $('#ms-surmar-col-etiq');
        var $p = $panel();
        if (show) {
            $wb.addClass('ms-surmar-activo');
            $colItems.removeClass('col-12').addClass('col-12 col-lg-7');
            $colEtiq.show();
            $p.show();
        } else {
            $wb.removeClass('ms-surmar-activo');
            $colItems.removeClass('col-lg-7').addClass('col-12');
            $colEtiq.hide();
            $p.hide();
            filasItems().removeClass('ms-linea-activa');
            uidActivo = null;
        }
        $('#ms-ap-emision-wrap').toggle(show && esAp());
    }

    function togglePanel() {
        var show = debeMostrar();
        if (!$panel().length) {
            return;
        }
        toggleLayout(show);
        renderHijas();
        if (!show) {
            porLinea = {};
            emisionPorLinea = {};
            syncHiddens();
            setMsg('');
            setScanEnabled(false);
            actualizarBadges();
            actualizarEmisionUi();
            return;
        }
        ensureAllUids();
        seleccionarPrimeraSiHaceFalta();
        syncHiddens();
        actualizarEmisionUi();
    }

    function emisionDe(uid) {
        if (!uid) {
            return null;
        }
        return emisionPorLinea[uid] || null;
    }

    function ensureEmision(uid) {
        if (!uid) {
            return null;
        }
        if (!emisionPorLinea[uid]) {
            var defSepara = parseInt($('#ms-ap-separa-default').val(), 10) || 2;
            emisionPorLinea[uid] = {
                separa_unidadmedida_id: defSepara,
                cant_unid_separa: 1,
                unidades: []
            };
        }
        return emisionPorLinea[uid];
    }

    function actualizarEmisionUi() {
        var showAp = debeMostrar() && esAp();
        $('#ms-ap-emision-wrap').toggle(showAp);
        var $btn = $('#ms_ap_abrir_emision');
        $btn.prop('disabled', !showAp || !uidActivo);
        var emi = uidActivo ? emisionDe(uidActivo) : null;
        var n = emi && Array.isArray(emi.unidades) ? emi.unidades.length : 0;
        var cant = emi ? (parseInt(emi.cant_unid_separa, 10) || 0) : 0;
        var txt = '';
        if (showAp && uidActivo) {
            if (n > 0) {
                txt = 'Emisión: ' + n + (cant ? ' / ' + cant : '') + ' unidad(es)';
                var $opt = $('#ms_ap_etiq_separa option[value="' + (emi.separa_unidadmedida_id || '') + '"]');
                var abr = $opt.data('abrev') || $opt.text() || '';
                if (abr) {
                    txt += ' — ' + String(abr).trim();
                }
            } else {
                txt = 'Sin emisión cargada para este renglón.';
            }
        }
        $('#ms_ap_emision_resumen').text(txt);
    }

    function syncEmisionHiddens() {
        var $h = $('#ms-etiquetas-emision-hiddens');
        $h.empty();
        if (!debeMostrar() || !esAp()) {
            return;
        }
        ensureAllUids();
        filasItems().each(function (idx) {
            var uid = ensureUid($(this));
            var emi = emisionDe(uid);
            if (!emi || !Array.isArray(emi.unidades) || !emi.unidades.length) {
                return;
            }
            var base = 'etiquetas_emision_linea[' + idx + ']';
            $h.append('<input type="hidden" name="' + base + '[separa_unidadmedida_id]" value="' + (parseInt(emi.separa_unidadmedida_id, 10) || 0) + '">');
            $h.append('<input type="hidden" name="' + base + '[cant_unid_separa]" value="' + (parseInt(emi.cant_unid_separa, 10) || 1) + '">');
            emi.unidades.forEach(function (u, ui) {
                var ub = base + '[unidades][' + ui + ']';
                $h.append('<input type="hidden" name="' + ub + '[nro_apertura]" value="' + (parseInt(u.nro_apertura, 10) || (ui + 1)) + '">');
                $h.append('<input type="hidden" name="' + ub + '[cant_pieza]" value="' + (parseFloat(u.cant_pieza) || 1) + '">');
                $h.append('<input type="hidden" name="' + ub + '[peso_bruto]" value="' + (parseFloat(u.peso_bruto) || 0) + '">');
                $h.append('<input type="hidden" name="' + ub + '[peso_tara]" value="' + (parseFloat(u.peso_tara) || 0) + '">');
                $h.append('<input type="hidden" name="' + ub + '[peso_neto]" value="' + (parseFloat(u.peso_neto) || 0) + '">');
                $h.append('<input type="hidden" name="' + ub + '[fecha_vto]" value="' + $('<div>').text(u.fecha_vto || '').html() + '">');
            });
        });
    }

    function renderApModalUnidades() {
        var $tb = $('#ms_ap_etiq_tbody_unidades');
        $tb.empty();
        var emi = apModalUid ? emisionDe(apModalUid) : null;
        var unidades = emi && Array.isArray(emi.unidades) ? emi.unidades : [];
        if (!unidades.length) {
            $tb.append('<tr class="ms-ap-etiq-empty"><td colspan="6" class="text-center text-muted">Sin unidades cargadas.</td></tr>');
            return;
        }
        unidades.forEach(function (u, i) {
            $tb.append(
                '<tr data-idx="' + i + '">' +
                '<td>' + (u.nro_apertura || (i + 1)) + '</td>' +
                '<td class="text-right">' + (parseFloat(u.cant_pieza) || 0).toFixed(2) + '</td>' +
                '<td class="text-right">' + (parseFloat(u.peso_bruto) || 0).toFixed(2) + '</td>' +
                '<td class="text-right">' + (parseFloat(u.peso_neto) || 0).toFixed(2) + '</td>' +
                '<td>' + $('<div>').text(u.fecha_vto || '—').html() + '</td>' +
                '<td class="text-center"><button type="button" class="btn-accion-tabla ms-ap-etiq-quitar-unidad" data-idx="' + i + '" title="Quitar">' +
                '<i class="fa fa-times-circle text-danger"></i></button></td></tr>'
            );
        });
    }

    function recalcularNetoApModal() {
        var bruto = parseFloat($('#ms_ap_etiq_bruto').val()) || 0;
        var tara = parseFloat($('#ms_ap_etiq_tara').val()) || 0;
        if (bruto > 0) {
            $('#ms_ap_etiq_neto').val((bruto - tara).toFixed(2));
        }
    }

    function setApModalMsg(text, ok) {
        var $m = $('#ms_ap_etiq_msg');
        $m.text(text || '');
        $m.removeClass('text-danger text-success');
        if (text) {
            $m.addClass(ok ? 'text-success' : 'text-danger');
        }
    }

    function abrirModalEmisionAp() {
        if (!esAp() || !uidActivo) {
            setMsg('Seleccioná un renglón AP para emitir', false);
            return;
        }
        apModalUid = uidActivo;
        var emi = ensureEmision(apModalUid);
        var $tr = filasItems().filter('[data-ms-uid="' + apModalUid + '"]').first();
        var sku = String($tr.find('.codigoarticulo').val() || '').trim();
        var desc = String($tr.find('.descripcionarticulo').val() || '').trim();
        var nro = $tr.find('.item').val() || '?';
        $('#ms_ap_etiq_ctx').html('Renglón <strong>#' + $('<div>').text(nro).html() + '</strong>' +
            (sku ? ' — SKU <code>' + $('<div>').text(sku).html() + '</code>' : '') +
            (desc ? ' <span class="text-muted">' + $('<div>').text(desc).html() + '</span>' : ''));
        $('#ms_ap_etiq_separa').val(String(emi.separa_unidadmedida_id || $('#ms-ap-separa-default').val() || ''));
        $('#ms_ap_etiq_cant_unid').val(parseInt(emi.cant_unid_separa, 10) || 1);
        var nextNro = (emi.unidades.length || 0) + 1;
        $('#ms_ap_etiq_nro').val(nextNro);
        $('#ms_ap_etiq_badge').text(emi.unidades.length + ' / ' + (parseInt(emi.cant_unid_separa, 10) || 1)).show();
        var cantLinea = parseFloat(String($tr.find('.cantidad-stock, .cantidad, input[name="cantidades[]"]').first().val() || '0').replace(',', '.')) || 0;
        if (!(parseFloat($('#ms_ap_etiq_neto').val()) > 0) && cantLinea > 0 && emi.unidades.length === 0) {
            $('#ms_ap_etiq_neto').val(cantLinea.toFixed(2));
            $('#ms_ap_etiq_bruto').val(cantLinea.toFixed(2));
        }
        if (!(parseFloat($('#ms_ap_etiq_piezas').val()) > 0)) {
            $('#ms_ap_etiq_piezas').val('1');
        }
        renderApModalUnidades();
        setApModalMsg('');
        $('#modalEmisionEtiquetaApSurmar').modal('show');
        setTimeout(function () { $('#ms_ap_etiq_separa').trigger('focus'); }, 200);
    }

    function confirmarUnidadApModal() {
        if (!apModalUid) {
            return;
        }
        var emi = ensureEmision(apModalUid);
        var cantUnid = Math.max(1, Math.min(50, parseInt($('#ms_ap_etiq_cant_unid').val(), 10) || 1));
        var separa = parseInt($('#ms_ap_etiq_separa').val(), 10) || 0;
        if (separa <= 0) {
            setApModalMsg('Elegí en que separa', false);
            $('#ms_ap_etiq_separa').trigger('focus');
            return;
        }
        recalcularNetoApModal();
        var neto = parseFloat($('#ms_ap_etiq_neto').val()) || 0;
        var bruto = parseFloat($('#ms_ap_etiq_bruto').val()) || 0;
        var tara = parseFloat($('#ms_ap_etiq_tara').val()) || 0;
        var piezas = parseFloat($('#ms_ap_etiq_piezas').val()) || 0;
        if (neto <= 0 && bruto > 0) {
            neto = bruto - tara;
        }
        if (neto <= 0) {
            setApModalMsg('Peso neto inválido', false);
            $('#ms_ap_etiq_neto').trigger('focus');
            return;
        }
        if (bruto <= 0) {
            bruto = neto + tara;
        }
        if (piezas <= 0) {
            piezas = 1;
        }
        if (emi.unidades.length >= cantUnid) {
            setApModalMsg('Ya cargó las ' + cantUnid + ' unidades. Pulse Listo o suba la cantidad que separa.', false);
            return;
        }
        var nro = emi.unidades.length + 1;
        emi.separa_unidadmedida_id = separa;
        emi.cant_unid_separa = cantUnid;
        emi.unidades.push({
            nro_apertura: nro,
            cant_pieza: piezas,
            peso_bruto: bruto,
            peso_tara: tara,
            peso_neto: neto,
            fecha_vto: String($('#ms_ap_etiq_vto').val() || '')
        });
        $('#ms_ap_etiq_nro').val(emi.unidades.length + 1);
        $('#ms_ap_etiq_badge').text(emi.unidades.length + ' / ' + cantUnid).show();
        renderApModalUnidades();
        syncEmisionHiddens();
        actualizarEmisionUi();
        setApModalMsg('Unidad ' + nro + ' confirmada', true);
        if (emi.unidades.length >= cantUnid) {
            setApModalMsg('Completó las ' + cantUnid + ' unidades. Pulse Listo.', true);
            return;
        }
        $('#ms_ap_etiq_piezas').val('1');
        $('#ms_ap_etiq_bruto').val('');
        $('#ms_ap_etiq_neto').val('');
        $('#ms_ap_etiq_bruto').trigger('focus');
    }

    function listoEmisionApModal() {
        if (!apModalUid) {
            $('#modalEmisionEtiquetaApSurmar').modal('hide');
            return;
        }
        var emi = ensureEmision(apModalUid);
        var cantUnid = Math.max(1, Math.min(50, parseInt($('#ms_ap_etiq_cant_unid').val(), 10) || 1));
        var separa = parseInt($('#ms_ap_etiq_separa').val(), 10) || 0;
        emi.separa_unidadmedida_id = separa || emi.separa_unidadmedida_id;
        emi.cant_unid_separa = cantUnid;
        if (!emi.unidades.length) {
            setApModalMsg('Confirmá al menos una unidad', false);
            return;
        }
        if (emi.unidades.length !== cantUnid) {
            setApModalMsg('Cantidad que separa (' + cantUnid + ') ≠ unidades (' + emi.unidades.length + '). Ajustá o seguí confirmando.', false);
            return;
        }
        syncEmisionHiddens();
        actualizarEmisionUi();
        $('#modalEmisionEtiquetaApSurmar').modal('hide');
        setMsg('Emisión AP del renglón lista (' + emi.unidades.length + ')', true);
    }

    function hidratarEmisionOld() {
        var raw = $('#ms-etiquetas-surmar-emision-old').text();
        if (!raw) {
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            return;
        }
        if (!data || typeof data !== 'object') {
            return;
        }
        ensureAllUids();
        var $filas = filasItems();
        Object.keys(data).forEach(function (k) {
            var idx = parseInt(k, 10);
            if (isNaN(idx)) {
                return;
            }
            var $tr = $filas.eq(idx);
            if (!$tr.length || !data[k] || typeof data[k] !== 'object') {
                return;
            }
            var uid = ensureUid($tr);
            var emi = data[k];
            emisionPorLinea[uid] = {
                separa_unidadmedida_id: parseInt(emi.separa_unidadmedida_id, 10) || (parseInt($('#ms-ap-separa-default').val(), 10) || 2),
                cant_unid_separa: parseInt(emi.cant_unid_separa, 10) || 1,
                unidades: Array.isArray(emi.unidades) ? emi.unidades.map(function (u, i) {
                    return {
                        nro_apertura: parseInt(u.nro_apertura, 10) || (i + 1),
                        cant_pieza: parseFloat(u.cant_pieza) || 1,
                        peso_bruto: parseFloat(u.peso_bruto) || 0,
                        peso_tara: parseFloat(u.peso_tara) || 0,
                        peso_neto: parseFloat(u.peso_neto) || 0,
                        fecha_vto: String(u.fecha_vto || '')
                    };
                }) : []
            };
        });
    }

    function agregar() {
        if (!debeMostrar()) {
            return;
        }
        if (!uidActivo) {
            setMsg('Seleccioná un renglón de ítem primero', false);
            return;
        }
        var raw = String($('#ms_etiqueta_scan').val() || '').trim();
        if (!raw) {
            setMsg('Ingrese un código de etiqueta', false);
            return;
        }
        var url = $('#ms-resolver-etiqueta-surmar-url').val();
        if (!url) {
            setMsg('URL resolver no configurada', false);
            return;
        }
        setMsg('Resolviendo…', true);
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            headers: { Accept: 'application/json' },
            data: {
                _token: $('#csrf_token').val() || $('meta[name="csrf-token"]').attr('content'),
                codigo: raw,
                empresa_id: $('#empresa_id').val() || $panel().data('empresa-surmar')
            },
            success: function (resp) {
                if (!resp || !resp.ok || !resp.etiqueta) {
                    setMsg((resp && resp.message) || 'No se pudo resolver', false);
                    return;
                }
                var e = resp.etiqueta;
                if (idsUsadosGlobal()[e.etiqueta_id]) {
                    setMsg('Etiqueta #' + e.etiqueta_id + ' ya está en otro renglón', false);
                    return;
                }
                etiquetasActivas().push(e);
                $('#ms_etiqueta_scan').val('');
                renderPanel();
                syncHiddens();
                setMsg('Etiqueta #' + e.etiqueta_id + ' en renglón activo', true);
                $('#ms_etiqueta_scan').focus();
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Error al resolver etiqueta';
                var preview = raw.length > 40 ? raw.slice(0, 40) + '…' : raw;
                setMsg(msg + ' [leído: ' + preview + ']', false);
            }
        });
    }

    function validarAntesDeSubmit() {
        if (!debeMostrar()) {
            return true;
        }
        ensureAllUids();
        syncHiddens();
        var faltan = [];
        filasItems().each(function (idx) {
            var $tr = $(this);
            var artId = parseInt($tr.find('.articulo_id').val(), 10) || 0;
            var cantRaw = $tr.find('.cantidad-stock, .cantidad, input[name="cantidades[]"]').first().val() || '0';
            var cant = parseFloat(String(cantRaw).replace(',', '.')) || 0;
            if (artId <= 0 && Math.abs(cant) < 1e-9) {
                return;
            }
            var uid = ensureUid($tr);
            if (!etiquetasDe(uid).length) {
                faltan.push(String($tr.find('.item').val() || (idx + 1)));
            }
        });
        if (faltan.length) {
            var msg = 'Surmar ' + abreviaturaTipo() + ': cada ítem debe tener al menos una etiqueta. Faltan renglón(es): ' + faltan.join(', ');
            setMsg(msg, false);
            alert(msg);
            var $falta = filasItems().filter(function () {
                var n = String($(this).find('.item').val() || '');
                return faltan.indexOf(n) !== -1;
            }).first();
            if ($falta.length) {
                seleccionar($falta);
            }
            $('#ms_etiqueta_scan').focus();
            return false;
        }
        if (esAp()) {
            var faltanEmi = [];
            filasItems().each(function (idx) {
                var $tr = $(this);
                var artId = parseInt($tr.find('.articulo_id').val(), 10) || 0;
                var cantRaw = $tr.find('.cantidad-stock, .cantidad, input[name="cantidades[]"]').first().val() || '0';
                var cant = parseFloat(String(cantRaw).replace(',', '.')) || 0;
                if (artId <= 0 && Math.abs(cant) < 1e-9) {
                    return;
                }
                var uid = ensureUid($tr);
                var emi = emisionDe(uid);
                var nUni = emi && Array.isArray(emi.unidades) ? emi.unidades.length : 0;
                var cantUnid = emi ? (parseInt(emi.cant_unid_separa, 10) || 0) : 0;
                if (!emi || nUni <= 0 || nUni !== cantUnid) {
                    faltanEmi.push(String($tr.find('.item').val() || (idx + 1)));
                }
            });
            if (faltanEmi.length) {
                var msgE = 'Surmar AP: cada ítem debe emitir etiquetas (en que separa / cantidad). Faltan renglón(es): ' + faltanEmi.join(', ');
                setMsg(msgE, false);
                alert(msgE);
                var $fe = filasItems().filter(function () {
                    var n = String($(this).find('.item').val() || '');
                    return faltanEmi.indexOf(n) !== -1;
                }).first();
                if ($fe.length) {
                    seleccionar($fe);
                    abrirModalEmisionAp();
                }
                return false;
            }
        }
        syncEmisionHiddens();
        return true;
    }

    function hidratarInicial() {
        var raw = $('#ms-etiquetas-surmar-inicial').text();
        if (!raw) {
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            return;
        }
        if (!data || typeof data !== 'object') {
            return;
        }
        ensureAllUids();
        var $filas = filasItems();
        if (Array.isArray(data)) {
            data.forEach(function (lista, idx) {
                var $tr = $filas.eq(idx);
                if (!$tr.length || !Array.isArray(lista)) {
                    return;
                }
                var uid = ensureUid($tr);
                porLinea[uid] = lista.map(normalizarPayload).filter(Boolean);
            });
        } else {
            Object.keys(data).forEach(function (k) {
                var idx = parseInt(k, 10);
                if (isNaN(idx)) {
                    return;
                }
                var $tr = $filas.eq(idx);
                if (!$tr.length || !Array.isArray(data[k])) {
                    return;
                }
                var uid = ensureUid($tr);
                porLinea[uid] = data[k].map(normalizarPayload).filter(Boolean);
            });
        }
    }

    function normalizarPayload(e) {
        if (e == null) {
            return null;
        }
        if (typeof e === 'number' || typeof e === 'string') {
            var id = parseInt(e, 10);
            if (id <= 0) {
                return null;
            }
            return { etiqueta_id: id, sku: '', peso_neto: 0, lote_proveedor: '' };
        }
        var eid = parseInt(e.etiqueta_id, 10) || 0;
        if (eid <= 0) {
            return null;
        }
        return e;
    }

    var hijasEmitidas = [];

    function descargarZpl(zpl) {
        var blob = new Blob([zpl], { type: 'text/plain' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'etiqueta_surmar.zpl';
        a.click();
    }

    function enviarZpl(zpl) {
        if (!zpl) {
            return;
        }
        if (window.BrowserPrint) {
            try {
                BrowserPrint.getDefaultDevice('printer', function (device) {
                    if (device) {
                        device.send(zpl);
                    } else {
                        descargarZpl(zpl);
                    }
                }, function () { descargarZpl(zpl); });
                return;
            } catch (e) {}
        }
        descargarZpl(zpl);
    }

    function reimprimirIds(ids) {
        ids = (ids || []).map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; });
        if (!ids.length) {
            setMsg('No hay etiquetas para reimprimir', false);
            return;
        }
        var url = $('#ms-zpl-etiquetas-surmar-url').val();
        if (!url) {
            setMsg('URL de impresión no configurada', false);
            return;
        }
        setMsg('Armando etiquetas…', true);
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: $('#csrf_token').val() || $('meta[name="csrf-token"]').attr('content'),
                ids: ids
            },
            success: function (resp) {
                if (!resp || !resp.ok || !resp.etiquetas || !resp.etiquetas.length) {
                    setMsg((resp && resp.message) || 'Sin etiquetas para imprimir', false);
                    return;
                }
                resp.etiquetas.forEach(function (e) { enviarZpl(e.zpl); });
                setMsg('Reimpresión enviada (' + resp.etiquetas.length + ')', true);
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo reimprimir';
                setMsg(msg, false);
            }
        });
    }

    function renderHijas() {
        var $wrap = $('#ms-etiquetas-emitidas-wrap');
        var $tb = $('#ms-tbody-etiquetas-emitidas');
        if (!$wrap.length) {
            return;
        }
        if (!debeMostrar() || !hijasEmitidas.length) {
            $wrap.hide();
            return;
        }
        $wrap.show();
        $tb.empty();
        hijasEmitidas.forEach(function (e) {
            $tb.append(
                '<tr>' +
                '<td>' + e.etiqueta_id + '</td>' +
                '<td>' + $('<div>').text(e.sku || '').html() + '</td>' +
                '<td class="text-right">' + (parseFloat(e.peso_neto) || 0).toFixed(2) + '</td>' +
                '<td>' + $('<div>').text(e.lote_proveedor || '—').html() + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn-accion-tabla ms-etiq-reimprimir" title="Reimprimir" data-id="' + e.etiqueta_id + '">' +
                '<i class="fa fa-print"></i></button></td></tr>'
            );
        });
    }

    function hidratarHijas() {
        var raw = $('#ms-etiquetas-surmar-hijas').text();
        hijasEmitidas = [];
        if (!raw) {
            renderHijas();
            return;
        }
        try {
            var data = JSON.parse(raw);
            if (Array.isArray(data)) {
                hijasEmitidas = data.map(normalizarPayload).filter(Boolean);
            }
        } catch (e) {
            hijasEmitidas = [];
        }
        renderHijas();
    }

    function limpiarUidEliminado(uid) {
        if (uid && porLinea[uid]) {
            delete porLinea[uid];
        }
        if (uid && emisionPorLinea[uid]) {
            delete emisionPorLinea[uid];
        }
        if (uidActivo === uid) {
            uidActivo = null;
        }
        if (apModalUid === uid) {
            apModalUid = null;
        }
    }

    $(function () {
        if (!$panel().length) {
            return;
        }

        hidratarInicial();
        hidratarEmisionOld();
        hidratarHijas();
        togglePanel();
        syncHiddens();
        actualizarEmisionUi();

        $(document).on('change', '#empresa_id, #tipotransaccion_stock_id', togglePanel);
        $(document).on('ms:tipotransaccion-changed', togglePanel);

        $(document).on('click', '#tabla-items-movimientostock tbody tr.item-pedido', function (e) {
            if (!debeMostrar()) {
                return;
            }
            var enCampo = $(e.target).closest('input, select, textarea, button, a, .eliminar, .consultaarticulo, .btn-saldos-articulo-linea').length > 0;
            seleccionar($(this), { enfocarScan: !enCampo });
        });

        $(document).on('focusin', '#tabla-items-movimientostock tbody tr.item-pedido', function () {
            if (!debeMostrar()) {
                return;
            }
            seleccionar($(this), { enfocarScan: false });
        });

        $(document).on('click', '#agrega_renglon', function () {
            setTimeout(function () {
                if (!debeMostrar()) {
                    return;
                }
                ensureAllUids();
                var $last = filasItems().last();
                if ($last.length) {
                    seleccionar($last, { enfocarScan: false });
                }
                syncHiddens();
            }, 0);
        });

        $(document).on('click', '#tabla-items-movimientostock .eliminar', function () {
            var $tr = $(this).closest('tr.item-pedido');
            var uid = String($tr.attr('data-ms-uid') || '');
            setTimeout(function () {
                limpiarUidEliminado(uid);
                if (debeMostrar()) {
                    seleccionarPrimeraSiHaceFalta();
                    syncHiddens();
                }
            }, 0);
        });

        $('#ms_etiqueta_agregar').on('click', function (e) {
            e.preventDefault();
            agregar();
        });
        $('#ms_etiqueta_scan').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregar();
            }
        });
        $(document).on('click', '.ms-etiq-reimprimir', function (e) {
            e.preventDefault();
            e.stopPropagation();
            reimprimirIds([$(this).data('id')]);
        });
        $('#ms_etiqueta_reimprimir_emitidas').on('click', function (e) {
            e.preventDefault();
            reimprimirIds(hijasEmitidas.map(function (x) { return x.etiqueta_id; }));
        });
        $(document).on('click', '.ms-etiq-quitar', function () {
            if (!uidActivo) {
                return;
            }
            var id = parseInt($(this).data('id'), 10);
            porLinea[uidActivo] = etiquetasActivas().filter(function (x) { return x.etiqueta_id !== id; });
            renderPanel();
            syncHiddens();
            setMsg('Etiqueta quitada', true);
        });

        $('form').on('submit', function (e) {
            if (!validarAntesDeSubmit()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
        });

        $('#ms_ap_abrir_emision').on('click', function (e) {
            e.preventDefault();
            abrirModalEmisionAp();
        });
        $('#ms_ap_etiq_confirmar_unidad').on('click', function (e) {
            e.preventDefault();
            confirmarUnidadApModal();
        });
        $('#ms_ap_etiq_listo').on('click', function (e) {
            e.preventDefault();
            listoEmisionApModal();
        });
        $('#ms_ap_etiq_bruto, #ms_ap_etiq_tara').on('input change', recalcularNetoApModal);
        $(document).on('click', '.ms-ap-etiq-quitar-unidad', function (e) {
            e.preventDefault();
            if (!apModalUid) {
                return;
            }
            var idx = parseInt($(this).data('idx'), 10);
            var emi = ensureEmision(apModalUid);
            if (!emi.unidades[idx]) {
                return;
            }
            emi.unidades.splice(idx, 1);
            emi.unidades.forEach(function (u, i) { u.nro_apertura = i + 1; });
            $('#ms_ap_etiq_nro').val(emi.unidades.length + 1);
            $('#ms_ap_etiq_badge').text(emi.unidades.length + ' / ' + (parseInt(emi.cant_unid_separa, 10) || 1)).show();
            renderApModalUnidades();
            syncEmisionHiddens();
            actualizarEmisionUi();
        });
        $('#ms_ap_etiq_cant_unid').on('change', function () {
            if (!apModalUid) {
                return;
            }
            var emi = ensureEmision(apModalUid);
            emi.cant_unid_separa = Math.max(1, Math.min(50, parseInt($(this).val(), 10) || 1));
            $('#ms_ap_etiq_badge').text(emi.unidades.length + ' / ' + emi.cant_unid_separa).show();
        });
        $(document).on('keydown', '.ms-ap-etiq-nav', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var $fields = $('.ms-ap-etiq-nav:visible:not([readonly])');
            var i = $fields.index(this);
            if (i >= 0 && i < $fields.length - 1) {
                $fields.eq(i + 1).trigger('focus');
            } else {
                confirmarUnidadApModal();
            }
        });
    });

    window.msSurmarEtiquetasToggle = togglePanel;
})(jQuery);
