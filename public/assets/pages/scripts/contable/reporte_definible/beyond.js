/**
 * Beyond premium: acceso, alertas, validar, diff, cobertura accionable.
 */
(function () {
    'use strict';
    var cfg = window.rdConfig || {};
    if (!cfg.urls || !cfg.urls.validar) return;

    function $(id) { return document.getElementById(id); }
    function csrfHeaders() {
        return {
            'X-CSRF-TOKEN': cfg.csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
    }
    function notify(msg, tipo) {
        if (window.Biblioteca && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(msg, 'anitaERP', tipo || 'success');
        } else { alert(msg); }
    }
    function postJson(url, body, method) {
        return fetch(url, {
            method: method || 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify(body || {})
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok || j.ok === false) {
                    throw new Error(j.message || j.mensaje || 'Error');
                }
                return j;
            });
        });
    }

    var alertas = cfg.alertas || [];

    function renderAlertas() {
        var tb = $('rd-alerta-tbody');
        if (!tb) return;
        var html = '';
        (alertas || []).forEach(function (a) {
            var detalle = a.expresion
                ? '<code>' + a.expresion + '</code>' + (a.etiqueta ? ' — ' + a.etiqueta : '')
                : (a.etiqueta || '<span class="text-muted">—</span>');
            html += '<tr><td>' + (a.tipo_label || a.tipo) + '</td><td>' + detalle + '</td><td>' + a.umbral + '</td><td>' +
                (a.activo ? 'Sí' : 'No') + '</td><td>' +
                (cfg.puedeActualizar
                    ? '<button type="button" class="btn btn-outline-danger btn-sm rd-alerta-del" data-id="' + a.id + '"><i class="fa fa-times"></i></button>'
                    : '') + '</td></tr>';
        });
        tb.innerHTML = html || '<tr><td colspan="5" class="text-muted text-center">Sin alertas</td></tr>';
        tb.querySelectorAll('.rd-alerta-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = String(cfg.urls.eliminarAlerta).replace('__AID__', btn.getAttribute('data-id'));
                postJson(url, {}, 'DELETE').then(function (j) {
                    alertas = j.alertas || [];
                    renderAlertas();
                }).catch(function (e) { notify(e.message, 'error'); });
            });
        });
    }

    var suscripciones = cfg.suscripciones || [];

    function escapar(txt) {
        return String(txt == null ? '' : txt).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function datosSuscripcionDesdeForm() {
        return {
            nombre: (($('rd-susc-nombre') || {}).value || '').trim(),
            periodicidad: ($('rd-susc-periodicidad') || {}).value,
            dia_mes: Number(($('rd-susc-dia-mes') || {}).value || 5),
            dia_semana: Number(($('rd-susc-dia-semana') || {}).value || 1),
            hora: ($('rd-susc-hora') || {}).value || '07:00',
            periodo_relativo: ($('rd-susc-periodo') || {}).value,
            formato: ($('rd-susc-formato') || {}).value,
            destinatarios: (($('rd-susc-destinatarios') || {}).value || '').trim(),
            mensaje: (($('rd-susc-mensaje') || {}).value || '').trim(),
            publicar: !!(($('rd-susc-publicar') || {}).checked),
            solo_si_alertas: !!(($('rd-susc-solo-alertas') || {}).checked),
            activo: !!(($('rd-susc-activo') || {}).checked)
        };
    }

    function cargarSuscripcionEnForm(s) {
        if ($('rd-susc-id')) $('rd-susc-id').value = s ? s.id : '';
        if ($('rd-susc-nombre')) $('rd-susc-nombre').value = s ? s.nombre : '';
        if ($('rd-susc-periodicidad')) $('rd-susc-periodicidad').value = s ? s.periodicidad : 'mensual';
        if ($('rd-susc-dia-mes')) $('rd-susc-dia-mes').value = s ? s.dia_mes : 5;
        if ($('rd-susc-dia-semana')) $('rd-susc-dia-semana').value = s ? s.dia_semana : 1;
        if ($('rd-susc-hora')) $('rd-susc-hora').value = s ? s.hora : '07:00';
        if ($('rd-susc-periodo')) $('rd-susc-periodo').value = s ? s.periodo_relativo : 'mes_anterior';
        if ($('rd-susc-formato')) $('rd-susc-formato').value = s ? s.formato : 'pdf';
        if ($('rd-susc-destinatarios')) $('rd-susc-destinatarios').value = s ? s.destinatarios : '';
        if ($('rd-susc-mensaje')) $('rd-susc-mensaje').value = s ? s.mensaje : '';
        if ($('rd-susc-publicar')) $('rd-susc-publicar').checked = s ? !!s.publicar : false;
        if ($('rd-susc-solo-alertas')) $('rd-susc-solo-alertas').checked = s ? !!s.solo_si_alertas : false;
        if ($('rd-susc-activo')) $('rd-susc-activo').checked = s ? !!s.activo : true;

        var titulo = $('rd-susc-titulo');
        if (titulo) titulo.textContent = s ? 'Editando: ' + s.nombre : 'Nuevo envío';
        var cancelar = $('rd-btn-susc-cancelar');
        if (cancelar) cancelar.classList.toggle('d-none', !s);
        toggleCamposPeriodicidad();
    }

    function toggleCamposPeriodicidad() {
        var per = ($('rd-susc-periodicidad') || {}).value;
        var mes = $('rd-susc-diames-wrap');
        var sem = $('rd-susc-diasemana-wrap');
        if (mes) mes.classList.toggle('d-none', per !== 'mensual');
        if (sem) sem.classList.toggle('d-none', per !== 'semanal');
    }

    function renderSuscripciones() {
        var tb = $('rd-susc-tbody');
        if (!tb) return;
        var html = '';
        (suscripciones || []).forEach(function (s) {
            var estado = s.ultimo_estado === 'ok'
                ? '<span class="badge badge-success">OK</span>'
                : (s.ultimo_estado === 'error'
                    ? '<span class="badge badge-danger">Error</span>'
                    : (s.ultimo_estado ? '<span class="badge badge-secondary">Omitida</span>' : ''));
            html += '<tr>' +
                '<td>' + escapar(s.nombre) +
                (s.activo ? '' : ' <span class="badge badge-secondary">inactivo</span>') +
                '<br><span class="small text-muted">' + escapar(s.filtros_texto) + '</span></td>' +
                '<td class="small">' + escapar(s.periodicidad_texto) + '</td>' +
                '<td class="small">' + escapar((s.formato || '').toUpperCase()) +
                (s.publicar ? '<br>publica el resultado' : '') +
                (s.solo_si_alertas ? '<br>solo con avisos' : '') + '</td>' +
                '<td class="small">' + escapar(s.destinatarios || '—') + '</td>' +
                '<td class="small">' + (s.ultima_ejecucion ? escapar(s.ultima_ejecucion) + ' ' + estado : '<span class="text-muted">nunca</span>') +
                (s.ultimo_mensaje ? '<br><span class="text-muted">' + escapar(s.ultimo_mensaje) + '</span>' : '') + '</td>' +
                '<td class="text-center">' +
                (cfg.puedeActualizar
                    ? '<button type="button" class="btn-accion-tabla rd-susc-edit" data-id="' + s.id + '" title="Editar"><i class="fa fa-edit"></i></button>' +
                      '<button type="button" class="btn-accion-tabla rd-susc-probar" data-id="' + s.id + '" title="Enviar ahora"><i class="fa fa-paper-plane text-primary"></i></button>' +
                      '<button type="button" class="btn-accion-tabla rd-susc-del" data-id="' + s.id + '" title="Eliminar"><i class="fa fa-times-circle text-danger"></i></button>'
                    : '') +
                '</td></tr>';
        });
        tb.innerHTML = html || '<tr><td colspan="6" class="text-muted text-center">Sin envíos programados</td></tr>';

        tb.querySelectorAll('.rd-susc-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = Number(btn.getAttribute('data-id'));
                var s = (suscripciones || []).filter(function (x) { return Number(x.id) === id; })[0];
                if (s) cargarSuscripcionEnForm(s);
            });
        });
        tb.querySelectorAll('.rd-susc-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Eliminar este envío programado?')) return;
                var url = String(cfg.urls.eliminarSuscripcion).replace('__SID__', btn.getAttribute('data-id'));
                postJson(url, {}, 'DELETE').then(function (j) {
                    suscripciones = j.suscripciones || [];
                    cargarSuscripcionEnForm(null);
                    renderSuscripciones();
                    notify('Envío eliminado');
                }).catch(function (e) { notify(e.message, 'error'); });
            });
        });
        tb.querySelectorAll('.rd-susc-probar').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Enviar el mail ahora con esta configuración?')) return;
                var url = String(cfg.urls.probarSuscripcion).replace('__SID__', btn.getAttribute('data-id'));
                btn.disabled = true;
                postJson(url, {}).then(function (j) {
                    suscripciones = j.suscripciones || suscripciones;
                    renderSuscripciones();
                    notify(j.mensaje || 'Enviado');
                }).catch(function (e) { notify(e.message, 'error'); })
                    .then(function () { btn.disabled = false; });
            });
        });
    }

    var notas = cfg.notas || [];

    function datosNotaDesdeForm() {
        return {
            rubro_id: Number(($('rd-nota-rubro') || {}).value || 0) || null,
            texto: (($('rd-nota-texto') || {}).value || '').trim(),
            periodo_desde: (($('rd-nota-desde') || {}).value || '').trim(),
            periodo_hasta: (($('rd-nota-hasta') || {}).value || '').trim(),
            orden: (($('rd-nota-orden') || {}).value || '').trim(),
            activo: !!(($('rd-nota-activo') || {}).checked)
        };
    }

    function cargarNotaEnForm(n) {
        if ($('rd-nota-id')) $('rd-nota-id').value = n ? n.id : '';
        if ($('rd-nota-rubro')) $('rd-nota-rubro').value = n && n.rubro_id ? n.rubro_id : '';
        if ($('rd-nota-texto')) $('rd-nota-texto').value = n ? n.texto : '';
        if ($('rd-nota-desde')) $('rd-nota-desde').value = n && n.periodo_desde ? n.periodo_desde : '';
        if ($('rd-nota-hasta')) $('rd-nota-hasta').value = n && n.periodo_hasta ? n.periodo_hasta : '';
        if ($('rd-nota-orden')) $('rd-nota-orden').value = n ? n.orden : '';
        if ($('rd-nota-activo')) $('rd-nota-activo').checked = n ? !!n.activo : true;

        var titulo = $('rd-nota-titulo');
        if (titulo) titulo.textContent = n ? 'Editando nota de ' + n.linea_texto : 'Nueva nota';
        var cancelar = $('rd-btn-nota-cancelar');
        if (cancelar) cancelar.classList.toggle('d-none', !n);
    }

    function renderNotas() {
        var tb = $('rd-nota-tbody');
        if (!tb) return;
        var html = '';
        (notas || []).forEach(function (n) {
            html += '<tr>' +
                '<td class="small">' + escapar(n.linea_texto) + '</td>' +
                '<td class="small">' + escapar(n.texto) + '</td>' +
                '<td class="small">' + escapar(n.vigencia_texto) + '</td>' +
                '<td class="small text-center">v' + n.version +
                (n.versiones > 1
                    ? ' <button type="button" class="btn-accion-tabla rd-nota-hist" data-id="' + n.id + '" title="Ver historial"><i class="fa fa-history text-primary"></i></button>'
                    : '') + '</td>' +
                '<td class="text-center">' + (n.activo ? 'Sí' : 'No') + '</td>' +
                '<td class="text-center">' +
                (cfg.puedeActualizar
                    ? '<button type="button" class="btn-accion-tabla rd-nota-edit" data-id="' + n.id + '" title="Editar (versiona)"><i class="fa fa-edit"></i></button>' +
                      '<button type="button" class="btn-accion-tabla rd-nota-del" data-id="' + n.id + '" title="Eliminar nota e historial"><i class="fa fa-times-circle text-danger"></i></button>'
                    : '') +
                '</td></tr>';
        });
        tb.innerHTML = html || '<tr><td colspan="6" class="text-muted text-center">Sin notas</td></tr>';

        tb.querySelectorAll('.rd-nota-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = Number(btn.getAttribute('data-id'));
                var n = (notas || []).filter(function (x) { return Number(x.id) === id; })[0];
                if (n) cargarNotaEnForm(n);
            });
        });
        tb.querySelectorAll('.rd-nota-del').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('¿Eliminar la nota y todo su historial de versiones?')) return;
                var url = String(cfg.urls.eliminarNota).replace('__NID__', btn.getAttribute('data-id'));
                postJson(url, {}, 'DELETE').then(function (j) {
                    notas = j.notas || [];
                    cargarNotaEnForm(null);
                    renderNotas();
                    notify('Nota eliminada');
                }).catch(function (e) { notify(e.message, 'error'); });
            });
        });
        tb.querySelectorAll('.rd-nota-hist').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = String(cfg.urls.historialNota).replace('__NID__', btn.getAttribute('data-id'));
                fetch(url, { headers: csrfHeaders() })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        var tbh = $('rd-nota-historial-tbody');
                        if (!tbh) return;
                        var filas = '';
                        (j.historial || []).forEach(function (h) {
                            filas += '<tr' + (h.vigente ? ' class="font-weight-bold"' : '') + '>' +
                                '<td class="text-center">v' + h.version + (h.vigente ? ' <span class="badge badge-info">vigente</span>' : '') + '</td>' +
                                '<td class="small">' + escapar(h.texto) + '</td>' +
                                '<td class="small">' + escapar(h.vigencia_texto) + '</td>' +
                                '<td class="small">' + escapar(h.usuario || '—') + '<br>' + escapar(h.fecha || '') + '</td>' +
                                '</tr>';
                        });
                        tbh.innerHTML = filas || '<tr><td colspan="4" class="text-muted text-center">Sin versiones</td></tr>';
                        if (window.jQuery) window.jQuery('#rd-modal-nota-historial').modal('show');
                    })
                    .catch(function (e) { notify(e.message || 'Error historial', 'error'); });
            });
        });
    }

    function bind() {
        // Enlaces del tipo …/editar#tab-notas: abrir la solapa apuntada.
        var hash = String(location.hash || '');
        if (hash.length > 1 && window.jQuery) {
            var solapa = document.querySelector('.nav-link[href="' + hash + '"]');
            if (solapa) window.jQuery(solapa).tab('show');
        }

        var btnNotaCancel = $('rd-btn-nota-cancelar');
        if (btnNotaCancel) {
            btnNotaCancel.addEventListener('click', function () { cargarNotaEnForm(null); });
        }
        var btnNota = $('rd-btn-nota-guardar');
        if (btnNota) {
            btnNota.addEventListener('click', function () {
                var datos = datosNotaDesdeForm();
                if (!datos.texto) {
                    notify('Escribí el texto de la nota', 'error');
                    return;
                }
                var id = ($('rd-nota-id') || {}).value;
                var url = id
                    ? String(cfg.urls.actualizarNota).replace('__NID__', id)
                    : cfg.urls.guardarNota;
                postJson(url, datos, id ? 'PUT' : 'POST').then(function (j) {
                    notas = j.notas || [];
                    cargarNotaEnForm(null);
                    renderNotas();
                    notify(id ? 'Nota actualizada (nueva versión)' : 'Nota agregada');
                }).catch(function (e) { notify(e.message, 'error'); });
            });
        }

        var selPer = $('rd-susc-periodicidad');
        if (selPer) {
            selPer.addEventListener('change', toggleCamposPeriodicidad);
            toggleCamposPeriodicidad();
        }
        var btnSuscCancel = $('rd-btn-susc-cancelar');
        if (btnSuscCancel) {
            btnSuscCancel.addEventListener('click', function () { cargarSuscripcionEnForm(null); });
        }
        var btnSusc = $('rd-btn-susc-guardar');
        if (btnSusc) {
            btnSusc.addEventListener('click', function () {
                var datos = datosSuscripcionDesdeForm();
                if (!datos.nombre) {
                    notify('Poné un nombre para el envío', 'error');
                    return;
                }
                var id = ($('rd-susc-id') || {}).value;
                var url = id
                    ? String(cfg.urls.actualizarSuscripcion).replace('__SID__', id)
                    : cfg.urls.guardarSuscripcion;
                if (id) datos.capturar_filtros = true;
                postJson(url, datos, id ? 'PUT' : 'POST').then(function (j) {
                    suscripciones = j.suscripciones || [];
                    cargarSuscripcionEnForm(null);
                    renderSuscripciones();
                    notify(id ? 'Envío actualizado' : 'Envío programado');
                }).catch(function (e) { notify(e.message, 'error'); });
            });
        }

        var btnAcc = $('rd-btn-sync-acceso');
        if (btnAcc) {
            btnAcc.addEventListener('click', function () {
                var raw = ($('rd-acceso-ids') || {}).value || '';
                var ids = raw.split(/[\s,;]+/).map(Number).filter(function (n) { return n > 0; });
                postJson(cfg.urls.syncAccesos, { usuario_ids: ids })
                    .then(function () { notify('Accesos actualizados'); })
                    .catch(function (e) { notify(e.message, 'error'); });
            });
        }
        var selTipo = $('rd-alerta-tipo');
        function toggleCamposEcuacion() {
            var esEcuacion = selTipo && selTipo.value === 'ecuacion';
            document.querySelectorAll('.rd-alerta-ecuacion-campo').forEach(function (el) {
                el.style.display = esEcuacion ? '' : 'none';
            });
        }
        if (selTipo) {
            selTipo.addEventListener('change', toggleCamposEcuacion);
            toggleCamposEcuacion();
        }

        var btnAl = $('rd-btn-add-alerta');
        if (btnAl) {
            btnAl.addEventListener('click', function () {
                postJson(cfg.urls.guardarAlerta, {
                    tipo: ($('rd-alerta-tipo') || {}).value,
                    expresion: ($('rd-alerta-expresion') || {}).value || '',
                    etiqueta: ($('rd-alerta-etiqueta') || {}).value || '',
                    umbral: Number(($('rd-alerta-umbral') || {}).value || 0)
                }).then(function (j) {
                    alertas = j.alertas || [];
                    renderAlertas();
                    notify('Alerta agregada');
                }).catch(function (e) { notify(e.message, 'error'); });
            });
        }
        var btnVal = $('rd-btn-validar');
        if (btnVal) {
            btnVal.addEventListener('click', function () {
                fetch(cfg.urls.validar, { headers: csrfHeaders() })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        var out = $('rd-validar-out');
                        if (!out) return;
                        out.classList.remove('d-none');
                        var issues = j.issues || [];
                        out.textContent = issues.length
                            ? issues.map(function (i) { return '[' + i.nivel + '] ' + i.mensaje; }).join('\n')
                            : 'OK — sin issues.';
                    })
                    .catch(function (e) { notify(e.message || 'Error validar', 'error'); });
            });
        }
        document.querySelectorAll('.rd-diff-ver').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = cfg.urls.diffVersion + '?version_a_id=' + btn.getAttribute('data-id');
                fetch(url, { headers: csrfHeaders() })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        var d = j.diff || {};
                        alert('Diff vs actual:\n' +
                            'Rubros +' + ((d.rubros && d.rubros.added) || []).length +
                            ' -' + ((d.rubros && d.rubros.removed) || []).length +
                            ' ~' + ((d.rubros && d.rubros.changed) || []).length +
                            '\nCuentas +' + ((d.cuentas && d.cuentas.added) || []).length +
                            ' -' + ((d.cuentas && d.cuentas.removed) || []).length);
                    })
                    .catch(function (e) { notify(e.message || 'Error diff', 'error'); });
            });
        });
        var btnCob = $('rd-btn-cobertura-add');
        if (btnCob) {
            btnCob.addEventListener('click', function () {
                var rubroId = window.rdConfig && window.rdConfig._seleccionadoId;
                // disenador keeps seleccionadoId private; leer del árbol activo
                var active = document.querySelector('#rd-tree .rd-tree-item.active');
                rubroId = active ? Number(active.getAttribute('data-id')) : 0;
                if (!rubroId) {
                    notify('Seleccioná un rubro en Estructura', 'error');
                    return;
                }
                var codigos = (cfg.huerfanas || []).map(function (h) { return Number(h.codigo || h.codigo_cuenta || 0); }).filter(Boolean);
                if (!codigos.length) {
                    notify('No hay huérfanas en la muestra', 'error');
                    return;
                }
                if (!confirm('¿Agregar ' + codigos.length + ' cuentas al rubro ' + rubroId + '?')) return;
                postJson(cfg.urls.coberturaAdd, { rubro_id: rubroId, codigos: codigos })
                    .then(function (j) {
                        notify('Agregadas: ' + (j.creadas || j.agregados || 0));
                        location.reload();
                    })
                    .catch(function (e) { notify(e.message, 'error'); });
            });
        }
        renderAlertas();
        renderSuscripciones();
        renderNotas();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
})();
