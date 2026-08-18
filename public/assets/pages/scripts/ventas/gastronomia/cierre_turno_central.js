(function () {
    'use strict';

    var app = document.getElementById('cierre-turno-central-app');
    if (!app) {
        return;
    }

    var cfg = window.CIERRE_TURNO_CENTRAL_GASTRONOMIA || {};
    var apiTurnos = app.getAttribute('data-api-turnos') || '';
    var apiEstado = app.getAttribute('data-api-estado') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var apiDiagnosticarHuecosArca = app.getAttribute('data-api-diagnosticar-huecos-arca') || '';
    var apiEjecutarSaneamientoHuecosArca = app.getAttribute('data-api-ejecutar-saneamiento-huecos-arca') || '';
    var apiConciliacion = app.getAttribute('data-api-conciliacion') || '';
    var apiConciliacionMedio = app.getAttribute('data-api-conciliacion-medio') || '';
    var apiConciliacionNc = app.getAttribute('data-api-conciliacion-nc') || '';
    var apiConciliacionInv = app.getAttribute('data-api-conciliacion-invitaciones') || '';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1' || cfg.puedeCerrar;

    var estadoActual = null;
    var turnoSeleccionadoId = 0;
    var cierreSobranteBase = 0;
    var cierreMediosContadoInicial = {};

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function empresaIdActiva() {
        var el = document.getElementById('empresa_id');
        return el ? String(el.value || '').trim() : '';
    }

    function csrfToken() {
        return cfg.csrf || '';
    }

    function getJson(url) {
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        var full = url + sep + 'empresa_id=' + encodeURIComponent(empresaIdActiva());
        return fetch(full, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function postJson(url, body) {
        var payload = Object.assign({}, body, {
            _token: csrfToken(),
            empresa_id: empresaIdActiva(),
        });
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function urlVerFactura(ventaId) {
        var base = cfg.urlFacturaVerBase || estadoActual?.url_factura_ver_base || '';
        if (!base || !ventaId) {
            return '#';
        }
        var url = base.replace(/\/$/, '') + '/' + ventaId + '/ver';
        if (window.ModoConsulta) {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function renderTablaTurnos(data) {
        var panelJ = document.getElementById('panel-jornada-central');
        var panel = document.getElementById('panel-turnos-central');
        if (!panel) {
            return;
        }

        if (!data.ok) {
            panel.innerHTML = '<div class="alert alert-danger">' + esc(data.error || 'Error') + '</div>';
            return;
        }

        if (panelJ) {
            var jHtml = '<span class="text-muted">Jornada ';
            jHtml += data.jornada_abierta
                ? '<span class="badge badge-success">abierta</span>'
                : '<span class="badge badge-secondary">cerrada / sin jornada activa</span>';
            if (data.fecha_jornada_fmt) {
                jHtml += ' — ' + esc(data.fecha_jornada_fmt);
            }
            jHtml += '</span>';
            panelJ.innerHTML = jHtml;
        }

        var turnos = data.turnos || [];
        if (!turnos.length) {
            panel.innerHTML = '<p class="text-muted mb-0">No hay turnos habilitados pendientes de cierre en esta empresa.</p>';
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-bordered table-sm mb-0" id="tabla-turnos-central">';
        html += '<thead><tr>';
        html += '<th>Terminal</th><th>Punto de venta</th><th>Turno</th><th>Habilitado</th>';
        html += '<th class="text-right">Facturación</th><th class="text-center">Comp.</th><th>Estado</th><th></th>';
        html += '</tr></thead><tbody>';

        turnos.forEach(function (t) {
            html += '<tr>';
            html += '<td>' + esc(t.identificador_pc) + '</td>';
            html += '<td>' + esc(t.puntoventa_etiqueta || t.configuracion_descripcion || '—') + '</td>';
            html += '<td>' + esc(t.turno_nombre) + ' <span class="text-muted">#' + esc(t.turno_operativo_id) + '</span></td>';
            html += '<td>' + esc(t.habilitacion_en) + '</td>';
            html += '<td class="text-right font-weight-bold">$' + fmt(t.total_ventas) + '</td>';
            html += '<td class="text-center">' + esc(t.cantidad_comprobantes) + '</td>';
            html += '<td>';
            if (t.conciliacion_ok) {
                html += '<span class="badge badge-success">Cuadra</span>';
            } else {
                html += '<span class="badge badge-warning">Revisar conciliación</span>';
            }
            if (t.es_ultimo_turno_dia) {
                html += ' <span class="badge badge-info">Último del día</span>';
            }
            html += '</td>';
            html += '<td class="text-nowrap">';
            html += '<button type="button" class="btn btn-sm btn-danger js-abrir-cierre-central"';
            html += ' data-id="' + esc(t.turno_operativo_id) + '"';
            html += ' data-pc="' + esc(t.identificador_pc) + '"';
            html += ' data-turno="' + esc(t.turno_nombre) + '"';
            html += ' data-pv="' + esc(t.puntoventa_etiqueta || '') + '">';
            html += '<i class="fa fa-lock"></i> Cerrar turno</button>';
            html += '</td></tr>';
        });

        html += '</tbody></table></div>';
        panel.innerHTML = html;

        panel.querySelectorAll('.js-abrir-cierre-central').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModalCierre(
                    parseInt(btn.getAttribute('data-id'), 10) || 0,
                    btn.getAttribute('data-pc') || '',
                    btn.getAttribute('data-turno') || '',
                    btn.getAttribute('data-pv') || '',
                );
            });
        });
    }

    function cargarTurnos() {
        var panel = document.getElementById('panel-turnos-central');
        if (panel) {
            panel.innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Cargando turnos…</p>';
        }
        getJson(apiTurnos).then(function (res) {
            renderTablaTurnos(res.data || {});
        });
    }

    function renderAlertas(estado) {
        var el = document.getElementById('alertas-control-central');
        if (!el || !window.GastronomiaTotalesTurnoRender) {
            return;
        }
        el.innerHTML = window.GastronomiaTotalesTurnoRender.renderAlertasControlHtml(estado);
    }

    function renderTotales(estado) {
        var el = document.getElementById('totales-cierre-central');
        if (!el || !window.GastronomiaTotalesTurnoRender || !estado.totales_turno) {
            if (el) {
                el.innerHTML = '';
            }
            return;
        }
        el.innerHTML = window.GastronomiaTotalesTurnoRender.renderTotalesHtml(estado.totales_turno, 'Facturación del turno', {
            conciliarMedios: true,
            arqueoEfectivo: true,
            arqueoMediosCierre: true,
            cuentacaja_efectivo_id: estado.cuentacaja_efectivo_id || 0,
        });
        enlazarConciliacionEnRoot(el);
        enlazarAutoSobrante(el);
        var inpInv = document.getElementById('central_redondeo_invitaciones');
        if (inpInv && estado.totales_turno) {
            inpInv.value = estado.totales_turno.redondeo_invitaciones_sugerido || 0;
        }
        var errBox = document.getElementById('errores-cierre-central');
        if (errBox) {
            var errs = estado.errores_cierre || [];
            if (errs.length) {
                errBox.classList.remove('d-none');
                errBox.innerHTML = errs.join('<br>');
            } else {
                errBox.classList.add('d-none');
                errBox.innerHTML = '';
            }
        }
    }

    function parseDecimalCierre(val) {
        var n = parseFloat(String(val).replace(',', '.'));
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function capturarBaselineContado(root) {
        cierreMediosContadoInicial = {};
        if (!root) {
            return;
        }
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
            if (ccId > 0) {
                cierreMediosContadoInicial[ccId] = parseDecimalCierre(inp.value);
            }
        });
    }

    function sincronizarSobrante(inpSf, root) {
        if (!inpSf || !root) {
            return;
        }
        var suma = 0;
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
            if (ccId <= 0) {
                return;
            }
            var contado = parseDecimalCierre(inp.value);
            var base = Object.prototype.hasOwnProperty.call(cierreMediosContadoInicial, ccId)
                ? cierreMediosContadoInicial[ccId]
                : contado;
            suma += base - contado;
        });
        inpSf.setAttribute('data-syncing-sobrante', '1');
        inpSf.value = String(Math.round((cierreSobranteBase + suma) * 100) / 100);
        inpSf.removeAttribute('data-syncing-sobrante');
    }

    function enlazarAutoSobrante(root) {
        var inpSf = document.getElementById('central_sobrante_faltante');
        if (!inpSf || !root || !root.querySelector('.js-medio-contado-cierre')) {
            return;
        }
        cierreSobranteBase = Math.round((parseFloat(inpSf.value) || 0) * 100) / 100;
        capturarBaselineContado(root);
        function onChange() {
            sincronizarSobrante(inpSf, root);
        }
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            inp.addEventListener('input', onChange);
            inp.addEventListener('blur', onChange);
        });
        onChange();
    }

    function abrirModalConciliacionMedio(modo, ctx) {
        var url = modo === 'nc' ? apiConciliacionNc
            : modo === 'invitaciones' ? apiConciliacionInv
                : apiConciliacionMedio;
        if (!url) {
            return;
        }
        var params = new URLSearchParams({
            empresa_id: empresaIdActiva(),
            turno_operativo_id: String(turnoSeleccionadoId),
        });
        if (modo === 'medio' && ctx.cuentacajaId) {
            params.set('cuentacaja_id', String(ctx.cuentacajaId));
        }
        if (ctx.mozoId) {
            params.set('mozo_id', String(ctx.mozoId));
        }
        getJson(url + '?' + params.toString()).then(function (res) {
            if (!res.ok || !res.data.ok) {
                alert(res.data.error || 'Error al consultar');
                return;
            }
            var tbody = document.getElementById('modal-conciliacion-medio-central-body');
            var titulo = document.getElementById('modal-conciliacion-medio-central-titulo');
            if (!tbody) {
                return;
            }
            var filas = res.data.facturas || res.data.notas_credito || res.data.invitaciones || [];
            titulo.textContent = res.data.medio_nombre || (modo === 'nc' ? 'Notas de crédito' : modo === 'invitaciones' ? 'Invitaciones' : 'Detalle');
            if (!filas.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted p-3">Sin registros.</td></tr>';
            } else {
                tbody.innerHTML = filas.map(function (f) {
                    var extra = f.monto_medio != null ? f.monto_medio
                        : f.monto_nota_credito != null ? f.monto_nota_credito
                            : f.total_facturado;
                    var link = cfg.puedeVerFactura
                        ? ' <a href="' + esc(urlVerFactura(f.venta_id)) + '" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary">Ver</a>'
                        : '';
                    return '<tr>'
                        + '<td>' + esc(f.codigo || '—') + '</td>'
                        + '<td>' + esc(f.hora || '') + '</td>'
                        + '<td>' + esc(f.cliente || '') + '</td>'
                        + '<td>' + esc(f.mozo_nombre || '') + '</td>'
                        + '<td class="text-right">$' + fmt(f.total_facturado) + '</td>'
                        + '<td class="text-right">$' + fmt(extra) + '</td>'
                        + '<td class="text-right">$' + fmt(f.total_cobrado) + '</td>'
                        + '<td>' + link + '</td>'
                        + '</tr>';
                }).join('');
            }
            if (window.jQuery) {
                window.jQuery('#modal-conciliacion-medio-central').modal('show');
            }
        });
    }

    function enlazarConciliacionEnRoot(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.js-conciliar-medio').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModalConciliacionMedio('medio', {
                    cuentacajaId: parseInt(btn.getAttribute('data-cuentacaja-id'), 10) || 0,
                    mozoId: parseInt(btn.getAttribute('data-mozo-id'), 10) || 0,
                });
            });
        });
        root.querySelectorAll('.js-conciliar-notas-credito').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModalConciliacionMedio('nc', {
                    mozoId: parseInt(btn.getAttribute('data-mozo-id'), 10) || 0,
                });
            });
        });
        root.querySelectorAll('.js-conciliar-invitaciones').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModalConciliacionMedio('invitaciones', {
                    mozoId: parseInt(btn.getAttribute('data-mozo-id'), 10) || 0,
                });
            });
        });
    }

    function cargarGrillaConciliacion(page) {
        page = page || 1;
        var solo = document.getElementById('filtro-solo-diferencias-central');
        var cont = document.getElementById('grilla-conciliacion-central');
        if (!cont || !apiConciliacion || turnoSeleccionadoId <= 0) {
            return;
        }
        cont.innerHTML = '<p class="text-muted p-3 mb-0"><i class="fa fa-spinner fa-spin"></i> Cargando…</p>';
        var params = new URLSearchParams({
            empresa_id: empresaIdActiva(),
            turno_operativo_id: String(turnoSeleccionadoId),
            page: String(page),
            solo_diferencias: solo && solo.checked ? '1' : '0',
        });
        getJson(apiConciliacion + '?' + params.toString()).then(function (res) {
            if (!res.ok || !res.data.ok || !window.GastronomiaTotalesTurnoRender) {
                cont.innerHTML = '<p class="alert alert-danger m-2">' + esc(res.data.error || 'Error') + '</p>';
                return;
            }
            cont.innerHTML = window.GastronomiaTotalesTurnoRender.renderGrillaConciliacionHtml(res.data.grilla);
            var pagHtml = window.GastronomiaTotalesTurnoRender.renderPaginacionGrillaHtml(
                res.data.grilla.paginacion,
                'grilla-conciliacion-central',
            );
            if (pagHtml) {
                cont.insertAdjacentHTML('beforeend', pagHtml);
            }
            cont.querySelectorAll('.js-grilla-page').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    cargarGrillaConciliacion(parseInt(btn.getAttribute('data-page'), 10) || 1);
                });
            });
        });
    }

    function abrirModalCierre(turnoId, pc, turnoNombre, pvEtiqueta) {
        turnoSeleccionadoId = turnoId;
        var hidden = document.getElementById('central_turno_operativo_id');
        if (hidden) {
            hidden.value = String(turnoId);
        }
        var resumen = document.getElementById('modal-cierre-turno-resumen');
        if (resumen) {
            resumen.innerHTML = '<strong>Terminal:</strong> ' + esc(pc)
                + ' · <strong>PV:</strong> ' + esc(pvEtiqueta || '—')
                + ' · <strong>Turno:</strong> ' + esc(turnoNombre)
                + ' <span class="text-muted">(#' + esc(turnoId) + ')</span>';
        }
        document.getElementById('totales-cierre-central').innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Cargando totales…</p>';
        document.getElementById('grilla-conciliacion-central').innerHTML = '<p class="text-muted p-3 mb-0 small">Pulse «Ver comprobantes» para cargar el detalle.</p>';

        var params = new URLSearchParams({
            empresa_id: empresaIdActiva(),
            turno_operativo_id: String(turnoId),
        });
        getJson(apiEstado + '?' + params.toString()).then(function (res) {
            if (!res.ok || !res.data.ok) {
                alert(res.data.error || 'No se pudo cargar el turno');
                return;
            }
            estadoActual = res.data;
            renderAlertas(estadoActual);
            renderTotales(estadoActual);
            if (window.jQuery) {
                window.jQuery('#modal-cierre-turno-central').modal('show');
            }
        });
    }

    function confirmarCierre() {
        if (!puedeCerrar || !estadoActual || turnoSeleccionadoId <= 0) {
            return;
        }
        if (estadoActual.totales_turno && !estadoActual.totales_turno.conciliacion_ok) {
            var diffC = Number(estadoActual.totales_turno.diferencia_cobranza || 0);
            var sug = Number(estadoActual.totales_turno.redondeo_invitaciones_sugerido || 0);
            var inpInvVal = parseFloat(document.getElementById('central_redondeo_invitaciones').value) || 0;
            var inpSf = parseFloat(document.getElementById('central_sobrante_faltante').value) || 0;
            var inpRt = parseFloat(document.getElementById('central_redondeo_turno').value) || 0;
            var baseInv = Number(estadoActual.totales_turno.total_invitaciones || 0);
            var residual = diffC - (inpInvVal - baseInv) - inpRt - inpSf;
            if (Math.abs(residual) >= 0.02) {
                alert(
                    'Hay diferencia de conciliación ($' + Math.abs(diffC).toFixed(2) + '). '
                    + 'Use redondeo invitaciones sugerido ($' + sug.toFixed(2) + ') o sobrante/faltante hasta cuadrar.'
                );
                return;
            }
        }
        if (!confirm('¿Confirma el cierre definitivo del turno #' + turnoSeleccionadoId + ' en terminal '
            + (estadoActual.identificador_pc_turno || '') + '?')) {
            return;
        }

        var payload = {
            turno_operativo_id: turnoSeleccionadoId,
            redondeo_invitaciones: document.getElementById('central_redondeo_invitaciones').value,
            redondeo_turno: document.getElementById('central_redondeo_turno').value,
            sobrante_faltante: document.getElementById('central_sobrante_faltante').value,
            observacion_cierre: document.getElementById('central_observacion_cierre').value,
        };
        var root = document.getElementById('totales-cierre-central');
        if (root && window.GastronomiaTotalesTurnoRender
            && window.GastronomiaTotalesTurnoRender.recolectarMediosContadoCierreDesdeRoot) {
            var medios = window.GastronomiaTotalesTurnoRender.recolectarMediosContadoCierreDesdeRoot(root);
            if (medios.length) {
                payload.medios_contado = medios;
            }
        }

        function enviarCierre() {
            postJson(apiCerrar, payload).then(function (res) {
                if (res.ok && res.data.ok) {
                    alert(res.data.mensaje || 'Turno cerrado.');
                    if (res.data.url_comprobante_pdf) {
                        window.open(res.data.url_comprobante_pdf, '_blank', 'noopener');
                    }
                    if (window.jQuery) {
                        window.jQuery('#modal-cierre-turno-central').modal('hide');
                    }
                    cargarTurnos();
                } else {
                    alert(res.data.error || 'No se pudo cerrar el turno.');
                }
            });
        }

        if (window.GastronomiaSaneamientoHuecosArca && apiDiagnosticarHuecosArca && apiEjecutarSaneamientoHuecosArca) {
            window.GastronomiaSaneamientoHuecosArca.interceptarCierre({
                apiDiagnosticar: apiDiagnosticarHuecosArca,
                apiEjecutar: apiEjecutarSaneamientoHuecosArca,
                estado: estadoActual,
                cantidadHuecosEstado: (estadoActual.huecos_arca_pendientes || {}).cantidad || 0,
                payloadExtra: { turno_operativo_id: turnoSeleccionadoId },
                onContinuarCierre: enviarCierre,
            });
        } else {
            enviarCierre();
        }
    }

    var btnGrilla = document.getElementById('btn-grilla-central');
    if (btnGrilla) {
        btnGrilla.addEventListener('click', function () {
            cargarGrillaConciliacion(1);
        });
    }

    var btnSubmit = document.getElementById('btn-submit-cierre-central');
    if (btnSubmit) {
        btnSubmit.addEventListener('click', confirmarCierre);
    }

    var formEmpresa = document.getElementById('form-filtro-cierre-central');
    if (formEmpresa) {
        formEmpresa.addEventListener('submit', function () {
            /* recarga GET */
        });
    }

    cargarTurnos();
})();
