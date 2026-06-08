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

    var ultimoBtnFilaArticulo = null;

    function urlFacturaVer(ventaId, articuloId, opciones) {
        opciones = opciones || {};
        var base = cfg.urlFacturaVerBase || '';
        if (!base || ventaId <= 0) {
            return '';
        }
        var url = base.replace(/\/$/, '') + '/' + ventaId + '/ver';
        if (articuloId > 0) {
            url += '?articulo_id=' + encodeURIComponent(articuloId);
        }
        if (opciones.hash) {
            url += opciones.hash;
        }
        if (window.ModoConsulta && typeof window.ModoConsulta.url === 'function') {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function urlFacturaDetalle(ventaId, articuloId) {
        return urlFacturaVer(ventaId, articuloId, { hash: '#tab-detalle' });
    }

    function botonesVerComprobante(ventaId, articuloId) {
        if (!cfg.puedeVerFactura || ventaId <= 0) {
            return '';
        }
        var urlDetalle = urlFacturaVer(ventaId, articuloId);
        var urlItems = urlFacturaDetalle(ventaId, articuloId);
        return '<a href="' + esc(urlDetalle) + '" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="Ver comprobante">'
            + '<i class="fas fa-eye"></i></a>'
            + '<a href="' + esc(urlItems) + '" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="Ítems e insumos">'
            + '<i class="fas fa-boxes text-info"></i></a>';
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

    function paramsDesdeFila(btn) {
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

    function paramsFacturas(btn) {
        return paramsDesdeFila(btn);
    }

    function fmtCantidad(n) {
        var x = Number(n);
        if (!isFinite(x)) {
            return '—';
        }
        return x.toFixed(3);
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

    function urlApiMovimientos(btn) {
        var articuloId = parseInt(btn.getAttribute('data-articulo-id'), 10) || 0;
        var base = (cfg.urlApiFacturasBase || '').replace(/\/$/, '');
        if (!base || articuloId <= 0) {
            return '';
        }
        var params = new URLSearchParams();
        var p = paramsDesdeFila(btn);
        Object.keys(p).forEach(function (k) {
            if (p[k] !== null && p[k] !== undefined && p[k] !== '') {
                params.set(k, String(p[k]));
            }
        });
        return base + '/' + articuloId + '/movimientos?' + params.toString();
    }

    function pintarMovimientos(data, btn) {
        var tbody = document.getElementById('modal-av-movimientos-body');
        var tfoot = document.getElementById('modal-av-movimientos-foot');
        var errEl = document.getElementById('modal-av-movimientos-error');
        var titulo = document.getElementById('modal-av-movimientos-titulo');
        var subtitulo = document.getElementById('modal-av-movimientos-subtitulo');
        var notaCant = document.getElementById('modal-av-movimientos-nota-cantidad');
        var linkKardex = document.getElementById('modal-av-link-kardex');
        var totalEntrada = document.getElementById('modal-av-mov-total-entrada');
        var totalSalida = document.getElementById('modal-av-mov-total-salida');

        if (!tbody) {
            return;
        }

        if (errEl) {
            errEl.classList.add('d-none');
            errEl.textContent = '';
        }

        var sku = btn.getAttribute('data-sku') || '';
        var cantRenglon = parseFloat(btn.getAttribute('data-cantidad-total') || '0') || 0;
        if (titulo) {
            titulo.textContent = 'Movimientos de stock — ' + (sku || 'artículo #' + btn.getAttribute('data-articulo-id'));
        }

        var movimientos = data.movimientos || [];
        var tot = data.totales || {};
        if (subtitulo) {
            subtitulo.textContent = movimientos.length + ' movimiento(s) de stock con los filtros aplicados.';
        }

        if (linkKardex && data.url_kardex) {
            var urlK = data.url_kardex;
            if (window.ModoConsulta && typeof window.ModoConsulta.url === 'function') {
                urlK = window.ModoConsulta.url(urlK);
            }
            linkKardex.href = urlK;
            linkKardex.classList.remove('d-none');
        } else if (linkKardex) {
            linkKardex.classList.add('d-none');
        }

        var btnVerFacturas = document.getElementById('modal-av-link-ver-facturas');
        if (btnVerFacturas) {
            if (movimientos.length) {
                btnVerFacturas.classList.remove('d-none');
            } else {
                btnVerFacturas.classList.add('d-none');
            }
        }

        if (!movimientos.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-muted small">Sin movimientos de stock para este artículo y filtros.</td></tr>';
            if (tfoot) {
                tfoot.classList.add('d-none');
            }
            if (notaCant) {
                notaCant.textContent = 'Cantidad del renglón: ' + fmtCantidad(cantRenglon) + '.';
            }
            return;
        }

        var articuloId = parseInt(btn.getAttribute('data-articulo-id'), 10) || 0;

        tbody.innerHTML = movimientos.map(function (m) {
            var ncBadge = m.es_nota_credito
                ? ' <span class="badge badge-warning">NC</span>'
                : '';
            var acciones = botonesVerComprobante(parseInt(m.venta_id, 10) || 0, articuloId);
            return '<tr>'
                + '<td>' + esc(m.id) + '</td>'
                + '<td>' + esc(m.fecha || '—') + '</td>'
                + '<td>' + esc(m.venta_id) + '</td>'
                + '<td>' + esc(m.venta_codigo || m.concepto || '—') + ncBadge + '</td>'
                + '<td><small>' + esc(m.puntoventa_etiqueta || '—') + '</small></td>'
                + '<td><small>' + esc(m.deposito_etiqueta || '—') + '</small></td>'
                + '<td class="text-right">' + (m.entrada != null ? esc(fmtCantidad(m.entrada)) : '') + '</td>'
                + '<td class="text-right">' + (m.salida != null ? esc(fmtCantidad(m.salida)) : '') + '</td>'
                + '<td class="facturas-dia-tabla-acciones text-nowrap">' + acciones + '</td>'
                + '</tr>';
        }).join('');

        var entradaTot = Number(tot.entrada_total || 0);
        var salidaTot = Number(tot.salida_total || 0);
        var cantVenta = Number(tot.cantidad_venta != null ? tot.cantidad_venta : cantRenglon);
        var netoStock = salidaTot - entradaTot;

        if (tfoot) {
            tfoot.classList.remove('d-none');
        }
        if (totalEntrada) {
            totalEntrada.textContent = fmtCantidad(entradaTot);
        }
        if (totalSalida) {
            totalSalida.textContent = fmtCantidad(salidaTot);
        }
        if (notaCant) {
            notaCant.textContent = 'Cantidad del renglón (ventas): '
                + fmtCantidad(cantRenglon)
                + ' · Salida − entrada (stock): '
                + fmtCantidad(netoStock)
                + (Math.abs(netoStock - cantVenta) > 0.001
                    ? ' · Comprobantes (neto): ' + fmtCantidad(cantVenta)
                    : '');
        }
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
            var ncBadge = f.es_nota_credito
                ? ' <span class="badge badge-warning">NC</span>'
                : '';
            var acciones = puedeVer ? botonesVerComprobante(f.venta_id, articuloId) : '';

            return '<tr>'
                + '<td>' + esc(f.venta_id) + '</td>'
                + '<td>' + esc(f.codigo) + ncBadge + '</td>'
                + '<td>' + esc(f.fecha_jornada || '—') + '</td>'
                + '<td>' + esc((f.fecha_comprobante || '—') + (f.hora ? ' ' + f.hora : '')) + '</td>'
                + '<td><small>' + esc(f.puntoventa_etiqueta || '—') + '</small></td>'
                + '<td><small>' + esc(f.deposito_etiqueta || '—') + '</small></td>'
                + '<td class="text-right">' + esc(Number(f.cantidad).toFixed(3)) + '</td>'
                + '<td class="text-right">' + esc(Number(f.importe).toFixed(2)) + '</td>'
                + '<td class="facturas-dia-tabla-acciones text-nowrap">' + acciones + '</td>'
                + '</tr>';
        }).join('');
    }

    function abrirModalFacturasDesdeBtn(btn) {
        if (!btn) {
            return;
        }
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
    }

    document.querySelectorAll('.js-av-ver-facturas').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            ultimoBtnFilaArticulo = btn;
            abrirModalFacturasDesdeBtn(btn);
        });
    });

    var btnVerFacturasModal = document.getElementById('modal-av-link-ver-facturas');
    if (btnVerFacturasModal) {
        btnVerFacturasModal.addEventListener('click', function (e) {
            e.preventDefault();
            if (ultimoBtnFilaArticulo) {
                abrirModalFacturasDesdeBtn(ultimoBtnFilaArticulo);
            }
        });
    }

    if (cfg.puedeVerMovimientos) {
        document.querySelectorAll('.js-av-ver-movimientos').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                ultimoBtnFilaArticulo = btn;

                var url = urlApiMovimientos(btn);
                if (!url) {
                    return;
                }

                var tbody = document.getElementById('modal-av-movimientos-body');
                var errEl = document.getElementById('modal-av-movimientos-error');
                var tfoot = document.getElementById('modal-av-movimientos-foot');
                var linkKardex = document.getElementById('modal-av-link-kardex');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Cargando…</td></tr>';
                }
                if (btnVerFacturasModal) {
                    btnVerFacturasModal.classList.add('d-none');
                }
                if (tfoot) {
                    tfoot.classList.add('d-none');
                }
                if (errEl) {
                    errEl.classList.add('d-none');
                }
                if (linkKardex) {
                    linkKardex.classList.add('d-none');
                    linkKardex.href = '#';
                }

                if (typeof $ !== 'undefined') {
                    $('#modal-av-movimientos-articulo').modal('show');
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
                                errEl.textContent = (res.body && (res.body.error || res.body.message)) || 'No se pudieron cargar los movimientos.';
                                errEl.classList.remove('d-none');
                            }
                            return;
                        }
                        pintarMovimientos(res.body, btn);
                    })
                    .catch(function () {
                        if (tbody) {
                            tbody.innerHTML = '';
                        }
                        if (errEl) {
                            errEl.textContent = 'Error de comunicación al consultar movimientos.';
                            errEl.classList.remove('d-none');
                        }
                    });
            });
        });
    }
})();
