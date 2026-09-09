(function ($) {
    'use strict';

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function renderHistoria(rows) {
        var $tb = $('#tablaSeguimientoHistoria tbody').empty();
        if (!rows || !rows.length) {
            $tb.append('<tr><td colspan="4" class="text-center text-muted">Sin movimientos de sector.</td></tr>');
            return;
        }
        rows.forEach(function (r) {
            var sec = (r.sector_legajocompras && r.sector_legajocompras.nombre) ? r.sector_legajocompras.nombre : '';
            var usr = (r.usuarios && r.usuarios.nombre) ? r.usuarios.nombre : '';
            var f = r.fecha ? String(r.fecha).replace('T', ' ').substring(0, 19) : '';
            $tb.append(
                '<tr><td>' + esc(f) + '</td><td>' + esc(sec) + '</td><td>' + esc(r.observacion || '') +
                '</td><td>' + esc(usr) + '</td></tr>'
            );
        });
    }

    function renderResumen(paquete) {
        var $box = $('#seguimientoFichaResumen').empty();
        if (!paquete) {
            $box.append('<p class="text-muted mb-0">Sin datos del paquete.</p>');
            return;
        }
        var facs = (paquete.facturas || []).map(function (f) { return f.etiqueta || ('#' + f.id); });
        var coms = (paquete.coms || []).map(function (c) { return c.documento || ('#' + c.id); });
        var cps = (paquete.comprobantes || []).map(function (c) { return c.etiqueta || ('#' + c.id); });
        var html = '<dl class="row mb-0 small">';
        html += '<dt class="col-sm-3">OC</dt><dd class="col-sm-9">' + esc(paquete.numero || '') +
            (paquete.es_anticipada ? ' <span class="badge badge-warning">Anticipado</span>' : '') + '</dd>';
        html += '<dt class="col-sm-3">Facturas PDF</dt><dd class="col-sm-9">' + esc(facs.length ? facs.join(' · ') : '—') + '</dd>';
        html += '<dt class="col-sm-3">COM</dt><dd class="col-sm-9">' + esc(coms.length ? coms.join(' · ') : '—') + '</dd>';
        html += '<dt class="col-sm-3">Cargadas CxP</dt><dd class="col-sm-9">' + esc(cps.length ? cps.join(' · ') : '—') + '</dd>';
        html += '</dl>';
        $box.html(html);
    }

    $(function () {
        $('.js-seguimiento-ficha').on('click', function () {
            var numero = $(this).data('numero') || '';
            $('#modalSeguimientoFicha .modal-title').text('Ficha del legajo OC ' + numero);
            $('#seguimientoFichaResumen').html('<p class="text-muted mb-0">Cargando…</p>');
            $('#tablaSeguimientoHistoria tbody').html('<tr><td colspan="4" class="text-center text-muted">Cargando…</td></tr>');
            $('#modalSeguimientoFicha').modal('show');
            $.get($(this).data('url')).done(function (resp) {
                renderResumen(resp && resp.paquete);
                renderHistoria((resp && resp.historia) || []);
            }).fail(function () {
                $('#seguimientoFichaResumen').html('<p class="text-danger mb-0">No se pudo leer la ficha.</p>');
                $('#tablaSeguimientoHistoria tbody').html('<tr><td colspan="4" class="text-center text-danger">Error</td></tr>');
            });
        });
    });
})(jQuery);
