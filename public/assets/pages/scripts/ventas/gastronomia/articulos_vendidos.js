(function () {
    'use strict';

    var cfg = window.ARTICULOS_VENDIDOS_GASTRONOMIA || {};

    function esc(s) {
        if (s == null) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function urlFacturaDetalle(ventaId, articuloId) {
        var base = cfg.urlFacturaVerBase || '';
        if (!base || ventaId <= 0) {
            return '';
        }
        var url = base.replace(/\/$/, '') + '/' + ventaId + '/ver?articulo_id=' + articuloId + '#tab-detalle';
        if (window.ModoConsulta && typeof window.ModoConsulta.url === 'function') {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function urlFormulaEditar(formulaId) {
        if (window.FormulaArticuloAccion && typeof window.FormulaArticuloAccion.urlEditar === 'function') {
            return window.FormulaArticuloAccion.urlEditar(formulaId);
        }
        var base = (cfg.urlFormulaBase || '').replace(/\/$/, '');
        if (!base || formulaId <= 0) {
            return '';
        }
        var url = base + '/' + formulaId + '/editar?origen=modal_consulta';
        if (window.ModoConsulta && typeof window.ModoConsulta.url === 'function') {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function actualizarLinkFormulaModal(data) {
        var linkFormula = document.getElementById('modal-av-link-formula');
        if (!linkFormula || !cfg.puedeVerFormula) {
            return;
        }

        var urlFormula = data.url_formula || '';
        if (urlFormula && window.ModoConsulta && typeof window.ModoConsulta.url === 'function') {
            urlFormula = window.ModoConsulta.url(urlFormula);
        }

        if (urlFormula) {
            linkFormula.href = urlFormula;
            linkFormula.classList.remove('d-none');
            linkFormula.title = data.formula_id ? 'Fórmula ERP #' + data.formula_id : 'Ver fórmula del artículo';
            return;
        }

        linkFormula.classList.add('d-none');
        linkFormula.href = '#';
        linkFormula.removeAttribute('title');
    }

    function paramsFacturas(btn) {
        var p = Object.assign({}, cfg.filtrosQuery || {});
        p.articulo_id = parseInt(btn.getAttribute('data-articulo-id'), 10) || 0;
        p.sku = btn.getAttribute('data-sku') || '';

        var depId = parseInt(btn.getAttribute('data-deposito-id'), 10) || 0;
        var pvId = parseInt(btn.getAttribute('data-puntoventa-id'), 10) || 0;
        if (depId > 0) {
            p.deposito_id = depId;
        }
        if (pvId > 0) {
            p.puntoventa_id = pvId;
        }

        return p;
    }

    function urlApiFacturas(btn) {
        var articuloId = parseInt(btn.getAttribute('data-articulo-id'), 10) || 0;
        var base = (cfg.urlApiFacturasBase || '').replace(/\/$/, '');
        if (!base || articuloId <= 0) {
            return '';
        }
        var params = new URLSearchParams();
        var p = paramsFacturas(btn);
        Object.keys(p).forEach(function (k) {
            if (p[k] !== null && p[k] !== undefined && p[k] !== '') {
                params.set(k, String(p[k]));
            }
        });
        return base + '/' + articuloId + '/facturas?' + params.toString();
    }

    function pintarFacturas(data, btn) {
        var tbody = document.getElementById('modal-av-facturas-body');
        var errEl = document.getElementById('modal-av-facturas-error');
        var titulo = document.getElementById('modal-av-facturas-titulo');
        var subtitulo = document.getElementById('modal-av-facturas-subtitulo');
        var linkFd = document.getElementById('modal-av-link-facturas-dia');

        if (!tbody) {
            return;
        }

        if (errEl) {
            errEl.classList.add('d-none');
            errEl.textContent = '';
        }

        var sku = btn.getAttribute('data-sku') || '';
        if (titulo) {
            titulo.textContent = 'Comprobantes — ' + (sku || 'artículo #' + btn.getAttribute('data-articulo-id'));
        }
        if (subtitulo) {
            subtitulo.textContent = (data.facturas || []).length + ' comprobante(s) con los filtros aplicados.';
        }

        if (linkFd && data.url_facturas_dia) {
            linkFd.href = data.url_facturas_dia;
            linkFd.classList.remove('d-none');
        } else if (linkFd) {
            linkFd.classList.add('d-none');
        }

        actualizarLinkFormulaModal(data);

        var facturas = data.facturas || [];
        if (!facturas.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-muted small">Sin comprobantes para este artículo y filtros.</td></tr>';
            return;
        }

        var articuloId = parseInt(btn.getAttribute('data-articulo-id'), 10) || 0;
        var puedeVer = !!cfg.puedeVerFactura;

        tbody.innerHTML = facturas.map(function (f) {
            var detUrl = puedeVer ? urlFacturaDetalle(f.venta_id, articuloId) : '';
            var ncBadge = f.es_nota_credito
                ? ' <span class="badge badge-warning">NC</span>'
                : '';
            var btnVer = detUrl
                ? '<a href="' + esc(detUrl) + '" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary" title="Ver ítems e insumos (2.ª solapa)"><i class="fas fa-eye"></i></a>'
                : '';

            return '<tr>'
                + '<td>' + esc(f.venta_id) + '</td>'
                + '<td>' + esc(f.codigo) + ncBadge + '</td>'
                + '<td>' + esc(f.fecha_jornada || '—') + '</td>'
                + '<td>' + esc((f.fecha_comprobante || '—') + (f.hora ? ' ' + f.hora : '')) + '</td>'
                + '<td><small>' + esc(f.puntoventa_etiqueta || '—') + '</small></td>'
                + '<td><small>' + esc(f.deposito_etiqueta || '—') + '</small></td>'
                + '<td class="text-right">' + esc(Number(f.cantidad).toFixed(3)) + '</td>'
                + '<td class="text-right">' + esc(Number(f.importe).toFixed(2)) + '</td>'
                + '<td class="text-nowrap">' + btnVer + '</td>'
                + '</tr>';
        }).join('');
    }

    document.querySelectorAll('.js-av-ver-facturas').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var url = urlApiFacturas(btn);
            if (!url) {
                return;
            }

            var tbody = document.getElementById('modal-av-facturas-body');
            var errEl = document.getElementById('modal-av-facturas-error');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Cargando…</td></tr>';
            }
            if (errEl) {
                errEl.classList.add('d-none');
            }
            var linkFormula = document.getElementById('modal-av-link-formula');
            if (linkFormula) {
                linkFormula.classList.add('d-none');
                linkFormula.href = '#';
            }

            if (typeof $ !== 'undefined') {
                $('#modal-av-facturas-articulo').modal('show');
            }

            fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    return r.json().then(function (j) {
                        return { ok: r.ok, body: j };
                    });
                })
                .then(function (res) {
                    if (!res.ok || !res.body || !res.body.ok) {
                        if (tbody) {
                            tbody.innerHTML = '';
                        }
                        if (errEl) {
                            errEl.textContent = (res.body && (res.body.error || res.body.message)) || 'No se pudieron cargar los comprobantes.';
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    pintarFacturas(res.body, btn);
                })
                .catch(function () {
                    if (tbody) {
                        tbody.innerHTML = '';
                    }
                    if (errEl) {
                        errEl.textContent = 'Error de comunicación al consultar comprobantes.';
                        errEl.classList.remove('d-none');
                    }
                });
        });
    });
})();
