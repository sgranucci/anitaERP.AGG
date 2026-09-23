/**
 * Cierre de turno Facturación Local: listado de facturas por medio de pago.
 */
(function () {
    'use strict';

    function fmt(n) {
        var v = Number(n);
        if (isNaN(v)) {
            v = 0;
        }
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function abrir(url) {
        var titulo = document.getElementById('modal-fl-facturas-medio-titulo');
        var body = document.getElementById('modal-fl-facturas-medio-body');
        if (titulo) {
            titulo.textContent = 'Facturas por medio de pago';
        }
        if (body) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-3">Cargando…</td></tr>';
        }
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#modal-fl-facturas-medio').modal('show');
        }
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, body: j };
            });
        }).then(function (res) {
            if (!body) {
                return;
            }
            var j = res.body || {};
            if (!res.ok || !j.ok) {
                body.innerHTML = '<tr><td colspan="6" class="text-danger p-3">' + esc(j.error || 'No se pudo cargar el listado.') + '</td></tr>';
                return;
            }
            if (titulo && j.titulo) {
                titulo.textContent = j.titulo;
            }
            var facturas = j.facturas || [];
            if (!facturas.length) {
                body.innerHTML = '<tr><td colspan="6" class="text-muted p-3">Sin comprobantes en este medio.</td></tr>';
                return;
            }
            var html = '';
            facturas.forEach(function (f) {
                html += '<tr>';
                html += '<td>' + esc(f.codigo || '—');
                if (f.es_nota_credito) {
                    html += ' <span class="badge badge-danger">NC</span>';
                }
                if (f.es_ticket_regalo) {
                    html += ' <span class="badge badge-secondary">Regalo</span>';
                }
                html += '</td>';
                html += '<td>' + esc(f.cuando || '') + '</td>';
                html += '<td>' + esc(f.cliente || '') + '</td>';
                html += '<td class="text-right">' + fmt(f.total) + '</td>';
                html += '<td class="text-right font-weight-bold">' + fmt(f.monto_medio) + '</td>';
                html += '<td class="text-nowrap text-right">';
                if (f.url_ver) {
                    html += '<a href="' + esc(f.url_ver) + '" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Ver</a> ';
                }
                if (f.puede_cambiar_medio && f.venta_id) {
                    html += '<button type="button" class="btn btn-sm btn-outline-warning js-fd-cambiar-medio-pago" data-venta-id="' + esc(f.venta_id) + '" title="Mover el medio de pago">Mover medio</button>';
                }
                html += '</td></tr>';
            });
            body.innerHTML = html;
        }).catch(function () {
            if (body) {
                body.innerHTML = '<tr><td colspan="6" class="text-danger p-3">Error de comunicación.</td></tr>';
            }
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.js-fl-facturas-medio') : null;
        if (!btn) {
            return;
        }
        e.preventDefault();
        var url = btn.getAttribute('data-url');
        if (url) {
            abrir(url);
        }
    });
})();
