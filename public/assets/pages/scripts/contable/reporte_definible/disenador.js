/**
 * Diseñador de reportes contables definibles — árbol FSV + cuentas.
 */
(function () {
    'use strict';

    var cfg = window.rdConfig || {};
    var estructura = cfg.estructura || [];
    var seleccionadoId = cfg.rubroInicial || null;
    var cuentasCache = [];

    function $(id) { return document.getElementById(id); }

    function urlRubro(tpl, rid) {
        return String(tpl).replace('__RID__', String(rid));
    }
    function urlCuenta(tpl, cid) {
        return String(tpl).replace('__CID__', String(cid));
    }

    function csrfHeaders() {
        return {
            'X-CSRF-TOKEN': cfg.csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
    }

    function renderTree() {
        var root = $('rd-tree');
        if (!root) return;
        if (!estructura.length) {
            root.innerHTML = '<div class="rd-empty">Sin rubros aún. Use «+ Rubro» para empezar la estructura.</div>';
            return;
        }
        var html = '';
        estructura.forEach(function (row) {
            var active = seleccionadoId && Number(seleccionadoId) === Number(row.id) ? ' active' : '';
            var indentPx = (row.depth || 0) * 18;
            var negrita = row.estilo_negrita ? ' negrita' : '';
            html += '<div class="rd-tree-item' + active + '" data-id="' + row.id + '">' +
                '<span class="rd-indent" style="width:' + indentPx + 'px"></span>' +
                '<span class="rd-badge-tipo ' + (row.tipo || '') + '">' + (row.tipo || '') + '</span>' +
                '<span class="rd-nombre' + negrita + '">' +
                    (row.codigo_linea ? '<span class="text-muted">' + esc(row.codigo_linea) + '</span> ' : '') +
                    esc(row.nombre) +
                '</span>' +
                '<span class="rd-meta">' +
                    (row.cuentas_count ? row.cuentas_count + ' cta' : '') +
                    (row.hijos_count ? (row.cuentas_count ? ' · ' : '') + row.hijos_count + ' hijos' : '') +
                '</span>' +
            '</div>';
        });
        root.innerHTML = html;
        root.querySelectorAll('.rd-tree-item').forEach(function (el) {
            el.addEventListener('click', function () {
                seleccionar(Number(el.getAttribute('data-id')));
            });
        });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function findRubro(id) {
        for (var i = 0; i < estructura.length; i++) {
            if (Number(estructura[i].id) === Number(id)) return estructura[i];
        }
        return null;
    }

    function seleccionar(id) {
        seleccionadoId = id;
        renderTree();
        var row = findRubro(id);
        var vacio = $('rd-rubro-vacio');
        var wrap = $('rd-rubro-form-wrap');
        if (!row) {
            if (vacio) vacio.classList.remove('d-none');
            if (wrap) wrap.classList.add('d-none');
            renderCuentas([]);
            return;
        }
        if (vacio) vacio.classList.add('d-none');
        if (wrap) wrap.classList.remove('d-none');
        $('rd-rubro-id').value = row.id;
        $('rd-codigo-linea').value = row.codigo_linea || '';
        $('rd-nombre').value = row.nombre || '';
        $('rd-tipo').value = row.tipo || 'cuentas';
        $('rd-formula').value = row.formula || '';
        $('rd-negrita').checked = !!row.estilo_negrita;
        $('rd-subrayado').checked = !!row.estilo_subrayado;
        if ($('rd-conjunto-id')) $('rd-conjunto-id').value = row.conjunto_id ? String(row.conjunto_id) : '';
        if ($('rd-lado-presentacion')) $('rd-lado-presentacion').value = row.lado_presentacion || '';
        if ($('rd-ocultar-si-cero')) $('rd-ocultar-si-cero').checked = !!row.ocultar_si_cero;
        actualizarAyudaTipo();
        toggleFormula();
        cargarCuentas(row.id);
    }

    function actualizarAyudaTipo() {
        var t = $('rd-tipo').value;
        var ayuda = (cfg.tiposRubroAyuda || {})[t] || '';
        var el = $('rd-tipo-ayuda');
        if (el) el.textContent = ayuda;
    }

    function toggleFormula() {
        var wrap = $('rd-formula-wrap');
        if (!wrap) return;
        if ($('rd-tipo').value === 'formula') wrap.classList.remove('d-none');
        else wrap.classList.add('d-none');
    }

    function cargarCuentas(rubroId) {
        var form = $('rd-cuentas-form');
        var hint = $('rd-cuentas-hint');
        var row = findRubro(rubroId);
        if (row && row.tipo === 'cuentas') {
            if (form) form.classList.remove('d-none');
            if (hint) hint.classList.add('d-none');
        } else {
            if (form) form.classList.add('d-none');
            if (hint) {
                hint.classList.remove('d-none');
                hint.textContent = row && row.tipo !== 'cuentas'
                    ? 'Este tipo de rubro no acumula cuentas directamente (use hijos o cámbielo a «Suma de cuentas»).'
                    : 'Seleccione un rubro.';
            }
        }

        fetch(urlRubro(cfg.urls.cuentasRubro, rubroId), { headers: csrfHeaders() })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                cuentasCache = data.cuentas || [];
                renderCuentas(cuentasCache);
            })
            .catch(function () { renderCuentas([]); });
    }

    function renderCuentas(list) {
        var tbody = $('rd-cuentas-tbody');
        var badge = $('rd-cuentas-count');
        if (badge) badge.textContent = String(list.length);
        if (!tbody) return;
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">Sin cuentas</td></tr>';
            return;
        }
        var html = '';
        list.forEach(function (c) {
            html += '<tr class="rd-cuenta-row">' +
                '<td>' + esc(c.codigo_fmt || c.codigo_cuenta) + '</td>' +
                '<td>' + esc(c.nombre || '') + '</td>' +
                '<td>' + esc(c.origen || 'R') + '</td>' +
                '<td>' + (Number(c.signo) < 0 ? '−' : '+') + '</td>' +
                '<td class="text-center">' +
                    (cfg.puedeActualizar && Number(c.id) > 0
                        ? '<button type="button" class="btn btn-link btn-sm text-danger p-0 rd-del-cta" data-id="' + c.id + '"><i class="fa fa-times-circle"></i></button>'
                        : '') +
                '</td></tr>';
        });
        tbody.innerHTML = html;
        tbody.querySelectorAll('.rd-del-cta').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Quitar esta cuenta del rubro?')) return;
                fetch(urlCuenta(cfg.urls.eliminarCuenta, btn.getAttribute('data-id')), {
                    method: 'DELETE',
                    headers: csrfHeaders(),
                    body: JSON.stringify({})
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.estructura) {
                            estructura = data.estructura;
                            renderTree();
                        }
                        cuentasCache = data.cuentas || [];
                        renderCuentas(cuentasCache);
                    });
            });
        });
    }

    function abrirNuevo(parentId) {
        $('rd-nuevo-parent-id').value = parentId ? String(parentId) : '';
        var hint = $('rd-nuevo-parent-hint');
        if (parentId) {
            var p = findRubro(parentId);
            hint.textContent = p ? 'Se creará como hijo de: ' + (p.codigo_linea || '') + ' ' + p.nombre : '';
        } else {
            hint.textContent = 'Se creará en el nivel raíz del informe.';
        }
        $('rd-nuevo-nombre').value = '';
        $('rd-nuevo-tipo').value = 'cuentas';
        $('#rd-modal-nuevo').modal('show');
    }

    function bind() {
        var btnNuevo = $('rd-btn-nuevo-rubro');
        if (btnNuevo) btnNuevo.addEventListener('click', function () { abrirNuevo(null); });

        var btnHijo = $('rd-btn-hijo');
        if (btnHijo) btnHijo.addEventListener('click', function () {
            if (!seleccionadoId) return;
            abrirNuevo(seleccionadoId);
        });

        var tipo = $('rd-tipo');
        if (tipo) {
            tipo.addEventListener('change', function () {
                actualizarAyudaTipo();
                toggleFormula();
            });
        }

        var formRubro = $('rd-form-rubro');
        if (formRubro) {
            formRubro.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!seleccionadoId || !cfg.puedeActualizar) return;
                var body = {
                    nombre: $('rd-nombre').value,
                    codigo_linea: $('rd-codigo-linea').value,
                    tipo: $('rd-tipo').value,
                    formula: $('rd-formula').value,
                    estilo_negrita: $('rd-negrita').checked ? 1 : 0,
                    estilo_subrayado: $('rd-subrayado').checked ? 1 : 0,
                    conjunto_id: $('rd-conjunto-id') ? ($('rd-conjunto-id').value || 0) : 0,
                    lado_presentacion: $('rd-lado-presentacion') ? ($('rd-lado-presentacion').value || '') : '',
                    ocultar_si_cero: $('rd-ocultar-si-cero') && $('rd-ocultar-si-cero').checked ? 1 : 0
                };
                fetch(urlRubro(cfg.urls.actualizarRubro, seleccionadoId), {
                    method: 'PUT',
                    headers: csrfHeaders(),
                    body: JSON.stringify(body)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.estructura) {
                            estructura = data.estructura;
                            seleccionar(seleccionadoId);
                        }
                    });
            });
        }

        var btnBorrar = $('rd-btn-borrar-rubro');
        if (btnBorrar) {
            btnBorrar.addEventListener('click', function () {
                if (!seleccionadoId || !confirm('¿Eliminar el rubro? Los hijos pasan al padre.')) return;
                fetch(urlRubro(cfg.urls.eliminarRubro, seleccionadoId), {
                    method: 'DELETE',
                    headers: csrfHeaders(),
                    body: JSON.stringify({})
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.estructura) {
                            estructura = data.estructura;
                            seleccionadoId = null;
                            renderTree();
                            seleccionar(null);
                        }
                    });
            });
        }

        var formNuevo = $('rd-form-nuevo');
        if (formNuevo) {
            formNuevo.addEventListener('submit', function (e) {
                e.preventDefault();
                var parentId = $('rd-nuevo-parent-id').value;
                var body = {
                    nombre: $('rd-nuevo-nombre').value,
                    tipo: $('rd-nuevo-tipo').value,
                    parent_id: parentId ? Number(parentId) : null
                };
                fetch(cfg.urls.guardarRubro, {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: JSON.stringify(body)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        $('#rd-modal-nuevo').modal('hide');
                        if (data.estructura) {
                            estructura = data.estructura;
                            renderTree();
                            if (estructura.length) {
                                seleccionar(estructura[estructura.length - 1].id);
                            }
                        }
                    });
            });
        }

        var btnAddCta = $('rd-btn-add-cuenta');
        if (btnAddCta) {
            btnAddCta.addEventListener('click', function () {
                if (!seleccionadoId) return;
                var codigoTxt = ($('rd_codigo_cuenta') || {}).value || '';
                var digits = String(codigoTxt).replace(/\D/g, '');
                if (!digits) {
                    alert('Ingrese un código de cuenta.');
                    return;
                }
                var hastaTxt = ($('rd_codigo_hasta') || {}).value || '';
                var hastaDigits = String(hastaTxt).replace(/\D/g, '');
                var body = {
                    codigo_cuenta: Number(digits),
                    codigo_hasta: hastaDigits ? Number(hastaDigits) : null,
                    cuentacontable_id: Number(($('rd_cuentacontable_id') || {}).value || 0) || null,
                    empresa_id: Number(($('empresa_id') || {}).value || 0) || null,
                    signo: Number(($('rd_signo') || {}).value || 1),
                    origen: 'R'
                };
                fetch(urlRubro(cfg.urls.guardarCuenta, seleccionadoId), {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: JSON.stringify(body)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok === false) {
                            alert(data.mensaje || 'No se pudo agregar la cuenta');
                            return;
                        }
                        if (data.estructura) {
                            estructura = data.estructura;
                            renderTree();
                        }
                        cuentasCache = data.cuentas || [];
                        renderCuentas(cuentasCache);
                        if ($('rd_codigo_cuenta')) $('rd_codigo_cuenta').value = '';
                        if ($('rd_codigo_hasta')) $('rd_codigo_hasta').value = '';
                        if ($('rd_nombre_cuenta')) $('rd_nombre_cuenta').value = '';
                        if ($('rd_cuentacontable_id')) $('rd_cuentacontable_id').value = '';
                    });
            });
        }

        var btnPreview = $('rd-btn-preview');
        if (btnPreview) {
            btnPreview.addEventListener('click', function () {
                var mes = Number(($('rd-preview-mes') || {}).value || 1);
                var anio = Number(($('rd-preview-anio') || {}).value || new Date().getFullYear());
                var layoutId = ($('rd-preview-layout') || {}).value || '';
                var params = new URLSearchParams();
                params.set('consultar', '1');
                params.set('modo_periodo', 'periodos');
                params.set('mes_desde', String(mes));
                params.set('anio_desde', String(anio));
                params.set('mes_hasta', String(mes));
                params.set('anio_hasta', String(anio));
                params.set('ocultar_ceros', '1');
                params.set('fuente_plan', 'partidagasto');
                if (layoutId) params.set('layout_id', layoutId);
                (cfg.empresaIds || []).forEach(function (id) {
                    params.append('empresa_ids[]', String(id));
                });
                var adv = $('rd-preview-adv');
                var tbody = $('rd-preview-tbody');
                var thead = $('rd-preview-thead');
                if (adv) adv.textContent = 'Calculando…';
                fetch(cfg.urls.preview + '?' + params.toString(), { headers: csrfHeaders() })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (adv) {
                            adv.textContent = (data.periodo_texto || '') +
                                (data.advertencias && data.advertencias.length
                                    ? ' · ' + data.advertencias.slice(0, 2).join(' · ')
                                    : '');
                        }
                        var cols = data.columnas || [];
                        var filas = data.filas || [];
                        if (thead) {
                            var h = '<tr><th>Código</th><th>Concepto</th>';
                            cols.forEach(function (c) { h += '<th class="text-right">' + esc(c.label || c.key) + '</th>'; });
                            h += '</tr>';
                            thead.innerHTML = h;
                        }
                        if (!tbody) return;
                        if (!filas.length) {
                            tbody.innerHTML = '<tr><td colspan="' + (2 + cols.length) + '" class="text-center text-muted">Sin filas</td></tr>';
                            return;
                        }
                        var html = '';
                        filas.forEach(function (f) {
                            html += '<tr' + (f.negrita ? ' style="font-weight:700"' : '') + '>';
                            html += '<td>' + esc(f.codigo || '') + '</td>';
                            html += '<td style="padding-left:' + ((f.depth || 0) * 12 + 8) + 'px">' + esc(f.nombre || '') + '</td>';
                            cols.forEach(function (c) {
                                var v = f.saldos ? f.saldos[c.key] : null;
                                var txt = '';
                                if (v !== null && v !== undefined && Math.abs(Number(v)) >= 0.005) {
                                    txt = Number(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    if (c.tipo === 'var_pct') txt += '%';
                                }
                                html += '<td class="text-right">' + txt + '</td>';
                            });
                            html += '</tr>';
                        });
                        tbody.innerHTML = html;
                    })
                    .catch(function () {
                        if (adv) adv.textContent = 'Error al calcular preview.';
                    });
            });
        }

        if (typeof activa_eventos_consulta_cuentacontable === 'function') {
            activa_eventos_consulta_cuentacontable();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bind();
        renderTree();
        if (seleccionadoId) seleccionar(seleccionadoId);
    });
})();
