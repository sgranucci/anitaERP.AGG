(function (window, $) {
    'use strict';

    function esc(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    function fmtValor(v) {
        if (v === null || v === undefined) return '—';
        if (typeof v === 'boolean') return v ? 'verdadero' : 'falso';
        if (typeof v === 'number') {
            if (Math.floor(v) === v && Math.abs(v) < 1e15) {
                return String(v);
            }
            return Number(v).toLocaleString('es-AR', { maximumFractionDigits: 4 });
        }
        return String(v);
    }

    function tipoClass(t) {
        return ({
            var: 'text-primary',
            call: 'text-info',
            bin: 'text-dark',
            un: 'text-dark',
            ter: 'text-warning'
        })[t] || 'text-muted';
    }

    function renderNodo(nodo) {
        var html = '<li>';
        html += '<code class="' + tipoClass(nodo.tipo) + '">' + esc(nodo.expr) + '</code>';
        html += ' <span class="text-muted">=</span> <strong>' + esc(fmtValor(nodo.valor)) + '</strong>';
        if (nodo.detalle) {
            html += ' <span class="badge badge-light text-muted">' + esc(nodo.detalle) + '</span>';
        }
        if (nodo.hijos && nodo.hijos.length) {
            html += '<ul>';
            nodo.hijos.forEach(function (h) { html += renderNodo(h); });
            html += '</ul>';
        }
        html += '</li>';
        return html;
    }

    function renderArbol(nodos) {
        if (!nodos || !nodos.length) {
            return '<p class="text-muted small mb-0">Sin rastro (fórmula vacía o no evaluada).</p>';
        }
        var html = '<div class="rastro-tree"><ul>';
        nodos.forEach(function (n) { html += renderNodo(n); });
        html += '</ul></div>';
        return html;
    }

    function renderKv(obj, limit) {
        limit = limit || 80;
        if (!obj || typeof obj !== 'object') {
            return '<span class="text-muted">—</span>';
        }
        var keys = Object.keys(obj);
        if (!keys.length) return '<span class="text-muted">vacío</span>';
        var html = '<div class="formula-dbg-kv">';
        keys.slice(0, limit).forEach(function (k) {
            html += '<span class="badge badge-light border mr-1 mb-1"><code>' + esc(k) + '</code>: '
                + esc(fmtValor(obj[k])) + '</span>';
        });
        if (keys.length > limit) {
            html += '<span class="text-muted small">… +' + (keys.length - limit) + '</span>';
        }
        html += '</div>';
        return html;
    }

    function renderPaso(p, idx) {
        var id = 'dbg-paso-' + idx + '-' + (p.codigo || 'x');
        var err = p.error ? ' border-danger' : '';
        var html = '<div class="card mb-2' + err + '">';
        html += '<div class="card-header py-2 d-flex justify-content-between align-items-center" '
            + 'data-toggle="collapse" data-target="#' + id + '" style="cursor:pointer;">';
        html += '<span><span class="badge badge-secondary">' + esc(p.codigo) + '</span> '
            + '<strong>' + esc(p.descripcion) + '</strong> '
            + (p.origen_label ? '<span class="badge badge-info ml-1">' + esc(p.origen_label) + '</span>' : '')
            + (p.en_set === false ? '<span class="badge badge-warning ml-1">fuera set</span>' : '')
            + '</span>';
        html += '<span>';
        if (p.error) {
            html += '<span class="badge badge-danger">error</span> ';
        }
        html += 'Importe: <strong>$ ' + esc(fmtValor(p.importe)) + '</strong> <i class="fa fa-chevron-down ml-1"></i></span>';
        html += '</div>';
        html += '<div class="collapse' + (p.error || (p.codigo && idx === 0) ? ' show' : '') + '" id="' + id + '">';
        html += '<div class="card-body py-2">';
        if (p.aviso) {
            html += '<div class="alert alert-warning py-1 px-2 small">' + esc(p.aviso) + '</div>';
        }
        if (p.error) {
            html += '<div class="alert alert-danger py-1 px-2 small">' + esc(p.error) + '</div>';
        }
        if (p.formula_cantidad) {
            html += '<div class="mb-1"><small class="text-muted">Cantidad:</small> <code>' + esc(p.formula_cantidad) + '</code>'
                + ' → <strong>' + esc(fmtValor(p.cantidad)) + '</strong></div>';
            if (p.rastro_cantidad && p.rastro_cantidad.length) {
                html += renderArbol(p.rastro_cantidad);
            }
        }
        if (p.formula_valor) {
            html += '<div class="mb-1 mt-2"><small class="text-muted">Valor:</small> <code>' + esc(p.formula_valor) + '</code>'
                + ' → <strong>' + esc(fmtValor(p.valor)) + '</strong></div>';
            if (p.rastro_valor && p.rastro_valor.length) {
                html += renderArbol(p.rastro_valor);
            }
        }
        html += '<div class="mb-1 mt-2"><small class="text-muted">Importe:</small> <code>'
            + esc(p.formula || '(cantidad × valor)') + '</code></div>';
        html += renderArbol(p.rastro);
        if (p.rastro_texto) {
            html += '<pre class="small bg-light border p-2 mt-2 mb-0" style="max-height:160px;overflow:auto;">'
                + esc(p.rastro_texto) + '</pre>';
        }
        if (p.acumuladores) {
            html += '<div class="mt-2"><small class="text-muted">Acumuladores:</small> '
                + renderKv(p.acumuladores, 40) + '</div>';
        }
        html += '</div></div></div>';
        return html;
    }

    function pintarResultado($host, resp) {
        if (!resp) {
            $host.html('<div class="alert alert-warning">Sin respuesta.</div>');
            return;
        }
        var html = '';
        var emp = resp.empleado || {};
        html += '<div class="small text-muted mb-2">'
            + esc(resp.liquidacion_label || '')
            + ' · Período ' + esc(resp.periodo)
            + ' · ' + esc(resp.tipo)
            + (emp.legajo ? ' · Legajo ' + esc(emp.legajo) + ' ' + esc(emp.nombre) : '')
            + '</div>';
        if (resp.set_efectivo) {
            html += '<div class="small mb-2">Set: <strong>'
                + esc(resp.set_efectivo.modo_label || resp.set_efectivo.modo || '')
                + '</strong> · ' + esc(resp.set_efectivo.cantidad_conceptos) + ' conceptos</div>';
        }
        if (resp.contexto_inicial && resp.contexto_inicial.variables) {
            html += '<details class="mb-2"><summary class="small">Variables iniciales del contexto</summary>'
                + renderKv(resp.contexto_inicial.variables, 120) + '</details>';
            html += '<details class="mb-2"><summary class="small">Bases / novedades V1</summary>'
                + '<div class="mb-1"><em>Bases</em> ' + renderKv(resp.contexto_inicial.bases, 40) + '</div>'
                + '<div><em>Novedades V1</em> ' + renderKv(resp.contexto_inicial.novedades_v1, 40) + '</div>'
                + '</details>';
        }
        var pasos = resp.pasos || [];
        if (!pasos.length) {
            html += '<div class="alert alert-warning py-2">No hay pasos para mostrar.</div>';
        } else {
            pasos.forEach(function (p, i) { html += renderPaso(p, i); });
        }
        $host.html(html);
    }

    window.FormulaDebugger = {
        pintarResultado: pintarResultado,
        renderArbol: renderArbol,
        esc: esc,
        fmtValor: fmtValor
    };
})(window, jQuery);
