(function () {
    'use strict';

    var app = document.getElementById('habilitacion-turno-app');
    if (!app || (window.HABILITACION_TURNO_ESTACIONAMIENTO && window.HABILITACION_TURNO_ESTACIONAMIENTO.modoCajaDirecto)) {
        return;
    }

    var apiEstado = app.getAttribute('data-api-estado') || '';
    var apiHabilitar = app.getAttribute('data-api-habilitar') || '';
    var apiActualizarMontoHabilitacion = app.getAttribute('data-api-actualizar-monto-habilitacion') || '';
    var apiCierreParcial = app.getAttribute('data-api-cierre-parcial') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var apiAnularCierre = app.getAttribute('data-api-anular-cierre') || '';
    var apiConciliacionTurno = app.getAttribute('data-api-conciliacion-turno') || '';
    var apiConciliacionMedio = app.getAttribute('data-api-conciliacion-medio') || '';
    var apiConciliacionNotasCredito = app.getAttribute('data-api-conciliacion-notas-credito') || '';
    var apiConciliacionInvitaciones = app.getAttribute('data-api-conciliacion-invitaciones') || '';
    var puedeHabilitar = app.getAttribute('data-puede-habilitar') === '1';
    var puedeCierreParcial = app.getAttribute('data-puede-cierre-parcial') === '1';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1';
    var puedeAnularCierre = app.getAttribute('data-puede-anular-cierre') === '1';
    var puedeModificarMontoHabilitacion = app.getAttribute('data-puede-modificar-monto-habilitacion') === '1';
    var puedeVerFactura = app.getAttribute('data-puede-ver-factura') === '1';
    var accion = app.getAttribute('data-accion') || '';
    var cfgGlobal = window.HABILITACION_TURNO_ESTACIONAMIENTO || {};
    var urlFacturaVerBase = cfgGlobal.urlFacturaVerBase || '';
    var urlPdfParcialBase = cfgGlobal.urlPdfParcialBase || '';

    if (!accion && cfgGlobal.accion) {
        accion = String(cfgGlobal.accion);
    }

    var estadoActual = null;
    var grillaMetaPorContenedor = {};
    var cierreSobranteBase = 0;
    var cierreMediosContadoInicial = {};

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function csrfToken() {
        if (cfgGlobal.csrf) {
            return String(cfgGlobal.csrf);
        }
        return app.getAttribute('data-csrf') || '';
    }

    function empresaIdActiva() {
        var el = document.getElementById('empresa_id');
        return el ? String(el.value || '').trim() : '';
    }

    function urlConEmpresa(url) {
        var eid = empresaIdActiva();
        if (!eid || !url) {
            return url;
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + 'empresa_id=' + encodeURIComponent(eid);
    }

    function postJson(url, body) {
        var token = csrfToken();
        var payload = Object.assign({}, body, { _token: token });
        var eid = empresaIdActiva();
        if (eid) {
            payload.empresa_id = eid;
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
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

    function mensajeErrorRespuesta(res) {
        if (!res) {
            return 'Sin respuesta del servidor';
        }
        var d = res.data || {};
        if (d.error) {
            return String(d.error);
        }
        if (d.message) {
            return String(d.message);
        }
        if (!res.ok) {
            return 'HTTP ' + (res.status || '') + (res.status === 403 ? ' — Sin permiso o sesión expirada' : '');
        }
        if (d.ok === false) {
            return 'Operación rechazada';
        }
        return 'Error desconocido';
    }

    function respuestaApiOk(res) {
        return res && res.ok && res.data && res.data.ok !== false;
    }

    function getJson(url) {
        return fetch(urlConEmpresa(url), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    data = {
                        ok: false,
                        error: r.ok
                            ? 'Respuesta no válida del servidor'
                            : (text && text.length < 300 ? text.replace(/<[^>]+>/g, ' ').trim() : 'HTTP ' + r.status),
                    };
                }
                return { ok: r.ok, status: r.status, data: data };
            });
        });
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderTotalesHtml(totales, titulo, opciones) {
        if (window.EstacionamientoTotalesTurnoRender) {
            return window.EstacionamientoTotalesTurnoRender.renderTotalesHtml(totales, titulo, opciones);
        }
        return '';
    }

    function urlVerFactura(ventaId) {
        var base = urlFacturaVerBase
            || estadoActual?.url_factura_ver_base
            || window.ESTACIONAMIENTO_FACTURA_VER_BASE
            || '';
        if (!base || !ventaId) {
            return '#';
        }
        var url = base.replace(/\/$/, '') + '/' + ventaId + '/ver';
        if (window.ModoConsulta) {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function sincronizarUrlFacturaVerBase(extra) {
        var base = urlFacturaVerBase || estadoActual?.url_factura_ver_base || extra || '';
        if (base) {
            window.ESTACIONAMIENTO_FACTURA_VER_BASE = base;
        }
    }

    function activarSolapaCierre(estado) {
        var wrap = document.getElementById('wrap-solapas-cierre');
        if (!wrap) {
            return;
        }
        wrap.classList.remove('d-none');

        var parciales = Number(estado.cierres_parciales || 0);
        var tabParcial = document.getElementById('tab-parcial-link');
        var tabDef = document.getElementById('tab-definitivo-link');

        if (parciales > 0) {
            if (tabDef && typeof $ !== 'undefined') {
                $('#tab-definitivo-link').tab('show');
            } else if (tabDef) {
                tabDef.classList.add('active');
                document.getElementById('tab-cierre-definitivo')?.classList.add('show', 'active');
                tabParcial?.classList.remove('active');
                document.getElementById('tab-cierre-parcial')?.classList.remove('show', 'active');
            }
        } else {
            if (tabParcial && typeof $ !== 'undefined') {
                $('#tab-parcial-link').tab('show');
            }
        }

        if (accion === 'cierre_definitivo' && tabDef && typeof $ !== 'undefined') {
            $('#tab-definitivo-link').tab('show');
        } else if (accion === 'cierre_parcial' && tabParcial && typeof $ !== 'undefined') {
            $('#tab-parcial-link').tab('show');
        }
    }

    function renderPanelTurnoHabilitado(estado) {
        var html = '<div class="card border mb-3 est-panel-turno shadow-sm"><div class="card-body py-3 est-turno-resumen-wrap">';
        html += '<div class="row align-items-center">';
        html += '<div class="col-md-3 col-6 mb-1 mb-md-0"><span class="text-muted d-block">Turno</span><strong>' + esc(estado.turno_nombre || '—') + '</strong></div>';
        html += '<div class="col-md-3 col-6 mb-1 mb-md-0"><span class="text-muted d-block">Usuario</span><strong>' + esc(estado.usuario_habilitado || '—') + '</strong></div>';
        html += '<div class="col-md-2 col-6"><span class="text-muted d-block">Jornada</span><strong>' + esc(estado.fecha_jornada_fmt || estado.fecha_jornada || '—') + '</strong></div>';
        html += '<div class="col-md-2 col-6"><span class="text-muted d-block">Habilitado</span><strong>' + esc(estado.habilitacion_en_fmt || estado.habilitacion_en || '—') + '</strong></div>';
        html += '<div class="col-md-2 col-12">';
        html += '<span class="text-muted d-block">Monto habilitación</span>';
        if (puedeModificarMontoHabilitacion && apiActualizarMontoHabilitacion) {
            html += '<div class="d-flex flex-wrap align-items-center mt-1" style="gap: 4px;">';
            html += '<input type="number" step="0.01" min="0" class="form-control form-control-sm" id="input-monto-habilitacion-edit" ';
            html += 'style="max-width: 130px;" value="' + esc(String(estado.monto_habilitacion != null ? estado.monto_habilitacion : 0)) + '" title="Corregir monto de habilitación"/>';
            html += '<button type="button" class="btn btn-sm btn-primary" id="btn-guardar-monto-habilitacion" title="Persistir corrección del monto">';
            html += '<i class="fa fa-save"></i> Guardar</button>';
            html += '</div>';
        } else {
            html += '<strong>$' + fmt(estado.monto_habilitacion) + '</strong>';
        }
        html += '<small class="text-muted d-block mt-1">' + (estado.cierres_parciales || 0) + ' cierre(s) parcial(es)</small>';
        html += '</div>';
        html += '</div>';
        if (estado.totales_turno) {
            var ok = estado.totales_turno.conciliacion_ok;
            html += '<div class="mt-2 pt-2 border-top">';
            html += '<span class="badge ' + (ok ? 'badge-success' : 'badge-warning') + '">';
            html += ok ? 'Conciliación OK' : 'Conciliación con diferencia';
            html += '</span>';
            var nConItems = Number(estado.cuentas_abiertas_con_items || estado.tickets_pendientes_ingreso || 0);
            if (nConItems > 0) {
                var badgeCuentas = estado.es_ultimo_turno_dia ? 'badge-danger' : 'badge-info';
                html += ' <span class="badge ' + badgeCuentas + '" title="Abiertas con consumos: bloquean cierre del último turno del día">'
                    + nConItems + ' abierta(s) con consumos</span>';
            }
            var nVacias = Number(estado.cuentas_abiertas_vacias || 0);
            if (nVacias > 0) {
                html += ' <span class="badge badge-info" title="Sin ítems: se descartan automáticamente al cerrar turno/jornada">'
                    + nVacias + ' abierta(s) sin ítems (auto-descartan)</span>';
            }
            if (Number(estado.cuentas_cerradas_sin_facturar || 0) > 0) {
                html += ' <span class="badge badge-secondary" title="Estado terminal por saneamiento: no bloquean cierre">'
                    + estado.cuentas_cerradas_sin_facturar + ' cerrada(s) sin facturar</span>';
            }
            html += '</div>';
        }
        html += '</div></div>';
        return html;
    }

    function enlazarEdicionMontoHabilitacion(estado) {
        if (!puedeModificarMontoHabilitacion || !apiActualizarMontoHabilitacion) {
            return;
        }
        var btn = document.getElementById('btn-guardar-monto-habilitacion');
        var inp = document.getElementById('input-monto-habilitacion-edit');
        if (!btn || !inp || !estado || !estado.turno_operativo_id) {
            return;
        }
        if (btn.getAttribute('data-monto-hab-bound') === '1') {
            return;
        }
        btn.setAttribute('data-monto-hab-bound', '1');
        btn.addEventListener('click', function () {
            var montoNuevo = parseDecimalCierre(inp.value);
            var montoActual = Math.round((parseFloat(estado.monto_habilitacion) || 0) * 100) / 100;
            if (montoNuevo < 0) {
                alert('El monto de habilitación no puede ser negativo.');
                return;
            }
            if (montoNuevo === montoActual) {
                alert('El monto indicado es igual al actual.');
                return;
            }
            if (!confirm(
                '¿Actualizar el monto de habilitación de $' + fmt(montoActual)
                + ' a $' + fmt(montoNuevo) + '?'
            )) {
                return;
            }
            btn.disabled = true;
            postJson(apiActualizarMontoHabilitacion, {
                turno_operativo_id: estado.turno_operativo_id,
                monto_habilitacion: montoNuevo,
            }).then(function (res) {
                btn.disabled = false;
                if (res.data.ok) {
                    alert(res.data.mensaje || 'Monto actualizado');
                    cargarEstado();
                } else {
                    alert(res.data.error || mensajeErrorRespuesta(res));
                }
            }).catch(function () {
                btn.disabled = false;
                alert('Error al guardar el monto de habilitación.');
            });
        });
    }

    function renderAlertasControl(containerId, estado) {
        var box = document.getElementById(containerId);
        if (!box || !window.EstacionamientoTotalesTurnoRender) {
            return;
        }
        box.innerHTML = window.EstacionamientoTotalesTurnoRender.renderAlertasControlHtml(estado);
    }

    function renderNumeracionFiscal(estado) {
        var el = document.getElementById('panel-numeracion-fiscal-turno');
        if (!el || !window.EstacionamientoTotalesTurnoRender) {
            return;
        }
        var html = window.EstacionamientoTotalesTurnoRender.renderNumeracionFiscalHtml(estado.numeracion_fiscal);
        el.innerHTML = html;
        el.classList.toggle('d-none', !html);
    }

    function poblarTabParcial(estado) {
        renderAlertasControl('alertas-control-parcial', estado);

        var el = document.getElementById('totales-tab-parcial');
        if (el && estado.totales_turno) {
            el.innerHTML = renderTotalesHtml(estado.totales_turno, 'Facturación del turno (vista parcial)', {
                conciliarMedios: true,
            });
            enlazarBotonesConciliacion(el);
        }

        var lista = document.getElementById('lista-cierres-parciales');
        if (lista && window.EstacionamientoTotalesTurnoRender) {
            lista.innerHTML = window.EstacionamientoTotalesTurnoRender.renderListaParcialesHtml(
                estado.cierres_parciales_lista || [],
                urlPdfParcialBase
            );
        }

        mostrarPlaceholderGrilla('grilla-conciliacion-parcial');
    }

    function parseDecimalCierre(str) {
        if (str == null || str === '') {
            return 0;
        }
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function destacarCampoAutoActualizado(el) {
        if (!el) {
            return;
        }
        el.classList.add('est-campo-auto-actualizado');
        window.setTimeout(function () {
            el.classList.remove('est-campo-auto-actualizado');
        }, 1800);
    }

    function capturarBaselineContadoCierre(root) {
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

    function sincronizarSobranteCierrePorMedios(inpSf, root) {
        if (!inpSf || !root) {
            return;
        }
        var sumaCompensacion = 0;
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
            if (ccId <= 0) {
                return;
            }
            var contado = parseDecimalCierre(inp.value);
            var baseContado = Object.prototype.hasOwnProperty.call(cierreMediosContadoInicial, ccId)
                ? cierreMediosContadoInicial[ccId]
                : contado;
            sumaCompensacion += baseContado - contado;
        });
        var nuevo = Math.round((cierreSobranteBase + sumaCompensacion) * 100) / 100;
        inpSf.setAttribute('data-syncing-sobrante', '1');
        inpSf.value = String(nuevo);
        inpSf.removeAttribute('data-syncing-sobrante');
        destacarCampoAutoActualizado(inpSf);
    }

    function enlazarAutoSobranteCierreDefinitivo(root) {
        var inpSf = document.getElementById('sobrante_faltante');
        if (!inpSf || !root || !root.querySelector('.js-medio-contado-cierre')) {
            return;
        }

        cierreSobranteBase = Math.round((parseFloat(inpSf.value) || 0) * 100) / 100;
        capturarBaselineContadoCierre(root);

        function onMedioChange() {
            sincronizarSobranteCierrePorMedios(inpSf, root);
        }

        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            if (inp.getAttribute('data-auto-sobrante-bound') === '1') {
                return;
            }
            inp.setAttribute('data-auto-sobrante-bound', '1');
            inp.addEventListener('input', onMedioChange);
            inp.addEventListener('blur', onMedioChange);
        });
        onMedioChange();

        if (inpSf.getAttribute('data-baseline-sobrante-bound') !== '1') {
            inpSf.setAttribute('data-baseline-sobrante-bound', '1');
            inpSf.addEventListener('input', function () {
                if (inpSf.getAttribute('data-syncing-sobrante') === '1') {
                    return;
                }
                var sumaCompensacion = 0;
                root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
                    var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
                    if (ccId <= 0) {
                        return;
                    }
                    var contado = parseDecimalCierre(inp.value);
                    var baseContado = Object.prototype.hasOwnProperty.call(cierreMediosContadoInicial, ccId)
                        ? cierreMediosContadoInicial[ccId]
                        : contado;
                    sumaCompensacion += baseContado - contado;
                });
                var actual = Math.round((parseFloat(inpSf.value) || 0) * 100) / 100;
                cierreSobranteBase = Math.round((actual - sumaCompensacion) * 100) / 100;
            });
        }
    }

    function poblarTabDefinitivo(estado) {
        var el = document.getElementById('totales-tab-definitivo');
        if (!el) {
            return;
        }
        renderAlertasControl('alertas-control-definitivo', estado);
        var bloques = [];
        if (estado.totales_turno && window.EstacionamientoTotalesTurnoRender) {
            bloques.push({
                html: renderTotalesHtml(estado.totales_turno, 'Facturación del turno', {
                    conciliarMedios: true,
                    arqueoEfectivo: true,
                    arqueoMediosCierre: true,
                    cuentacaja_efectivo_id: estado.cuentacaja_efectivo_id || 0,
                }),
            });
        }
        if (estado.totales_dia && window.EstacionamientoTotalesTurnoRender) {
            bloques.push({
                html: renderTotalesHtml(estado.totales_dia, 'Acumulado del día (esta PC)', { conciliarMedios: false }),
            });
        }
        el.innerHTML = window.EstacionamientoTotalesTurnoRender
            ? window.EstacionamientoTotalesTurnoRender.renderTotalesResumenHtml(bloques)
            : '';

        if (window.EstacionamientoTotalesTurnoRender && window.EstacionamientoTotalesTurnoRender.enlazarArqueoEfectivoCierre) {
            window.EstacionamientoTotalesTurnoRender.enlazarArqueoEfectivoCierre(el);
        }
        enlazarAutoSobranteCierreDefinitivo(el);

        var inpInv = document.getElementById('redondeo_invitaciones');
        if (inpInv && estado.totales_turno) {
            inpInv.value = estado.totales_turno.redondeo_invitaciones_sugerido || 0;
        }

        var erroresBox = document.getElementById('errores-cierre-turno');
        if (erroresBox) {
            var errs = estado.errores_cierre || [];
            if (errs.length) {
                erroresBox.classList.remove('d-none');
                erroresBox.innerHTML = errs.join('<br>');
            } else {
                erroresBox.classList.add('d-none');
                erroresBox.innerHTML = '';
            }
        }

        enlazarBotonesConciliacion(el);
        mostrarPlaceholderGrilla('grilla-conciliacion-turno');
    }

    function soloDiferenciasParaContenedor(containerId) {
        var chk = document.querySelector('.js-filtro-solo-diferencias[data-grilla-target="' + containerId + '"]');
        return chk ? chk.checked : false;
    }

    function urlConciliacionTurno(page, containerId) {
        var solo = soloDiferenciasParaContenedor(containerId) ? '1' : '0';
        return apiConciliacionTurno + '?page=' + encodeURIComponent(page) + '&solo_diferencias=' + solo;
    }

    function mostrarPlaceholderGrilla(containerId) {
        var cont = document.getElementById(containerId);
        var meta = grillaMetaPorContenedor[containerId];
        if (!cont || !meta || !window.EstacionamientoTotalesTurnoRender) {
            return;
        }
        cont.innerHTML = window.EstacionamientoTotalesTurnoRender.renderGrillaPlaceholderHtml(meta);
        cont.querySelectorAll('.js-cargar-grilla-comprobantes').forEach(function (btn) {
            btn.setAttribute('data-grilla-target', containerId);
        });
        enlazarControlesGrilla(cont, containerId);
    }

    function pintarGrillaEnContenedor(containerId, grilla) {
        var cont = document.getElementById(containerId);
        if (!cont || !grilla || !window.EstacionamientoTotalesTurnoRender) {
            return;
        }
        cont.innerHTML = window.EstacionamientoTotalesTurnoRender.renderGrillaConciliacionHtml(grilla);
        var pag = grilla.paginacion;
        if (pag) {
            var pagHtml = window.EstacionamientoTotalesTurnoRender.renderPaginacionGrillaHtml(pag, containerId);
            cont.querySelectorAll('.est-grilla-paginacion, .est-grilla-paginacion-footer').forEach(function (el) {
                el.innerHTML = pagHtml;
                el.setAttribute('data-grilla-container', containerId);
            });
        }
        enlazarControlesGrilla(cont, containerId);
        enlazarBotonesConciliacion(cont);
    }

    function enlazarControlesGrilla(root, containerId) {
        root.querySelectorAll('.js-cargar-grilla-comprobantes').forEach(function (btn) {
            if (btn.dataset.boundCargarGrilla) {
                return;
            }
            btn.dataset.boundCargarGrilla = '1';
            btn.addEventListener('click', function () {
                cargarPaginaGrilla(containerId, 1);
            });
        });
        root.querySelectorAll('.js-grilla-pagina').forEach(function (btn) {
            if (btn.dataset.boundPagGrilla) {
                return;
            }
            btn.dataset.boundPagGrilla = '1';
            btn.addEventListener('click', function () {
                var p = parseInt(btn.getAttribute('data-page'), 10);
                if (p > 0) {
                    cargarPaginaGrilla(containerId, p);
                }
            });
        });
    }

    function cargarMetaGrilla(containerId) {
        if (!apiConciliacionTurno) {
            return Promise.resolve();
        }
        return getJson(apiConciliacionTurno + '?page=0').then(function (res) {
            if (!respuestaApiOk(res)) {
                var c = document.getElementById(containerId);
                if (c) {
                    c.innerHTML = '<div class="alert alert-danger m-2 mb-0 small"><strong>Conciliación:</strong> '
                        + mensajeErrorRespuesta(res) + '</div>';
                }
                return;
            }
            if (res.data.url_factura_ver_base) {
                sincronizarUrlFacturaVerBase(res.data.url_factura_ver_base);
            }
            grillaMetaPorContenedor[containerId] = res.data.grilla || {};
            mostrarPlaceholderGrilla(containerId);
        }).catch(function (err) {
            var c = document.getElementById(containerId);
            if (c) {
                c.innerHTML = '<div class="alert alert-danger m-2 mb-0 small">' + (err.message || 'Error de red') + '</div>';
            }
        });
    }

    function cargarMetaGrillas() {
        return Promise.all([
            cargarMetaGrilla('grilla-conciliacion-parcial'),
            cargarMetaGrilla('grilla-conciliacion-turno'),
        ]);
    }

    function cargarPaginaGrilla(containerId, page) {
        if (!apiConciliacionTurno) {
            return Promise.resolve();
        }
        var cont = document.getElementById(containerId);
        if (cont) {
            cont.innerHTML = '<p class="text-muted p-3 mb-0"><i class="fa fa-spinner fa-spin"></i> Cargando comprobantes…</p>';
        }
        return getJson(urlConciliacionTurno(page, containerId)).then(function (res) {
            if (!cont) {
                return;
            }
            if (!respuestaApiOk(res)) {
                cont.innerHTML = '<div class="alert alert-danger m-2 mb-0 small"><strong>Conciliación:</strong> '
                    + mensajeErrorRespuesta(res) + '</div>';
                return;
            }
            if (res.data.url_factura_ver_base) {
                sincronizarUrlFacturaVerBase(res.data.url_factura_ver_base);
            }
            var grilla = res.data.grilla || {};
            if (page === 0) {
                grillaMetaPorContenedor[containerId] = grilla;
                mostrarPlaceholderGrilla(containerId);
            } else {
                pintarGrillaEnContenedor(containerId, grilla);
            }
        }).catch(function (err) {
            if (cont) {
                cont.innerHTML = '<div class="alert alert-danger m-2 mb-0 small">' + (err.message || 'Error de red') + '</div>';
            }
        });
    }

    function enlazarBotonesConciliacion(container) {
        var root = container || document;
        root.querySelectorAll('.js-conciliar-medio').forEach(function (btn) {
            if (btn.dataset.boundConciliar) {
                return;
            }
            btn.dataset.boundConciliar = '1';
            btn.addEventListener('click', function () {
                var rawUsuarioId = btn.getAttribute('data-usuario-habilitado-id');
                var usuarioId = rawUsuarioId !== null && rawUsuarioId !== '' ? parseInt(rawUsuarioId, 10) : null;
                abrirModalMedio(
                    parseInt(btn.getAttribute('data-cuentacaja-id'), 10),
                    btn.getAttribute('data-medio-nombre') || '',
                    isNaN(usuarioId) ? null : usuarioId,
                    btn.getAttribute('data-usuario-habilitado-nombre') || ''
                );
            });
        });
        root.querySelectorAll('.js-conciliar-nc').forEach(function (btn) {
            if (btn.dataset.boundConciliarNc) {
                return;
            }
            btn.dataset.boundConciliarNc = '1';
            btn.addEventListener('click', function () {
                var rawUsuarioId = btn.getAttribute('data-usuario-habilitado-id');
                var usuarioId = rawUsuarioId !== null && rawUsuarioId !== '' ? parseInt(rawUsuarioId, 10) : null;
                abrirModalNotasCredito(
                    isNaN(usuarioId) ? null : usuarioId,
                    btn.getAttribute('data-usuario-habilitado-nombre') || ''
                );
            });
        });
        root.querySelectorAll('.js-conciliar-invitaciones').forEach(function (btn) {
            if (btn.dataset.boundConciliarInv) {
                return;
            }
            btn.dataset.boundConciliarInv = '1';
            btn.addEventListener('click', function () {
                var rawUsuarioId = btn.getAttribute('data-usuario-habilitado-id');
                var usuarioId = rawUsuarioId !== null && rawUsuarioId !== '' ? parseInt(rawUsuarioId, 10) : null;
                abrirModalInvitaciones(
                    isNaN(usuarioId) ? null : usuarioId,
                    btn.getAttribute('data-usuario-habilitado-nombre') || ''
                );
            });
        });
        root.querySelectorAll('.js-ver-factura-detalle').forEach(function (lnk) {
            if (lnk.dataset.boundVer) {
                return;
            }
            lnk.dataset.boundVer = '1';
            lnk.addEventListener('click', function (e) {
                e.preventDefault();
                var vid = parseInt(lnk.getAttribute('data-venta-id'), 10);
                if (vid > 0) {
                    window.open(urlVerFactura(vid), '_blank', 'noopener');
                }
            });
        });
    }

    function setHeadersModalConciliacion(modo) {
        var labels = modo === 'nc'
            ? {
                comprobante: 'Nota de crédito',
                hora: 'Hora',
                cliente: 'Cliente',
                item: 'Usuario habilitado',
                total: 'Total NC',
                extra: 'Factura origen',
                cobrado: 'Cobrado (devuelto)',
                acciones: '',
            }
            : modo === 'invitaciones'
            ? {
                comprobante: 'Comprobante',
                hora: 'Hora',
                cliente: 'Cliente',
                item: 'Usuario habilitado',
                total: 'Total',
                extra: 'Descuento',
                cobrado: 'Cobrado',
                acciones: '',
            }
            : {
                comprobante: 'Comprobante',
                hora: 'Hora',
                cliente: 'Cliente',
                item: 'Usuario habilitado',
                total: 'Facturado',
                extra: 'Este medio',
                cobrado: 'Cobrado total',
                acciones: '',
            };
        var map = {
            'modal-conc-th-comprobante': labels.comprobante,
            'modal-conc-th-hora': labels.hora,
            'modal-conc-th-cliente': labels.cliente,
            'modal-conc-th-item': labels.item,
            'modal-conc-th-total': labels.total,
            'modal-conc-th-extra': labels.extra,
            'modal-conc-th-cobrado': labels.cobrado,
            'modal-conc-th-acciones': labels.acciones,
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = map[id];
            }
        });
    }

    function abrirModalMedio(cuentacajaId, medioNombre, usuarioHabilitadoId, usuarioHabilitadoNombre) {
        if (!cuentacajaId || !apiConciliacionMedio) {
            return;
        }
        var titulo = document.getElementById('modal-conciliacion-medio-titulo');
        var body = document.getElementById('modal-conciliacion-medio-body');
        setHeadersModalConciliacion('facturas');
        if (titulo) {
            var tit = 'Facturas — ' + (medioNombre || 'Medio de pago');
            if (usuarioHabilitadoNombre) {
                tit += ' · ' + usuarioHabilitadoNombre;
            }
            titulo.textContent = tit;
        }
        if (body) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">Cargando…</td></tr>';
        }
        if (typeof $ !== 'undefined') {
            $('#modal-conciliacion-medio').modal('show');
        }

        var url = apiConciliacionMedio + '?cuentacaja_id=' + encodeURIComponent(cuentacajaId);
        if (usuarioHabilitadoId && usuarioHabilitadoId > 0) {
            url += '&usuario_habilitado_id=' + encodeURIComponent(usuarioHabilitadoId);
        }
        getJson(url).then(function (res) {
            if (!body) {
                return;
            }
            if (!res.data.ok) {
                body.innerHTML = '<tr><td colspan="8" class="text-danger p-3">' + (res.data.error || 'Error') + '</td></tr>';
                return;
            }
            var facturas = res.data.facturas || [];
            var baseVer = res.data.url_factura_ver_base || urlFacturaVerBase;
            if (!facturas.length) {
                body.innerHTML = '<tr><td colspan="8" class="text-muted p-3">'
                    + (usuarioHabilitadoNombre
                        ? 'Sin facturas de ' + usuarioHabilitadoNombre + ' con este medio en el turno.'
                        : 'Sin facturas con este medio en el turno.')
                    + '</td></tr>';
                return;
            }
            var html = '';
            facturas.forEach(function (f) {
                html += '<tr>';
                html += '<td>' + (f.codigo || '—') + (f.es_invitacion ? ' <span class="badge badge-secondary">Inv.</span>' : '') + '</td>';
                html += '<td>' + (f.hora || '') + '</td>';
                html += '<td>' + (f.cliente || '') + '</td>';
                html += '<td>' + (f.usuario_habilitado_nombre || '') + '</td>';
                html += '<td class="text-right">$' + fmt(f.total_facturado) + '</td>';
                html += '<td class="text-right font-weight-bold">$' + fmt(f.monto_medio) + '</td>';
                html += '<td class="text-right">$' + fmt(f.total_cobrado) + '</td>';
                html += '<td class="text-nowrap">';
                if (f.venta_id && (baseVer || urlVerFactura(f.venta_id) !== '#')) {
                    var verUrl = urlVerFactura(f.venta_id);
                    html += '<a href="' + verUrl + '" class="btn btn-sm btn-primary" target="_blank" rel="noopener" title="Ver factura">';
                    html += '<i class="fa fa-eye"></i> Ver</a>';
                }
                html += '</td></tr>';
            });
            body.innerHTML = html;
        });
    }

    function abrirModalNotasCredito(usuarioHabilitadoId, usuarioHabilitadoNombre) {
        if (!apiConciliacionNotasCredito) {
            return;
        }
        var titulo = document.getElementById('modal-conciliacion-medio-titulo');
        var body = document.getElementById('modal-conciliacion-medio-body');
        setHeadersModalConciliacion('nc');
        if (titulo) {
            titulo.textContent = usuarioHabilitadoNombre
                ? 'Notas de crédito — ' + usuarioHabilitadoNombre
                : 'Notas de crédito del turno';
        }
        if (body) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">Cargando…</td></tr>';
        }
        if (typeof $ !== 'undefined') {
            $('#modal-conciliacion-medio').modal('show');
        }

        var url = apiConciliacionNotasCredito;
        if (usuarioHabilitadoId && usuarioHabilitadoId > 0) {
            url += '?usuario_habilitado_id=' + encodeURIComponent(usuarioHabilitadoId);
        }

        getJson(url).then(function (res) {
            if (!body) {
                return;
            }
            if (!res.data.ok) {
                body.innerHTML = '<tr><td colspan="8" class="text-danger p-3">' + (res.data.error || 'Error') + '</td></tr>';
                return;
            }
            var notas = res.data.notas_credito || [];
            var baseVer = res.data.url_factura_ver_base || urlFacturaVerBase;
            if (!notas.length) {
                body.innerHTML = '<tr><td colspan="8" class="text-muted p-3">'
                    + (usuarioHabilitadoNombre ? 'Sin notas de crédito de ' + usuarioHabilitadoNombre + ' en el turno.' : 'Sin notas de crédito en el turno.')
                    + '</td></tr>';
                return;
            }
            var html = '';
            notas.forEach(function (n) {
                html += '<tr>';
                html += '<td><span class="badge badge-danger">NC</span> ' + (n.codigo || '—') + '</td>';
                html += '<td>' + (n.hora || '') + '</td>';
                html += '<td>' + (n.cliente || '') + '</td>';
                html += '<td>' + (n.usuario_habilitado_nombre || '') + '</td>';
                html += '<td class="text-right font-weight-bold" style="color:#922b21;">$' + fmt(n.monto_nota_credito) + '</td>';
                html += '<td class="text-right">';
                if (n.factura_origen_id) {
                    var origenUrl = urlVerFactura(n.factura_origen_id);
                    if (origenUrl !== '#') {
                        html += '<a href="' + origenUrl + '" target="_blank" rel="noopener" title="Ver factura origen">';
                        html += (n.factura_origen_codigo || ('#' + n.factura_origen_id));
                        html += '</a>';
                    } else {
                        html += (n.factura_origen_codigo || ('#' + n.factura_origen_id));
                    }
                } else {
                    html += '—';
                }
                html += '</td>';
                html += '<td class="text-right" style="color:#922b21;">$' + fmt(n.total_cobrado) + '</td>';
                html += '<td class="text-nowrap">';
                if (n.venta_id && (baseVer || urlVerFactura(n.venta_id) !== '#')) {
                    var verUrl = urlVerFactura(n.venta_id);
                    html += '<a href="' + verUrl + '" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" title="Ver nota de crédito">';
                    html += '<i class="fa fa-eye"></i> Ver NC</a>';
                }
                html += '</td></tr>';
            });
            body.innerHTML = html;
        });
    }

    function abrirModalInvitaciones(usuarioHabilitadoId, usuarioHabilitadoNombre) {
        if (!apiConciliacionInvitaciones) {
            return;
        }
        var titulo = document.getElementById('modal-conciliacion-medio-titulo');
        var body = document.getElementById('modal-conciliacion-medio-body');
        setHeadersModalConciliacion('invitaciones');
        if (titulo) {
            titulo.textContent = usuarioHabilitadoNombre
                ? 'Invitaciones $0,01 — ' + usuarioHabilitadoNombre
                : 'Invitaciones $0,01 del turno';
        }
        if (body) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">Cargando…</td></tr>';
        }
        if (typeof $ !== 'undefined') {
            $('#modal-conciliacion-medio').modal('show');
        }

        var url = apiConciliacionInvitaciones;
        if (usuarioHabilitadoId && usuarioHabilitadoId > 0) {
            url += '?usuario_habilitado_id=' + encodeURIComponent(usuarioHabilitadoId);
        }

        getJson(url).then(function (res) {
            if (!body) {
                return;
            }
            if (!res.data.ok) {
                body.innerHTML = '<tr><td colspan="8" class="text-danger p-3">' + (res.data.error || 'Error') + '</td></tr>';
                return;
            }
            var facturas = res.data.facturas || [];
            if (!facturas.length) {
                body.innerHTML = '<tr><td colspan="8" class="text-muted p-3">'
                    + (usuarioHabilitadoNombre ? 'Sin invitaciones $0,01 de ' + usuarioHabilitadoNombre + ' en el turno.' : 'Sin invitaciones $0,01 en el turno.')
                    + '</td></tr>';
                return;
            }
            var html = '';
            facturas.forEach(function (f) {
                var descLabel = f.descuento_nombre || f.descuento_codigo || '—';
                if (Number(f.descuento_pct) > 0) {
                    descLabel += ' (' + f.descuento_pct + '%)';
                }
                html += '<tr>';
                html += '<td>' + (f.codigo || '—') + ' <span class="badge badge-warning text-dark">Inv.</span></td>';
                html += '<td>' + (f.hora || '') + '</td>';
                html += '<td>' + (f.cliente || '') + '</td>';
                html += '<td>' + (f.usuario_habilitado_nombre || '') + '</td>';
                html += '<td class="text-right font-weight-bold">$' + fmt(f.total_facturado) + '</td>';
                html += '<td>' + descLabel + '</td>';
                html += '<td class="text-right text-muted">—</td>';
                html += '<td class="text-nowrap">';
                if (f.venta_id && urlVerFactura(f.venta_id) !== '#') {
                    var verUrl = urlVerFactura(f.venta_id);
                    html += '<a href="' + verUrl + '" class="btn btn-sm btn-warning text-dark" target="_blank" rel="noopener" title="Ver factura">';
                    html += '<i class="fa fa-eye"></i> Ver</a>';
                }
                html += '</td></tr>';
            });
            body.innerHTML = html;
        });
    }

    function renderAlertaJornadaActiva(estado) {
        var el = document.getElementById('alert-jornada-activa');
        if (!el) {
            return;
        }
        if (estado && estado.jornada_abierta) {
            var fecha = estado.fecha_jornada_fmt || estado.fecha_jornada || '—';
            var html = 'Jornada activa: <strong>' + fecha + '</strong>';
            var usr = estado.jornada_usuario_apertura || '';
            var cuando = estado.jornada_apertura_en || '';
            if (usr) {
                html += ' · Abierta por <strong>' + usr + '</strong>';
                if (cuando) {
                    html += ' (' + cuando + ')';
                }
            }
            el.innerHTML = html;
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
        var inpJornada = document.getElementById('fecha_jornada_activa');
        if (inpJornada && estado && estado.jornada_abierta) {
            inpJornada.value = estado.fecha_jornada_fmt || estado.fecha_jornada || '';
        }
    }

    function actualizarSelectTurnos(habilitablesIds) {
        var select = document.getElementById('turno_estacionamiento_id');
        if (!select) {
            return;
        }
        var habilitables = (habilitablesIds || []).map(function (id) {
            return String(id);
        });
        var seleccionValida = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (!opt.value) {
                opt.disabled = false;
                opt.hidden = false;
                return;
            }
            var puedeAbrir = habilitables.indexOf(String(opt.value)) >= 0;
            opt.disabled = !puedeAbrir;
            opt.hidden = !puedeAbrir;
            if (opt.value === select.value && puedeAbrir) {
                seleccionValida = true;
            }
        });
        if (!seleccionValida) {
            select.value = '';
        }
    }

    function renderAnularCierre(estado) {
        var btn = document.getElementById('btn-abrir-anular-cierre');
        if (!btn || !puedeAnularCierre) {
            return;
        }

        var info = estado.cierre_anulable || null;
        var visible = info
            && info.puede_anular
            && !estado.turno_habilitado
            && estado.jornada_abierta;

        if (visible) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    }

    function poblarModalAnularCierre() {
        var info = estadoActual && estadoActual.cierre_anulable ? estadoActual.cierre_anulable : null;
        var detalle = document.getElementById('anular-cierre-detalle');
        var hint = document.getElementById('hint-confirmacion-anular-cierre');
        var inputConf = document.getElementById('confirmacion_anular_cierre');
        var motivo = document.getElementById('motivo_anular_cierre');

        if (!info || !info.puede_anular) {
            return false;
        }

        if (detalle) {
            detalle.innerHTML =
                '<ul class="mb-0 small">' +
                '<li><strong>Turno:</strong> ' + esc(info.turno_nombre || '') + ' (op. #' + esc(info.turno_operativo_id) + ')</li>' +
                '<li><strong>Cierre Nº:</strong> ' + esc(info.numero_cierre != null ? info.numero_cierre : '—') + '</li>' +
                '<li><strong>Cerrado:</strong> ' + esc(info.cierre_en_fmt || '') +
                (info.usuario_cierre ? ' — ' + esc(info.usuario_cierre) : '') + '</li>' +
                '<li><strong>Terminal:</strong> <code>' + esc(info.identificador_pc || '') + '</code></li>' +
                '</ul>';
        }
        if (motivo) {
            motivo.value = '';
        }
        if (inputConf) {
            inputConf.value = '';
            inputConf.placeholder = info.texto_confirmacion || '';
        }
        if (hint && info.texto_confirmacion) {
            hint.textContent = 'Escriba exactamente: ' + info.texto_confirmacion;
        }

        return true;
    }

    function abrirModalAnularCierre() {
        if (!poblarModalAnularCierre()) {
            alert('No hay un cierre anulable en esta terminal.');
            return;
        }
        var modal = document.getElementById('modal-anular-cierre');
        if (modal && typeof window.jQuery !== 'undefined') {
            window.jQuery(modal).modal('show');
        }
    }

    function actualizarUi(estado) {
        estadoActual = estado;
        if (estado.url_factura_ver_base) {
            urlFacturaVerBase = estado.url_factura_ver_base;
        }

        renderAlertaJornadaActiva(estado);
        renderAnularCierre(estado);

        var panel = document.getElementById('panel-estado-turno');
        var cardHab = document.getElementById('card-habilitar');
        var wrapSolapas = document.getElementById('wrap-solapas-cierre');

        if (!panel) {
            return;
        }

        if (estado.url_factura_ver_base) {
            sincronizarUrlFacturaVerBase(estado.url_factura_ver_base);
        }

        if (estado.turno_habilitado) {
            panel.innerHTML = renderPanelTurnoHabilitado(estado);
            enlazarEdicionMontoHabilitacion(estado);
            if (cardHab) {
                cardHab.classList.add('d-none');
            }
            activarSolapaCierre(estado);
            renderNumeracionFiscal(estado);
            grillaMetaPorContenedor = {};
            cargarMetaGrillas().then(function () {
                poblarTabParcial(estado);
                poblarTabDefinitivo(estado);
            });
        } else {
            grillaMetaPorContenedor = {};
            var msg = '<div class="alert alert-warning mb-0">Sin turno habilitado en esta terminal.</div>';
            var errsHab = estado.errores_habilitacion || [];
            if (errsHab.length) {
                msg += '<div class="alert alert-danger mt-2 mb-0">' + errsHab.join('<br>') + '</div>';
            }
            panel.innerHTML = msg;
            var habilitablesIds = estado.turnos_estacionamiento_habilitables_ids || [];
            actualizarSelectTurnos(habilitablesIds);
            if (cardHab && estado.puede_habilitar) {
                cardHab.classList.remove('d-none');
            } else if (cardHab) {
                cardHab.classList.add('d-none');
            }
            if (!estado.puede_habilitar && !errsHab.length && estado.jornada_abierta && estado.sin_turnos_por_abrir) {
                msg += '<div class="alert alert-info mt-2 mb-0">No hay más turnos por abrir en esta terminal.</div>';
                panel.innerHTML = msg;
            }
            if (wrapSolapas) {
                wrapSolapas.classList.add('d-none');
            }
            renderNumeracionFiscal({ numeracion_fiscal: { filas: [] } });
        }
    }

    function cargarEstado() {
        return getJson(apiEstado).then(function (res) {
            if (res.data.ok) {
                actualizarUi(res.data);
            }
        });
    }

    if (puedeAnularCierre && apiAnularCierre) {
        var btnAbrirAnular = document.getElementById('btn-abrir-anular-cierre');
        if (btnAbrirAnular) {
            btnAbrirAnular.addEventListener('click', abrirModalAnularCierre);
        }

        var formAnular = document.getElementById('form-anular-cierre');
        if (formAnular) {
            formAnular.addEventListener('submit', function (e) {
                e.preventDefault();
                var info = estadoActual && estadoActual.cierre_anulable ? estadoActual.cierre_anulable : null;
                if (!info || !info.puede_anular || !info.turno_operativo_id) {
                    alert('No hay un cierre anulable en esta terminal.');
                    return;
                }
                var motivoVal = (document.getElementById('motivo_anular_cierre') || {}).value || '';
                if (!String(motivoVal).trim()) {
                    alert('Indique el motivo de la anulación.');
                    return;
                }
                var conf = (document.getElementById('confirmacion_anular_cierre') || {}).value || '';
                if (!confirm(
                    '¿Anular el cierre del turno «' + (info.turno_nombre || '') + '» en esta terminal?\n'
                    + 'El turno volverá a estado HABILITADO. Esta acción queda registrada en el log.'
                )) {
                    return;
                }
                postJson(apiAnularCierre, {
                    turno_operativo_id: info.turno_operativo_id,
                    confirmacion: conf,
                    motivo: motivoVal,
                }).then(function (res) {
                    if (res.data.ok) {
                        var modal = document.getElementById('modal-anular-cierre');
                        if (modal && typeof window.jQuery !== 'undefined') {
                            window.jQuery(modal).modal('hide');
                        }
                        alert(res.data.mensaje || 'Cierre anulado');
                        cargarEstado();
                    } else {
                        alert(res.data.error || mensajeErrorRespuesta(res));
                    }
                });
            });
        }
    }

    if (puedeHabilitar) {
        var formHab = document.getElementById('form-habilitar-turno');
        if (formHab) {
            formHab.addEventListener('submit', function (e) {
                e.preventDefault();
                var turnoId = document.getElementById('turno_estacionamiento_id').value;
                var usuarioId = document.getElementById('usuario_habilitado_id').value;
                if (!turnoId) {
                    alert('Seleccione el turno a habilitar.');
                    return;
                }
                if (!usuarioId) {
                    alert('Indique el usuario habilitado (código + Tab o lupa de consulta).');
                    return;
                }
                postJson(apiHabilitar, {
                    turno_estacionamiento_id: turnoId,
                    monto_habilitacion: document.getElementById('monto_habilitacion').value,
                    usuario_habilitado_id: usuarioId,
                    observacion: document.getElementById('observacion_habilitacion').value,
                }).then(function (res) {
                    if (respuestaApiOk(res)) {
                        alert(res.data.mensaje || 'Turno habilitado correctamente.');
                        cargarEstado();
                    } else {
                        alert(mensajeErrorRespuesta(res));
                    }
                }).catch(function () {
                    alert('Error de comunicación al habilitar el turno.');
                });
            });
        }
    }

    if (puedeCierreParcial) {
        var btnParcial = document.getElementById('btn-submit-cierre-parcial');
        if (btnParcial) {
            btnParcial.addEventListener('click', function () {
                if (!confirm('¿Registrar cierre parcial completo del turno? El turno seguirá habilitado.')) {
                    return;
                }
                postJson(apiCierreParcial, {}).then(function (res) {
                    if (res.data.ok) {
                        alert(res.data.mensaje || 'Cierre parcial registrado');
                        if (res.data.url_comprobante_pdf) {
                            window.open(res.data.url_comprobante_pdf, '_blank', 'noopener');
                        }
                        cargarEstado();
                    } else {
                        alert(res.data.error || 'Error');
                    }
                });
            });
        }
    }

    if (puedeCerrar) {
        var formCerr = document.getElementById('form-cerrar-turno');
        if (formCerr) {
            formCerr.addEventListener('submit', function (e) {
                e.preventDefault();
                if (estadoActual && estadoActual.totales_turno && !estadoActual.totales_turno.conciliacion_ok) {
                    var diffC = Number(estadoActual.totales_turno.diferencia_cobranza || 0);
                    var sug = Number(estadoActual.totales_turno.redondeo_invitaciones_sugerido || 0);
                    var inpInvVal = parseFloat(document.getElementById('redondeo_invitaciones').value) || 0;
                    var inpSf = parseFloat(document.getElementById('sobrante_faltante').value) || 0;
                    var inpRt = parseFloat(document.getElementById('redondeo_turno').value) || 0;
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
                if (!confirm('¿Confirma el cierre definitivo del turno en esta terminal?')) {
                    return;
                }
                var payloadCierre = {
                    redondeo_invitaciones: document.getElementById('redondeo_invitaciones').value,
                    redondeo_turno: document.getElementById('redondeo_turno').value,
                    sobrante_faltante: document.getElementById('sobrante_faltante').value,
                    observacion_cierre: document.getElementById('observacion_cierre').value,
                };
                var rootMediosCierre = document.getElementById('tab-cierre-definitivo')
                    || document.getElementById('totales-tab-definitivo');
                if (rootMediosCierre && window.EstacionamientoTotalesTurnoRender
                    && window.EstacionamientoTotalesTurnoRender.recolectarMediosContadoCierreDesdeRoot) {
                    var mediosContado = window.EstacionamientoTotalesTurnoRender
                        .recolectarMediosContadoCierreDesdeRoot(rootMediosCierre);
                    if (mediosContado.length) {
                        payloadCierre.medios_contado = mediosContado;
                    }
                }
                postJson(apiCerrar, payloadCierre).then(function (res) {
                    if (res.data.ok) {
                        alert(res.data.mensaje || 'Turno cerrado');
                        if (res.data.url_comprobante_pdf) {
                            window.open(res.data.url_comprobante_pdf, '_blank', 'noopener');
                        }
                        cargarEstado();
                    } else {
                        alert(res.data.error || 'Error');
                    }
                });
            });
        }
    }

    document.querySelectorAll('.js-refrescar-grilla-conciliacion').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.getAttribute('data-grilla-target');
            if (!target) {
                return;
            }
            cargarPaginaGrilla(target, 1);
        });
    });

    document.querySelectorAll('.js-filtro-solo-diferencias').forEach(function (chk) {
        chk.addEventListener('change', function () {
            var target = chk.getAttribute('data-grilla-target');
            if (!target) {
                return;
            }
            cargarPaginaGrilla(target, 1);
        });
    });

    if (typeof $('a[data-toggle="tab"]').tab === 'function') {
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var href = e.target ? e.target.getAttribute('href') : '';
            document.querySelectorAll('#tabs-cierre-turno .nav-link').forEach(function (link) {
                var selected = link.getAttribute('href') === href;
                link.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            if (href === '#tab-cierre-definitivo') {
                if (!grillaMetaPorContenedor['grilla-conciliacion-turno']) {
                    cargarMetaGrilla('grilla-conciliacion-turno');
                }
            } else if (href === '#tab-cierre-parcial') {
                if (!grillaMetaPorContenedor['grilla-conciliacion-parcial']) {
                    cargarMetaGrilla('grilla-conciliacion-parcial');
                }
            }
        });
    }

    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }

    sincronizarUrlFacturaVerBase(urlFacturaVerBase);

    var btnInvitaciones = document.getElementById('btn-consultar-invitaciones');
    if (btnInvitaciones) {
        btnInvitaciones.addEventListener('click', function () {
            if (!estadoActual || !estadoActual.turno_habilitado) {
                alert('No hay turno habilitado.');
                return;
            }
            abrirModalInvitaciones(null, '');
        });
    }

    document.querySelectorAll('.js-auto-consultar-empresa').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var form = sel.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    cargarEstado();
})();
