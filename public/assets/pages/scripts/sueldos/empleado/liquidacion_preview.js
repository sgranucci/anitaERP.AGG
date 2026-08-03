(function ($) {
    'use strict';

    var ultimaResp = null;

    function fmt(n) {
        var x = Number(n) || 0;
        return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    function panel() {
        return $('#liquidacion-preview-panel');
    }

    function pintarExcluidos(resp) {
        var set = (resp && resp.set_efectivo) || {};
        var excluidos = set.excluidos || [];
        $('#preview-excluidos-count').text(excluidos.length ? ' (' + excluidos.length + ')' : '');
        var $tb = $('#tbody-preview-excluidos');
        if (!excluidos.length) {
            $tb.html('<tr><td colspan="3" class="text-muted text-center small">Ningún candidato excluido por elegibilidad/explícito.</td></tr>');
            return;
        }
        var html = '';
        excluidos.forEach(function (e) {
            html += '<tr>' +
                '<td>' + esc(e.codigo) + '</td>' +
                '<td>' + esc(e.descripcion) + '</td>' +
                '<td class="small">' + esc(e.motivo) + '</td>' +
                '</tr>';
        });
        $tb.html(html);
    }

    function pintar(resp) {
        ultimaResp = resp || {};
        var solo = $('#preview-solo-con-importe').is(':checked');
        var lineas = ultimaResp.lineas || [];
        if (solo) {
            lineas = lineas.filter(function (l) {
                return Math.abs(Number(l.importe) || 0) > 0.00005;
            });
        }

        var $tb = $('#tbody-preview-conceptos');
        if (!lineas.length) {
            $tb.html('<tr><td colspan="5" class="text-center text-muted py-3">Ningún concepto con importe para este filtro.</td></tr>');
        } else {
            var html = '';
            lineas.forEach(function (l) {
                var tipo = l.tipo || '';
                var badge = 'secondary';
                if (l.columna === 'haber') badge = 'success';
                else if (l.columna === 'descuento') badge = 'danger';
                else if (tipo === 'contribucion') badge = 'info';
                var origenLabel = l.origen_label || l.origen || '—';
                var origenBadge = l.origen_badge || 'secondary';
                html += '<tr>' +
                    '<td>' + esc(l.codigo) + '</td>' +
                    '<td>' + esc(l.descripcion) +
                    (l.leyenda ? '<div class="small text-muted">' + esc(l.leyenda) + '</div>' : '') +
                    '</td>' +
                    '<td><span class="badge badge-' + esc(origenBadge) + '">' + esc(origenLabel) + '</span></td>' +
                    '<td><span class="badge badge-' + badge + '">' + esc(tipo) + '</span></td>' +
                    '<td class="text-right">' + fmt(l.importe) + '</td>' +
                    '</tr>';
            });
            $tb.html(html);
        }

        var t = ultimaResp.totales || {};
        $('#preview-totales').show();
        $('#preview-tot-haber').text('$ ' + fmt(t.haber));
        $('#preview-tot-descuento').text('$ ' + fmt(t.descuento));
        $('#preview-tot-neto').text('$ ' + fmt(t.neto));

        var set = ultimaResp.set_efectivo || {};
        var modoTxt = set.modo_label || set.modo || '';
        $('#preview-meta').text(
            (ultimaResp.liquidacion_label || '') +
            ' · Período ' + (ultimaResp.periodo || '') +
            ' · ' + (ultimaResp.tipo || '') +
            (modoTxt ? ' · ' + modoTxt : '') +
            ' · ' + (t.cantidad || 0) + ' líneas en recibo' +
            (set.cantidad_excluidos ? ' · ' + set.cantidad_excluidos + ' excluidos del set' : '')
        );

        pintarExcluidos(ultimaResp);

        var errs = ultimaResp.errores || [];
        var $err = $('#preview-errores');
        if (errs.length) {
            $err.removeClass('d-none').html(
                '<div class="alert alert-warning py-1 px-2 small mb-2">' +
                errs.map(function (e) { return esc(e); }).join('<br>') +
                '</div>'
            );
        } else {
            $err.addClass('d-none').empty();
        }
    }

    function simular() {
        var $p = panel();
        var url = $p.data('url');
        if (!url) {
            return;
        }
        var $btn = $('#btn-simular-liquidacion');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $('#tbody-preview-conceptos').html(
            '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Calculando…</td></tr>'
        );

        $.get(url, {
            periodo: $('#preview-periodo').val(),
            tipo: $('#preview-tipo').val()
        }).done(function (resp) {
            pintar(resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errores || [])[0])) ||
                'No se pudo simular la liquidación.';
            $('#tbody-preview-conceptos').html(
                '<tr><td colspan="5" class="text-center text-danger py-3"></td></tr>'
            );
            $('#tbody-preview-conceptos td').text(msg);
            $('#preview-errores').removeClass('d-none').html(
                '<div class="alert alert-danger py-1 px-2 small mb-2"></div>'
            ).find('div').text(msg);
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-calculator"></i> Simular');
        });
    }

    function depurar() {
        var $p = panel();
        var url = $p.data('url-debug');
        if (!url || !window.FormulaDebugger) {
            return;
        }
        $('#preview-debug-filtros').show();
        $('#preview-debug-wrap').removeClass('d-none');
        var $host = $('#preview-debug-host');
        $host.html('<div class="text-muted small py-3 text-center"><i class="fa fa-spinner fa-spin"></i> Depurando…</div>');
        var params = {
            periodo: $('#preview-periodo').val(),
            tipo: $('#preview-tipo').val()
        };
        var cod = $('#preview-debug-concepto').val();
        if (cod) {
            params.concepto_codigo = cod;
        }
        $.get(url, params).done(function (resp) {
            window.FormulaDebugger.pintarResultado($host, resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo depurar.';
            $host.html('<div class="alert alert-danger py-2 small"></div>').find('div').text(msg);
        });
    }

    $(document).on('click', '#btn-simular-liquidacion', simular);
    $(document).on('click', '#btn-depurar-formulas', depurar);
    $(document).on('click', '#btn-cerrar-debug', function () {
        $('#preview-debug-wrap').addClass('d-none');
        $('#preview-debug-filtros').hide();
    });

    $(document).on('change', '#preview-solo-con-importe', function () {
        if (ultimaResp) {
            pintar(ultimaResp);
        }
    });

    $(document).on('shown.bs.tab', 'a[href="#tab-bases"]', function () {
        if (!ultimaResp && panel().length) {
            simular();
        }
    });
})(jQuery);
