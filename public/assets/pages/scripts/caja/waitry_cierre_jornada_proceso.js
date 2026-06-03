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

    function renderCuadro(data) {
        data = data || {};
        var filas = data.cuadro_filas || [];
        var tbody = el('tbody-cuadro-cierre');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        var totales = { qr: 0, mp: 0, efectivo: 0, otros: 0, total: 0 };
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            if (fila.tipo === 'waitry_impago') {
                tr.className = 'table-secondary';
            } else if (fila.tipo === 'waitry_cash') {
                tr.className = 'table-warning';
            } else if (fila.tipo === 'waitry_pago') {
                tr.className = 'table-info';
            }
            var cols = ['qr', 'mp', 'efectivo', 'otros', 'total'];
            tr.innerHTML = '<td>' + escapeHtml(fila.etiqueta || '') + '</td>'
                + cols.map(function (c) {
                    return '<td class="text-right">' + fmtMoney(fila[c]) + '</td>';
                }).join('');
            tbody.appendChild(tr);
            if (fila.tipo !== 'waitry_impago') {
                cols.forEach(function (c) {
                    totales[c] += parseFloat(fila[c]) || 0;
                });
            }
        });
        el('cuadro-total-qr').textContent = fmtMoney(totales.qr);
        el('cuadro-total-mp').textContent = fmtMoney(totales.mp);
        el('cuadro-total-efectivo').textContent = fmtMoney(totales.efectivo);
        el('cuadro-total-otros').textContent = fmtMoney(totales.otros);
        el('cuadro-total-general').textContent = fmtMoney(data.total_cuadro || totales.total);
        el('label-total-facturacion').textContent = fmtMoney(data.total_facturacion || 0);
        el('label-pendiente-facturar').textContent = fmtMoney(data.total_pendiente_facturar || 0);
        el('label-impago-waitry').textContent = fmtMoney(data.total_impago_waitry || 0);
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

    function urlGrupoMovimientos(grupo) {
        var base = String(CFG.urlMovimientosBase || '').replace(/\/$/, '');
        return base + '/' + encodeURIComponent(grupo);
    }

    function cargarPaginaGrupo(container, grupo, pagina, params) {
        container.innerHTML = '<p class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Cargando…</p>';
        var url = urlGrupoMovimientos(grupo)
            + '?empresa_id=' + params.empresa_id
            + '&fecha_jornada=' + encodeURIComponent(params.fecha_jornada)
            + '&pagina=' + pagina + '&por_pagina=50';

        apiGet(url).then(function (data) {
            container.dataset.cargado = '1';
            var items = data.items || [];
            if (items.length === 0) {
                container.innerHTML = '<p class="text-muted small mb-0">Sin registros en este grupo.</p>';
                return;
            }
            var html = '<table class="table table-sm table-striped mb-1"><thead><tr>'
                + '<th>#W</th><th>Ref.</th><th>Total</th><th>Factura</th><th>Medio</th></tr></thead><tbody>';
            items.forEach(function (it) {
                var facturaTxt = it.venta_codigo || '—';
                if (it.es_nota_credito) {
                    facturaTxt += ' (NC)';
                }
                html += '<tr><td>' + (it.waitry_order_id || '') + '</td>'
                    + '<td>' + escapeHtml(it.display_id || '') + '</td>'
                    + '<td class="text-right">' + fmtMoney(it.total) + '</td>'
                    + '<td>' + escapeHtml(facturaTxt) + '</td>'
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
        (resumen || []).filter(function (g) {
            return (g.cantidad || 0) > 0;
        }).forEach(function (g, idx) {
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
        el('meta-ventana').textContent = meta.ventana_operativa || '—';
        el('meta-rango').textContent = meta.rango_calendario_waitry || '—';
        var cantidad = meta.cantidad_movimientos;
        el('meta-cantidad').textContent = (cantidad !== undefined && cantidad !== null)
            ? String(cantidad)
            : '—';
        el('meta-ids').textContent = meta.rango_etiqueta || '—';
        mostrar('panel-proceso-meta', true);
        renderNotas(data.notas);
        renderCuadro(data);
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
            if (data.cuadro_filas || data.grilla) {
                renderCuadro(data);
            }
        }).catch(function (e) {
            setError(e.message);
        }).finally(function () {
            mostrar('proceso-loading', false);
        });
    }

    function campoBaseDesdeId(campoId) {
        return (campoId || '').replace(/_id$/, '');
    }

    function empresaIdFormulario() {
        var empresa = el('empresa_id');
        return empresa ? parseInt(empresa.value, 10) || 0 : 0;
    }

    function limpiarCampoCuenta($campo) {
        $campo.find('.cuentacontable_id').val('');
        $campo.find('.codigocuentacontable').val('');
        $campo.find('.nombrecuentacontable').val('');
    }

    function cargarCampoCuenta($campo, cfg) {
        var campoId = $campo.data('campo-id');
        var base = campoBaseDesdeId(campoId);
        var codigo = cfg[base + '_codigo'] || '';
        $campo.find('.cuentacontable_id').val(cfg[campoId] || '');
        $campo.find('.codigocuentacontable').val(codigo);
        $campo.find('.codigo_previo').val(codigo);
        $campo.find('.nombrecuentacontable').val(cfg[base + '_nombre'] || '');
    }

    function syncEmpresaEnFilasConfig() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        var emp = empresaIdFormulario();
        window.jQuery('.cfg-cuenta-campo .empresa').val(emp > 0 ? String(emp) : '');
    }

    function cargarConfigEnModal(cfg) {
        cfg = cfg || {};
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        syncEmpresaEnFilasConfig();
        window.jQuery('.cfg-cuenta-campo').each(function () {
            cargarCampoCuenta(window.jQuery(this), cfg);
        });
    }

    function initConsultaCuentasConfig() {
        if (typeof window.jQuery === 'undefined' || typeof activa_eventos_consulta_cuentacontable !== 'function') {
            return;
        }
        var $ = window.jQuery;

        syncEmpresaEnFilasConfig();
        activa_eventos_consulta_cuentacontable();

        // Filas fijas de config: no borrar el <tr> ni invocar leeCentroCosto (patrón artículo es dinámico).
        $('.cfg-cuenta-campo .codigocuentacontable').off('change').on('change', function (event) {
            event.preventDefault();
            var $campo = $(this).closest('.cfg-cuenta-campo');
            var codigo = ($(this).val() || '').trim();
            var empresaId = empresaIdFormulario();
            $campo.find('.empresa').val(empresaId > 0 ? String(empresaId) : '');

            if (!codigo) {
                limpiarCampoCuenta($campo);
                return;
            }
            if (empresaId <= 0) {
                alert('Seleccione empresa.');
                limpiarCampoCuenta($campo);
                return;
            }
            $.get(
                carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresaId + '/' + encodeURIComponent(codigo),
                function (data) {
                    if (data && data.id > 0) {
                        $campo.find('.cuentacontable_id').val(data.id);
                        $campo.find('.cuentacontable_id_previa').val(data.id);
                        $campo.find('.nombrecuentacontable').val(data.nombre || '');
                        $campo.find('.codigo_previo').val(codigo);
                    } else {
                        alert('No existe la cuenta para la empresa seleccionada.');
                        limpiarCampoCuenta($campo);
                    }
                },
            );
        });

        $('#modal-config-contable').on('shown.bs.modal', syncEmpresaEnFilasConfig);

        $('#empresa_id').on('change', function () {
            syncEmpresaEnFilasConfig();
            $('.cfg-cuenta-campo').each(function () {
                limpiarCampoCuenta($(this));
            });
        });
    }

    function leerConfigDesdeModal() {
        var payload = {};
        if (typeof window.jQuery === 'undefined') {
            return payload;
        }
        window.jQuery('.cfg-cuenta-campo').each(function () {
            var campoId = window.jQuery(this).data('campo-id');
            payload[campoId] = window.jQuery(this).find('.cuentacontable_id').val() || '';
        });
        return payload;
    }

    function initConfig() {
        initConsultaCuentasConfig();
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
                    alert('Seleccione empresa.');
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
                var payload = leerConfigDesdeModal();
                apiPost((CFG.urlConfigGuardarBase || '').replace('__EMPRESA_ID__', p.empresa_id), payload).then(function (r) {
                    if (r && r.config) {
                        CFG.configInicial = r.config;
                        cargarConfigEnModal(r.config);
                    }
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
