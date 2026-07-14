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
    var btnCerrarDisabledInicial = btnCerrar ? btnCerrar.disabled : false;
    var avisoCierreEnProgreso = document.getElementById('jornada-cierre-en-progreso');

    function empresaId() {
        var fromSelect = selectEmpresa ? parseInt(selectEmpresa.value, 10) || 0 : 0;
        if (fromSelect > 0) {
            return fromSelect;
        }
        var fromCfg = window.JORNADA_GASTRONOMIA && parseInt(window.JORNADA_GASTRONOMIA.empresaId, 10);
        return fromCfg > 0 ? fromCfg : 0;
    }

    var waitryPreviewCargaEnCurso = false;

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
        var nombre = escHtml(ln.etiqueta || ln.cuentacaja_nombre || '');
        var tipo = escHtml(ln.tipo_waitry || 'totem');
        var mSis = Number(ln.monto_sistema || 0);
        var mZ = ln.monto_informe_z != null ? Number(ln.monto_informe_z) : null;
        if (scope === 'preview' && (mZ == null || isNaN(mZ)) && mSis > 0) {
            mZ = mSis;
        }
        var inputVal = mZ != null && !isNaN(mZ) ? escHtml(formatMontoInformeZInput(mZ)) : '';
        var soloLecturaPreview = scope === 'preview';

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
        if (soloLecturaPreview) {
            html += ' readonly tabindex="-1" title="Precarga automática — ajuste solo en Caja al rendir"';
        }
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
        var granTotalSistema = 0;
        totems.forEach(function (bloque, idxTotem) {
            granTotalSistema += Number(bloque.total_ingreso_sistema || 0);
            var maps = mapaSistemaDesdeLineas(bloque.lineas || []);
            var titulo = escHtml(bloque.ubicacion_nombre || 'Tótem');
            if (bloque.detalle) {
                titulo += ' — ' + escHtml(bloque.detalle);
            }
            if (bloque.waitry_table_id) {
                titulo += ' <span class="text-muted">(tableId ' + parseInt(bloque.waitry_table_id, 10) + ')</span>';
            }

            var totemIdCard = parseInt(bloque.totem_id, 10);
            if (isNaN(totemIdCard)) {
                totemIdCard = 0;
            }
            html += '<div class="card mb-2 jornada-informe-z-totem" data-totem-idx="' + idxTotem + '" data-totem-id="' + totemIdCard + '"';
            if (bloque.plantilla_unificada) {
                html += ' data-plantilla-unificada="1"';
            }
            if (bloque.waitry_table_id) {
                html += ' data-waitry-table-id="' + parseInt(bloque.waitry_table_id, 10) + '"';
            }
            html += '>';
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

            html += '</tbody>';
            html += '<tfoot><tr class="jornada-informe-z-totales-fila bg-light font-weight-bold">';
            html += '<td>Total</td>';
            html += '<td class="text-right jornada-informe-z-sistema js-total-sistema">$' + formatearMonto(bloque.total_ingreso_sistema) + '</td>';
            html += '<td class="text-right jornada-informe-z-sistema js-total-informe-z">$0,00</td>';
            html += '<td class="text-right js-total-diferencia">—</td>';
            html += '<td></td></tr></tfoot></table>';
            html += '<div class="px-2 py-1 border-top">';
            html += '<button type="button" class="btn btn-link btn-sm p-0 js-informe-z-agregar-linea" data-scope="' + scope + '">';
            html += '+ Agregar medio de pago</button></div>';
            html += '</div></div>';
        });
        html += '<div class="jornada-informe-z-resumen-totales alert alert-secondary py-2 mb-0 mt-2">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-center">';
        html += '<span><strong>Total Informe Z</strong></span>';
        html += '<span class="text-right">';
        html += '<span class="mr-3"><span class="text-muted small">Sistema</span> ';
        html += '<strong class="jornada-informe-z-sistema js-informe-z-gran-total-sistema">$' + formatearMonto(granTotalSistema) + '</strong></span>';
        html += '<span class="mr-3"><span class="text-muted small">Informe Z</span> ';
        html += '<strong class="js-informe-z-gran-total-z">$0,00</strong></span>';
        html += '<span><span class="text-muted small">Diferencia</span> ';
        html += '<strong class="js-informe-z-gran-total-diff">—</strong></span>';
        html += '</span></div></div>';
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

    function actualizarTotalesInformeZTabla(tabla) {
        if (!tabla) {
            return { sistema: 0, informeZ: 0, diferencia: 0 };
        }
        var totalSis = 0;
        var totalZ = 0;
        tabla.querySelectorAll('.jornada-informe-z-linea').forEach(function (tr) {
            var mSis = parseFloat(tr.getAttribute('data-monto-sistema') || '0') || 0;
            var inp = tr.querySelector('.js-monto-informe-z');
            var mZ = inp ? parseMontoInformeZ(inp.value) : 0;
            totalSis += mSis;
            totalZ += mZ;
        });
        totalSis = Math.round(totalSis * 100) / 100;
        totalZ = Math.round(totalZ * 100) / 100;
        var diff = Math.round((totalZ - totalSis) * 100) / 100;
        var tol = toleranciaInformeZ();
        var ok = Math.abs(diff) <= tol || (totalSis <= tol && totalZ <= tol);
        var celSis = tabla.querySelector('.js-total-sistema');
        var celZ = tabla.querySelector('.js-total-informe-z');
        var celDiff = tabla.querySelector('.js-total-diferencia');
        if (celSis) {
            celSis.textContent = '$' + formatearMonto(totalSis);
        }
        if (celZ) {
            celZ.textContent = '$' + formatearMonto(totalZ);
        }
        if (celDiff) {
            celDiff.textContent = (diff >= 0 ? '+' : '') + formatearMonto(diff);
            celDiff.className = 'text-right js-total-diferencia ' + (ok ? 'text-success' : 'text-danger');
        }
        return { sistema: totalSis, informeZ: totalZ, diferencia: diff };
    }

    function actualizarGranTotalInformeZ(wrap) {
        if (!wrap) {
            return;
        }
        var totalSis = 0;
        var totalZ = 0;
        wrap.querySelectorAll('.jornada-informe-z-tabla').forEach(function (tabla) {
            var parcial = actualizarTotalesInformeZTabla(tabla);
            totalSis += parcial.sistema;
            totalZ += parcial.informeZ;
        });
        totalSis = Math.round(totalSis * 100) / 100;
        totalZ = Math.round(totalZ * 100) / 100;
        var diff = Math.round((totalZ - totalSis) * 100) / 100;
        var tol = toleranciaInformeZ();
        var ok = Math.abs(diff) <= tol || (totalSis <= tol && totalZ <= tol);
        var celSis = wrap.querySelector('.js-informe-z-gran-total-sistema');
        var celZ = wrap.querySelector('.js-informe-z-gran-total-z');
        var celDiff = wrap.querySelector('.js-informe-z-gran-total-diff');
        if (celSis) {
            celSis.textContent = '$' + formatearMonto(totalSis);
        }
        if (celZ) {
            celZ.textContent = '$' + formatearMonto(totalZ);
        }
        if (celDiff) {
            celDiff.textContent = (diff >= 0 ? '+' : '') + formatearMonto(diff);
            celDiff.className = 'js-informe-z-gran-total-diff ' + (ok ? 'text-success' : 'text-danger font-weight-bold');
        }
    }

    function actualizarDiferenciasEnContenedor(contenedor) {
        if (!contenedor) {
            return;
        }
        contenedor.querySelectorAll('.jornada-informe-z-linea').forEach(function (tr) {
            actualizarDiferenciaEnFilaInformeZ(tr);
        });
        var wraps = contenedor.classList.contains('jornada-informe-z-wrap')
            ? [contenedor]
            : Array.prototype.slice.call(contenedor.querySelectorAll('.jornada-informe-z-wrap'));
        wraps.forEach(function (wrap) {
            actualizarGranTotalInformeZ(wrap);
        });
    }

    function recolectarPayloadInformeZDesdeContenedor(contenedor, totemsMeta, jornadaId) {
        var totemsOut = [];
        var cards = contenedor ? contenedor.querySelectorAll('.jornada-informe-z-totem') : [];
        cards.forEach(function (card) {
            var totemId = parseInt(card.getAttribute('data-totem-id'), 10) || 0;
            var tableId = parseInt(card.getAttribute('data-waitry-table-id'), 10) || 0;
            var lineas = [];
            card.querySelectorAll('.jornada-informe-z-linea').forEach(function (tr) {
                var ccId = parseInt((tr.querySelector('.cuentacaja_id') || {}).value, 10) || 0;
                var codInp = tr.querySelector('.codigo');
                var nomInp = tr.querySelector('.nombre');
                var inp = tr.querySelector('.js-monto-informe-z');
                var monto = parseMontoInformeZ(inp ? inp.value : '0');
                var mSistema = parseFloat(inp ? (inp.getAttribute('data-monto-sistema') || tr.getAttribute('data-monto-sistema') || '0') : '0') || 0;
                if (ccId <= 0 && monto <= 0 && mSistema <= 0) {
                    return;
                }
                lineas.push({
                    cuentacaja_id: ccId > 0 ? ccId : null,
                    cuentacaja_codigo: codInp ? String(codInp.value || '').trim() : '',
                    cuentacaja_nombre: nomInp ? String(nomInp.value || '').trim() : '',
                    tipo_waitry: tr.getAttribute('data-tipo-waitry') || 'totem',
                    monto: monto,
                    monto_informe_z: monto,
                    monto_sistema: Math.round(mSistema * 100) / 100,
                });
            });
            var esUnificado = card.getAttribute('data-plantilla-unificada') === '1';
            if (totemId > 0 || (esUnificado && lineas.length > 0)) {
                var entry = { totem_id: esUnificado ? 0 : totemId, lineas: lineas };
                if (tableId > 0 && !esUnificado) {
                    entry.waitry_table_id = tableId;
                }
                totemsOut.push(entry);
            }
        });

        if (totemsOut.length === 0 && totemsMeta && totemsMeta.length > 0) {
            (totemsMeta || []).forEach(function (bloque) {
                var tid = bloque.plantilla_unificada ? 0 : (parseInt(bloque.totem_id, 10) || 0);
                if (tid > 0 || bloque.plantilla_unificada) {
                    totemsOut.push({ totem_id: tid, lineas: [] });
                }
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
                var trInp = ev.target.closest('tr');
                actualizarDiferenciaEnFilaInformeZ(trInp);
                var contInp = trInp && (trInp.closest('#preview-informe-z-tablas')
                    || trInp.closest('#informe-z-contenido')
                    || trInp.closest('.jornada-informe-z-wrap'));
                if (contInp) {
                    actualizarDiferenciasEnContenedor(contInp);
                }
            }
        });

        document.body.addEventListener('blur', function (ev) {
            if (ev.target && ev.target.classList.contains('js-monto-informe-z')) {
                var val = parseMontoInformeZ(ev.target.value);
                ev.target.value = val > 0 ? formatMontoInformeZInput(val) : '';
                var trBlur = ev.target.closest('tr');
                actualizarDiferenciaEnFilaInformeZ(trBlur);
                var contBlur = trBlur && (trBlur.closest('#preview-informe-z-tablas')
                    || trBlur.closest('#informe-z-contenido')
                    || trBlur.closest('.jornada-informe-z-wrap'));
                if (contBlur) {
                    actualizarDiferenciasEnContenedor(contBlur);
                }
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

    function acumularMontosInformeZEnMapa(destino, lineas) {
        (lineas || []).forEach(function (ln) {
            var monto = ln.monto_informe_z != null ? Number(ln.monto_informe_z) : Number(ln.monto || 0);
            if (isNaN(monto)) {
                monto = 0;
            }
            var ccId = parseInt(ln.cuentacaja_id, 10) || 0;
            var tipo = String(ln.tipo_waitry || '').trim();
            if (ccId > 0) {
                destino.porCuenta[String(ccId)] = (destino.porCuenta[String(ccId)] || 0) + monto;
            }
            if (tipo) {
                destino.porTipo[tipo] = (destino.porTipo[tipo] || 0) + monto;
            }
        });
    }

    function mapaMontosInformeZPreservar(totemsPayload) {
        var mapa = {};
        (totemsPayload || []).forEach(function (t) {
            var tid = parseInt(t.totem_id, 10);
            if (isNaN(tid)) {
                tid = 0;
            }
            if (!mapa[tid]) {
                mapa[tid] = { porCuenta: {}, porTipo: {} };
            }
            acumularMontosInformeZEnMapa(mapa[tid], t.lineas || []);
        });
        return mapa;
    }

    function bloqueMapaInformeZPreservar(bloque, mapaPreservar) {
        if (!mapaPreservar) {
            return null;
        }
        if (bloque.plantilla_unificada) {
            var agg = { porCuenta: {}, porTipo: {} };
            Object.keys(mapaPreservar).forEach(function (key) {
                var src = mapaPreservar[key];
                Object.keys(src.porCuenta || {}).forEach(function (cc) {
                    agg.porCuenta[cc] = (agg.porCuenta[cc] || 0) + Number(src.porCuenta[cc] || 0);
                });
                Object.keys(src.porTipo || {}).forEach(function (tp) {
                    agg.porTipo[tp] = (agg.porTipo[tp] || 0) + Number(src.porTipo[tp] || 0);
                });
            });
            return agg;
        }
        var tid = parseInt(bloque.totem_id, 10) || 0;
        return mapaPreservar[tid] || null;
    }

    function fusionarMontosInformeZEnPlantilla(totems, mapaPreservar) {
        if (!mapaPreservar || !totems) {
            return totems;
        }
        return totems.map(function (bloque) {
            var bloqueMapa = bloqueMapaInformeZPreservar(bloque, mapaPreservar);
            if (!bloqueMapa) {
                return bloque;
            }
            var lineas = (bloque.lineas || []).map(function (ln) {
                var ccId = parseInt(ln.cuentacaja_id, 10) || 0;
                var tipo = String(ln.tipo_waitry || '').trim();
                var montoZ = null;
                if (ccId > 0 && bloqueMapa.porCuenta[String(ccId)] != null) {
                    montoZ = bloqueMapa.porCuenta[String(ccId)];
                } else if (tipo && bloqueMapa.porTipo[tipo] != null) {
                    montoZ = bloqueMapa.porTipo[tipo];
                }
                if (montoZ == null) {
                    return ln;
                }
                return Object.assign({}, ln, { monto_informe_z: montoZ });
            });
            return Object.assign({}, bloque, { lineas: lineas });
        });
    }

    function habilitarBotonRefrescoWaitry(habilitar) {
        var btn = document.getElementById('btn-refrescar-lectura-waitry-z');
        if (btn) {
            btn.disabled = !habilitar || waitryPreviewCargaEnCurso;
        }
    }

    function mostrarCargaPreviewWaitry(enCurso) {
        waitryPreviewCargaEnCurso = !!enCurso;
        var banner = document.getElementById('waitry-preview-cargando');
        var contenedor = document.getElementById('preview-cierre-totem-waitry');
        var acciones = document.getElementById('preview-informe-z-acciones');

        if (banner) {
            banner.classList.toggle('d-none', !enCurso);
        }

        if (contenedor) {
            contenedor.classList.toggle('d-none', enCurso);
        }
        if (acciones && enCurso) {
            acciones.classList.add('d-none');
        }

        habilitarBotonRefrescoWaitry(!enCurso && !!estadoPreviewInformeZ.snapshot);

        if (btnCerrar && puedeCerrar) {
            btnCerrar.disabled = enCurso || btnCerrarDisabledInicial;
        }
    }

    function renderPreviewCierreTotem(preview) {
        var contenedor = document.getElementById('preview-cierre-totem-waitry');
        var acciones = document.getElementById('preview-informe-z-acciones');
        if (!contenedor) {
            return;
        }

        contenedor.classList.remove('d-none');

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

        if (preview.waitry_order_id_hasta != null && parseInt(preview.waitry_order_id_hasta, 10) > 0) {
            html += '<p class="mb-2 small">';
            html += '<strong>Último ticket Waitry (se congela al cerrar la jornada):</strong> #'
                + escHtml(String(preview.waitry_order_id_hasta));
            if (preview.rango_etiqueta) {
                html += ' · ' + escHtml(preview.rango_etiqueta);
            }
            html += '</p>';
        }

        if (preview.ventana_operativa) {
            html += '<p class="text-muted mb-1">Ventana: ' + escHtml(preview.ventana_operativa) + '</p>';
        }
        if (preview.rango_etiqueta) {
            html += '<p class="mb-2">' + escHtml(preview.rango_etiqueta) + '</p>';
        }
        if (preview.tramo_ultimo_ticket_origen === 'ultimo_leido') {
            html += '<p class="text-muted small mb-2">Último ticket del tramo: el mayor ID leído en esta vista previa '
                + '(al cerrar se congela; no se vuelve a consultar Waitry).</p>';
        }

        var totalIngreso = preview.total_ingreso_totem != null
            ? Number(preview.total_ingreso_totem)
            : Number((preview.total_general || {}).total_ingreso || 0);
        var cantCanceladas = parseInt(preview.cantidad_canceladas_excluidas, 10) || 0;
        var cantAnuladasDesc = parseInt(preview.cantidad_anuladas_descuento_excluidas, 10) || 0;
        var resAnuladasDesc = preview.waitry_anuladas_descuento || {};
        var cantOrdenes = preview.cantidad_ingreso_totem != null
            ? parseInt(preview.cantidad_ingreso_totem, 10)
            : parseInt(preview.cantidad_ordenes, 10) || parseInt((preview.total_general || {}).cantidad_ordenes || 0, 10);

        html += '<div class="alert alert-secondary py-2 mb-2">';
        html += '<strong>Ingreso tótem (sistema):</strong> $' + formatearMonto(totalIngreso);
        html += ' · <strong>Cobros en tótem (no cancelados):</strong> ' + cantOrdenes;
        if (cantCanceladas > 0) {
            html += ' · <span class="text-muted">Excluidas ' + cantCanceladas + ' cancelada(s) en Waitry</span>';
        }
        if (cantAnuladasDesc > 0) {
            html += ' · <span class="text-muted">Excluidas ' + cantAnuladasDesc
                + ' anulada(s) por descuento 100 % (neto $0)</span>';
        }
        html += '</div>';

        var diag = preview.diagnostico_waitry || null;
        if (diag) {
            var ordTramo = parseInt(diag.ordenes_waitry_en_tramo, 10) || 0;
            var conIngreso = parseInt(diag.lineas_con_ingreso_totem, 10) || 0;
            var totalResumen = Number(diag.total_ingreso_resumen || 0);
            if (ordTramo > 0 && (totalResumen <= 0.0001 || conIngreso <= 0)) {
                html += '<div class="alert alert-warning py-2 mb-2 small">';
                html += 'Waitry devolvió ' + ordTramo + ' orden(es) en el tramo, pero ninguna se contó como cobro en tótem '
                    + '(revisar medio de pago, ventana operativa o waitry_table_id del tótem).';
                html += '</div>';
            } else if (ordTramo <= 0 && totalResumen <= 0.0001) {
                html += '<div class="alert alert-warning py-2 mb-2 small">';
                html += 'No hay órdenes Waitry nuevas en el tramo desde el último cierre. Si hubo ventas en el tótem, use '
                    + '«Actualizar lectura Waitry» después de que se registren en Waitry.';
                html += '</div>';
            }
        }

        html += '<div class="alert alert-info py-2 mb-2 small">';
        html += '<strong><i class="fa fa-info-circle"></i> Informe Z automático</strong> — ';
        html += 'No hace falta cargar montos en Gastronomía. Al cerrar la jornada se graba el Informe Z ';
        html += 'igual a la columna <strong>Sistema</strong> (totales Waitry por medio de pago). ';
        html += 'Si el Z físico de Waitry difiere, <strong>Caja</strong> puede corregirlo al rendir la jornada.';
        html += '</div>';

        if (preview.conciliacion && preview.conciliacion.ok) {
            html += '<div class="alert alert-success py-2 mb-2 small mb-2">';
            html += '<i class="fa fa-check"></i> Precarga lista: Informe Z = Sistema (diferencia $0,00). Puede cerrar la jornada directamente.';
            html += '</div>';
        }

        html += '<div id="preview-informe-z-resultado" class="d-none"></div>';
        html += '<div id="preview-informe-z-tablas">';
        html += construirHtmlTablasInformeZ(preview.totems || [], 'preview');
        html += '</div>';
        html += '<p class="text-muted mb-0 mt-2 small">Los totales Sistema salen de la carga inicial o de «Actualizar lectura Waitry». '
            + 'Al cerrar se congela el último ticket Waitry y se guarda el Informe Z automático (sin volver a consultar Waitry).</p>';

        contenedor.innerHTML = html;

        var tablasEl = document.getElementById('preview-informe-z-tablas');
        actualizarDiferenciasEnContenedor(tablasEl || contenedor);

        if (acciones) {
            acciones.classList.add('d-none');
        }

        habilitarBotonRefrescoWaitry(true);
    }

    function cargarPreviewCierreTotem(opciones) {
        opciones = opciones || {};
        var contenedor = document.getElementById('preview-cierre-totem-waitry');
        if (!cierreTotemHabilitado || !jornadaAbierta) {
            return Promise.resolve(null);
        }

        var eid = empresaId();
        var url = urlPreviewCierreTotem(eid);
        if (!url || eid <= 0) {
            return Promise.reject(new Error('Empresa o URL de vista previa inválida.'));
        }

        url += (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();

        mostrarCargaPreviewWaitry(true);

        var timeoutMs = 90000;
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = controller
            ? window.setTimeout(function () {
                controller.abort();
            }, timeoutMs)
            : null;

        return fetch(url, {
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
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
                if (!res.ok || !res.data.ok) {
                    throw new Error(extraerMensajeError(res, 'No se pudieron consultar los totales Waitry.'));
                }
                var preview = res.data.preview;
                if (opciones.mapaInformeZPreservar && preview && preview.totems) {
                    preview.totems = fusionarMontosInformeZEnPlantilla(
                        preview.totems,
                        opciones.mapaInformeZPreservar
                    );
                }
                renderPreviewCierreTotem(preview);
                return preview;
            })
            .catch(function (e) {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
                var msg = e && e.name === 'AbortError'
                    ? 'La consulta Waitry superó el tiempo de espera (' + Math.round(timeoutMs / 1000) + ' s).'
                    : (e.message || 'Error al consultar Waitry.');
                if (contenedor) {
                    contenedor.classList.remove('d-none');
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
                }
                throw e;
            })
            .finally(function () {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
                mostrarCargaPreviewWaitry(false);
            });
    }

    function refrescarLecturaWaitryAntesCierre() {
        if (!cierreTotemHabilitado || !jornadaAbierta) {
            return;
        }
        var msg = '¿Actualizar la lectura Waitry?\n\n'
            + '• Se consultará de nuevo el tótem (puede tardar hasta 1 minuto).\n'
            + '• Se actualizarán los totales del sistema (columna Sistema).\n'
            + '• Al cerrar la jornada se congelará el nuevo último ticket Waitry.\n'
            + '• Los montos del Informe Z que ya cargó en pantalla se conservan.\n\n'
            + 'Al confirmar el cierre del día no se vuelve a consultar Waitry.';
        if (!window.confirm(msg)) {
            return;
        }

        var tablasEl = document.getElementById('preview-informe-z-tablas');
        var preservar = null;
        if (tablasEl) {
            var payload = recolectarPayloadInformeZDesdeContenedor(
                tablasEl,
                estadoPreviewInformeZ.totems,
                estadoPreviewInformeZ.jornadaId
            );
            preservar = mapaMontosInformeZPreservar(payload.totems);
        }

        var btnRefresco = document.getElementById('btn-refrescar-lectura-waitry-z');
        if (btnRefresco) {
            btnRefresco.disabled = true;
        }

        cargarPreviewCierreTotem({ mapaInformeZPreservar: preservar })
            .then(function () {
                alertar('Lectura Waitry actualizada. Revise los totales Sistema y el último ticket antes de cerrar la jornada.', false);
            })
            .catch(function (e) {
                alertar(e.message || 'No se pudo actualizar la lectura Waitry.', true);
            })
            .finally(function () {
                habilitarBotonRefrescoWaitry(!!estadoPreviewInformeZ.snapshot);
            });
    }

    if (cierreTotemHabilitado && jornadaAbierta) {
        cargarPreviewCierreTotem();

        var btnRefrescoWaitry = document.getElementById('btn-refrescar-lectura-waitry-z');
        if (btnRefrescoWaitry) {
            btnRefrescoWaitry.addEventListener('click', refrescarLecturaWaitryAntesCierre);
        }

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
        if (!estadoPreviewInformeZ.totems || estadoPreviewInformeZ.totems.length === 0) {
            return null;
        }

        var totemsOut = [];
        estadoPreviewInformeZ.totems.forEach(function (bloque) {
            var lineas = [];
            (bloque.lineas || []).forEach(function (ln) {
                var mSis = Math.round((Number(ln.monto_sistema || 0)) * 100) / 100;
                if (mSis <= 0) {
                    return;
                }
                lineas.push({
                    cuentacaja_id: ln.cuentacaja_id > 0 ? ln.cuentacaja_id : null,
                    cuentacaja_codigo: ln.cuentacaja_codigo || '',
                    cuentacaja_nombre: ln.cuentacaja_nombre || ln.etiqueta || '',
                    tipo_waitry: ln.tipo_waitry || '',
                    monto: mSis,
                    monto_informe_z: mSis,
                    monto_sistema: mSis,
                });
            });
            if (lineas.length === 0) {
                return;
            }
            var entry = {
                totem_id: bloque.plantilla_unificada ? 0 : (parseInt(bloque.totem_id, 10) || 0),
                lineas: lineas,
            };
            if (bloque.waitry_table_id) {
                entry.waitry_table_id = parseInt(bloque.waitry_table_id, 10);
            }
            totemsOut.push(entry);
        });

        return totemsOut.length > 0 ? totemsOut : null;
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

    function fechaMaximaJornadaAbrir() {
        var fechaInput = document.getElementById('fecha_jornada_abrir');
        if (fechaInput && fechaInput.max) {
            return fechaInput.max;
        }
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    if (puedeAbrir && btnAbrir) {
        btnAbrir.addEventListener('click', function () {
            var fechaInput = document.getElementById('fecha_jornada_abrir');
            var obsInput = document.getElementById('observacion_abrir');
            var fechaVal = fechaInput ? fechaInput.value : '';
            var fechaMax = fechaMaximaJornadaAbrir();

            if (fechaVal && fechaVal > fechaMax) {
                alertar('La fecha de jornada no puede ser posterior a hoy (' + fechaMax + ').', true);
                return;
            }

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

    function enviarCierreJornada(bodyCerrar) {
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

                finalizarUiTrasCierreJornada(jornadaId, { abrirPdf: !!ct });
                return;
            }
            alertar(extraerMensajeError(res, 'No se pudo cerrar la jornada.'), true);
            mostrarCierreJornadaEnProgreso(false);
        }).catch(function () {
            alertar('Error de comunicación al cerrar la jornada (sin respuesta del servidor).', true);
            mostrarCierreJornadaEnProgreso(false);
        });
    }

    function validarCierreTotemAntesDeCerrar() {
        if (!cierreTotemHabilitado) {
            return null;
        }
        if (waitryPreviewCargaEnCurso) {
            return 'Espere a que termine la lectura Waitry antes de cerrar la jornada.';
        }
        if (!estadoPreviewInformeZ.snapshot) {
            return 'Debe cargar la vista previa del cierre tótem (se actualiza al abrir la pantalla o con «Actualizar lectura Waitry»). '
                + 'El cierre de jornada no vuelve a consultar Waitry: la grabación usa ese snapshot.';
        }
        if (!recolectarInformeZPreviewParaCierre()) {
            return 'Espere a que carguen los totales Sistema del Informe Z (vista previa Waitry) antes de cerrar la jornada.';
        }
        return null;
    }

    if (puedeCerrar && btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            if (!window.confirm('¿Confirma el cierre de la jornada? No podrá facturar hasta abrir una nueva.')) {
                return;
            }

            var errTotem = validarCierreTotemAntesDeCerrar();
            if (errTotem) {
                alertar(errTotem, true);
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

            enviarCierreJornada(bodyCerrar);
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

            var msg = '¿Eliminar la apertura del ' + fechaJornada + ' (ID ' + jornadaId + ')?'
                + '\n\nSolo permitido si la jornada no tiene movimientos (turnos operativos ni comprobantes).';
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

    function renderCobrosPostCierre(cobrosPostCierre) {
        var contenedor = document.getElementById('informe-z-post-cierre');
        if (!contenedor) {
            return;
        }
        if (!cobrosPostCierre || !cobrosPostCierre.tiene_anomalias) {
            contenedor.innerHTML = '';
            return;
        }

        var html = '<div class="alert alert-warning mt-3 mb-0 cobros-post-cierre-panel">';
        html += '<h6 class="alert-heading mb-2"><i class="fa fa-exclamation-triangle"></i> ';
        html += 'Cobros en tótem posteriores al cierre (' + (cobrosPostCierre.cantidad_comandas || 0) + ')</h6>';
        html += '<p class="small mb-2">Comandas dentro de la ventana cobradas en Waitry después del cierre ';
        html += '(' + escHtml(cobrosPostCierre.cierre_jornada_en_fmt || '—') + '). ';
        html += 'El Informe Z histórico no cambia; Tesorería debe sumar estos importes al total de facturación post-cierre.</p>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-2 bg-white">';
        html += '<thead class="thead-light"><tr><th>Comanda</th><th>Medio</th><th class="text-right">Monto</th>';
        html += '<th>Colocada</th><th>Cobrada Waitry</th><th>Factura proceso</th></tr></thead><tbody>';

        (cobrosPostCierre.comandas || []).forEach(function (c) {
            html += '<tr>';
            html += '<td>' + escHtml(c.display_id || '—');
            if (c.waitry_order_id) {
                html += ' <span class="text-muted small">(#' + parseInt(c.waitry_order_id, 10) + ')</span>';
            }
            html += '</td>';
            html += '<td>' + escHtml(c.medio_etiqueta || '—') + '</td>';
            html += '<td class="text-right">$' + formatearMonto(c.total || 0) + '</td>';
            html += '<td>' + escHtml(c.placed_at_fmt || '—') + '</td>';
            html += '<td>' + escHtml(c.cobro_en_fmt || '—');
            if (c.minutos_despues_cierre) {
                html += ' <span class="text-muted small">(+' + parseInt(c.minutos_despues_cierre, 10) + ' min)</span>';
            }
            html += '</td><td class="small">';
            if (c.facturada_proceso) {
                html += escHtml(c.numero_comprobante || 'Sí');
                if (c.cierre_jornada_proceso_lote) {
                    html += ' <span class="text-muted">(lote ' + parseInt(c.cierre_jornada_proceso_lote, 10) + ')</span>';
                }
            } else {
                html += '<span class="text-muted">Pendiente</span>';
            }
            html += '</td></tr>';
        });

        html += '</tbody></table></div>';
        html += '<div class="row small font-weight-bold">';
        html += '<div class="col-md-4">Informe Z al cierre:<span class="d-block">$' + formatearMonto(cobrosPostCierre.total_cierre_historico || 0) + '</span></div>';
        html += '<div class="col-md-4">+ Post-cierre tótem:<span class="d-block text-warning">$' + formatearMonto(cobrosPostCierre.total_post_cierre || 0) + '</span></div>';
        html += '<div class="col-md-4">= Total Tesorería:<span class="d-block" style="font-size:1.05rem;">$' + formatearMonto(cobrosPostCierre.total_tesoreria || 0) + '</span></div>';
        html += '</div></div>';

        contenedor.innerHTML = html;
    }

    function renderTransmisionFaltanteZ(bloque) {
        var contenedor = document.getElementById('informe-z-transmision-faltante');
        if (!contenedor) {
            return;
        }
        if (!bloque || !bloque.tiene_diferencias) {
            contenedor.innerHTML = '';
            return;
        }

        var html = '<div class="alert alert-danger mt-3 mb-0 transmision-faltante-z-panel">';
        html += '<h6 class="alert-heading mb-2"><i class="fa fa-exclamation-circle"></i> ';
        html += 'Comandas no transmitidas a tiempo (' + (bloque.cantidad_comandas || 0) + ')</h6>';
        html += '<p class="small mb-2">No entraron al Informe Z del cierre (retraso / hueco Waitry en el snapshot). ';
        html += 'El Z histórico no se modifica; Tesorería debe sumar estos importes al presentar.';
        if (bloque.calculado_en_fmt) {
            html += ' Verificado ' + escHtml(bloque.calculado_en_fmt) + '.';
        }
        html += '</p>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-2 bg-white">';
        html += '<thead class="thead-light"><tr><th>Comanda</th><th>Medio</th><th class="text-right">Monto</th>';
        html += '<th>Colocada</th><th>Tótem / mesa</th></tr></thead><tbody>';

        (bloque.comandas || []).forEach(function (c) {
            html += '<tr>';
            html += '<td>' + escHtml(c.display_id || '—');
            if (c.waitry_order_id) {
                html += ' <span class="text-muted small">(#' + parseInt(c.waitry_order_id, 10) + ')</span>';
            }
            html += '</td>';
            html += '<td>' + escHtml(c.medio_label || c.tipo_medio || '—') + '</td>';
            html += '<td class="text-right">$' + formatearMonto(c.monto || 0) + '</td>';
            html += '<td>' + escHtml(c.placed_at_fmt || '—') + '</td>';
            html += '<td class="small">' + escHtml(c.waitry_layout_name || '');
            if (c.waitry_table_name) {
                html += ' / ' + escHtml(c.waitry_table_name);
            }
            html += '</td></tr>';
        });

        html += '</tbody></table></div>';
        html += '<div class="row small font-weight-bold">';
        html += '<div class="col-md-4">Informe Z al cierre:<span class="d-block">$' + formatearMonto(bloque.total_z_historico || 0) + '</span></div>';
        html += '<div class="col-md-4">+ No transmitidas:<span class="d-block text-danger">$' + formatearMonto(bloque.total_faltante || 0) + '</span></div>';
        html += '<div class="col-md-4">= Total Tesorería (ajuste):<span class="d-block" style="font-size:1.05rem;">$' + formatearMonto(bloque.total_tesoreria || 0) + '</span></div>';
        html += '</div></div>';

        contenedor.innerHTML = html;
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
        renderCobrosPostCierre(datos.cobros_post_cierre || null);
        renderTransmisionFaltanteZ(datos.transmision_faltante_z || null);

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
        var contenedorPostCierre = document.getElementById('informe-z-post-cierre');
        if (contenedorPostCierre) {
            contenedorPostCierre.innerHTML = '';
        }
        var contenedorTransmision = document.getElementById('informe-z-transmision-faltante');
        if (contenedorTransmision) {
            contenedorTransmision.innerHTML = '';
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
