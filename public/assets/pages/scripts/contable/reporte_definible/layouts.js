/**
 * Editor de layouts + reglas IC (consolidación) — reportes definibles.
 */
(function () {
    'use strict';

    var cfg = window.rdConfig || {};
    if (!cfg.urls || !cfg.urls.layouts) {
        return;
    }

    var payload = cfg.layoutsPayload || { sistema: [], informe: [], layout_default_id: null, tipos_columna: {} };
    var reglas = cfg.eliReglas || [];
    var layoutSelId = null;
    var tipos = cfg.tiposColumnaLayout || payload.tipos_columna || {};

    function $(id) { return document.getElementById(id); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function csrfHeaders() {
        return {
            'X-CSRF-TOKEN': cfg.csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
    }

    function urlLay(tpl, lid) {
        return String(tpl).replace('__LID__', String(lid));
    }

    function urlCol(tpl, lid, cid) {
        return String(tpl).replace('__LID__', String(lid)).replace('__CID__', String(cid));
    }

    function urlEli(tpl, rid) {
        return String(tpl).replace('__RID__', String(rid));
    }

    function notify(msg, tipo) {
        if (window.Biblioteca && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(msg, 'anitaERP', tipo || 'success');
        } else {
            alert(msg);
        }
    }

    function applyLayouts(data) {
        if (!data) return;
        payload = data;
        if (data.tipos_columna) {
            tipos = data.tipos_columna;
        }
        renderLayouts();
        if (layoutSelId) {
            seleccionarLayout(layoutSelId);
        }
    }

    function findInforme(id) {
        var list = payload.informe || [];
        for (var i = 0; i < list.length; i++) {
            if (Number(list[i].id) === Number(id)) return list[i];
        }
        return null;
    }

    function renderLayouts() {
        var tbSis = $('rd-layouts-sistema');
        var tbInf = $('rd-layouts-informe');
        if (tbSis) {
            var hs = '';
            (payload.sistema || []).forEach(function (lay) {
                hs += '<tr>' +
                    '<td>' + esc(lay.codigo) + '</td>' +
                    '<td>' + esc(lay.nombre) + ' <span class="text-muted small">(' + (lay.columnas || []).length + ' col)</span></td>' +
                    '<td>' + (cfg.puedeActualizar
                        ? '<button type="button" class="btn btn-outline-primary btn-sm rd-clonar-layout" data-id="' + lay.id + '">Clonar</button>'
                        : '') + '</td></tr>';
            });
            tbSis.innerHTML = hs || '<tr><td colspan="3" class="text-muted text-center">Sin presets</td></tr>';
            tbSis.querySelectorAll('.rd-clonar-layout').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    clonarLayout(Number(btn.getAttribute('data-id')));
                });
            });
        }
        if (tbInf) {
            var hi = '';
            var defId = payload.layout_default_id;
            (payload.informe || []).forEach(function (lay) {
                var active = layoutSelId && Number(layoutSelId) === Number(lay.id);
                var esDef = Number(defId) === Number(lay.id) || lay.es_default;
                hi += '<tr class="' + (active ? 'table-info' : '') + '" style="cursor:pointer" data-id="' + lay.id + '">' +
                    '<td class="rd-sel-layout">' + esc(lay.codigo) + '</td>' +
                    '<td class="rd-sel-layout">' + esc(lay.nombre) + '</td>' +
                    '<td class="rd-sel-layout text-center">' + (esDef ? '★' : '') + '</td>' +
                    '<td>' + (cfg.puedeActualizar
                        ? '<button type="button" class="btn btn-outline-danger btn-sm rd-del-layout" data-id="' + lay.id + '" title="Eliminar"><i class="fa fa-times"></i></button>'
                        : '') + '</td></tr>';
            });
            tbInf.innerHTML = hi || '<tr><td colspan="4" class="text-muted text-center">Ninguno aún — cloná un preset</td></tr>';
            tbInf.querySelectorAll('.rd-sel-layout').forEach(function (cell) {
                cell.addEventListener('click', function () {
                    var tr = cell.closest('tr');
                    seleccionarLayout(Number(tr.getAttribute('data-id')));
                });
            });
            tbInf.querySelectorAll('.rd-del-layout').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!confirm('¿Eliminar este layout?')) return;
                    eliminarLayout(Number(btn.getAttribute('data-id')));
                });
            });
        }
    }

    function seleccionarLayout(id) {
        layoutSelId = id;
        renderLayouts();
        var lay = findInforme(id);
        var vacio = $('rd-layout-detalle-vacio');
        var det = $('rd-layout-detalle');
        var titulo = $('rd-layout-detalle-titulo');
        var hint = $('rd-layout-detalle-hint');
        if (!lay) {
            if (vacio) vacio.classList.remove('d-none');
            if (det) det.classList.add('d-none');
            if (hint) hint.textContent = 'Elegí un layout del informe';
            return;
        }
        if (vacio) vacio.classList.add('d-none');
        if (det) det.classList.remove('d-none');
        if (titulo) titulo.textContent = lay.codigo + ' — columnas';
        if (hint) hint.textContent = lay.nombre;
        var nom = $('rd-layout-nombre');
        if (nom) nom.value = lay.nombre || '';
        renderColumnas(lay);
    }

    function metaDefaults(tipo, meta) {
        meta = meta && typeof meta === 'object' ? meta : {};
        return {
            offset_meses: meta.offset_meses != null ? meta.offset_meses : 0,
            presupuesto_escenario_id: meta.presupuesto_escenario_id != null ? meta.presupuesto_escenario_id : '',
            numerador_key: meta.numerador_key || '',
            denominador_key: meta.denominador_key || '',
            expr: meta.expr || '',
            base_key: meta.base_key || 'actual',
            ref_key: meta.ref_key || 'plan',
            valuacion: meta.valuacion || 'historico',
            moneda_id: meta.moneda_id != null ? meta.moneda_id : ''
        };
    }

    function htmlMetaInputs(tipo, meta, disabled) {
        var m = metaDefaults(tipo, meta);
        var dis = disabled ? ' disabled' : '';
        var h = '<div class="rd-col-meta" data-tipo="' + esc(tipo) + '">';
        if (tipo === 'periodo_offset') {
            h += '<label class="small text-muted mb-0">offset_meses</label>' +
                '<input type="number" class="form-control form-control-sm rd-meta-offset" value="' + esc(m.offset_meses) + '"' + dis + '>';
        } else if (tipo === 'plan') {
            h += '<label class="small text-muted mb-0">escenario id</label>' +
                '<input type="number" class="form-control form-control-sm rd-meta-escenario" value="' + esc(m.presupuesto_escenario_id) + '" placeholder="global"' + dis + '>';
        } else if (tipo === 'pct_sobre') {
            h += '<label class="small text-muted mb-0">numerador / denominador</label>' +
                '<div class="d-flex">' +
                '<input type="text" class="form-control form-control-sm rd-meta-num" value="' + esc(m.numerador_key) + '" placeholder="num key"' + dis + '>' +
                '<input type="text" class="form-control form-control-sm rd-meta-den ml-1" value="' + esc(m.denominador_key) + '" placeholder="den key"' + dis + '>' +
                '</div>';
        } else if (tipo === 'formula_col') {
            h += '<label class="small text-muted mb-0">expr</label>' +
                '<input type="text" class="form-control form-control-sm rd-meta-expr" value="' + esc(m.expr) + '" placeholder="a+b/c"' + dis + '>';
        } else if (tipo === 'var' || tipo === 'var_pct') {
            h += '<label class="small text-muted mb-0">base / ref</label>' +
                '<div class="d-flex">' +
                '<input type="text" class="form-control form-control-sm rd-meta-base" value="' + esc(m.base_key) + '" placeholder="actual"' + dis + '>' +
                '<input type="text" class="form-control form-control-sm rd-meta-ref ml-1" value="' + esc(m.ref_key) + '" placeholder="plan"' + dis + '>' +
                '</div>';
        } else if (tipo === 'valuacion') {
            var opciones = [
                ['historico', 'Histórico'],
                ['ajustado', 'Ajustado por inflación'],
                ['moneda', 'Convertido a moneda']
            ].map(function (o) {
                return '<option value="' + o[0] + '"' + (m.valuacion === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
            }).join('');
            h += '<label class="small text-muted mb-0">valuación / moneda</label>' +
                '<div class="d-flex">' +
                '<select class="form-control form-control-sm rd-meta-valuacion"' + dis + '>' + opciones + '</select>' +
                '<input type="number" class="form-control form-control-sm rd-meta-moneda ml-1" style="width:90px" ' +
                'value="' + esc(m.moneda_id) + '" placeholder="mon. id"' + dis + '>' +
                '</div>';
        } else {
            h += '<span class="text-muted small">—</span>';
        }
        h += '</div>';
        return h;
    }

    function leerMetaDesdeTr(tr) {
        var tipo = tr.querySelector('.rd-col-tipo-sel').value;
        var box = tr.querySelector('.rd-col-meta');
        if (!box) return null;
        var meta = {};
        if (tipo === 'periodo_offset') {
            var off = box.querySelector('.rd-meta-offset');
            meta.offset_meses = off ? Number(off.value || 0) : 0;
        } else if (tipo === 'plan') {
            var escInp = box.querySelector('.rd-meta-escenario');
            var escVal = escInp ? String(escInp.value || '').trim() : '';
            if (escVal !== '') meta.presupuesto_escenario_id = Number(escVal);
        } else if (tipo === 'pct_sobre') {
            var num = box.querySelector('.rd-meta-num');
            var den = box.querySelector('.rd-meta-den');
            meta.numerador_key = num ? String(num.value || '').trim() : '';
            meta.denominador_key = den ? String(den.value || '').trim() : '';
        } else if (tipo === 'formula_col') {
            var ex = box.querySelector('.rd-meta-expr');
            meta.expr = ex ? String(ex.value || '').trim() : '';
        } else if (tipo === 'var' || tipo === 'var_pct') {
            var base = box.querySelector('.rd-meta-base');
            var ref = box.querySelector('.rd-meta-ref');
            meta.base_key = base ? String(base.value || '').trim() || 'actual' : 'actual';
            meta.ref_key = ref ? String(ref.value || '').trim() || 'plan' : 'plan';
        } else if (tipo === 'valuacion') {
            var val = box.querySelector('.rd-meta-valuacion');
            var mon = box.querySelector('.rd-meta-moneda');
            meta.valuacion = val ? String(val.value || 'historico') : 'historico';
            var monVal = mon ? String(mon.value || '').trim() : '';
            if (monVal !== '') meta.moneda_id = Number(monVal);
        } else {
            return null;
        }
        return meta;
    }

    function renderColumnas(lay) {
        var tb = $('rd-layout-columnas');
        if (!tb) return;
        var cols = (lay.columnas || []).slice().sort(function (a, b) {
            return (a.orden || 0) - (b.orden || 0) || (a.id || 0) - (b.id || 0);
        });
        var html = '';
        cols.forEach(function (c) {
            var tipoOpts = '';
            Object.keys(tipos).forEach(function (tk) {
                tipoOpts += '<option value="' + esc(tk) + '"' + (c.tipo === tk ? ' selected' : '') + '>' + esc(tipos[tk]) + '</option>';
            });
            html += '<tr data-col-id="' + c.id + '">' +
                '<td><input type="number" class="form-control form-control-sm rd-col-orden" value="' + (c.orden || 0) + '" min="1" style="width:70px"' + (cfg.puedeActualizar ? '' : ' disabled') + '></td>' +
                '<td><input type="text" class="form-control form-control-sm rd-col-key" value="' + esc(c.key) + '"' + (cfg.puedeActualizar ? '' : ' readonly') + '></td>' +
                '<td><input type="text" class="form-control form-control-sm rd-col-label" value="' + esc(c.label) + '"' + (cfg.puedeActualizar ? '' : ' readonly') + '></td>' +
                '<td><select class="form-control form-control-sm rd-col-tipo-sel"' + (cfg.puedeActualizar ? '' : ' disabled') + '>' + tipoOpts + '</select></td>' +
                '<td>' + htmlMetaInputs(c.tipo, c.meta, !cfg.puedeActualizar) + '</td>' +
                '<td>' + (cfg.puedeActualizar
                    ? '<button type="button" class="btn btn-outline-primary btn-sm rd-col-save" title="Guardar"><i class="fa fa-save"></i></button> ' +
                      '<button type="button" class="btn btn-outline-danger btn-sm rd-col-del" title="Quitar"><i class="fa fa-times"></i></button>'
                    : '') + '</td></tr>';
        });
        tb.innerHTML = html || '<tr><td colspan="6" class="text-muted text-center">Sin columnas</td></tr>';
        if (!cfg.puedeActualizar) return;
        tb.querySelectorAll('.rd-col-tipo-sel').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var tr = sel.closest('tr');
                var cell = tr.querySelector('td:nth-child(5)');
                if (cell) {
                    cell.innerHTML = htmlMetaInputs(sel.value, {}, false);
                }
            });
        });
        tb.querySelectorAll('.rd-col-save').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tr = btn.closest('tr');
                var data = {
                    orden: Number(tr.querySelector('.rd-col-orden').value || 0),
                    key: tr.querySelector('.rd-col-key').value,
                    label: tr.querySelector('.rd-col-label').value,
                    tipo: tr.querySelector('.rd-col-tipo-sel').value
                };
                var meta = leerMetaDesdeTr(tr);
                if (meta !== null) {
                    data.meta = meta;
                }
                guardarColumna(Number(tr.getAttribute('data-col-id')), data);
            });
        });
        tb.querySelectorAll('.rd-col-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Quitar columna?')) return;
                eliminarColumna(Number(btn.closest('tr').getAttribute('data-col-id')));
            });
        });
    }

    function postJson(url, body, method) {
        return fetch(url, {
            method: method || 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify(body || {})
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok || j.ok === false) {
                    var msg = (j.message || (j.errors && Object.values(j.errors).flat().join(' ')) || 'Error');
                    throw new Error(msg);
                }
                return j;
            });
        });
    }

    function clonarLayout(origenId) {
        postJson(cfg.urls.clonarLayout, { layout_origen_id: origenId })
            .then(function (j) {
                applyLayouts(j.layouts);
                if (j.layout && j.layout.id) seleccionarLayout(j.layout.id);
                notify('Layout clonado al informe');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function crearLayout() {
        var codigo = ($('rd-layout-nuevo-codigo') || {}).value || '';
        var nombre = ($('rd-layout-nuevo-nombre') || {}).value || '';
        postJson(cfg.urls.crearLayout, { codigo: codigo, nombre: nombre })
            .then(function (j) {
                applyLayouts(j.layouts);
                if (j.layout && j.layout.id) seleccionarLayout(j.layout.id);
                if ($('rd-layout-nuevo-codigo')) $('rd-layout-nuevo-codigo').value = '';
                if ($('rd-layout-nuevo-nombre')) $('rd-layout-nuevo-nombre').value = '';
                notify('Layout creado');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function guardarLayoutCabecera() {
        if (!layoutSelId) return;
        var nombre = ($('rd-layout-nombre') || {}).value || '';
        postJson(urlLay(cfg.urls.actualizarLayout, layoutSelId), { nombre: nombre }, 'PUT')
            .then(function (j) {
                applyLayouts(j.layouts);
                notify('Layout actualizado');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function marcarDefault() {
        if (!layoutSelId) return;
        postJson(urlLay(cfg.urls.defaultLayout, layoutSelId), {})
            .then(function (j) {
                applyLayouts(j.layouts);
                notify('Layout marcado como default');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function eliminarLayout(id) {
        postJson(urlLay(cfg.urls.eliminarLayout, id), {}, 'DELETE')
            .then(function (j) {
                if (Number(layoutSelId) === Number(id)) layoutSelId = null;
                applyLayouts(j.layouts);
                seleccionarLayout(layoutSelId);
                notify('Layout eliminado');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function agregarColumna() {
        if (!layoutSelId) return;
        var tipo = ($('rd-col-tipo') || {}).value || 'actual';
        postJson(urlLay(cfg.urls.agregarColumna, layoutSelId), { tipo: tipo })
            .then(function (j) {
                applyLayouts(j.layouts);
                notify('Columna agregada');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function guardarColumna(colId, data) {
        if (!layoutSelId) return;
        postJson(urlCol(cfg.urls.actualizarColumna, layoutSelId, colId), data, 'PUT')
            .then(function (j) {
                applyLayouts(j.layouts);
                notify('Columna guardada');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function eliminarColumna(colId) {
        if (!layoutSelId) return;
        postJson(urlCol(cfg.urls.eliminarColumna, layoutSelId, colId), {}, 'DELETE')
            .then(function (j) {
                applyLayouts(j.layouts);
                notify('Columna eliminada');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    /* —— Eliminaciones IC + participación —— */

    var participaciones = cfg.participaciones || [];

    function renderEli() {
        var tb = $('rd-eli-tbody');
        if (!tb) return;
        var html = '';
        (reglas || []).forEach(function (r) {
            var ambitoTxt = r.es_global ? 'Global' : (r.ambito === 'pareja'
                ? ('Pareja ' + (r.empresa_a_id || '') + '/' + (r.empresa_b_id || ''))
                : 'Todas');
            html += '<tr>' +
                '<td>' + esc(r.nombre) + '</td>' +
                '<td>' + esc(r.codigo_fmt) + '</td>' +
                '<td>' + esc(ambitoTxt) + '</td>' +
                '<td>' + (r.activo ? 'Sí' : 'No') + '</td>' +
                '<td>' + ((!r.es_global && cfg.puedeActualizar)
                    ? '<button type="button" class="btn btn-outline-secondary btn-sm rd-eli-toggle" data-id="' + r.id + '" data-activo="' + (r.activo ? '0' : '1') + '">' +
                      (r.activo ? 'Desactivar' : 'Activar') + '</button> ' +
                      '<button type="button" class="btn btn-outline-danger btn-sm rd-eli-del" data-id="' + r.id + '"><i class="fa fa-times"></i></button>'
                    : '') + '</td></tr>';
        });
        tb.innerHTML = html || '<tr><td colspan="5" class="text-muted text-center">Sin reglas — al consolidar no se elimina ninguna cuenta</td></tr>';
        tb.querySelectorAll('.rd-eli-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                postJson(urlEli(cfg.urls.actualizarEliRegla, btn.getAttribute('data-id')), {
                    activo: btn.getAttribute('data-activo') === '1'
                }, 'PUT')
                    .then(function (j) {
                        reglas = j.reglas || [];
                        renderEli();
                    })
                    .catch(function (e) { notify(e.message, 'error'); });
            });
        });
        tb.querySelectorAll('.rd-eli-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Eliminar regla?')) return;
                postJson(urlEli(cfg.urls.eliminarEliRegla, btn.getAttribute('data-id')), {}, 'DELETE')
                    .then(function (j) {
                        reglas = j.reglas || [];
                        renderEli();
                        notify('Regla eliminada');
                    })
                    .catch(function (e) { notify(e.message, 'error'); });
            });
        });
    }

    function agregarEli() {
        postJson(cfg.urls.guardarEliRegla, {
            nombre: ($('rd-eli-nombre') || {}).value || '',
            codigo_desde: Number(($('rd-eli-desde') || {}).value || 0),
            codigo_hasta: Number(($('rd-eli-hasta') || {}).value || 0) || null,
            ambito: ($('rd-eli-ambito') || {}).value || 'todas',
            empresa_a_id: Number(($('rd-eli-emp-a') || {}).value || 0) || null,
            empresa_b_id: Number(($('rd-eli-emp-b') || {}).value || 0) || null
        })
            .then(function (j) {
                reglas = j.reglas || [];
                renderEli();
                if ($('rd-eli-nombre')) $('rd-eli-nombre').value = '';
                if ($('rd-eli-desde')) $('rd-eli-desde').value = '';
                if ($('rd-eli-hasta')) $('rd-eli-hasta').value = '';
                notify('Regla IC agregada');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function renderPart() {
        var tb = $('rd-part-tbody');
        if (!tb) return;
        var html = '';
        (participaciones || []).forEach(function (p) {
            var vig = (p.vigente_desde || '') + (p.vigente_hasta ? ' → ' + p.vigente_hasta : '');
            html += '<tr>' +
                '<td>' + esc(p.empresa_id) + '</td>' +
                '<td>' + esc(p.porcentaje) + '</td>' +
                '<td>' + esc(vig || '—') + '</td>' +
                '<td>' + (cfg.puedeActualizar
                    ? '<button type="button" class="btn btn-outline-danger btn-sm rd-part-del" data-id="' + p.id + '"><i class="fa fa-times"></i></button>'
                    : '') + '</td></tr>';
        });
        tb.innerHTML = html || '<tr><td colspan="4" class="text-muted text-center">Sin % — cada empresa al 100%</td></tr>';
        tb.querySelectorAll('.rd-part-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Quitar participación?')) return;
                var url = String(cfg.urls.eliminarParticipacion).replace('__PID__', btn.getAttribute('data-id'));
                postJson(url, {}, 'DELETE')
                    .then(function (j) {
                        participaciones = j.participaciones || [];
                        renderPart();
                    })
                    .catch(function (e) { notify(e.message, 'error'); });
            });
        });
    }

    function agregarPart() {
        if (!cfg.urls.guardarParticipacion) return;
        postJson(cfg.urls.guardarParticipacion, {
            empresa_id: Number(($('rd-part-empresa') || {}).value || 0),
            porcentaje: Number(($('rd-part-pct') || {}).value || 100),
            vigente_desde: ($('rd-part-desde') || {}).value || null,
            vigente_hasta: ($('rd-part-hasta') || {}).value || null
        })
            .then(function (j) {
                participaciones = j.participaciones || [];
                renderPart();
                notify('Participación guardada');
            })
            .catch(function (e) { notify(e.message, 'error'); });
    }

    function toggleEliPareja() {
        var ambito = ($('rd-eli-ambito') || {}).value || 'todas';
        document.querySelectorAll('.rd-eli-pareja').forEach(function (el) {
            if (ambito === 'pareja') el.classList.remove('d-none');
            else el.classList.add('d-none');
        });
    }

    function bind() {
        var btnCrear = $('rd-btn-crear-layout');
        if (btnCrear) btnCrear.addEventListener('click', crearLayout);
        var btnGuardar = $('rd-btn-guardar-layout');
        if (btnGuardar) btnGuardar.addEventListener('click', guardarLayoutCabecera);
        var btnDef = $('rd-btn-default-layout');
        if (btnDef) btnDef.addEventListener('click', marcarDefault);
        var btnCol = $('rd-btn-add-columna');
        if (btnCol) btnCol.addEventListener('click', agregarColumna);
        var btnEli = $('rd-btn-add-eli');
        if (btnEli) btnEli.addEventListener('click', agregarEli);
        var btnPart = $('rd-btn-add-part');
        if (btnPart) btnPart.addEventListener('click', agregarPart);
        var selAmbito = $('rd-eli-ambito');
        if (selAmbito) selAmbito.addEventListener('change', toggleEliPareja);
        toggleEliPareja();
        renderLayouts();
        renderEli();
        renderPart();
        if ((payload.informe || []).length) {
            seleccionarLayout(payload.layout_default_id || payload.informe[0].id);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
