(function () {
    'use strict';

    var CFG = window.WAITRY_CIERRE_JORNADA_PROCESO || {};

    function el(id) {
        return document.getElementById(id);
    }

    function empresaYFechaDesdeFormulario() {
        var empresa = el('empresa_id');
        var fecha = el('fecha_jornada');
        return {
            empresa_id: empresa ? parseInt(empresa.value, 10) || 0 : 0,
            fecha_jornada: fecha ? (fecha.value || '') : '',
        };
    }

    function fmtMoney(n) {
        var x = parseFloat(n);
        if (isNaN(x)) {
            return '$ 0,00';
        }
        return '$ ' + x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function mostrar(id, visible) {
        var node = el(id);
        if (!node) {
            return;
        }
        node.classList[(visible ? 'remove' : 'add')]('d-none');
    }

    function setError(msg) {
        var box = el('proceso-error');
        if (!box) {
            return;
        }
        if (!msg) {
            box.classList.add('d-none');
            box.textContent = '';
            return;
        }
        box.textContent = msg;
        box.classList.remove('d-none');
    }

    function apiGet(url) {
        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) {
                    throw new Error(data.error || 'Error en la consulta.');
                }
                return data;
            });
        });
    }

    function apiPost(url, body) {
        var fd = new FormData();
        fd.append('_token', CFG.csrf || '');
        Object.keys(body || {}).forEach(function (k) {
            fd.append(k, body[k]);
        });
        return fetch(url, {
            method: 'POST',
            body: fd,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) {
                    throw new Error(data.error || 'Error en la operación.');
                }
                return data;
            });
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function renderGrilla(grilla, totalFacturacion) {
        el('celda-qr-sin').textContent = fmtMoney(grilla.qr_sin_facturar);
        el('celda-qr-fact').textContent = fmtMoney(grilla.qr_facturado_anita);
        el('celda-mp-fact').textContent = fmtMoney(grilla.mp_facturado_anita);
        el('celda-efe-fact').textContent = fmtMoney(grilla.efectivo_facturado_anita);
        el('celda-total-fact').textContent = fmtMoney(totalFacturacion);
    }

    function renderNotas(notas) {
        var ul = el('lista-notas');
        if (!ul) {
            return;
        }
        ul.innerHTML = '';
        (notas || []).forEach(function (n) {
            var li = document.createElement('li');
            li.textContent = n;
            ul.appendChild(li);
        });
        mostrar('panel-proceso-notas', (notas || []).length > 0);
    }

    function cargarPaginaGrupo(container, grupo, pagina, params) {
        container.innerHTML = '<p class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Cargando…</p>';
        var url = (CFG.urlMovimientosBase || '') + encodeURIComponent(grupo)
            + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada)
            + '&pagina=' + pagina + '&por_pagina=50';

        apiGet(url).then(function (data) {
            container.dataset.cargado = '1';
            var html = '<table class="table table-sm table-striped mb-1"><thead><tr>'
                + '<th>#W</th><th>Ref.</th><th>Total</th><th>Factura</th><th>Medio</th></tr></thead><tbody>';
            (data.items || []).forEach(function (it) {
                html += '<tr><td>' + (it.waitry_order_id || '') + '</td>'
                    + '<td>' + escapeHtml(it.display_id) + '</td>'
                    + '<td class="text-right">' + fmtMoney(it.total) + '</td>'
                    + '<td>' + escapeHtml(it.venta_codigo || '—') + '</td>'
                    + '<td>' + escapeHtml(it.waitry_medio_label || '') + '</td></tr>';
            });
            html += '</tbody></table><div class="small text-muted">Pág. ' + data.pagina + '/' + data.total_paginas
                + ' (' + data.total + ')</div>';
            if (data.pagina > 1) {
                html += ' <button type="button" class="btn btn-xs btn-outline-secondary js-pag-grupo" data-pag="'
                    + (data.pagina - 1) + '">Ant.</button>';
            }
            if (data.pagina < data.total_paginas) {
                html += ' <button type="button" class="btn btn-xs btn-outline-secondary js-pag-grupo" data-pag="'
                    + (data.pagina + 1) + '">Sig.</button>';
            }
            container.innerHTML = html;
            container.querySelectorAll('.js-pag-grupo').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    cargarPaginaGrupo(container, grupo, parseInt(btn.dataset.pag, 10) || 1, params);
                });
            });
        }).catch(function (e) {
            container.innerHTML = '<p class="text-danger small">' + escapeHtml(e.message) + '</p>';
        });
    }

    function renderGrupos(resumen, params) {
        var cont = el('acordeon-grupos');
        if (!cont) {
            return;
        }
        cont.innerHTML = '';
        (resumen || []).forEach(function (g, idx) {
            var bodyId = 'grp-body-' + idx;
            var card = document.createElement('div');
            card.className = 'card mb-1';
            card.innerHTML =
                '<div class="card-header p-1">'
                + '<button class="btn btn-link btn-sm btn-block text-left collapsed" type="button" '
                + 'data-toggle="collapse" data-target="#' + bodyId + '">'
                + escapeHtml(g.titulo) + ' <span class="badge badge-secondary">' + g.cantidad + '</span>'
                + ' <span class="float-right">' + fmtMoney(g.total) + '</span></button></div>'
                + '<div id="' + bodyId + '" class="collapse" data-parent="#acordeon-grupos">'
                + '<div class="card-body p-2"><div class="grupo-detalle" data-grupo="' + g.clave + '">'
                + '<p class="text-muted small mb-0">Expandir…</p></div></div></div>';
            cont.appendChild(card);
        });
        cont.querySelectorAll('.collapse').forEach(function (collapseEl) {
            collapseEl.addEventListener('shown.bs.collapse', function () {
                var grupo = collapseEl.querySelector('.grupo-detalle');
                if (grupo && !grupo.dataset.cargado) {
                    cargarPaginaGrupo(grupo, grupo.dataset.grupo, 1, params);
                }
            });
        });
    }

    function renderAsientos(preview) {
        if (!preview) {
            mostrar('panel-proceso-asientos', false);
            return;
        }
        var adv = el('asientos-advertencias');
        adv.innerHTML = '';
        (preview.advertencias || []).forEach(function (a) {
            var d = document.createElement('div');
            d.className = 'alert alert-warning py-1 mb-1 small';
            d.textContent = a;
            adv.appendChild(d);
        });
        el('asientos-debe').textContent = fmtMoney(preview.resumen_debe);
        el('asientos-haber').textContent = fmtMoney(preview.resumen_haber);
        var lista = el('lista-asientos');
        lista.innerHTML = '';
        (preview.asientos || []).forEach(function (as) {
            var box = document.createElement('div');
            box.className = 'card mb-1';
            var tbl = '<table class="table table-sm mb-0"><tbody>';
            (as.lineas || []).forEach(function (ln) {
                if (ln.tipo === 'info') {
                    return;
                }
                tbl += '<tr><td>' + (ln.cuenta_id || '') + '</td><td class="small">' + escapeHtml(ln.concepto)
                    + '</td><td class="text-right">' + fmtMoney(ln.debe) + '</td><td class="text-right">'
                    + fmtMoney(ln.haber) + '</td></tr>';
            });
            tbl += '</tbody></table>';
            box.innerHTML = '<div class="card-header py-1 small"><strong>' + escapeHtml(as.titulo) + '</strong> '
                + escapeHtml(as.venta_codigo || '') + ' ' + fmtMoney(as.total) + '</div>'
                + '<div class="card-body p-0">' + tbl + '</div>';
            lista.appendChild(box);
        });
        mostrar('panel-proceso-asientos', (preview.asientos || []).length > 0);
    }

    function aplicarAnalisis(data, params) {
        setError('');
        var meta = data.meta || {};
        el('meta-ventana').textContent = meta.ventana_operativa || '';
        el('meta-rango').textContent = meta.rango_etiqueta || '';
        mostrar('panel-proceso-meta', true);
        renderNotas(data.notas);
        renderGrilla(data.grilla || {}, data.total_facturacion || 0);
        mostrar('panel-proceso-grilla', true);
        renderGrupos(data.grupos_resumen || [], params);
        mostrar('panel-proceso-grupos', true);
        mostrar('panel-proceso-asientos', false);
    }

    function analizar() {
        var params = empresaYFechaDesdeFormulario();
        if (params.empresa_id <= 0 || !params.fecha_jornada) {
            setError('Seleccione empresa y fecha de jornada y pulse Consultar.');
            return;
        }
        setError('');
        mostrar('proceso-loading', true);
        var url = (CFG.urlAnalizar || '') + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada);
        apiGet(url).then(function (data) {
            aplicarAnalisis(data, params);
        }).catch(function (e) {
            setError(e.message);
        }).finally(function () {
            mostrar('proceso-loading', false);
        });
    }

    function recalcular() {
        var params = empresaYFechaDesdeFormulario();
        var pct = parseFloat((el('input-porcentaje') || {}).value, 10);
        if (isNaN(pct) || pct < 0) {
            pct = 0;
        }
        setError('');
        mostrar('proceso-loading', true);
        apiPost(CFG.urlRecalcular || '', {
            empresa_id: params.empresa_id,
            fecha_jornada: params.fecha_jornada,
            porcentaje: pct,
        }).then(function (data) {
            el('label-objetivo-importe').textContent = 'Objetivo: ' + fmtMoney(data.objetivo_importe)
                + ' (' + data.porcentaje + '%)';
            renderAsientos(data.preview_asientos);
        }).catch(function (e) {
            setError(e.message);
        }).finally(function () {
            mostrar('proceso-loading', false);
        });
    }

    function cargarConfigEnModal(cfg) {
        cfg = cfg || {};
        el('cfg_cuenta_ventas_id').value = cfg.cuenta_ventas_id || '';
        el('cfg_cuenta_iva_id').value = cfg.cuenta_iva_id || '';
        el('cfg_cuenta_impuesto_interno_id').value = cfg.cuenta_impuesto_interno_id || '';
        el('cfg_cuenta_fondo_fijo_maquinas_id').value = cfg.cuenta_fondo_fijo_maquinas_id || '';
    }

    function initConfig() {
        cargarConfigEnModal(CFG.configInicial);
        var btnCfg = el('btn-config-contable');
        if (btnCfg) {
            btnCfg.addEventListener('click', function () {
                var p = empresaYFechaDesdeFormulario();
                if (p.empresa_id > 0) {
                    apiGet((CFG.urlConfigBase || '').replace('__EMPRESA_ID__', p.empresa_id)).then(function (r) {
                        cargarConfigEnModal(r.config);
                        $('#modal-config-contable').modal('show');
                    }).catch(function (e) {
                        alert(e.message);
                    });
                } else {
                    $('#modal-config-contable').modal('show');
                }
            });
        }
        var form = el('form-config-contable');
        if (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var p = empresaYFechaDesdeFormulario();
                if (p.empresa_id <= 0) {
                    alert('Seleccione empresa.');
                    return;
                }
                apiPost((CFG.urlConfigGuardarBase || '').replace('__EMPRESA_ID__', p.empresa_id), {
                    cuenta_ventas_id: el('cfg_cuenta_ventas_id').value,
                    cuenta_iva_id: el('cfg_cuenta_iva_id').value,
                    cuenta_impuesto_interno_id: el('cfg_cuenta_impuesto_interno_id').value,
                    cuenta_fondo_fijo_maquinas_id: el('cfg_cuenta_fondo_fijo_maquinas_id').value,
                }).then(function () {
                    $('#modal-config-contable').modal('hide');
                }).catch(function (e) {
                    alert(e.message);
                });
            });
        }
    }

    function init() {
        if (!CFG.puedeProceso) {
            return;
        }
        var btnAnalizar = el('btn-proceso-analizar');
        if (btnAnalizar) {
            btnAnalizar.addEventListener('click', analizar);
        }
        var btnRecalc = el('btn-proceso-recalcular');
        if (btnRecalc) {
            btnRecalc.addEventListener('click', recalcular);
        }
        initConfig();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
