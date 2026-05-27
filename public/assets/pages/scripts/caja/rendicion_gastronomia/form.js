(function () {
    'use strict';

    var app = document.getElementById('rendicion-gastronomia-app');
    if (!app) {
        return;
    }

    var apiTurno = app.getAttribute('data-api-turno');
    var apiTurnoNumero = app.getAttribute('data-api-turno-numero') || '';
    var apiProponerCodigo = app.getAttribute('data-api-proponer-codigo') || '';
    var modo = app.getAttribute('data-modo') || 'crear';
    var rendicionId = app.getAttribute('data-rendicion-id') || '';
    var inicial = {};
    try {
        inicial = JSON.parse(app.getAttribute('data-inicial') || '{}');
    } catch (e) {
        inicial = {};
    }

    var inpTurnoId = document.getElementById('turno_operativo_gastronomia_id');
    var inpTurnoNumero = document.getElementById('turno_operativo_numero');
    var selEmpresa = document.getElementById('empresa_id');
    var panelDatos = document.getElementById('panel-datos-turno');
    var tbody = document.getElementById('tbody-movimientos');
    var totalGrillaEl = document.getElementById('total-grilla');
    var alertDiff = document.getElementById('alert-diferencias');
    var linkComprobante = document.getElementById('link-comprobante-cierre');
    var panelResumen = document.getElementById('panel-resumen-cierre');
    var lblTurnoSeleccionado = document.getElementById('lbl-turno-seleccionado');
    var formEl = document.getElementById('form-rendicion-gastronomia');

    var R = window.GastronomiaTotalesTurnoRender || {};
    var fmt = R.fmt || function (n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function setVal(id, v) {
        var el = document.getElementById(id);
        if (el) {
            el.value = v;
        }
    }

    function setText(id, t) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = t;
        }
    }

    function renderResumenTurnoHtml(totalesTurno) {
        if (!panelResumen || !totalesTurno) {
            if (panelResumen) {
                panelResumen.innerHTML = '';
            }
            return;
        }
        if (R.renderTotalesHtml) {
            panelResumen.innerHTML = R.renderTotalesHtml(
                totalesTurno,
                'Totales del turno a rendir',
                {}
            );
            return;
        }
        if (R.renderConciliacionHtml) {
            panelResumen.innerHTML = R.renderConciliacionHtml(totalesTurno);
        }
    }

    function aplicarEtiquetasTurno(d) {
        setText('lbl-pc', d.identificador_pc || '—');
        setText('lbl-turno', d.turno_nombre || '—');
        setText('lbl-jornada', d.fecha_jornada || '—');
        setText('lbl-fondo', '$' + fmt(d.iniciodelfondo != null ? d.iniciodelfondo : d.monto_habilitacion));
        setText('lbl-monto-habilitacion', '$' + fmt(d.monto_habilitacion != null ? d.monto_habilitacion : d.iniciodelfondo));
        setText('lbl-habilitacion-en', d.habilitacion_en || '—');
        setText('lbl-cierre-en', d.cierre_en || '—');
        setText('lbl-pv-cae', d.puntoventa_cae_label || '—');
        setText('lbl-pv-caea', d.puntoventa_caea_label || '—');

        var habUsu = '';
        if (d.usuario_habilita || d.usuario_habilitado) {
            habUsu = ' — ' + (d.usuario_habilita || '') + ' → ' + (d.usuario_habilitado || '');
        }
        setText('lbl-usuarios-habilitacion', habUsu);

        var cierreUsu = d.usuario_cierre ? ' — ' + d.usuario_cierre : '';
        setText('lbl-usuario-cierre', cierreUsu);
    }

    function empresaIdActual() {
        if (!selEmpresa) {
            return parseInt(val('empresa_id'), 10) || 0;
        }
        return parseInt(selEmpresa.value, 10) || 0;
    }

    function parseDecimal(str) {
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

    function formatearDecimalInput(inp) {
        if (!inp) {
            return;
        }
        inp.value = fmt(parseDecimal(inp.value));
    }

    function normalizarDecimalesGrillaAntesDeEnviar() {
        tbody.querySelectorAll('.js-monto-medio, .js-cotizacion-decimal').forEach(function (inp) {
            inp.value = String(parseDecimal(inp.value));
        });
    }

    function limpiarTurnoSeleccionado() {
        if (inpTurnoId) {
            inpTurnoId.value = '';
        }
        if (inpTurnoNumero) {
            inpTurnoNumero.value = '';
        }
        if (lblTurnoSeleccionado) {
            lblTurnoSeleccionado.textContent = '—';
        }
        panelDatos.classList.add('d-none');
        if (panelResumen) {
            panelResumen.innerHTML = '';
        }
        tbody.innerHTML = '<tr><td colspan="3" class="text-muted text-center p-3">Seleccione un cierre de turno.</td></tr>';
        if (linkComprobante) {
            linkComprobante.classList.add('d-none');
        }
        if (totalGrillaEl) {
            totalGrillaEl.textContent = '$0,00';
        }
    }

    function recalcularDiferencias() {
        var totalCobrado = parseFloat(val('totalcobrado')) || 0;
        var redondeo = parseFloat(val('totalredondeo')) || 0;
        var redInv = parseFloat(val('totalredondeoinvitacion')) || 0;
        var sobrante = parseFloat(val('sobrantefaltante')) || 0;
        var totalGrilla = 0;

        tbody.querySelectorAll('.js-monto-medio').forEach(function (inp) {
            totalGrilla += parseDecimal(inp.value);
        });
        tbody.querySelectorAll('.js-monto-nc-valor').forEach(function (inp) {
            totalGrilla += parseFloat(inp.value) || 0;
        });
        totalGrilla = Math.round(totalGrilla * 100) / 100;

        if (totalGrillaEl) {
            totalGrillaEl.textContent = '$' + fmt(totalGrilla);
        }

        var totalAjustado = Math.round((totalGrilla + redondeo + redInv + sobrante) * 100) / 100;
        var diff = Math.round((totalAjustado - totalCobrado) * 100) / 100;
        var cuadra = Math.abs(diff) <= 0.02;

        if (!alertDiff) {
            return;
        }

        alertDiff.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
        if (cuadra) {
            alertDiff.classList.add('alert-success');
            alertDiff.innerHTML = '<strong>Cuadra.</strong> Total ajustado $' + fmt(totalAjustado)
                + ' = cobrado del turno $' + fmt(totalCobrado) + '.';
        } else {
            alertDiff.classList.add('alert-warning');
            var tipo = diff > 0 ? 'Sobra' : 'Falta';
            alertDiff.innerHTML = '<strong>Diferencia a compensar:</strong> ' + tipo + ' $' + fmt(Math.abs(diff))
                + ' — Total grilla $' + fmt(totalGrilla)
                + ' + ajustes $' + fmt(redondeo + redInv + sobrante)
                + ' = $' + fmt(totalAjustado)
                + ' vs cobrado turno $' + fmt(totalCobrado) + '.';
        }
    }

    function renderMovimientos(movimientos) {
        if (!movimientos || !movimientos.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted text-center p-3">Sin medios de pago en el cierre.</td></tr>';
            recalcularDiferencias();
            return;
        }

        var html = '';
        var idxPersistido = 0;
        movimientos.forEach(function (m) {
            var esNc = !!(m.es_nota_credito);
            var montoFmt = fmt(parseFloat(m.monto) || 0);
            var cotFmt = fmt(parseFloat(m.cotizacion != null ? m.cotizacion : 1) || 1);
            var trStyle = esNc ? ' style="background:#fdecea;"' : '';
            var tdStyle = esNc ? ' style="color:#922b21;font-weight:bold;"' : '';

            html += '<tr' + trStyle + '>';
            html += '<td' + tdStyle + '>' + esc(m.nombre || m.codigo || (esNc ? 'Notas de crédito' : 'Medio #' + m.cuentacaja_id));
            if (!esNc) {
                html += '<input type="hidden" name="movimientos[' + idxPersistido + '][cuentacaja_id]" value="' + esc(m.cuentacaja_id) + '"/>';
            }
            html += '</td>';
            html += '<td class="text-right"' + tdStyle + '>';
            if (esNc) {
                html += '<input type="hidden" class="js-monto-nc-valor" value="' + esc(String(parseFloat(m.monto) || 0)) + '"/>';
                html += '<span class="d-inline-block py-1">$' + esc(montoFmt) + '</span>';
            } else {
                html += '<input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-monto-medio js-monto-decimal js-recalcula" ';
                html += 'name="movimientos[' + idxPersistido + '][monto]" value="' + esc(montoFmt) + '"/>';
            }
            html += '</td>';
            html += '<td class="text-right"' + tdStyle + '>';
            if (esNc) {
                html += '<span class="d-inline-block py-1">$' + esc(cotFmt) + '</span>';
            } else {
                html += '<input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-cotizacion-decimal" ';
                html += 'name="movimientos[' + idxPersistido + '][cotizacion]" value="' + esc(cotFmt) + '"/>';
            }
            html += '</td>';
            html += '</tr>';
            if (!esNc) {
                idxPersistido++;
            }
        });
        tbody.innerHTML = html;
        bindRecalcula();
        recalcularDiferencias();
    }

    function setTurnoSeleccionado(id, etiqueta) {
        if (inpTurnoId) {
            inpTurnoId.value = id;
        }
        if (inpTurnoNumero) {
            inpTurnoNumero.value = id;
        }
        if (lblTurnoSeleccionado) {
            lblTurnoSeleccionado.textContent = etiqueta || ('Op. #' + id);
        }
    }

    function aplicarDatosTurno(d) {
        panelDatos.classList.remove('d-none');

        if (selEmpresa && d.empresa_id && selEmpresa.tagName === 'SELECT') {
            selEmpresa.value = String(d.empresa_id);
        }
        setVal('puntoventa_cae_id', d.puntoventa_cae_id);
        setVal('puntoventa_caea_id', d.puntoventa_caea_id);
        setVal('iniciodelfondo', d.iniciodelfondo);
        setVal('totalfactura', d.totalfactura);
        setVal('totalcobrado', d.totalcobrado);
        setVal('totalinvitacion', d.totalinvitacion);
        setVal('totalnotacredito', d.totalnotacredito);
        setVal('totalredondeo', d.totalredondeo);
        setVal('totalredondeoinvitacion', d.totalredondeoinvitacion);
        setVal('sobrantefaltante', d.sobrantefaltante);

        var etiqueta = 'Op. #' + d.turno_operativo_gastronomia_id
            + ' — ' + (d.turno_nombre || '')
            + ' — ' + (d.identificador_pc || '')
            + ' — cierre ' + (d.cierre_en || '');
        setTurnoSeleccionado(d.turno_operativo_gastronomia_id, etiqueta);

        aplicarEtiquetasTurno(d);
        renderResumenTurnoHtml(d.totales_turno);

        if (linkComprobante && d.url_comprobante_cierre) {
            linkComprobante.href = d.url_comprobante_cierre;
            linkComprobante.classList.remove('d-none');
        }

        renderMovimientos(d.movimientos || []);
    }

    function cargarTurno(turnoId) {
        if (!turnoId) {
            limpiarTurnoSeleccionado();
            return;
        }

        var body = new FormData();
        body.append('turno_operativo_gastronomia_id', turnoId);
        if (rendicionId) {
            body.append('excepto_rendicion_id', rendicionId);
        }
        var token = document.querySelector('input[name="_token"]');
        if (token) {
            body.append('_token', token.value);
        }

        fetch(apiTurno, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.ok) {
                    alert(json.mensaje || 'No se pudo cargar el turno.');
                    limpiarTurnoSeleccionado();
                    return;
                }
                aplicarDatosTurno(json.datos);
            })
            .catch(function () {
                alert('Error de comunicación al cargar el cierre de turno.');
            });
    }

    window.rendicionGastronomiaCargarTurno = cargarTurno;

    var buscandoTurnoPorNumero = false;

    function buscarTurnoPorNumero() {
        if (!inpTurnoNumero || modo === 'editar' || buscandoTurnoPorNumero) {
            return;
        }
        var numero = parseInt(inpTurnoNumero.value, 10);
        if (!numero || numero <= 0) {
            limpiarTurnoSeleccionado();
            return;
        }

        var empresaId = empresaIdActual();
        if (!empresaId) {
            alert('Seleccione una empresa.');
            inpTurnoNumero.value = '';
            return;
        }

        var url = apiTurnoNumero + '/' + numero + '?empresa_id=' + empresaId;
        if (rendicionId) {
            url += '&excepto_rendicion_id=' + rendicionId;
        }

        buscandoTurnoPorNumero = true;

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                return r.json().then(function (json) {
                    return { httpOk: r.ok, json: json };
                });
            })
            .then(function (res) {
                if (!res.json || !res.json.ok) {
                    alert((res.json && res.json.mensaje) ? res.json.mensaje : 'Cierre no encontrado.');
                    limpiarTurnoSeleccionado();
                    return;
                }
                setTurnoSeleccionado(res.json.turno.id, res.json.turno.etiqueta);
                cargarTurno(String(res.json.turno.id));
            })
            .catch(function () {
                alert('Error al buscar el cierre por número.');
            })
            .finally(function () {
                buscandoTurnoPorNumero = false;
            });
    }

    function onKeydownTurnoNumero(ev) {
        if (ev.key !== 'Enter' && ev.keyCode !== 13) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        buscarTurnoPorNumero();
    }

    function bindRecalcula() {
        document.querySelectorAll('.js-recalcula').forEach(function (el) {
            el.removeEventListener('input', recalcularDiferencias);
            el.addEventListener('input', recalcularDiferencias);
        });
        document.querySelectorAll('.js-monto-decimal, .js-cotizacion-decimal').forEach(function (el) {
            el.removeEventListener('blur', onBlurDecimal);
            el.addEventListener('blur', onBlurDecimal);
        });
    }

    function onBlurDecimal(ev) {
        formatearDecimalInput(ev.target);
        if (ev.target.classList.contains('js-monto-medio')) {
            recalcularDiferencias();
        }
    }

    function initEdicionLocal() {
        if (!inicial.turno_operativo_gastronomia_id) {
            return;
        }
        panelDatos.classList.remove('d-none');
        if (lblTurnoSeleccionado && inicial.turno_etiqueta) {
            lblTurnoSeleccionado.textContent = inicial.turno_etiqueta;
        }
        aplicarEtiquetasTurno(inicial);

        var totalesTurno = null;
        try {
            totalesTurno = JSON.parse(app.getAttribute('data-totales-turno') || 'null');
        } catch (e) {
            totalesTurno = null;
        }
        renderResumenTurnoHtml(totalesTurno);

        if (linkComprobante && inicial.url_comprobante_cierre) {
            linkComprobante.href = inicial.url_comprobante_cierre;
            linkComprobante.classList.remove('d-none');
        }

        renderMovimientos(inicial.movimientos || []);
        recalcularDiferencias();
    }

    function actualizarCodigoPropuesto() {
        if (modo !== 'crear' || !apiProponerCodigo) {
            return;
        }
        var empresaId = empresaIdActual();
        if (!empresaId) {
            setVal('codigo', '');
            return;
        }
        fetch(apiProponerCodigo + '?empresa_id=' + empresaId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.ok && json.codigo) {
                    setVal('codigo', json.codigo);
                }
            })
            .catch(function () { /* silencioso */ });
    }

    if (selEmpresa && modo === 'crear' && selEmpresa.tagName === 'SELECT') {
        selEmpresa.addEventListener('change', function () {
            limpiarTurnoSeleccionado();
            actualizarCodigoPropuesto();
        });
    }

    if (inpTurnoNumero && modo === 'crear') {
        inpTurnoNumero.addEventListener('blur', buscarTurnoPorNumero);
        inpTurnoNumero.addEventListener('keydown', onKeydownTurnoNumero);
    }

    if (formEl) {
        formEl.addEventListener('keydown', function (ev) {
            if ((ev.key === 'Enter' || ev.keyCode === 13) && ev.target && ev.target.id === 'turno_operativo_numero') {
                ev.preventDefault();
                ev.stopPropagation();
            }
        });

        formEl.addEventListener('submit', function (ev) {
            normalizarDecimalesGrillaAntesDeEnviar();
            if (modo === 'crear' && inpTurnoId && !inpTurnoId.value) {
                ev.preventDefault();
                alert('Debe cargar un cierre de turno antes de guardar.');
                if (inpTurnoNumero) {
                    inpTurnoNumero.focus();
                }
            }
        });
    }

    bindRecalcula();

    if (modo === 'editar') {
        initEdicionLocal();
    } else if (inpTurnoId && inpTurnoId.value) {
        if (lblTurnoSeleccionado && inicial.turno_etiqueta) {
            lblTurnoSeleccionado.textContent = inicial.turno_etiqueta;
        }
        cargarTurno(inpTurnoId.value);
    }
})();
