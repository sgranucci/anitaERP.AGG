(function () {
    'use strict';

    var app = document.getElementById('saneamiento-turno-app');
    if (!app) {
        return;
    }

    var cfg = window.SANEAMIENTO_TURNO_GASTRONOMIA || {};
    var apiDiagnostico = app.getAttribute('data-api-diagnostico') || '';
    var apiExtender = app.getAttribute('data-api-extender') || '';
    var apiRetroactivo = app.getAttribute('data-api-retroactivo') || '';
    var apiRecalcular = app.getAttribute('data-api-recalcular') || '';
    var apiCerrarCuentas = app.getAttribute('data-api-cerrar-cuentas') || '';
    var urlInformePdf = app.getAttribute('data-url-informe-pdf') || '';
    var puedeEjecutar = app.getAttribute('data-puede-ejecutar') === '1' || cfg.puedeEjecutar;

    function csrfToken() {
        return cfg.csrf || '';
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({}, body, { _token: csrfToken() })),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function getJson(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function urlVerFactura(ventaId) {
        var base = cfg.urlFacturasDia || '';
        if (!base || !ventaId) {
            return '#';
        }
        var url = base.replace(/\/$/, '') + '/' + ventaId + '/ver';
        if (window.ModoConsulta) {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function renderCuentasPendientes(term) {
        if (!term.cuentas_pendientes_detalle || !term.cuentas_pendientes_detalle.length) {
            return '';
        }
        var abiertas = Number(term.cuentas_abiertas || 0);
        var cerradas = Number(term.cuentas_cerradas_sin_facturar || 0);
        var resumen = [];
        if (abiertas > 0) {
            resumen.push('<strong class="text-warning">' + abiertas + ' abierta(s)</strong> — bloquean el cierre del último turno.');
        }
        if (cerradas > 0) {
            resumen.push('<span class="text-muted">' + cerradas + ' cerrada(s) sin facturar</span> — estado terminal del saneamiento; no bloquean el cierre.');
        }
        var html = '<h6 class="text-warning mt-2">Cuentas no facturadas en esta terminal (' + term.cuentas_pendientes + ')</h6>';
        html += '<p class="small mb-2">' + resumen.join('<br>') + '</p>';
        html += '<p class="small text-muted mb-2">Las cuentas <strong>cerradas sin facturar</strong> no se muestran como mesa ocupada en el facturador.</p>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead><tr><th>ID</th><th>Referencia</th><th>Apertura</th><th>Estado</th><th class="text-center">Ítems</th>';
        html += '<th>Mozo</th><th class="text-right">Subtotal est.</th><th>Facturador</th></tr></thead><tbody>';
        term.cuentas_pendientes_detalle.forEach(function (c) {
            var estadoLbl = c.estado_etiqueta || c.estado || '—';
            var badgeEstado = c.estado === 'cerrada' ? 'badge-secondary' : 'badge-warning';
            var itemsLbl = c.tiene_items ? String(c.lineas || 0) : '0';
            if (!c.tiene_items) {
                itemsLbl += ' <span class="text-muted">(vacía)</span>';
            }
            html += '<tr>';
            html += '<td>' + esc(c.id) + '</td>';
            html += '<td>' + esc(c.etiqueta) + '</td>';
            html += '<td>' + esc(c.apertura_en_fmt || c.apertura_en || '—') + '</td>';
            html += '<td><span class="badge ' + badgeEstado + '">' + esc(estadoLbl) + '</span></td>';
            html += '<td class="text-center">' + itemsLbl + '</td>';
            html += '<td>' + esc(c.mozo || '—') + '</td>';
            html += '<td class="text-right">$' + fmt(c.subtotal) + '</td>';
            html += '<td class="small">' + esc(c.acceso_facturador || '—') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function renderTerminal(term) {
        var html = '<div class="card mb-3 border-secondary">';
        html += '<div class="card-header py-2"><strong>Terminal: ' + esc(term.identificador_pc) + '</strong>';
        if (term.turno_habilitado) {
            html += ' <span class="badge badge-success ml-2">Turno habilitado #' + esc(term.turno_operativo_activo_id) + '</span>';
        }
        if (term.cantidad_huerfanas > 0) {
            html += ' <span class="badge badge-danger ml-1">' + term.cantidad_huerfanas + ' huérfana(s)</span>';
        } else {
            html += ' <span class="badge badge-success ml-1">Sin huérfanas</span>';
        }
        if (term.puede_habilitar_turno) {
            html += ' <span class="badge badge-info ml-1">Puede habilitar turno</span>';
        }
        var abiertas = Number(term.cuentas_abiertas || 0);
        var cerradasInactivas = Number(term.cuentas_cerradas_sin_facturar || 0);
        if (abiertas > 0) {
            html += ' <span class="badge badge-warning ml-1">' + abiertas + ' abierta(s) sin facturar</span>';
        }
        if (cerradasInactivas > 0) {
            html += ' <span class="badge badge-secondary ml-1" title="Estado terminal: no bloquean cierre">'
                + cerradasInactivas + ' cerrada(s) sin facturar</span>';
        }
        html += '</div><div class="card-body">';

        html += renderCuentasPendientes(term);

        if (term.facturas_huerfanas && term.facturas_huerfanas.length) {
            html += '<h6 class="text-danger">Facturas huérfanas</h6>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
            html += '<thead><tr><th>Comprobante</th><th>Hora</th><th>Cliente</th><th class="text-right">Total</th><th></th></tr></thead><tbody>';
            term.facturas_huerfanas.forEach(function (f) {
                html += '<tr>';
                html += '<td>' + esc(f.codigo || '#' + f.venta_id) + '</td>';
                html += '<td>' + esc(f.emitido_en || f.hora) + '</td>';
                html += '<td>' + esc(f.cliente) + '</td>';
                html += '<td class="text-right">$' + fmt(f.total) + '</td>';
                html += '<td><a href="' + esc(urlVerFactura(f.venta_id)) + '" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary">Ver</a></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }

        if (term.turnos && term.turnos.length) {
            html += '<h6 class="mt-3">Turnos de la jornada</h6>';
            html += '<ul class="list-group list-group-flush mb-2">';
            term.turnos.forEach(function (t) {
                html += '<li class="list-group-item py-2 d-flex justify-content-between align-items-center flex-wrap">';
                html += '<span>#' + t.id + ' ' + esc(t.turno_nombre) + ' — ' + esc(t.habilitacion_en) + ' → ' + esc(t.cierre_en);
                html += ' · $' + fmt(t.monto_facturacion_turno) + '</span>';
                if (puedeEjecutar) {
                    html += '<button type="button" class="btn btn-xs btn-outline-primary js-recalcular" data-id="' + t.id + '">Recalcular totales</button>';
                }
                html += '</li>';
            });
            html += '</ul>';
        }

        if (term.sugerencias && term.sugerencias.length && puedeEjecutar) {
            html += '<h6>Acciones sugeridas</h6>';
            term.sugerencias.forEach(function (s) {
                html += '<div class="alert alert-light border py-2 mb-2">';
                html += esc(s.detalle);
                if (s.accion === 'extender_cierre' && s.turno_operativo_id) {
                    html += ' <button type="button" class="btn btn-sm btn-warning ml-2 js-extender-cierre" data-id="' + s.turno_operativo_id + '">Extender cierre</button>';
                }
                if (s.accion === 'crear_retroactivo') {
                    html += ' <button type="button" class="btn btn-sm btn-danger ml-2 js-crear-retroactivo" data-pc="' + esc(term.identificador_pc) + '">Crear turno retroactivo</button>';
                }
                if (s.accion === 'cerrar_cuentas' && s.turno_operativo_id) {
                    html += ' <button type="button" class="btn btn-sm btn-outline-danger ml-2 js-cerrar-cuentas"';
                    html += ' data-turno-id="' + s.turno_operativo_id + '"';
                    html += ' data-confirmacion="' + esc(s.confirmacion || '') + '"';
                    html += ' data-cantidad="' + esc(s.cantidad || 0) + '">Cerrar sin facturar cuentas abiertas</button>';
                }
                html += '</div>';
            });
        }

        html += '</div></div>';
        return html;
    }

    function renderDiagnostico(data) {
        var panel = document.getElementById('panel-diagnostico');
        if (!panel) {
            return;
        }
        if (!data.ok) {
            panel.innerHTML = '<div class="alert alert-danger">' + esc(data.error || 'Error') + '</div>';
            return;
        }

        var j = data.jornada || {};
        var html = '<div class="alert alert-info py-2">';
        html += 'Jornada #' + esc(j.id) + ' — ' + esc(j.fecha_jornada_fmt || j.fecha_jornada);
        html += j.abierta ? ' <span class="badge badge-success">Abierta</span>' : ' <span class="badge badge-secondary">Cerrada</span>';
        html += '</div>';

        if (!data.terminales || !data.terminales.length) {
            html += '<p class="text-muted">No hay terminales configuradas para esta empresa.</p>';
        } else {
            data.terminales.forEach(function (term) {
                html += renderTerminal(term);
            });
        }

        panel.innerHTML = html;
        enlazarAcciones(panel);
    }

    function cerrarCuentasConConfirmacion(turnoId, confirmacionEsperada, cantidad) {
        var msg = 'Se cerrarán sin facturar las cuentas ABIERTAS de la terminal.\n';
        msg += 'Cuentas abiertas a cerrar: ' + cantidad + '.\n';
        msg += '(Las cerradas sin facturar son estado terminal y no requieren acción.)\n\n';
        msg += 'Para confirmar escriba exactamente:\n' + confirmacionEsperada;
        var ingresado = prompt(msg, '');
        if (ingresado === null) {
            return;
        }
        if (ingresado.trim() !== confirmacionEsperada) {
            alert('Confirmación incorrecta. Debe escribir: ' + confirmacionEsperada);
            return;
        }
        var motivo = prompt('Motivo u observación (opcional):', '') || '';
        postJson(apiCerrarCuentas, {
            turno_operativo_id: turnoId,
            confirmacion: ingresado.trim(),
            motivo: motivo,
        }).then(function (res) {
            alert(res.data.mensaje || res.data.error || 'Listo');
            cargarDiagnostico();
        });
    }

    function enlazarAcciones(root) {
        root.querySelectorAll('.js-extender-cierre').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                if (!confirm('¿Extender el cierre del turno #' + id + ' para cubrir facturas huérfanas?')) {
                    return;
                }
                postJson(apiExtender, { turno_operativo_id: id }).then(function (res) {
                    alert(res.data.mensaje || res.data.error || 'Listo');
                    cargarDiagnostico();
                });
            });
        });

        root.querySelectorAll('.js-crear-retroactivo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pc = btn.getAttribute('data-pc');
                var turnos = cfg.turnos || [];
                if (!turnos.length) {
                    alert('No hay turnos maestro configurados.');
                    return;
                }
                var opts = turnos.map(function (t, i) {
                    return (i + 1) + ') ' + t.nombre + ' (id ' + t.id + ')';
                }).join('\n');
                var sel = prompt('Indique número de turno maestro:\n' + opts, '1');
                if (!sel) {
                    return;
                }
                var idx = parseInt(sel, 10) - 1;
                if (idx < 0 || idx >= turnos.length) {
                    alert('Selección inválida.');
                    return;
                }
                if (!confirm('¿Crear turno retroactivo cerrado en ' + pc + '?')) {
                    return;
                }
                postJson(apiRetroactivo, {
                    identificador_pc: pc,
                    empresa_id: document.getElementById('empresa_id')?.value || '',
                    turno_gastronomia_id: turnos[idx].id,
                    monto_habilitacion: 0,
                }).then(function (res) {
                    alert(res.data.mensaje || res.data.error || 'Listo');
                    cargarDiagnostico();
                });
            });
        });

        root.querySelectorAll('.js-recalcular').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                postJson(apiRecalcular, { turno_operativo_id: id }).then(function (res) {
                    alert(res.data.mensaje || res.data.error || 'Listo');
                    cargarDiagnostico();
                });
            });
        });

        root.querySelectorAll('.js-cerrar-cuentas').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cerrarCuentasConConfirmacion(
                    btn.getAttribute('data-turno-id'),
                    btn.getAttribute('data-confirmacion') || '',
                    btn.getAttribute('data-cantidad') || '0',
                );
            });
        });
    }

    function cargarDiagnostico() {
        var empresaId = document.getElementById('empresa_id')?.value || '';
        var pc = document.getElementById('identificador_pc')?.value || '';
        var url = apiDiagnostico + '?empresa_id=' + encodeURIComponent(empresaId);
        if (pc) {
            url += '&identificador_pc=' + encodeURIComponent(pc);
        }
        var panel = document.getElementById('panel-diagnostico');
        if (panel) {
            panel.innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Analizando…</p>';
        }
        return getJson(url).then(function (res) {
            renderDiagnostico(res.data);
        });
    }

    var btnPdf = document.getElementById('btn-exportar-informe-pdf');
    if (btnPdf && urlInformePdf) {
        btnPdf.addEventListener('click', function () {
            var empresaId = document.getElementById('empresa_id')?.value || '';
            var pc = document.getElementById('identificador_pc')?.value || '';
            var url = urlInformePdf + '?empresa_id=' + encodeURIComponent(empresaId) + '&inline=1';
            if (pc) {
                url += '&identificador_pc=' + encodeURIComponent(pc);
            }
            window.open(url, '_blank', 'noopener');
        });
    }

    if (document.getElementById('empresa_id')) {
        cargarDiagnostico();
    }
})();
