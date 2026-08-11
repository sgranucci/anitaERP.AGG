(function ($) {
    'use strict';

    /** @type {Object.<string, Array>} uid → etiquetas */
    var porLinea = {};
    var uidActivo = null;
    var uidSeq = 0;

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
                    '<td class="text-center">' +
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
    }

    function setScanEnabled(on) {
        $('#ms_etiqueta_scan, #ms_etiqueta_agregar').prop('disabled', !on || !uidActivo);
    }

    function seleccionar($tr) {
        if (!$tr || !$tr.length || !debeMostrar()) {
            return;
        }
        ensureAllUids();
        uidActivo = ensureUid($tr);
        filasItems().removeClass('ms-linea-activa');
        $tr.addClass('ms-linea-activa');
        setScanEnabled(true);
        renderPanel();
        setMsg('');
        $('#ms_etiqueta_scan').trigger('focus');
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
    }

    function togglePanel() {
        var show = debeMostrar();
        if (!$panel().length) {
            return;
        }
        toggleLayout(show);
        if (!show) {
            porLinea = {};
            syncHiddens();
            setMsg('');
            setScanEnabled(false);
            actualizarBadges();
            return;
        }
        ensureAllUids();
        seleccionarPrimeraSiHaceFalta();
        syncHiddens();
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
                setMsg(msg, false);
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

    function limpiarUidEliminado(uid) {
        if (uid && porLinea[uid]) {
            delete porLinea[uid];
        }
        if (uidActivo === uid) {
            uidActivo = null;
        }
    }

    $(function () {
        if (!$panel().length) {
            return;
        }

        hidratarInicial();
        togglePanel();
        syncHiddens();

        $(document).on('change', '#empresa_id, #tipotransaccion_stock_id', togglePanel);
        $(document).on('ms:tipotransaccion-changed', togglePanel);

        $(document).on('click', '#tabla-items-movimientostock tbody tr.item-pedido', function (e) {
            if (!debeMostrar()) {
                return;
            }
            if ($(e.target).closest('button, a, .eliminar, .consultaarticulo, .btn-saldos-articulo-linea').length) {
                return;
            }
            seleccionar($(this));
        });

        $(document).on('focusin', '#tabla-items-movimientostock tbody tr.item-pedido', function () {
            if (!debeMostrar()) {
                return;
            }
            seleccionar($(this));
        });

        $(document).on('click', '#agrega_renglon', function () {
            setTimeout(function () {
                if (!debeMostrar()) {
                    return;
                }
                ensureAllUids();
                var $last = filasItems().last();
                if ($last.length) {
                    seleccionar($last);
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
    });

    window.msSurmarEtiquetasToggle = togglePanel;
})(jQuery);
