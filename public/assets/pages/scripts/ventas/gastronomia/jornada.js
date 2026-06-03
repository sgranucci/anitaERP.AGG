(function () {
    'use strict';

    var app = document.getElementById('jornada-gastronomia-app');
    if (!app) {
        return;
    }

    var apiEstadoBase = app.getAttribute('data-api-estado') || '';
    var apiAbrir = app.getAttribute('data-api-abrir') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var apiEliminar = app.getAttribute('data-api-eliminar') || '';
    var apiAnularCierre = app.getAttribute('data-api-anular-cierre') || '';
    var puedeAbrir = app.getAttribute('data-puede-abrir') === '1';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1';
    var puedeEliminar = app.getAttribute('data-puede-eliminar') === '1';
    var puedeAnularCierre = app.getAttribute('data-puede-anular-cierre') === '1';
    var cierreTotemHabilitado = app.getAttribute('data-cierre-totem-habilitado') === '1';
    var jornadaAbierta = app.getAttribute('data-jornada-abierta') === '1';

    var selectEmpresa = document.getElementById('empresa_id');
    var btnAbrir = document.getElementById('btn-abrir-jornada');
    var btnCerrar = document.getElementById('btn-cerrar-jornada');
    var btnCerrarHtmlOriginal = btnCerrar ? btnCerrar.innerHTML : '';
    var avisoCierreEnProgreso = document.getElementById('jornada-cierre-en-progreso');

    function empresaId() {
        return selectEmpresa ? parseInt(selectEmpresa.value, 10) || 0 : 0;
    }

    if (selectEmpresa) {
        selectEmpresa.addEventListener('change', function () {
            var form = document.getElementById('form-empresa-jornada');
            if (form) {
                form.submit();
                return;
            }
            var url = new URL(window.location.href);
            url.searchParams.set('empresa_id', String(selectEmpresa.value || ''));
            window.location.href = url.toString();
        });
    }

    function urlPreviewCierreTotem(empresaIdParam) {
        var base = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlPreviewCierreTotemBase) || '';
        if (!base) {
            return '';
        }
        return base.replace('__EMPRESA_ID__', String(empresaIdParam));
    }

    function formatearMonto(n) {
        return Number(n || 0).toFixed(2);
    }

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toleranciaInformeZ() {
        var t = window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.toleranciaInformeZ;
        return typeof t === 'number' && !isNaN(t) ? t : 0.02;
    }

    var estadoPreviewInformeZ = {
        jornadaId: 0,
        totems: [],
        snapshot: null,
    };

    function parseMontoInformeZ(str) {
        var s = String(str || '').trim();
        if (s === '') {
            return 0;
        }
        s = s.replace(/\s/g, '').replace(/\$/g, '');
        if (s.indexOf(',') >= 0 && s.indexOf('.') >= 0) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else if (s.indexOf(',') >= 0) {
            s = s.replace(',', '.');
        }
        var n = parseFloat(s);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function formatMontoInformeZInput(n) {
        if (n === '' || n == null || isNaN(Number(n))) {
            return '';
        }
        var num = Math.round(Number(n) * 100) / 100;
        if (num === 0) {
            return '';
        }
        var parts = num.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(',');
    }

    function mapaSistemaDesdeLineas(lineas) {
        var porCuenta = {};
        var porTipo = {};
        (lineas || []).forEach(function (ln) {
            var m = Number(ln.monto_sistema || 0);
            var ccId = parseInt(ln.cuentacaja_id, 10) || 0;
            if (ccId > 0) {
                porCuenta[String(ccId)] = m;
            }
            var tipo = String(ln.tipo_waitry || '').trim();
            if (tipo) {
                porTipo[tipo] = m;
            }
        });
        return { porCuenta: porCuenta, porTipo: porTipo };
    }

    var jornadaInformeZCuentaActiva = null;

    function urlCuentacajaPorCodigo(codigo) {
        var base = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlCuentacajaPorCodigoBase) || '';
        if (!base) {
            return '';
        }
        return base.replace(/\/$/, '') + '/' + encodeURIComponent(String(codigo || '').trim());
    }

    function htmlFilaInformeZ(ln, scope) {
        ln = ln || {};
        scope = scope || 'modal';
        var ccId = ln.cuentacaja_id != null ? parseInt(ln.cuentacaja_id, 10) : 0;
        var codigo = escHtml(ln.cuentacaja_codigo || '');
        var nombre = escHtml(ln.cuentacaja_nombre || ln.etiqueta || '');
        var tipo = escHtml(ln.tipo_waitry || 'totem');
        var mSis = Number(ln.monto_sistema || 0);
        var mZ = ln.monto_informe_z != null ? Number(ln.monto_informe_z) : null;
        var inputVal = mZ != null && !isNaN(mZ) ? escHtml(formatMontoInformeZInput(mZ)) : '';

        var html = '<tr class="jornada-informe-z-linea" data-tipo-waitry="' + tipo + '" data-monto-sistema="' + formatearMonto(mSis) + '">';
        html += '<td><div class="jornada-informe-z-cuenta-wrap">';
        html += '<input type="hidden" class="cuentacaja_id" value="' + (ccId > 0 ? ccId : '') + '">';
        html += '<button type="button" title="Consulta cuentas (uso Gastronomía)" class="btn-accion-tabla consultacuentacaja tooltipsC">';
        html += '<i class="fa fa-search text-primary"></i></button>';
        html += '<input type="text" class="form-control form-control-sm codigo" value="' + codigo + '" placeholder="Cód." autocomplete="off">';
        html += '<input type="text" class="form-control form-control-sm nombre" value="' + nombre + '" placeholder="Medio de pago" readonly>';
        html += '</div></td>';
        html += '<td class="text-right js-sistema-cell jornada-informe-z-sistema">$' + formatearMonto(mSis) + '</td>';
        html += '<td class="text-right"><input type="text" inputmode="decimal" autocomplete="off"';
        html += ' class="form-control jornada-informe-z-monto js-monto-informe-z js-monto-informe-z-' + scope + '"';
        html += ' data-scope="' + scope + '" data-monto-sistema="' + formatearMonto(mSis) + '"';
        html += ' value="' + inputVal + '" placeholder="0,00"></td>';
        html += '<td class="text-right js-diff-cell">—</td>';
        html += '<td class="text-center"><button type="button" title="Quitar línea" class="btn-accion-tabla js-informe-z-quitar-linea">';
        html += '<i class="fa fa-times-circle text-danger"></i></button></td>';
        html += '</tr>';
        return html;
    }

    function construirHtmlTablasInformeZ(totems, scope) {
        scope = scope || 'modal';
        initInformeZEventosGlobales();
        if (!totems || totems.length === 0) {
            return '<p class="text-muted mb-0">No hay tótems configurados para esta empresa.</p>';
        }

        var html = '<div class="jornada-informe-z-wrap">';
        totems.forEach(function (bloque, idxTotem) {
            var maps = mapaSistemaDesdeLineas(bloque.lineas || []);
            var titulo = escHtml(bloque.ubicacion_nombre || 'Tótem');
            if (bloque.detalle) {
                titulo += ' — ' + escHtml(bloque.detalle);
            }
            if (bloque.waitry_table_id) {
                titulo += ' <span class="text-muted">(tableId ' + parseInt(bloque.waitry_table_id, 10) + ')</span>';
            }

            html += '<div class="card mb-2 jornada-informe-z-totem" data-totem-idx="' + idxTotem + '" data-totem-id="' + parseInt(bloque.totem_id, 10) + '">';
            html += '<div class="card-header py-2"><strong>' + titulo + '</strong>';
            html += ' <span class="float-right text-muted small">Sistema: $' + formatearMonto(bloque.total_ingreso_sistema) + '</span></div>';
            html += '<div class="card-body p-0"><table class="table table-sm table-bordered mb-0 jornada-informe-z-tabla">';
            html += '<thead><tr><th>Cuenta de caja</th><th class="text-right">Sistema</th>';
            html += '<th class="text-right" style="min-width:150px">Informe Z</th><th class="text-right">Diferencia</th><th style="width:36px"></th></tr></thead>';
            html += '<tbody data-sistema-por-cuenta="' + escHtml(JSON.stringify(maps.porCuenta)) + '"';
            html += ' data-sistema-por-tipo="' + escHtml(JSON.stringify(maps.porTipo)) + '">';

            var lineas = bloque.lineas || [];
            if (lineas.length === 0) {
                html += htmlFilaInformeZ({}, scope);
            } else {
                lineas.forEach(function (ln) {
                    html += htmlFilaInformeZ(ln, scope);
                });
            }

            html += '</tbody></table>';
            html += '<div class="px-2 py-1 border-top">';
            html += '<button type="button" class="btn btn-link btn-sm p-0 js-informe-z-agregar-linea" data-scope="' + scope + '">';
            html += '+ Agregar medio de pago</button></div>';
            html += '</div></div>';
        });
        html += '</div>';

        return html;
    }

    function actualizarSistemaEnFilaInformeZ(tr) {
        if (!tr) {
            return;
        }
        var tbody = tr.closest('tbody');
        var ccId = parseInt((tr.querySelector('.cuentacaja_id') || {}).value, 10) || 0;
        var mSis = 0;
        if (tbody && ccId > 0) {
            try {
                var map = JSON.parse(tbody.getAttribute('data-sistema-por-cuenta') || '{}');
                if (map[String(ccId)] != null) {
                    mSis = Number(map[String(ccId)]) || 0;
                }
            } catch (e) {
                mSis = 0;
            }
        }
        if (mSis <= 0) {
            var tipo = tr.getAttribute('data-tipo-waitry') || '';
            if (tbody && tipo) {
                try {
                    var mapT = JSON.parse(tbody.getAttribute('data-sistema-por-tipo') || '{}');
                    if (mapT[tipo] != null) {
                        mSis = Number(mapT[tipo]) || 0;
                    }
                } catch (e2) {
                    mSis = 0;
                }
            }
        }
        tr.setAttribute('data-monto-sistema', formatearMonto(mSis));
        var inp = tr.querySelector('.js-monto-informe-z');
        if (inp) {
            inp.setAttribute('data-monto-sistema', formatearMonto(mSis));
        }
        var cell = tr.querySelector('.js-sistema-cell');
        if (cell) {
            cell.textContent = '$' + formatearMonto(mSis);
        }
    }

    function asignarCuentaEnFilaInformeZ(tr, cuenta) {
        if (!tr) {
            return;
        }
        if (!cuenta || !cuenta.id) {
            var idInpClear = tr.querySelector('.cuentacaja_id');
            var codInpClear = tr.querySelector('.codigo');
            var nomInpClear = tr.querySelector('.nombre');
            if (idInpClear) {
                idInpClear.value = '';
            }
            if (codInpClear) {
                codInpClear.value = '';
            }
            if (nomInpClear) {
                nomInpClear.value = '';
            }
            actualizarSistemaEnFilaInformeZ(tr);
            actualizarDiferenciaEnFilaInformeZ(tr);
            return;
        }
        var idInp = tr.querySelector('.cuentacaja_id');
        var codInp = tr.querySelector('.codigo');
        var nomInp = tr.querySelector('.nombre');
        if (idInp) {
            idInp.value = cuenta.id;
        }
        if (codInp) {
            codInp.value = cuenta.codigo || '';
        }
        if (nomInp) {
            nomInp.value = cuenta.nombre || '';
        }
        actualizarSistemaEnFilaInformeZ(tr);
        actualizarDiferenciaEnFilaInformeZ(tr);
        var monto = tr.querySelector('.js-monto-informe-z');
        if (monto) {
            monto.focus();
        }
    }

    function actualizarDiferenciaEnFilaInformeZ(tr) {
        if (!tr) {
            return;
        }
        var inp = tr.querySelector('.js-monto-informe-z');
        var cell = tr.querySelector('.js-diff-cell');
        if (!inp || !cell) {
            return;
        }
        var tol = toleranciaInformeZ();
        var mSis = parseFloat(inp.getAttribute('data-monto-sistema') || tr.getAttribute('data-monto-sistema') || '0') || 0;
        var mZ = parseMontoInformeZ(inp.value);
        var diff = Math.round((mZ - mSis) * 100) / 100;
        var ok = Math.abs(diff) <= tol || (mSis <= tol && mZ <= tol);
        cell.textContent = (diff >= 0 ? '+' : '') + formatearMonto(diff);
        cell.className = 'text-right js-diff-cell ' + (ok ? 'text-success' : 'text-danger font-weight-bold');
    }

    function actualizarDiferenciasEnContenedor(contenedor) {
        if (!contenedor) {
            return;
        }
        contenedor.querySelectorAll('.jornada-informe-z-linea').forEach(function (tr) {
            actualizarDiferenciaEnFilaInformeZ(tr);
        });
    }

    function recolectarPayloadInformeZDesdeContenedor(contenedor, totemsMeta, jornadaId) {
        var totemsOut = [];
        var cards = contenedor ? contenedor.querySelectorAll('.jornada-informe-z-totem') : [];
        cards.forEach(function (card) {
            var totemId = parseInt(card.getAttribute('data-totem-id'), 10) || 0;
            var lineas = [];
            card.querySelectorAll('.jornada-informe-z-linea').forEach(function (tr) {
                var ccId = parseInt((tr.querySelector('.cuentacaja_id') || {}).value, 10) || 0;
                var codInp = tr.querySelector('.codigo');
                var nomInp = tr.querySelector('.nombre');
                var inp = tr.querySelector('.js-monto-informe-z');
                var monto = parseMontoInformeZ(inp ? inp.value : '0');
                if (ccId <= 0 && monto <= 0) {
                    return;
                }
                lineas.push({
                    cuentacaja_id: ccId > 0 ? ccId : null,
                    cuentacaja_codigo: codInp ? String(codInp.value || '').trim() : '',
                    cuentacaja_nombre: nomInp ? String(nomInp.value || '').trim() : '',
                    tipo_waitry: tr.getAttribute('data-tipo-waitry') || 'totem',
                    monto: monto,
                });
            });
            if (totemId > 0) {
                totemsOut.push({ totem_id: totemId, lineas: lineas });
            }
        });

        if (totemsOut.length === 0 && totemsMeta && totemsMeta.length > 0) {
            (totemsMeta || []).forEach(function (bloque) {
                totemsOut.push({ totem_id: bloque.totem_id, lineas: [] });
            });
        }

        return { jornada_id: jornadaId, totems: totemsOut };
    }

    function initInformeZEventosGlobales() {
        if (initInformeZEventosGlobales._done) {
            return;
        }
        initInformeZEventosGlobales._done = true;

        document.body.addEventListener('click', function (ev) {
            var btnConsulta = ev.target.closest('.jornada-informe-z-tabla .consultacuentacaja');
            if (btnConsulta) {
                ev.preventDefault();
                var tr = btnConsulta.closest('tr');
                if (!tr || typeof window.jQuery === 'undefined') {
                    return;
                }
                var eid = empresaId() || (window.GASTRONOMIA && window.GASTRONOMIA.empresaId) || 0;
                if (eid <= 0) {
                    alertar('Seleccione una empresa.', true);
                    return;
                }
                jornadaInformeZCuentaActiva = tr.querySelector('.cuentacaja_id');
                window.jQuery('#consultacuentacajaModal').one('shown.bs.modal.jornadaInformeZ', function () {
                    if (typeof buscar_datos_cuentacaja === 'function') {
                        buscar_datos_cuentacaja('');
                    }
                    window.jQuery(this).find('#consultacuentacaja').trigger('focus');
                });
                window.jQuery('#consultacuentacajaModal').modal('show');
                return;
            }

            var btnAgregar = ev.target.closest('.js-informe-z-agregar-linea');
            if (btnAgregar) {
                ev.preventDefault();
                var card = btnAgregar.closest('.jornada-informe-z-totem');
                var tbody = card ? card.querySelector('tbody') : null;
                if (!tbody) {
                    return;
                }
                tbody.insertAdjacentHTML('beforeend', htmlFilaInformeZ({}, btnAgregar.getAttribute('data-scope') || 'modal'));
                var newTr = tbody.lastElementChild;
                var cod = newTr ? newTr.querySelector('.codigo') : null;
                if (cod) {
                    cod.focus();
                }
                return;
            }

            var btnQuitar = ev.target.closest('.js-informe-z-quitar-linea');
            if (btnQuitar) {
                ev.preventDefault();
                var trQ = btnQuitar.closest('tr');
                var tbodyQ = trQ ? trQ.closest('tbody') : null;
                if (!trQ || !tbodyQ) {
                    return;
                }
                if (tbodyQ.querySelectorAll('.jornada-informe-z-linea').length <= 1) {
                    asignarCuentaEnFilaInformeZ(trQ, null);
                    var montoInp = trQ.querySelector('.js-monto-informe-z');
                    if (montoInp) {
                        montoInp.value = '';
                    }
                    actualizarDiferenciaEnFilaInformeZ(trQ);
                    return;
                }
                trQ.remove();
                var cont = tbodyQ.closest('#preview-informe-z-tablas')
                    || tbodyQ.closest('#informe-z-contenido')
                    || tbodyQ.closest('.jornada-informe-z-wrap');
                actualizarDiferenciasEnContenedor(cont);
            }
        });

        document.body.addEventListener('input', function (ev) {
            if (ev.target && ev.target.classList.contains('js-monto-informe-z')) {
                actualizarDiferenciaEnFilaInformeZ(ev.target.closest('tr'));
            }
        });

        document.body.addEventListener('blur', function (ev) {
            if (ev.target && ev.target.classList.contains('js-monto-informe-z')) {
                var val = parseMontoInformeZ(ev.target.value);
                ev.target.value = val > 0 ? formatMontoInformeZInput(val) : '';
                actualizarDiferenciaEnFilaInformeZ(ev.target.closest('tr'));
            }
        }, true);

        document.body.addEventListener('keydown', function (ev) {
            var codInp = ev.target;
            if (!codInp || !codInp.classList || !codInp.classList.contains('codigo')) {
                return;
            }
            if (!codInp.closest('.jornada-informe-z-tabla')) {
                return;
            }
            if (ev.key !== 'Enter' && ev.keyCode !== 13) {
                return;
            }
            ev.preventDefault();
            var tr = codInp.closest('tr');
            var codigo = String(codInp.value || '').trim();
            if (!codigo || !tr) {
                return;
            }
            var url = urlCuentacajaPorCodigo(codigo);
            if (!url) {
                return;
            }
            fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data && parseInt(data.id, 10) > 0) {
                        asignarCuentaEnFilaInformeZ(tr, data);
                    } else {
                        alertar((data && data.error) || 'Cuenta de caja no encontrada.', true);
                    }
                })
                .catch(function () {
                    alertar('No se pudo buscar la cuenta de caja.', true);
                });
        });

        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(document).off('click.jornadaInformeZElige', '.eligeconsultacuentacaja');
            window.jQuery(document).on('click.jornadaInformeZElige', '.eligeconsultacuentacaja', function () {
                if (!jornadaInformeZCuentaActiva) {
                    return;
                }
                var tr = jornadaInformeZCuentaActiva.closest('tr');
                if (!tr || !tr.closest('.jornada-informe-z-tabla')) {
                    return;
                }
                var trModal = window.jQuery(this).closest('tr');
                var id = parseInt(trModal.find('.cuentacaja_id').html(), 10) || 0;
                if (id <= 0) {
                    return;
                }
                asignarCuentaEnFilaInformeZ(tr, {
                    id: id,
                    nombre: trModal.find('.nombre').html(),
                    codigo: trModal.find('.codigo').html(),
                });
                window.jQuery('#consultacuentacajaModal').modal('hide');
                jornadaInformeZCuentaActiva = null;
            });
        }
    }

    function renderResultadoConciliacionEn(el, conciliacion) {
        if (!el || !conciliacion) {
            return;
        }
        var ok = !!conciliacion.ok;
        var cls = ok ? 'alert alert-success' : 'alert alert-warning';
        var txt = ok
            ? 'Cuadra: Informe Z coincide con el sistema.'
            : 'Atención: hay diferencias entre Informe Z y el sistema.';
        el.className = 'mb-2 ' + cls + ' py-2 small';
        el.textContent = txt;
        el.classList.remove('d-none');
    }

    function renderPreviewCierreTotem(preview) {
        var contenedor = document.getElementById('preview-cierre-totem-waitry');
        var acciones = document.getElementById('preview-informe-z-acciones');
        if (!contenedor) {
            return;
        }

        if (!preview) {
            contenedor.innerHTML = '<p class="text-muted mb-0">Vista previa Waitry no disponible.</p>';
            if (acciones) {
                acciones.classList.add('d-none');
            }
            return;
        }

        estadoPreviewInformeZ.jornadaId = parseInt(preview.jornada_id, 10) || 0;
        estadoPreviewInformeZ.totems = preview.totems || [];
        estadoPreviewInformeZ.snapshot = preview.snapshot_cierre || null;

        var html = '';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-start mb-2">';
        html += '<strong>Conciliación Informe Z — tótems Waitry</strong>';
        html += '<span class="text-muted">Actualizado: ' + escHtml(preview.preview_en || '—') + '</span>';
        html += '</div>';

        if (preview.ventana_operativa) {
            html += '<p class="text-muted mb-1">Ventana: ' + escHtml(preview.ventana_operativa) + '</p>';
        }
        if (preview.rango_etiqueta) {
            html += '<p class="mb-2">' + escHtml(preview.rango_etiqueta) + '</p>';
        }

        var totalIngreso = preview.total_ingreso_totem != null
            ? Number(preview.total_ingreso_totem)
            : Number((preview.total_general || {}).total_ingreso || 0);
        var cantOrdenes = preview.cantidad_ingreso_totem != null
            ? parseInt(preview.cantidad_ingreso_totem, 10)
            : parseInt((preview.total_general || {}).cantidad_ordenes || preview.cantidad_ordenes || 0, 10);

        html += '<div class="alert alert-secondary py-2 mb-2">';
        html += '<strong>Ingreso tótem (sistema):</strong> $' + formatearMonto(totalIngreso);
        html += ' · <strong>Órdenes:</strong> ' + cantOrdenes;
        html += '</div>';

        if (preview.hay_discrepancias) {
            html += '<div class="alert alert-warning py-2 mb-2">';
            html += 'Hay ' + parseInt(preview.cantidad_discrepancias || 0, 10)
                + ' discrepancia(s) para revisar en auditoría del día';
            var huecos = parseInt(preview.cantidad_huecos_secuencia || 0, 10);
            if (huecos > 0) {
                html += ' (incluye ' + huecos + ' hueco(s) en secuencia Waitry; no se consultan al cerrar la jornada)';
            } else {
                html += ' (órdenes Waitry vs ERP)';
            }
            html += '.';
            html += '</div>';
        }

        if (preview.informe_z_cargado && preview.informe_z_en) {
            html += '<p class="text-muted mb-2">Informe Z guardado: ' + escHtml(preview.informe_z_en);
            if (preview.usuario_informe_z) {
                html += ' (' + escHtml(preview.usuario_informe_z) + ')';
            }
            html += '</p>';
        }

        html += '<div id="preview-informe-z-resultado" class="d-none"></div>';
        html += '<div id="preview-informe-z-tablas">';
        html += construirHtmlTablasInformeZ(preview.totems || [], 'preview');
        html += '</div>';
        html += '<p class="text-muted mb-0 mt-2 small">Los totales Waitry se consultan una sola vez aquí. Al cerrar la jornada solo se guardan el Informe Z y el último ticket Waitry (sin volver a consultar Waitry).</p>';

        contenedor.innerHTML = html;

        var tablasEl = document.getElementById('preview-informe-z-tablas');
        actualizarDiferenciasEnContenedor(tablasEl || contenedor);

        if (preview.conciliacion) {
            renderResultadoConciliacionEn(document.getElementById('preview-informe-z-resultado'), preview.conciliacion);
        }

        if (acciones) {
            acciones.classList.toggle('d-none', (preview.totems || []).length === 0);
        }
    }

    function cargarPreviewCierreTotem() {
        var contenedor = document.getElementById('preview-cierre-totem-waitry');
        if (!contenedor || !cierreTotemHabilitado || !jornadaAbierta) {
            return;
        }

        var eid = empresaId();
        var url = urlPreviewCierreTotem(eid);
        if (!url || eid <= 0) {
            return;
        }

        contenedor.innerHTML = '<div class="text-muted mb-0">'
            + '<i class="fa fa-spinner fa-spin"></i> Consultando totales Waitry del tótem…'
            + ' <span class="small">(puede tardar hasta 1 minuto)</span></div>';

        var timeoutMs = 90000;
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = controller
            ? window.setTimeout(function () {
                controller.abort();
            }, timeoutMs)
            : null;

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller ? controller.signal : undefined,
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.data.ok) {
                    throw new Error(extraerMensajeError(res, 'No se pudieron consultar los totales Waitry.'));
                }
                renderPreviewCierreTotem(res.data.preview);
            })
            .catch(function (e) {
                var msg = e && e.name === 'AbortError'
                    ? 'La consulta Waitry superó el tiempo de espera (' + Math.round(timeoutMs / 1000) + ' s).'
                    : (e.message || 'Error al consultar Waitry.');
                contenedor.innerHTML = '<div class="alert alert-danger py-2 mb-2">'
                    + escHtml(msg)
                    + '</div>'
                    + '<button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reintentar-preview-waitry">'
                    + 'Reintentar consulta Waitry</button>';
                var btnRetry = document.getElementById('btn-reintentar-preview-waitry');
                if (btnRetry) {
                    btnRetry.addEventListener('click', function () {
                        cargarPreviewCierreTotem();
                    });
                }
            })
            .finally(function () {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
            });
    }

    if (cierreTotemHabilitado && jornadaAbierta) {
        cargarPreviewCierreTotem();

        var btnGuardarPreview = document.getElementById('btn-guardar-informe-z-preview');
        if (btnGuardarPreview) {
            btnGuardarPreview.addEventListener('click', function () {
                var jid = estadoPreviewInformeZ.jornadaId;
                var urlGuardar = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlInformeZBorradorGuardar) || '';
                var tablasEl = document.getElementById('preview-informe-z-tablas');
                if (jid <= 0 || !urlGuardar || !tablasEl) {
                    return;
                }
                btnGuardarPreview.disabled = true;
                var payloadGuardar = recolectarPayloadInformeZDesdeContenedor(
                    tablasEl,
                    estadoPreviewInformeZ.totems,
                    jid,
                );
                if (estadoPreviewInformeZ.snapshot) {
                    payloadGuardar.snapshot_cierre = estadoPreviewInformeZ.snapshot;
                }
                postJson(urlGuardar, payloadGuardar).then(function (res) {
                    if (res.ok && res.data.ok) {
                        alertar(res.data.mensaje || 'Informe Z guardado.', false);
                        if (res.data.conciliacion) {
                            renderResultadoConciliacionEn(
                                document.getElementById('preview-informe-z-resultado'),
                                res.data.conciliacion,
                            );
                        }
                        if (res.data.totems) {
                            estadoPreviewInformeZ.totems = res.data.totems;
                        }
                        btnGuardarPreview.disabled = false;
                        return;
                    }
                    alertar(extraerMensajeError(res, 'No se pudo guardar el Informe Z.'), true);
                    btnGuardarPreview.disabled = false;
                }).catch(function () {
                    alertar('Error de comunicación al guardar el Informe Z.', true);
                    btnGuardarPreview.disabled = false;
                });
            });
        }
    }

    function recolectarInformeZPreviewParaCierre() {
        var tablasEl = document.getElementById('preview-informe-z-tablas');
        if (!tablasEl || estadoPreviewInformeZ.totems.length === 0) {
            return null;
        }

        var algunoIngresado = false;
        tablasEl.querySelectorAll('.js-monto-informe-z').forEach(function (inp) {
            if (String(inp.value || '').trim() !== '' && parseMontoInformeZ(inp.value) > 0) {
                algunoIngresado = true;
            }
        });
        if (!algunoIngresado) {
            return null;
        }

        var payload = recolectarPayloadInformeZDesdeContenedor(
            tablasEl,
            estadoPreviewInformeZ.totems,
            estadoPreviewInformeZ.jornadaId,
        );
        return payload.totems || null;
    }

    function csrfToken() {
        if (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.csrf) {
            return String(window.JORNADA_GASTRONOMIA.csrf);
        }
        if (app && app.getAttribute('data-csrf')) {
            return app.getAttribute('data-csrf');
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        if (typeof window.jQuery !== 'undefined') {
            var jq = window.jQuery;
            var fromInput = jq('input[name="_token"]').val();
            if (fromInput) {
                return String(fromInput);
            }
            var fromHidden = jq('#csrf_token').val();
            if (fromHidden) {
                return String(fromHidden);
            }
        }
        return '';
    }

    function postJson(url, body) {
        var token = csrfToken();
        if (!token) {
            return Promise.resolve({
                ok: false,
                status: 0,
                data: {
                    error: 'No se encontró el token CSRF en la página. Recargue (F5) e intente de nuevo.',
                    motivo: 'csrf',
                },
            });
        }

        var payload = Object.assign({}, body, { _token: token });

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
            return r.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    data = { error: text && text.length < 500 ? text : null };
                }
                return { ok: r.ok, status: r.status, data: data };
            });
        });
    }

    function extraerMensajeError(res, fallback) {
        var d = res && res.data ? res.data : {};
        if (d.error && String(d.error).trim() !== '') {
            return String(d.error);
        }
        if (d.message && String(d.message).trim() !== '') {
            return String(d.message);
        }
        if (d.errors && typeof d.errors === 'object') {
            var partes = [];
            Object.keys(d.errors).forEach(function (k) {
                var v = d.errors[k];
                if (Array.isArray(v)) {
                    partes = partes.concat(v);
                } else if (v) {
                    partes.push(String(v));
                }
            });
            if (partes.length) {
                return partes.join(' ');
            }
        }
        if (res.status === 403) {
            return 'Acceso denegado (403). Verifique permisos de jornada.';
        }
        if (res.status === 419) {
            var raw = (d.message || d.error || '').toString();
            if (/csrf/i.test(raw)) {
                return 'Token de seguridad vencido o inválido (CSRF). Recargue la página con F5 e intente abrir la jornada de nuevo.';
            }
            return 'Sesión expirada (419). Recargue la página e inicie sesión de nuevo.';
        }
        if (res.status >= 500) {
            return 'Error del servidor (HTTP ' + res.status + ').';
        }
        return fallback;
    }

    function mostrarCierreJornadaEnProgreso(enProgreso) {
        if (avisoCierreEnProgreso) {
            avisoCierreEnProgreso.classList.toggle('d-none', !enProgreso);
        }
        if (btnCerrar) {
            if (enProgreso) {
                btnCerrar.disabled = true;
                btnCerrar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Cerrando jornada…';
            } else {
                btnCerrar.disabled = false;
                btnCerrar.innerHTML = btnCerrarHtmlOriginal || 'Cerrar jornada';
            }
        }
        var obsCerrar = document.getElementById('observacion_cerrar');
        if (obsCerrar) {
            obsCerrar.disabled = enProgreso;
        }
    }

    function alertar(msg, esError) {
        if (typeof toastr !== 'undefined') {
            if (esError) {
                toastr.error(msg);
            } else {
                toastr.success(msg);
            }
        } else {
            window.alert(msg);
        }
    }

    function urlComprobanteCierreTotem(jornadaId) {
        var base = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlComprobanteTotemBase) || '';
        if (!base) {
            return '';
        }
        return base.replace('__JORNADA_ID__', String(jornadaId));
    }

    /**
     * Abre el PDF en pestaña nueva (mismo patrón que cierre de turno / saneamiento).
     */
    function abrirComprobanteCierreTotemEnPestana(jornadaId) {
        var url = typeof jornadaId === 'string' && jornadaId.indexOf('http') === 0
            ? jornadaId
            : urlComprobanteCierreTotem(jornadaId);
        if (!url) {
            return false;
        }
        var win = window.open(url, '_blank', 'noopener');
        if (!win) {
            alertar('El navegador bloqueó la ventana del PDF. Permita ventanas emergentes o use el enlace del historial.', true);
            return false;
        }
        return true;
    }

    /**
     * Tras cerrar jornada: PDF en otra pestaña y recarga para habilitar apertura de la nueva jornada.
     */
    function finalizarUiTrasCierreJornada(jornadaId, opts) {
        opts = opts || {};
        if (opts.abrirPdf && jornadaId > 0) {
            abrirComprobanteCierreTotemEnPestana(jornadaId);
        }
        if (opts.informeZPendiente && jornadaId > 0) {
            var url = new URL(window.location.href);
            url.searchParams.set('informe_z_jornada', String(jornadaId));
            window.location.href = url.pathname + '?' + url.searchParams.toString();
            return;
        }
        window.location.reload();
    }

    if (puedeAbrir && btnAbrir) {
        btnAbrir.addEventListener('click', function () {
            var fechaInput = document.getElementById('fecha_jornada_abrir');
            var obsInput = document.getElementById('observacion_abrir');
            btnAbrir.disabled = true;

            postJson(apiAbrir, {
                empresa_id: empresaId(),
                fecha_jornada: fechaInput ? fechaInput.value : '',
                observacion: obsInput ? obsInput.value : '',
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Jornada abierta.', false);
                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo abrir la jornada.'), true);
                btnAbrir.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al abrir la jornada (sin respuesta del servidor).', true);
                btnAbrir.disabled = false;
            });
        });
    }

    if (puedeCerrar && btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            if (!window.confirm('¿Confirma el cierre de la jornada? No podrá facturar hasta abrir una nueva.')) {
                return;
            }

            var obsInput = document.getElementById('observacion_cerrar');
            mostrarCierreJornadaEnProgreso(true);

            var bodyCerrar = {
                empresa_id: empresaId(),
                observacion: obsInput ? obsInput.value : '',
            };
            var informeZTotems = recolectarInformeZPreviewParaCierre();
            if (informeZTotems) {
                bodyCerrar.informe_z_totems = informeZTotems;
            }
            if (estadoPreviewInformeZ.snapshot) {
                bodyCerrar.cierre_totem_snapshot = estadoPreviewInformeZ.snapshot;
            }

            postJson(apiCerrar, bodyCerrar).then(function (res) {
                if (res.ok && res.data.ok) {
                    var msg = res.data.mensaje || 'Jornada cerrada.';
                    var ct = res.data.jornada && res.data.jornada.cierre_totem;
                    var jornadaId = res.data.jornada && res.data.jornada.id;

                    if (ct) {
                        if (ct.cantidad_lineas) {
                            msg += ' Órdenes Waitry: ' + ct.cantidad_lineas + '.';
                        }
                        if (ct.total_ingreso_totem != null && ct.total_ingreso_totem > 0) {
                            msg += ' Ingreso tótem: $' + Number(ct.total_ingreso_totem).toFixed(2) + '.';
                        }
                    }

                    alertar(msg, false);

                    var informeZYaCargado = res.data.jornada && res.data.jornada.informe_z_cargado;

                    if (!cierreTotemHabilitado || jornadaId <= 0) {
                        window.location.reload();
                        return;
                    }

                    if (!informeZYaCargado) {
                        finalizarUiTrasCierreJornada(jornadaId, {
                            abrirPdf: false,
                            informeZPendiente: true,
                        });
                        return;
                    }

                    finalizarUiTrasCierreJornada(jornadaId, { abrirPdf: !!ct });
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo cerrar la jornada.'), true);
                mostrarCierreJornadaEnProgreso(false);
            }).catch(function () {
                alertar('Error de comunicación al cerrar la jornada (sin respuesta del servidor).', true);
                mostrarCierreJornadaEnProgreso(false);
            });
        });
    }

    var anulacionCierreActual = null;

    function leerCierreAnulableDesdePagina() {
        var raw = app.getAttribute('data-cierre-anulable') || '';
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function datosAnulacionDesdeBoton(btn) {
        var id = parseInt(btn.getAttribute('data-jornada-id'), 10) || 0;
        if (id <= 0) {
            return null;
        }
        return {
            jornada_id: id,
            fecha_jornada_fmt: btn.getAttribute('data-fecha-jornada') || '',
            cierre_en_fmt: btn.getAttribute('data-cierre-en') || '',
            usuario_cierre: btn.getAttribute('data-usuario-cierre') || '',
            texto_confirmacion: btn.getAttribute('data-texto-confirmacion') || ('ANULAR-JORNADA-' + id),
        };
    }

    function poblarModalAnularCierreJornada(info) {
        if (!info || !info.jornada_id) {
            return false;
        }
        anulacionCierreActual = info;

        var detalle = document.getElementById('anular-cierre-jornada-detalle');
        var hint = document.getElementById('hint-confirmacion-anular-cierre-jornada');
        var inputConf = document.getElementById('confirmacion_anular_cierre_jornada');
        var motivo = document.getElementById('motivo_anular_cierre_jornada');

        if (detalle) {
            detalle.innerHTML =
                '<ul class="mb-0 small">' +
                '<li><strong>Jornada:</strong> #' + escHtml(info.jornada_id) +
                ' — ' + escHtml(info.fecha_jornada_fmt || '') + '</li>' +
                '<li><strong>Cerrada:</strong> ' + escHtml(info.cierre_en_fmt || '') +
                (info.usuario_cierre ? ' — ' + escHtml(info.usuario_cierre) : '') + '</li>' +
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

    function abrirModalAnularCierreJornada(info) {
        if (!poblarModalAnularCierreJornada(info)) {
            alertar('No hay un cierre de jornada que se pueda anular.', true);
            return;
        }
        var modal = document.getElementById('modal-anular-cierre-jornada');
        if (modal && typeof window.jQuery !== 'undefined') {
            window.jQuery(modal).modal('show');
        }
    }

    if (puedeAnularCierre && apiAnularCierre) {
        var btnToolbarAnular = document.getElementById('btn-abrir-anular-cierre-jornada');
        if (btnToolbarAnular) {
            btnToolbarAnular.addEventListener('click', function () {
                var info = leerCierreAnulableDesdePagina();
                abrirModalAnularCierreJornada(info);
            });
        }

        app.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.js-anular-cierre-jornada');
            if (!btn) {
                return;
            }
            abrirModalAnularCierreJornada(datosAnulacionDesdeBoton(btn));
        });

        var formAnular = document.getElementById('form-anular-cierre-jornada');
        if (formAnular) {
            formAnular.addEventListener('submit', function (e) {
                e.preventDefault();
                var info = anulacionCierreActual;
                if (!info || !info.jornada_id) {
                    alertar('Seleccione la jornada a anular.', true);
                    return;
                }
                var motivoVal = (document.getElementById('motivo_anular_cierre_jornada') || {}).value || '';
                if (!String(motivoVal).trim()) {
                    alertar('Indique el motivo de la anulación.', true);
                    return;
                }
                var conf = (document.getElementById('confirmacion_anular_cierre_jornada') || {}).value || '';
                var textoConf = info.texto_confirmacion || ('ANULAR-JORNADA-' + info.jornada_id);
                if (conf !== textoConf) {
                    alertar('La confirmación no coincide. Debe escribir: ' + textoConf, true);
                    return;
                }
                if (!window.confirm(
                    '¿Anular el cierre de la jornada #' + info.jornada_id
                    + ' (' + (info.fecha_jornada_fmt || '') + ')?\n'
                    + 'La jornada quedará ABIERTA. Esta acción queda registrada en el log.'
                )) {
                    return;
                }

                var btnSubmit = document.getElementById('btn-submit-anular-cierre-jornada');
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                }

                postJson(apiAnularCierre, {
                    jornada_id: info.jornada_id,
                    confirmacion: conf,
                    motivo: motivoVal,
                }).then(function (res) {
                    if (res.ok && res.data.ok) {
                        var modal = document.getElementById('modal-anular-cierre-jornada');
                        if (modal && typeof window.jQuery !== 'undefined') {
                            window.jQuery(modal).modal('hide');
                        }
                        alertar(res.data.mensaje || 'Cierre de jornada anulado.', false);
                        window.location.reload();
                        return;
                    }
                    alertar(extraerMensajeError(res, 'No se pudo anular el cierre.'), true);
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                    }
                }).catch(function () {
                    alertar('Error de comunicación al anular el cierre.', true);
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                    }
                });
            });
        }
    }

    if (puedeEliminar && apiEliminar) {
        app.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.js-eliminar-jornada');
            if (!btn) {
                return;
            }

            var jornadaId = parseInt(btn.getAttribute('data-jornada-id'), 10) || 0;
            var fechaJornada = btn.getAttribute('data-fecha-jornada') || '';
            if (jornadaId <= 0) {
                return;
            }

            var msg = '¿Eliminar la jornada del ' + fechaJornada + ' (ID ' + jornadaId + ')?'
                + '\n\nSolo se permite si no tiene turnos ni comprobantes.';
            if (!window.confirm(msg)) {
                return;
            }

            btn.disabled = true;

            postJson(apiEliminar, {
                jornada_id: jornadaId,
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Jornada eliminada.', false);
                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo eliminar la jornada.'), true);
                btn.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al eliminar la jornada (sin respuesta del servidor).', true);
                btn.disabled = false;
            });
        });
    }

    function urlInformeZDatos(jornadaId) {
        var base = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlInformeZDatosBase) || '';
        if (!base) {
            return '';
        }
        return base.replace('__JORNADA_ID__', String(jornadaId));
    }

    var modalInformeZ = document.getElementById('modal-informe-z-totem');
    var contenedorInformeZ = document.getElementById('informe-z-contenido');
    var resultadoInformeZ = document.getElementById('informe-z-resultado');
    var subtituloInformeZ = document.getElementById('informe-z-subtitulo');
    var btnGuardarInformeZ = document.getElementById('btn-informe-z-guardar');
    var btnOmitirImprimir = document.getElementById('btn-informe-z-omitir-imprimir');
    var estadoModalInformeZ = {
        jornadaId: 0,
        totems: [],
        ofrecerImpresion: false,
    };

    function renderResultadoConciliacion(conciliacion) {
        renderResultadoConciliacionEn(resultadoInformeZ, conciliacion);
    }

    function renderTablasInformeZ(datos) {
        if (!contenedorInformeZ) {
            return;
        }
        var totems = datos.totems || [];
        estadoModalInformeZ.totems = totems;

        if (totems.length === 0) {
            contenedorInformeZ.innerHTML = '<p class="text-muted">No hay tótems configurados para esta empresa.</p>';
            if (btnGuardarInformeZ) {
                btnGuardarInformeZ.disabled = true;
            }
            return;
        }

        contenedorInformeZ.innerHTML = construirHtmlTablasInformeZ(totems, 'modal');
        actualizarDiferenciasEnContenedor(contenedorInformeZ);

        if (btnGuardarInformeZ) {
            btnGuardarInformeZ.disabled = false;
        }
    }

    function actualizarDiferenciasInformeZ() {
        actualizarDiferenciasEnContenedor(contenedorInformeZ);
    }

    function recolectarPayloadInformeZ() {
        return recolectarPayloadInformeZDesdeContenedor(
            contenedorInformeZ,
            estadoModalInformeZ.totems,
            estadoModalInformeZ.jornadaId,
        );
    }

    function abrirModalInformeZ(jornadaId, opts) {
        opts = opts || {};
        if (!modalInformeZ || !cierreTotemHabilitado || jornadaId <= 0) {
            if (opts.ofrecerImpresion) {
                finalizarUiTrasCierreJornada(jornadaId, { abrirPdf: true });
            }
            return;
        }

        estadoModalInformeZ.jornadaId = jornadaId;
        estadoModalInformeZ.ofrecerImpresion = !!opts.ofrecerImpresion;

        if (contenedorInformeZ) {
            contenedorInformeZ.innerHTML = '<p class="text-muted">Cargando totales del sistema…</p>';
        }
        if (resultadoInformeZ) {
            resultadoInformeZ.classList.add('d-none');
            resultadoInformeZ.textContent = '';
        }
        if (btnGuardarInformeZ) {
            btnGuardarInformeZ.disabled = true;
        }
        if (btnOmitirImprimir) {
            if (opts.ofrecerImpresion) {
                btnOmitirImprimir.classList.remove('d-none');
            } else {
                btnOmitirImprimir.classList.add('d-none');
            }
        }

        var url = urlInformeZDatos(jornadaId);
        if (!url) {
            return;
        }

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.data.ok) {
                    throw new Error(extraerMensajeError(res, 'No se pudieron cargar los datos del Informe Z.'));
                }
                var d = res.data;
                if (subtituloInformeZ) {
                    var sub = 'Jornada ' + (d.fecha_jornada || '') + ' — ingrese los montos del Informe Z de cada tótem.';
                    if (d.informe_z_cargado && d.informe_z_en) {
                        sub += ' Última carga: ' + d.informe_z_en;
                        if (d.usuario_informe_z) {
                            sub += ' (' + d.usuario_informe_z + ')';
                        }
                    }
                    subtituloInformeZ.textContent = sub;
                }
                renderTablasInformeZ(d);
                if (d.conciliacion) {
                    renderResultadoConciliacion(d.conciliacion);
                }
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(modalInformeZ).modal('show');
                } else {
                    modalInformeZ.style.display = 'block';
                }
            })
            .catch(function (e) {
                alertar(e.message || 'Error al cargar Informe Z.', true);
                if (opts.ofrecerImpresion) {
                    finalizarUiTrasCierreJornada(jornadaId, { abrirPdf: true });
                }
            });
    }

    if (btnGuardarInformeZ) {
        btnGuardarInformeZ.addEventListener('click', function () {
            var jid = estadoModalInformeZ.jornadaId;
            var urlGuardar = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlInformeZGuardar) || '';
            if (jid <= 0 || !urlGuardar) {
                return;
            }
            btnGuardarInformeZ.disabled = true;
            postJson(urlGuardar, recolectarPayloadInformeZ()).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Informe Z guardado.', false);
                    if (res.data.conciliacion) {
                        renderResultadoConciliacion(res.data.conciliacion);
                    }
                    var imprimir = estadoModalInformeZ.ofrecerImpresion;
                    if (typeof window.jQuery !== 'undefined') {
                        window.jQuery(modalInformeZ).modal('hide');
                    }
                    finalizarUiTrasCierreJornada(jid, { abrirPdf: imprimir });
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo guardar el Informe Z.'), true);
                btnGuardarInformeZ.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al guardar el Informe Z.', true);
                btnGuardarInformeZ.disabled = false;
            });
        });
    }

    if (btnOmitirImprimir) {
        btnOmitirImprimir.addEventListener('click', function () {
            var jid = estadoModalInformeZ.jornadaId;
            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(modalInformeZ).modal('hide');
            }
            finalizarUiTrasCierreJornada(jid, { abrirPdf: jid > 0 });
        });
    }

    app.addEventListener('click', function (ev) {
        var btnInformeZ = ev.target.closest('.js-informe-z');
        if (btnInformeZ) {
            ev.preventDefault();
            var jidZ = parseInt(btnInformeZ.getAttribute('data-jornada-id'), 10) || 0;
            if (jidZ > 0) {
                abrirModalInformeZ(jidZ, { ofrecerImpresion: false });
            }
            return;
        }
    });

    (function iniciarInformeZPostCierre() {
        var params = new URLSearchParams(window.location.search);
        var jid = parseInt(params.get('informe_z_jornada'), 10) || 0;
        if (jid <= 0 || !cierreTotemHabilitado) {
            return;
        }
        params.delete('informe_z_jornada');
        var qs = params.toString();
        var cleanUrl = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState({}, '', cleanUrl);
        abrirModalInformeZ(jid, { ofrecerImpresion: true });
    })();
})();
