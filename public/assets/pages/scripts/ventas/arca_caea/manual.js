/**
 * Herramienta presentación manual CAEA (ERP → Anita fallback).
 * Se carga con el detalle del modal.
 */
(function ($) {
    'use strict';

    function token() {
        var $t = $('input[name="_token"]').first();
        return $t.length ? $t.val() : '';
    }

    function msg($box, texto, tipo) {
        $box.removeClass('text-danger text-success text-muted text-warning');
        if (tipo === 'ok') {
            $box.addClass('text-success');
        } else if (tipo === 'err') {
            $box.addClass('text-danger');
        } else if (tipo === 'warn') {
            $box.addClass('text-warning');
        } else {
            $box.addClass('text-muted');
        }
        $box.text(texto || '');
    }

    function padPv(n) {
        var s = String(n || '');
        while (s.length < 5) {
            s = '0' + s;
        }
        return s;
    }

    function formatMoney(v) {
        if (v === null || v === undefined || v === '') {
            return '—';
        }
        var n = Number(v);
        if (isNaN(n)) {
            return String(v);
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function leerForm($root) {
        return {
            pto_vta: parseInt($root.find('#arca_manual_pto').val(), 10) || 0,
            tipo_afip: parseInt($root.find('#arca_manual_tipo').val(), 10) || 0,
            numero: parseInt($root.find('#arca_manual_numero').val(), 10) || 0,
            tipo_anita: String($root.find('#arca_manual_tipo_anita').val() || '').trim(),
            letra: String($root.find('#arca_manual_letra').val() || '').trim().toUpperCase(),
        };
    }

    function cargarEnForm($root, row) {
        $root.find('#arca_manual_pto').val(row.pto_vta || '');
        $root.find('#arca_manual_tipo').val(row.tipo_afip || '');
        $root.find('#arca_manual_numero').val(row.proximo || row.numero || '');
        $root.find('#arca_manual_tipo_anita').val(row.tipo_anita || '');
        $root.find('#arca_manual_letra').val(row.letra || '');
    }

    $(document).on('click', '.js-arca-caea-manual-proximos', function () {
        var $root = $('#arca-caea-herramienta-manual');
        if (!$root.length) {
            return;
        }
        var $msg = $root.find('#arca-caea-manual-msg');
        var $wrap = $root.find('#arca-caea-manual-proximos-wrap');
        var $body = $root.find('#arca-caea-manual-proximos-body');
        msg($msg, 'Consultando ARCA / ERP / Anita…', 'info');
        $body.empty();
        $wrap.addClass('d-none');

        $.ajax({
            url: $root.data('url-proximos'),
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json',
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                msg($msg, (resp && resp.mensaje) || 'No se pudo listar pendientes.', 'err');
                return;
            }
            var rows = resp.pendientes || [];
            if (!rows.length) {
                msg($msg, 'No hay próximos pendientes detectados (ERP/Anita) para los PV CAEA.', 'warn');
                return;
            }
            rows.forEach(function (row) {
                if (row.error) {
                    $body.append(
                        $('<tr>').append(
                            $('<td>').text(padPv(row.pto_vta)),
                            $('<td>').text(row.etiqueta || ('T' + row.tipo_afip)),
                            $('<td colspan="5" class="small text-danger">').text(row.error)
                        )
                    );
                    return;
                }
                var $btn = $('<button type="button" class="btn btn-warning btn-sm js-arca-caea-manual-elegir">Elegir</button>');
                $btn.data('row', row);
                var detalle = [];
                if (row.fecha) {
                    detalle.push(row.fecha);
                }
                if (row.total != null) {
                    detalle.push('$' + formatMoney(row.total));
                }
                if (row.cliente) {
                    detalle.push(row.cliente);
                }
                $body.append(
                    $('<tr>').append(
                        $('<td>').text(padPv(row.pto_vta)),
                        $('<td>').text(row.etiqueta || ('T' + row.tipo_afip)),
                        $('<td>').text(row.ultimo_arca != null ? ('#' + row.ultimo_arca) : '—'),
                        $('<td>').text(row.proximo != null ? ('#' + row.proximo) : '—'),
                        $('<td>').text(row.fuente || '—'),
                        $('<td class="small">').text(detalle.join(' · ') || '—'),
                        $('<td>').append($btn)
                    )
                );
            });
            $wrap.removeClass('d-none');
            msg($msg, 'Se listaron ' + rows.length + ' combinación(es). Elija una o complete el formulario.', 'ok');
        }).fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al consultar pendientes.';
            msg($msg, m, 'err');
        });
    });

    $(document).on('click', '.js-arca-caea-manual-elegir', function () {
        var $root = $('#arca-caea-herramienta-manual');
        var row = $(this).data('row') || {};
        cargarEnForm($root, row);
        msg($root.find('#arca-caea-manual-msg'), 'Cargado PV ' + padPv(row.pto_vta) + ' T' + row.tipo_afip + ' #' + row.proximo + '. Puede previsualizar o presentar.', 'ok');
    });

    $(document).on('click', '.js-arca-caea-manual-preview', function () {
        var $root = $('#arca-caea-herramienta-manual');
        var $msg = $root.find('#arca-caea-manual-msg');
        var payload = leerForm($root);
        if (!payload.pto_vta || !payload.tipo_afip || !payload.numero) {
            msg($msg, 'Complete PV, tipo AFIP y número.', 'err');
            return;
        }
        msg($msg, 'Previsualizando…', 'info');
        $.ajax({
            url: $root.data('url-preview'),
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json',
            data: $.extend({ _token: token() }, payload),
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                msg($msg, (resp && resp.mensaje) || 'No se pudo previsualizar.', 'err');
                return;
            }
            var p = resp.preview || {};
            var partes = [
                resp.mensaje || '',
                'Fuente: ' + (p.fuente || '—'),
                p.fecha ? ('Fecha ' + p.fecha) : '',
                p.total != null ? ('Total $' + formatMoney(p.total)) : '',
                p.cliente ? p.cliente : '',
            ].filter(Boolean);
            msg($msg, partes.join(' · '), resp.correlativo_ok ? 'ok' : 'warn');
            if (p.tipo_anita) {
                $root.find('#arca_manual_tipo_anita').val(p.tipo_anita);
            }
            if (p.letra) {
                $root.find('#arca_manual_letra').val(p.letra);
            }
        }).fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al previsualizar.';
            msg($msg, m, 'err');
        });
    });

    $(document).on('click', '.js-arca-caea-manual-informar', function () {
        var $root = $('#arca-caea-herramienta-manual');
        var $msg = $root.find('#arca-caea-manual-msg');
        var payload = leerForm($root);
        if (!payload.pto_vta || !payload.tipo_afip || !payload.numero) {
            msg($msg, 'Complete PV, tipo AFIP y número.', 'err');
            return;
        }
        if (!confirm('¿Presentar en ARCA PV ' + padPv(payload.pto_vta) + ' T' + payload.tipo_afip + ' #' + payload.numero + '?')) {
            return;
        }
        msg($msg, 'Presentando en ARCA…', 'info');
        var $btns = $root.find('button');
        $btns.prop('disabled', true);
        $.ajax({
            url: $root.data('url-informar'),
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json',
            data: $.extend({ _token: token() }, payload),
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                msg($msg, (resp && resp.mensaje) || 'No se pudo presentar.', 'err');
                return;
            }
            msg($msg, resp.mensaje || 'Presentado OK.', 'ok');
        }).fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al presentar.';
            msg($msg, m, 'err');
        }).always(function () {
            $btns.prop('disabled', false);
        });
    });
})(jQuery);
