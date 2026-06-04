(function () {
    'use strict';

    var app = document.getElementById('rendicion-gastronomia-app');
    if (!app) {
        return;
    }

    var apiTurno = app.getAttribute('data-api-turno');
    var apiJornada = app.getAttribute('data-api-jornada') || '';
    var apiTurnoNumero = app.getAttribute('data-api-turno-numero') || '';
    var apiJornadaNumero = app.getAttribute('data-api-jornada-numero') || '';
    var apiProponerCodigo = app.getAttribute('data-api-proponer-codigo') || '';
    var modo = app.getAttribute('data-modo') || 'crear';
    var rendicionId = app.getAttribute('data-rendicion-id') || '';
    var inicial = {};
    try {
        inicial = JSON.parse(app.getAttribute('data-inicial') || '{}');
    } catch (e) {
        inicial = {};
    }

    var inpTipo = document.getElementById('tipo_rendicion');
    var inpTurnoId = document.getElementById('turno_operativo_gastronomia_id');
    var inpTurnoNumero = document.getElementById('turno_operativo_numero');
    var inpJornadaId = document.getElementById('jornada_gastronomia_id');
    var inpJornadaNumero = document.getElementById('jornada_gastronomia_numero');
    var lblJornadaSeleccionada = document.getElementById('lbl-jornada-seleccionada');
    var panelAuditoriaJornada = document.getElementById('panel-auditoria-jornada');
    var panelInformeZJornada = document.getElementById('panel-informe-z-jornada');
    var contenidoInformeZJornada = document.getElementById('contenido-informe-z-jornada');
    var toleranciaInformeZ = parseFloat(app.getAttribute('data-tolerancia-informe-z')) || 0.02;
    var chkVerificacionGastronomia = document.getElementById('chk_verificacion_gastronomia');
    var hintVerificacionFooter = document.getElementById('hint-verificacion-footer');
    var bloqueVerificacionFooter = document.getElementById('bloque-verificacion-footer');
    var verifItemJornadaWaitry = document.getElementById('verif-item-jornada-waitry');
    var verifItemJornadaZ = document.getElementById('verif-item-jornada-z');
    var cierreGastronomiaCargado = !!(inicial.cierre_cargado);
    var selEmpresa = document.getElementById('empresa_id');
    var panelDatos = document.getElementById('panel-datos-turno');
    var tbody = document.getElementById('tbody-movimientos');
    var totalGrillaEl = document.getElementById('total-grilla');
    var alertDiff = document.getElementById('alert-diferencias');
    var linkComprobante = document.getElementById('link-comprobante-cierre');
    var panelResumen = document.getElementById('panel-resumen-cierre');
    var lblTurnoSeleccionado = document.getElementById('lbl-turno-seleccionado');
    var formEl = document.getElementById('form-rendicion-gastronomia');
    var btnGuardarRendicion = formEl ? formEl.querySelector('button[type="submit"]') : null;
    var avisoSinCierreCargado = document.getElementById('aviso-sin-cierre-cargado');
    var alertErroresEl = document.getElementById('alert-errores-rendicion-gastronomia');

    var cuentacajaEfectivoId = parseInt(inicial.cuentacaja_efectivo_id, 10) || 0;
    var esperadosSistema = {};
    var sobranteFaltanteBase = 0;
    /** Montos rendidos al cargar el cierre, por cuentacaja_id (baseline para sobrante/faltante). */
    var montosRendidosAlCargar = {};
    var sincronizandoSobrante = false;

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

    function scrollToAlertaErrores(el) {
        if (!el) {
            return;
        }
        window.setTimeout(function () {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 80);
    }

    function mostrarErrorRendicion(mensaje) {
        var textos = Array.isArray(mensaje) ? mensaje : [String(mensaje || 'Ocurrió un error.')];
        var items = '';
        textos.forEach(function (t) {
            var linea = String(t || '').trim();
            if (linea !== '') {
                items += '<li>' + esc(linea) + '</li>';
            }
        });
        if (!items) {
            items = '<li>Ocurrió un error.</li>';
        }

        if (alertErroresEl) {
            var cont = alertErroresEl.querySelector('.js-contenido-errores-rendicion');
            if (cont) {
                cont.innerHTML = '<ul class="mb-0 pl-3">' + items + '</ul>';
            }
            alertErroresEl.classList.remove('d-none');
            scrollToAlertaErrores(alertErroresEl);
            return;
        }

        alert(textos.join('\n'));
    }

    function ocultarErrorRendicion() {
        if (!alertErroresEl) {
            return;
        }
        alertErroresEl.classList.add('d-none');
        var cont = alertErroresEl.querySelector('.js-contenido-errores-rendicion');
        if (cont) {
            cont.innerHTML = '';
        }
    }

    window.rendicionGastronomiaMostrarError = mostrarErrorRendicion;
    window.rendicionGastronomiaOcultarError = ocultarErrorRendicion;

    function cuentacajaIdDesdeFila(tr) {
        if (!tr) {
            return 0;
        }
        var hid = tr.querySelector('input[name*="[cuentacaja_id]"]');
        return hid ? (parseInt(hid.value, 10) || 0) : 0;
    }

    /** Baseline al cargar turno: sobrante del cierre + montos rendidos iniciales en grilla. */
    function capturarBaselineAjustes() {
        sobranteFaltanteBase = Math.round((parseFloat(val('sobrantefaltante')) || 0) * 100) / 100;
        montosRendidosAlCargar = {};
        tbody.querySelectorAll('.js-monto-medio-rendicion').forEach(function (inp) {
            var ccId = cuentacajaIdDesdeFila(inp.closest('tr'));
            if (ccId > 0) {
                montosRendidosAlCargar[ccId] = parseDecimal(inp.value);
            }
        });
    }

    /**
     * Compensa en sobrante/faltante las diferencias entre montos rendidos y los valores al cargar,
     * para que total grilla + ajustes siga cuadrando con el cobrado del sistema.
     * Fórmula: sobrante = base + Σ(monto_inicial_medio − monto_rendido_medio).
     */
    function sincronizarSobrantePorMedios() {
        if (sincronizandoSobrante) {
            return;
        }
        if (!tbody.querySelector('.js-monto-medio-rendicion')) {
            return;
        }
        var sumaCompensacion = 0;
        tbody.querySelectorAll('.js-monto-medio-rendicion').forEach(function (inp) {
            var ccId = cuentacajaIdDesdeFila(inp.closest('tr'));
            if (ccId <= 0) {
                return;
            }
            var rendido = parseDecimal(inp.value);
            var baseRendido = Object.prototype.hasOwnProperty.call(montosRendidosAlCargar, ccId)
                ? montosRendidosAlCargar[ccId]
                : rendido;
            sumaCompensacion += baseRendido - rendido;
        });
        var nuevo = Math.round((sobranteFaltanteBase + sumaCompensacion) * 100) / 100;
        var elSf = document.getElementById('sobrantefaltante');
        if (!elSf) {
            return;
        }
        sincronizandoSobrante = true;
        elSf.value = String(nuevo);
        sincronizandoSobrante = false;
        destacarCampoAutoActualizado(elSf);
    }

    function destacarCampoAutoActualizado(el) {
        if (!el) {
            return;
        }
        el.classList.add('gastro-campo-auto-actualizado');
        window.setTimeout(function () {
            el.classList.remove('gastro-campo-auto-actualizado');
        }, 1800);
    }

    function recalcularDesdeMedios() {
        sincronizarSobrantePorMedios();
        recalcularDiferencias();
    }

    function mapaEsperadosDesdeTotales(totales) {
        var map = {};
        if (!totales || !totales.por_medio_pago) {
            return map;
        }
        totales.por_medio_pago.forEach(function (p) {
            var ccId = parseInt(p.cuentacaja_id, 10) || 0;
            if (ccId > 0) {
                var esperado = p.esperado != null ? p.esperado : p.total;
                map[ccId] = Math.round((parseFloat(esperado) || 0) * 100) / 100;
            }
        });
        return map;
    }

    function mapaEsperadosDesdeMovimientos(movimientos) {
        var map = {};
        (movimientos || []).forEach(function (m) {
            if (m.es_nota_credito) {
                return;
            }
            var ccId = parseInt(m.cuentacaja_id, 10) || 0;
            if (ccId > 0) {
                var esperado = m.esperado != null ? m.esperado : m.monto;
                map[ccId] = Math.round((parseFloat(esperado) || 0) * 100) / 100;
            }
        });
        return map;
    }

    function aplicarConfigMedios(d) {
        if (d.cuentacaja_efectivo_id != null) {
            cuentacajaEfectivoId = parseInt(d.cuentacaja_efectivo_id, 10) || 0;
        }
        if (d.totales_turno) {
            esperadosSistema = mapaEsperadosDesdeTotales(d.totales_turno);
        } else if (d.totales_dia) {
            esperadosSistema = mapaEsperadosDesdeTotales(d.totales_dia);
        } else if (d.movimientos) {
            esperadosSistema = mapaEsperadosDesdeMovimientos(d.movimientos);
        }
    }

    function esFilaEfectivo(cuentacajaId) {
        return cuentacajaEfectivoId > 0 && parseInt(cuentacajaId, 10) === cuentacajaEfectivoId;
    }

    function htmlCeldaEsperadoSistema(cuentacajaId) {
        var ccId = parseInt(cuentacajaId, 10) || 0;
        if (ccId <= 0) {
            return '<span class="text-muted">—</span>';
        }
        var esperado = esperadosSistema[ccId];
        if (esperado == null || Math.abs(esperado) < 0.001) {
            return '<span class="text-muted">$' + fmt(0) + '</span>';
        }
        var cls = esFilaEfectivo(ccId)
            ? 'gastro-rendicion-esperado-efectivo'
            : 'gastro-rendicion-esperado-medio font-weight-bold';
        return '<span class="' + cls + ' d-inline-block py-1">$' + esc(fmt(esperado)) + '</span>';
    }

    function htmlHintDiffMedio(cuentacajaId, montoRendido) {
        var ccId = parseInt(cuentacajaId, 10) || 0;
        if (ccId <= 0) {
            return '';
        }
        var esperado = esperadosSistema[ccId];
        if (esperado == null) {
            return '';
        }
        var rendido = typeof montoRendido === 'number' ? montoRendido : parseDecimal(montoRendido);
        var diff = Math.round((rendido - esperado) * 100) / 100;
        if (Math.abs(diff) <= 0.02) {
            return '';
        }
        var tipo = diff > 0 ? 'sobra' : 'falta';
        return '<small class="d-block text-warning js-hint-diff-medio mt-1">Δ $' + esc(fmt(Math.abs(diff)))
            + ' (' + tipo + ' vs sistema). '
            + 'Se compensa en <strong>sobrante / faltante</strong> abajo.</small>';
    }

    function actualizarHintsDiffMedios() {
        tbody.querySelectorAll('.js-monto-medio-rendicion').forEach(function (inp) {
            var tr = inp.closest('tr');
            var cont = tr ? tr.querySelector('.js-contenedor-hint-medio') : null;
            if (!cont) {
                return;
            }
            cont.innerHTML = htmlHintDiffMedio(cuentacajaIdDesdeFila(tr), inp.value);
        });
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

    function opcionesResumenTotales(totalesTurno) {
        var opts = {};
        if (totalesTurno && totalesTurno.arqueo_medios_cierre) {
            opts.arqueoMediosCierre = true;
            opts.arqueoSoloLectura = true;
            opts.cuentacaja_efectivo_id = cuentacajaEfectivoId > 0 ? cuentacajaEfectivoId : 1;
        }
        return opts;
    }

    function actualizarEncabezadoMediosRendidos(desdeContadoCierre) {
        var thMonto = document.querySelector('#tabla-movimientos thead .gastro-col-monto');
        if (!thMonto) {
            return;
        }
        thMonto.textContent = desdeContadoCierre
            ? 'Monto rendido (desde cierre)'
            : 'Monto rendido';
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
                opcionesResumenTotales(totalesTurno)
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

    function tipoActual() {
        if (inpTipo) {
            return inpTipo.value === 'jornada' ? 'jornada' : 'turno';
        }
        return 'turno';
    }

    function esJornada() {
        return tipoActual() === 'jornada';
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

    function actualizarAvisoSinCierre() {
        if (!avisoSinCierreCargado) {
            return;
        }
        avisoSinCierreCargado.classList.toggle('d-none', cierreGastronomiaCargado);
    }

    function fijarTipoUiSinLimpiar(tipo) {
        if (inpTipo) {
            inpTipo.value = tipo === 'jornada' ? 'jornada' : 'turno';
        }
        document.querySelectorAll('input[name="tipo_ui"]').forEach(function (radio) {
            var activo = radio.value === (tipo === 'jornada' ? 'jornada' : 'turno');
            radio.checked = activo;
            var lbl = radio.closest('label');
            if (lbl) {
                lbl.classList.toggle('active', activo);
            }
        });
        var bloqueTurno = document.getElementById('bloque-seleccion-turno');
        var bloqueJornada = document.getElementById('bloque-seleccion-jornada');
        var tituloSel = document.getElementById('titulo-seleccion-cierre');
        var tituloPanel = document.getElementById('titulo-panel-datos');
        var lblCodigo = document.getElementById('lbl-codigo');
        var esJorn = tipo === 'jornada';
        if (bloqueTurno) {
            bloqueTurno.classList.toggle('d-none', esJorn);
        }
        if (bloqueJornada) {
            bloqueJornada.classList.toggle('d-none', !esJorn);
        }
        if (tituloSel) {
            tituloSel.textContent = esJorn ? 'Cierre de jornada a rendir' : 'Cierre de turno a rendir';
        }
        if (tituloPanel) {
            tituloPanel.textContent = esJorn ? 'Datos de la jornada rendida' : 'Datos del turno rendido';
        }
        if (lblCodigo) {
            lblCodigo.textContent = esJorn ? 'Código interno (ERP)' : 'Ticket / código Anita';
        }
    }

    function actualizarEstadoBotonGuardar() {
        if (modo !== 'crear' || !btnGuardarRendicion) {
            return;
        }
        var puedeGuardar = cierreGastronomiaCargado
            && chkVerificacionGastronomia
            && chkVerificacionGastronomia.checked;
        btnGuardarRendicion.disabled = !puedeGuardar;
        if (hintVerificacionFooter) {
            if (!cierreGastronomiaCargado) {
                hintVerificacionFooter.textContent = 'Primero cargue el cierre (Consultar o número + Enter). Luego marque la casilla para habilitar Guardar.';
            } else if (!chkVerificacionGastronomia || !chkVerificacionGastronomia.checked) {
                hintVerificacionFooter.textContent = 'Cierre cargado. Marque la casilla de verificación para habilitar el botón Guardar.';
            } else {
                hintVerificacionFooter.textContent = 'Verificación confirmada. Puede registrar la rendición.';
            }
        }
        if (bloqueVerificacionFooter) {
            bloqueVerificacionFooter.classList.toggle('alert-warning', !puedeGuardar);
            bloqueVerificacionFooter.classList.toggle('alert-success', puedeGuardar);
        }
    }

    function habilitarVerificacionCajero(esJornadaCierre) {
        cierreGastronomiaCargado = true;
        actualizarAvisoSinCierre();
        if (chkVerificacionGastronomia) {
            chkVerificacionGastronomia.disabled = false;
        }
        actualizarEstadoBotonGuardar();
        if (verifItemJornadaWaitry) {
            verifItemJornadaWaitry.classList.toggle('d-none', !esJornadaCierre);
        }
        if (verifItemJornadaZ) {
            verifItemJornadaZ.classList.toggle('d-none', !esJornadaCierre);
        }
        var itemComp = document.getElementById('verif-item-comprobante');
        if (itemComp && linkComprobante && !linkComprobante.classList.contains('d-none')) {
            itemComp.classList.remove('text-muted');
            itemComp.classList.add('text-success');
        }
    }

    function deshabilitarVerificacionCajero() {
        cierreGastronomiaCargado = false;
        actualizarAvisoSinCierre();
        if (chkVerificacionGastronomia) {
            chkVerificacionGastronomia.disabled = true;
            chkVerificacionGastronomia.checked = false;
        }
        actualizarEstadoBotonGuardar();
        if (verifItemJornadaWaitry) {
            verifItemJornadaWaitry.classList.add('d-none');
        }
        if (verifItemJornadaZ) {
            verifItemJornadaZ.classList.add('d-none');
        }
        ['verif-item-comprobante', 'verif-item-totales', 'verif-item-medios'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.classList.add('text-muted');
                el.classList.remove('text-success');
            }
        });
    }

    function limpiarSeleccion() {
        if (inpTurnoId) {
            inpTurnoId.value = '';
        }
        if (inpTurnoNumero) {
            inpTurnoNumero.value = '';
        }
        if (inpJornadaId) {
            inpJornadaId.value = '';
        }
        if (inpJornadaNumero) {
            inpJornadaNumero.value = '';
        }
        if (lblTurnoSeleccionado) {
            lblTurnoSeleccionado.textContent = '—';
        }
        if (lblJornadaSeleccionada) {
            lblJornadaSeleccionada.textContent = '—';
        }
        if (panelAuditoriaJornada) {
            panelAuditoriaJornada.classList.add('d-none');
        }
        if (panelInformeZJornada) {
            panelInformeZJornada.classList.add('d-none');
        }
        if (contenidoInformeZJornada) {
            contenidoInformeZJornada.innerHTML = '';
        }
        var avisoTotem = document.getElementById('aviso-sin-cierre-totem-jornada');
        if (avisoTotem) {
            avisoTotem.remove();
        }
        panelDatos.classList.add('d-none');
        var tituloFact = document.getElementById('titulo-panel-facturacion');
        if (tituloFact) {
            tituloFact.textContent = 'Facturación y cobranzas del turno';
        }
        if (panelResumen) {
            panelResumen.innerHTML = '';
        }
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center p-3">Seleccione un cierre de turno.</td></tr>';
        if (linkComprobante) {
            linkComprobante.classList.add('d-none');
        }
        if (totalGrillaEl) {
            totalGrillaEl.textContent = '$0,00';
        }
        cuentacajaEfectivoId = 0;
        esperadosSistema = {};
        sobranteFaltanteBase = 0;
        montosRendidosAlCargar = {};
        deshabilitarVerificacionCajero();
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

        actualizarHintsDiffMedios();

        alertDiff.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
        if (cuadra) {
            alertDiff.classList.add('alert-success');
            alertDiff.innerHTML = '<strong>Cuadra.</strong> Total ajustado $' + fmt(totalAjustado)
                + ' = cobrado $' + fmt(totalCobrado) + '.';
        } else {
            alertDiff.classList.add('alert-warning');
            var tipoDiff = diff > 0 ? 'Sobra' : 'Falta';
            alertDiff.innerHTML = '<strong>Diferencia a compensar:</strong> ' + tipoDiff + ' $' + fmt(Math.abs(diff))
                + ' — Total grilla $' + fmt(totalGrilla)
                + ' + ajustes $' + fmt(redondeo + redInv + sobrante)
                + ' = $' + fmt(totalAjustado)
                + ' vs cobrado $' + fmt(totalCobrado) + '.';
        }
    }

    function limpiarTurnoSeleccionado() {
        limpiarSeleccion();
    }

    function renderMovimientos(movimientos) {
        if (!movimientos || !movimientos.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center p-3">Sin medios de pago en el cierre.</td></tr>';
            recalcularDiferencias();
            return;
        }

        var html = '';
        var idxPersistido = 0;
        movimientos.forEach(function (m) {
            var esNc = !!(m.es_nota_credito);
            var ccId = parseInt(m.cuentacaja_id, 10) || 0;
            var esEfectivo = !esNc && esFilaEfectivo(ccId);
            var montoFmt = fmt(parseFloat(m.monto) || 0);
            var cotFmt = fmt(parseFloat(m.cotizacion != null ? m.cotizacion : 1) || 1);
            var trStyle = esNc ? ' style="background:#fdecea;"' : '';
            var trClass = esEfectivo ? ' gastro-rendicion-fila-efectivo' : '';
            var tdStyle = esNc ? ' style="color:#922b21;font-weight:bold;"' : '';

            html += '<tr' + trStyle + (trClass ? ' class="' + trClass.trim() + '"' : '') + '>';
            html += '<td' + tdStyle + '>' + esc(m.nombre || m.codigo || (esNc ? 'Notas de crédito' : 'Medio #' + m.cuentacaja_id));
            if (esEfectivo) {
                html += ' <span class="badge badge-info badge-sm">Efectivo</span>';
            }
            if (!esNc && m.desde_contado_cierre) {
                html += ' <span class="badge badge-success badge-sm" title="Precargado del arqueo del cierre de turno">Cierre</span>';
            }
            if (!esNc) {
                html += '<input type="hidden" name="movimientos[' + idxPersistido + '][cuentacaja_id]" value="' + esc(m.cuentacaja_id) + '"/>';
            }
            html += '</td>';
            html += '<td class="gastro-col-esperado text-right"' + tdStyle + '>' + htmlCeldaEsperadoSistema(ccId) + '</td>';
            html += '<td class="gastro-col-monto text-right"' + tdStyle + '><div class="gastro-celda-numero">';
            if (esNc) {
                html += '<input type="hidden" class="js-monto-nc-valor" value="' + esc(String(parseFloat(m.monto) || 0)) + '"/>';
                html += '<span class="d-inline-block py-1">$' + esc(montoFmt) + '</span>';
            } else {
                html += '<input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-monto-medio js-monto-medio-rendicion js-monto-decimal js-recalcula" ';
                html += 'name="movimientos[' + idxPersistido + '][monto]" value="' + esc(montoFmt) + '"/>';
                html += '<div class="js-contenedor-hint-medio">' + htmlHintDiffMedio(ccId, montoFmt) + '</div>';
            }
            html += '</div></td>';
            html += '<td class="gastro-col-cotiz text-right"' + tdStyle + '><div class="gastro-celda-numero">';
            if (esNc) {
                html += '<span class="gastro-cotiz-valor">' + esc(cotFmt) + '</span>';
            } else {
                html += '<input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-cotizacion-decimal" ';
                html += 'name="movimientos[' + idxPersistido + '][cotizacion]" value="' + esc(cotFmt) + '"/>';
            }
            html += '</div></td>';
            html += '</tr>';
            if (!esNc) {
                idxPersistido++;
            }
        });
        tbody.innerHTML = html;
        bindRecalcula();
        capturarBaselineAjustes();
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

    function mostrarAvisoCierreTotem(d) {
        var idAviso = 'aviso-sin-cierre-totem-jornada';
        var existente = document.getElementById(idAviso);
        if (existente) {
            existente.remove();
        }
        if (!d.sin_cierre_totem_jornada || !d.aviso_cierre_totem) {
            return;
        }
        var div = document.createElement('div');
        div.id = idAviso;
        div.className = 'alert alert-danger py-2 mb-3';
        div.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + escHtml(d.aviso_cierre_totem);
        if (panelAuditoriaJornada && panelAuditoriaJornada.parentNode) {
            panelAuditoriaJornada.parentNode.insertBefore(div, panelAuditoriaJornada);
        } else if (panelDatos) {
            panelDatos.insertBefore(div, panelDatos.firstChild);
        }
    }

    function mostrarAuditoriaJornada(d) {
        if (!panelAuditoriaJornada) {
            return;
        }
        mostrarAvisoCierreTotem(d);
        panelAuditoriaJornada.classList.remove('d-none');
        var hasta = parseInt(d.waitry_order_id_hasta, 10) || 0;
        setText('lbl-waitry-hasta', hasta > 0 ? String(hasta) : '—');
        setText('lbl-waitry-rango', d.waitry_rango_etiqueta || (hasta > 0 ? 'Último order id #'+hasta : '—'));
        var proximo = parseInt(d.proximo_waitry_order_id, 10) || (hasta > 0 ? hasta + 1 : 0);
        setText('lbl-waitry-proximo', proximo > 0 ? String(proximo) : '—');
        renderNumeracionPorPuntoventa(d);
        renderInformeZJornada(d);
    }

    function renderNumeracionPorPuntoventa(d) {
        var cont = document.getElementById('contenedor-numeracion-pv');
        if (!cont) {
            return;
        }
        var filas = d.numeracion_por_puntoventa;
        if (!filas || !filas.length) {
            var numeracionJson = d.numeracion_comprobantes;
            if (numeracionJson && numeracionJson.por_puntoventa) {
                filas = numeracionJson.por_puntoventa;
            }
        }
        if (!filas || !filas.length) {
            cont.innerHTML = '<p class="text-muted mb-0">'
                + escHtml(d.numeracion_resumen || 'Sin comprobantes o sin PV CAE/CAEA configurados en gastronomía.')
                + '</p>';
            return;
        }

        var html = '<table class="table table-sm table-bordered rendicion-numeracion-pv mb-0">';
        html += '<thead class="thead-light"><tr>';
        html += '<th>Terminal</th><th>Rol</th><th>Punto de venta</th>';
        html += '<th class="text-right">Últ. ticket</th><th class="text-right">Cant.</th>';
        html += '<th class="text-right">Últ. NC</th><th class="text-right">Cant. NC</th>';
        html += '</tr></thead><tbody>';
        filas.forEach(function (f) {
            var term = escHtml(f.terminal_pc || '—');
            if (f.terminal_descripcion) {
                term += ' <span class="text-muted">' + escHtml(f.terminal_descripcion) + '</span>';
            }
            var pv = escHtml(trimPvLabel(f));
            html += '<tr>';
            html += '<td>' + term + '</td>';
            html += '<td><span class="badge badge-secondary">' + escHtml(f.rol_etiqueta || f.rol || '—') + '</span></td>';
            html += '<td>' + pv + '</td>';
            html += '<td class="text-right font-weight-bold">' + fmtEntero(f.ultimo_ticket) + '</td>';
            html += '<td class="text-right">' + fmtEntero(f.cantidad_tickets) + '</td>';
            html += '<td class="text-right">' + fmtEntero(f.ultimo_nota_credito) + '</td>';
            html += '<td class="text-right">' + fmtEntero(f.cantidad_notas_credito) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        if (d.numeracion_resumen) {
            html += '<p class="text-muted small mt-2 mb-0">' + escHtml(d.numeracion_resumen) + '</p>';
        }
        cont.innerHTML = html;
    }

    function trimPvLabel(f) {
        var cod = String(f.puntoventa_codigo || '').trim();
        var nom = String(f.puntoventa_nombre || '').trim();
        if (cod && nom) {
            return cod + ' — ' + nom;
        }
        return cod || nom || 'PV #' + (f.puntoventa_id || '—');
    }

    function fmtEntero(v) {
        if (v == null || v === '' || parseInt(v, 10) <= 0) {
            return '—';
        }
        return String(parseInt(v, 10));
    }

    function renderInformeZJornada(d) {
        if (!panelInformeZJornada || !contenidoInformeZJornada) {
            return;
        }
        var conciliacion = d.conciliacion_informe_z;
        var cargado = !!d.informe_z_cargado;
        var tol = parseFloat(d.tolerancia_informe_z) || toleranciaInformeZ;

        if (!cargado || !conciliacion || !conciliacion.totems || conciliacion.totems.length === 0) {
            panelInformeZJornada.classList.remove('d-none');
            var msg = '<p class="text-muted mb-0">';
            if (d.cierre_totem_habilitado === false) {
                msg += 'Cierre Waitry/tótem deshabilitado en configuración. No hay Informe Z para esta jornada.';
            } else {
                msg += 'Informe Z no cargado. Regístrelo desde <strong>Ventas → Gastronomía → Jornada</strong> antes de cerrar la jornada.';
            }
            msg += '</p>';
            contenidoInformeZJornada.innerHTML = msg;
            return;
        }

        panelInformeZJornada.classList.remove('d-none');
        var html = '';
        if (conciliacion.ok) {
            html += '<div class="alert alert-success py-2 mb-2">Informe Z cuadra con el sistema (tolerancia $'
                + esc(fmt(tol)) + ').</div>';
        } else {
            html += '<div class="alert alert-warning py-2 mb-2">Hay diferencias entre Informe Z y totales del sistema (tolerancia $'
                + esc(fmt(tol)) + ').</div>';
        }
        if (d.informe_z_en) {
            html += '<p class="text-muted mb-2">Cargado: ' + escHtml(d.informe_z_en);
            if (d.usuario_informe_z) {
                html += ' (' + escHtml(d.usuario_informe_z) + ')';
            }
            html += '</p>';
        }

        (conciliacion.totems || []).forEach(function (bloque) {
            html += '<div class="mb-3">';
            html += '<table class="table table-sm table-bordered rendicion-informe-z-tabla mb-0">';
            html += '<thead class="thead-light"><tr>';
            html += '<th colspan="3">' + escHtml(bloque.ubicacion_nombre || 'Tótem');
            if (bloque.detalle) {
                html += ' — ' + escHtml(bloque.detalle);
            }
            if (bloque.waitry_table_id) {
                html += ' <span class="text-muted">(tableId ' + escHtml(String(bloque.waitry_table_id)) + ')</span>';
            }
            if (!bloque.ok) {
                html += ' <span class="text-danger font-weight-bold">— DIFERENCIA</span>';
            }
            html += '</th>';
            html += '<th class="text-right">Sist. $' + esc(fmt(bloque.total_sistema || 0))
                + ' / Z $' + esc(fmt(bloque.total_informe_z || 0)) + '</th>';
            html += '</tr><tr>';
            html += '<th>Medio</th><th class="text-right">Sistema</th><th class="text-right">Informe Z</th><th class="text-right">Diferencia</th>';
            html += '</tr></thead><tbody>';
            (bloque.lineas || []).forEach(function (ln) {
                var trCls = ln.ok ? '' : ' class="rendicion-informe-z-diff"';
                html += '<tr' + trCls + '>';
                html += '<td>' + escHtml(ln.etiqueta || '—') + '</td>';
                html += '<td class="text-right">$' + esc(fmt(ln.monto_sistema || 0)) + '</td>';
                html += '<td class="text-right">$' + esc(fmt(ln.monto_informe_z || 0)) + '</td>';
                html += '<td class="text-right">$' + esc(fmt(ln.diferencia || 0)) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        });

        contenidoInformeZJornada.innerHTML = html;
    }

    function escHtml(s) {
        return esc(s);
    }

    function aplicarDatosJornada(d) {
        panelDatos.classList.remove('d-none');
        var tituloFact = document.getElementById('titulo-panel-facturacion');
        if (tituloFact) {
            tituloFact.textContent = 'Facturación y cobranzas de la jornada (todas las terminales)';
        }

        if (selEmpresa && d.empresa_id && selEmpresa.tagName === 'SELECT') {
            selEmpresa.value = String(d.empresa_id);
        }
        if (d.codigo_propuesto) {
            setVal('codigo', d.codigo_propuesto);
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

        var etiqueta = 'Jornada #' + d.jornada_gastronomia_id
            + ' — ' + (d.fecha_jornada || '')
            + ' — cierre ' + (d.cierre_en || '');
        if (inpJornadaId) {
            inpJornadaId.value = d.jornada_gastronomia_id;
        }
        if (inpJornadaNumero) {
            inpJornadaNumero.value = d.jornada_gastronomia_id;
        }
        if (lblJornadaSeleccionada) {
            lblJornadaSeleccionada.textContent = etiqueta;
        }

        setText('lbl-pc', 'Todas las terminales');
        setText('lbl-turno', 'Cierre de jornada');
        setText('lbl-jornada', d.fecha_jornada || '—');
        setText('lbl-fondo', '$' + fmt(0));
        setText('lbl-monto-habilitacion', '—');
        setText('lbl-habilitacion-en', d.apertura_en || '—');
        setText('lbl-cierre-en', d.cierre_en || '—');
        setText('lbl-pv-cae', d.puntoventa_cae_label || '—');
        setText('lbl-pv-caea', d.puntoventa_caea_label || '—');
        setText('lbl-usuarios-habilitacion', d.usuario_apertura ? ' — ' + d.usuario_apertura : '');
        setText('lbl-usuario-cierre', d.usuario_cierre ? ' — ' + d.usuario_cierre : '');

        renderResumenTurnoHtml(d.totales_dia);

        if (linkComprobante && d.url_comprobante_cierre) {
            linkComprobante.href = d.url_comprobante_cierre;
            linkComprobante.classList.remove('d-none');
        } else if (linkComprobante) {
            linkComprobante.classList.add('d-none');
        }

        mostrarAuditoriaJornada(d);
        aplicarConfigMedios(d);
        renderMovimientos(d.movimientos || []);
        habilitarVerificacionCajero(true);
    }

    function aplicarDatosTurno(d) {
        panelDatos.classList.remove('d-none');
        if (panelAuditoriaJornada) {
            panelAuditoriaJornada.classList.add('d-none');
        }
        if (panelInformeZJornada) {
            panelInformeZJornada.classList.add('d-none');
        }
        if (contenidoInformeZJornada) {
            contenidoInformeZJornada.innerHTML = '';
        }
        var tituloFactTurno = document.getElementById('titulo-panel-facturacion');
        if (tituloFactTurno) {
            tituloFactTurno.textContent = 'Facturación y cobranzas del turno';
        }

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

        aplicarConfigMedios(d);
        actualizarEncabezadoMediosRendidos(!!d.movimientos_desde_contado_cierre);
        renderMovimientos(d.movimientos || []);
        habilitarVerificacionCajero(false);
    }

    function mostrarCargandoCierre(mensaje) {
        panelDatos.classList.remove('d-none');
        if (panelResumen) {
            panelResumen.innerHTML = '<p class="text-muted mb-0"><i class="fa fa-spinner fa-spin"></i> '
                + escHtml(mensaje || 'Cargando datos del cierre…') + '</p>';
        }
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center p-3">'
            + escHtml(mensaje || 'Cargando medios de pago…') + '</td></tr>';
    }

    function cargarTurno(turnoId) {
        if (!turnoId) {
            limpiarTurnoSeleccionado();
            return;
        }

        fijarTipoUiSinLimpiar('turno');
        mostrarCargandoCierre('Cargando cierre de turno…');

        var body = new FormData();
        body.append('turno_operativo_gastronomia_id', turnoId);
        if (rendicionId) {
            body.append('excepto_rendicion_id', rendicionId);
        }
        var token = document.querySelector('input[name="_token"]');
        if (token) {
            body.append('_token', token.value);
        }

        fetchConTimeout(apiTurno, { method: 'POST', body: body, credentials: 'same-origin' }, 120000)
            .then(function (json) {
                if (!json.ok) {
                    mostrarErrorRendicion(json.mensaje || 'No se pudo cargar el turno.');
                    limpiarTurnoSeleccionado();
                    return;
                }
                aplicarDatosTurno(json.datos);
            })
            .catch(function (e) {
                mostrarErrorRendicion(e && e.message ? e.message : 'Error de comunicación al cargar el cierre de turno.');
                limpiarTurnoSeleccionado();
            });
    }

    function fetchConTimeout(url, options, timeoutMs) {
        var ms = timeoutMs || 120000;
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var opts = options || {};
        if (controller) {
            opts.signal = controller.signal;
        }
        var timer = controller
            ? window.setTimeout(function () { controller.abort(); }, ms)
            : null;

        return fetch(url, opts)
            .then(function (r) {
                return r.json().then(function (data) {
                    return { httpOk: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.httpOk || !res.data || !res.data.ok) {
                    var msg = (res.data && res.data.mensaje)
                        ? res.data.mensaje
                        : 'No se pudieron cargar los datos del cierre.';
                    throw new Error(msg);
                }
                return res.data;
            })
            .finally(function () {
                if (timer) {
                    window.clearTimeout(timer);
                }
            })
            .catch(function (e) {
                if (e && e.name === 'AbortError') {
                    throw new Error('La consulta superó el tiempo de espera (' + Math.round(ms / 1000) + ' s).');
                }
                throw e;
            });
    }

    function cargarJornada(jornadaId) {
        if (!jornadaId || !apiJornada) {
            limpiarSeleccion();
            return;
        }

        fijarTipoUiSinLimpiar('jornada');
        mostrarCargandoCierre('Cargando datos de la jornada…');

        var body = new FormData();
        body.append('jornada_gastronomia_id', jornadaId);
        if (rendicionId) {
            body.append('excepto_rendicion_id', rendicionId);
        }
        var token = document.querySelector('input[name="_token"]');
        if (token) {
            body.append('_token', token.value);
        }

        fetchConTimeout(apiJornada, { method: 'POST', body: body, credentials: 'same-origin' }, 120000)
            .then(function (json) {
                aplicarDatosJornada(json.datos);
            })
            .catch(function (e) {
                mostrarErrorRendicion(e && e.message ? e.message : 'Error de comunicación al cargar la jornada.');
                limpiarSeleccion();
            });
    }

    window.rendicionGastronomiaCargarTurno = cargarTurno;
    window.rendicionGastronomiaCargarJornada = cargarJornada;
    window.rendicionGastronomiaFijarTipo = fijarTipoUiSinLimpiar;

    var buscandoTurnoPorNumero = false;
    var buscandoJornadaPorNumero = false;

    function buscarJornadaPorNumero() {
        if (!inpJornadaNumero || modo === 'editar' || buscandoJornadaPorNumero || !apiJornadaNumero) {
            return;
        }
        var numero = parseInt(inpJornadaNumero.value, 10);
        if (!numero || numero <= 0) {
            limpiarSeleccion();
            return;
        }

        var empresaId = empresaIdActual();
        if (!empresaId) {
            mostrarErrorRendicion('Seleccione una empresa.');
            inpJornadaNumero.value = '';
            return;
        }

        var url = apiJornadaNumero + '/' + numero + '?empresa_id=' + empresaId;
        if (rendicionId) {
            url += '&excepto_rendicion_id=' + rendicionId;
        }

        buscandoJornadaPorNumero = true;

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                return r.json().then(function (json) {
                    return { httpOk: r.ok, json: json };
                });
            })
            .then(function (res) {
                if (!res.json || !res.json.ok) {
                    mostrarErrorRendicion((res.json && res.json.mensaje) ? res.json.mensaje : 'Jornada no encontrada.');
                    limpiarSeleccion();
                    return;
                }
                if (inpJornadaId) {
                    inpJornadaId.value = res.json.jornada.id;
                }
                if (inpJornadaNumero) {
                    inpJornadaNumero.value = res.json.jornada.id;
                }
                if (lblJornadaSeleccionada) {
                    lblJornadaSeleccionada.textContent = res.json.jornada.etiqueta || ('Jornada #' + res.json.jornada.id);
                }
                cargarJornada(String(res.json.jornada.id));
            })
            .catch(function () {
                mostrarErrorRendicion('Error al buscar la jornada por número.');
            })
            .finally(function () {
                buscandoJornadaPorNumero = false;
            });
    }

    function onKeydownJornadaNumero(ev) {
        if (ev.key !== 'Enter' && ev.keyCode !== 13) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        buscarJornadaPorNumero();
    }

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
            mostrarErrorRendicion('Seleccione una empresa.');
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
                    mostrarErrorRendicion((res.json && res.json.mensaje) ? res.json.mensaje : 'Cierre no encontrado.');
                    limpiarTurnoSeleccionado();
                    return;
                }
                setTurnoSeleccionado(res.json.turno.id, res.json.turno.etiqueta);
                cargarTurno(String(res.json.turno.id));
            })
            .catch(function () {
                mostrarErrorRendicion('Error al buscar el cierre por número.');
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
        tbody.querySelectorAll('.js-monto-medio-rendicion').forEach(function (el) {
            el.removeEventListener('input', recalcularDesdeMedios);
            el.addEventListener('input', recalcularDesdeMedios);
        });
        document.querySelectorAll('.js-monto-decimal, .js-cotizacion-decimal').forEach(function (el) {
            el.removeEventListener('blur', onBlurDecimal);
            el.addEventListener('blur', onBlurDecimal);
        });
    }

    function onBlurDecimal(ev) {
        formatearDecimalInput(ev.target);
        if (ev.target.classList.contains('js-monto-medio-rendicion')) {
            recalcularDesdeMedios();
        } else if (ev.target.classList.contains('js-monto-medio')) {
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

        cuentacajaEfectivoId = parseInt(inicial.cuentacaja_efectivo_id, 10) || cuentacajaEfectivoId;
        if (totalesTurno) {
            esperadosSistema = mapaEsperadosDesdeTotales(totalesTurno);
        } else if (inicial.movimientos && Object.keys(esperadosSistema).length === 0) {
            esperadosSistema = mapaEsperadosDesdeMovimientos(inicial.movimientos);
        }

        actualizarEncabezadoMediosRendidos(!!inicial.movimientos_desde_contado_cierre);
        renderMovimientos(inicial.movimientos || []);
        recalcularDiferencias();
        habilitarVerificacionCajero(false);
    }

    function aplicarTipoUi(tipo) {
        if (inpTipo) {
            inpTipo.value = tipo;
        }
        var bloqueTurno = document.getElementById('bloque-seleccion-turno');
        var bloqueJornada = document.getElementById('bloque-seleccion-jornada');
        var tituloSel = document.getElementById('titulo-seleccion-cierre');
        var tituloPanel = document.getElementById('titulo-panel-datos');
        var lblCodigo = document.getElementById('lbl-codigo');

        if (tipo === 'jornada') {
            if (bloqueTurno) {
                bloqueTurno.classList.add('d-none');
            }
            if (bloqueJornada) {
                bloqueJornada.classList.remove('d-none');
            }
            if (tituloSel) {
                tituloSel.textContent = 'Cierre de jornada a rendir';
            }
            if (tituloPanel) {
                tituloPanel.textContent = 'Datos de la jornada rendida';
            }
            if (lblCodigo) {
                lblCodigo.textContent = 'Código interno (ERP)';
            }
        } else {
            if (bloqueTurno) {
                bloqueTurno.classList.remove('d-none');
            }
            if (bloqueJornada) {
                bloqueJornada.classList.add('d-none');
            }
            if (tituloSel) {
                tituloSel.textContent = 'Cierre de turno a rendir';
            }
            if (tituloPanel) {
                tituloPanel.textContent = 'Datos del turno rendido';
            }
            if (lblCodigo) {
                lblCodigo.textContent = 'Ticket / código Anita';
            }
        }
        limpiarSeleccion();
        actualizarCodigoPropuesto();
    }

    function actualizarCodigoPropuesto() {
        if (modo !== 'crear') {
            return;
        }
        if (esJornada()) {
            setVal('codigo', '');
            return;
        }
        if (!apiProponerCodigo) {
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

    document.querySelectorAll('input[name="tipo_ui"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked) {
                aplicarTipoUi(radio.value);
            }
        });
    });

    if (selEmpresa && modo === 'crear' && selEmpresa.tagName === 'SELECT') {
        selEmpresa.addEventListener('change', function () {
            limpiarSeleccion();
            actualizarCodigoPropuesto();
        });
    }

    if (inpTurnoNumero && modo === 'crear') {
        inpTurnoNumero.addEventListener('blur', buscarTurnoPorNumero);
        inpTurnoNumero.addEventListener('keydown', onKeydownTurnoNumero);
    }

    if (inpJornadaNumero && modo === 'crear') {
        inpJornadaNumero.addEventListener('blur', buscarJornadaPorNumero);
        inpJornadaNumero.addEventListener('keydown', onKeydownJornadaNumero);
    }

    if (formEl) {
        formEl.addEventListener('keydown', function (ev) {
            if ((ev.key === 'Enter' || ev.keyCode === 13) && ev.target
                && (ev.target.id === 'turno_operativo_numero' || ev.target.id === 'jornada_gastronomia_numero')) {
                ev.preventDefault();
                ev.stopPropagation();
            }
        });

        formEl.addEventListener('submit', function (ev) {
            normalizarDecimalesGrillaAntesDeEnviar();
            var cajaId = parseInt(val('caja_id'), 10) || 0;
            if (modo === 'crear' && cajaId <= 0) {
                ev.preventDefault();
                mostrarErrorRendicion('No tiene caja asignada. Ingrese desde Movimientos de caja o solicite asignación de cajero.');
                return;
            }
            if (modo === 'crear' && esJornada() && inpJornadaId && !inpJornadaId.value) {
                ev.preventDefault();
                mostrarErrorRendicion('Debe cargar una jornada cerrada antes de guardar.');
                if (inpJornadaNumero) {
                    inpJornadaNumero.focus();
                }
                return;
            }
            if (modo === 'crear' && !esJornada() && inpTurnoId && !inpTurnoId.value) {
                ev.preventDefault();
                mostrarErrorRendicion('Debe cargar un cierre de turno antes de guardar.');
                if (inpTurnoNumero) {
                    inpTurnoNumero.focus();
                }
                return;
            }
            if (modo === 'crear' && !cierreGastronomiaCargado) {
                ev.preventDefault();
                mostrarErrorRendicion('Debe cargar y revisar el cierre de gastronomía antes de registrar la rendición.');
                return;
            }
            if (modo === 'crear' && chkVerificacionGastronomia && !chkVerificacionGastronomia.checked) {
                ev.preventDefault();
                actualizarEstadoBotonGuardar();
                if (bloqueVerificacionFooter) {
                    bloqueVerificacionFooter.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                if (chkVerificacionGastronomia && !chkVerificacionGastronomia.disabled) {
                    chkVerificacionGastronomia.focus();
                }
                return;
            }
        });
    }

    if (chkVerificacionGastronomia) {
        chkVerificacionGastronomia.addEventListener('change', actualizarEstadoBotonGuardar);
    }

    function actualizarBaselineSobranteManual() {
        if (sincronizandoSobrante) {
            return;
        }
        var sumaCompensacion = 0;
        tbody.querySelectorAll('.js-monto-medio-rendicion').forEach(function (inp) {
            var ccId = cuentacajaIdDesdeFila(inp.closest('tr'));
            if (ccId <= 0) {
                return;
            }
            var rendido = parseDecimal(inp.value);
            var baseRendido = Object.prototype.hasOwnProperty.call(montosRendidosAlCargar, ccId)
                ? montosRendidosAlCargar[ccId]
                : rendido;
            sumaCompensacion += baseRendido - rendido;
        });
        var actual = Math.round((parseFloat(val('sobrantefaltante')) || 0) * 100) / 100;
        sobranteFaltanteBase = Math.round((actual - sumaCompensacion) * 100) / 100;
    }

    function bindSobranteManualBaseline() {
        var inpSf = document.getElementById('sobrantefaltante');
        if (!inpSf || inpSf.getAttribute('data-baseline-bound') === '1') {
            return;
        }
        inpSf.setAttribute('data-baseline-bound', '1');
        inpSf.addEventListener('input', function () {
            actualizarBaselineSobranteManual();
        });
    }

    document.querySelectorAll('.js-cerrar-error-rendicion').forEach(function (btn) {
        btn.addEventListener('click', function () {
            ocultarErrorRendicion();
        });
    });

    var flashErr = document.getElementById('alerta-flash-errores-rendicion');
    if (flashErr) {
        scrollToAlertaErrores(flashErr);
    }

    bindRecalcula();
    bindSobranteManualBaseline();

    if (modo === 'crear' && inpTipo) {
        aplicarTipoUi(inpTipo.value || 'turno');
        actualizarAvisoSinCierre();
        actualizarEstadoBotonGuardar();
    }

    if (modo === 'editar') {
        if (inicial.tipo === 'jornada') {
            panelDatos.classList.remove('d-none');
            var tituloFactEd = document.getElementById('titulo-panel-facturacion');
            if (tituloFactEd) {
                tituloFactEd.textContent = 'Facturación y cobranzas de la jornada (todas las terminales)';
            }
            if (panelAuditoriaJornada) {
                mostrarAuditoriaJornada(inicial);
            }
            var totalesDia = null;
            try {
                totalesDia = JSON.parse(app.getAttribute('data-totales-dia') || 'null');
            } catch (e2) {
                totalesDia = null;
            }
            renderResumenTurnoHtml(totalesDia);
            cuentacajaEfectivoId = parseInt(inicial.cuentacaja_efectivo_id, 10) || cuentacajaEfectivoId;
            if (totalesDia) {
                esperadosSistema = mapaEsperadosDesdeTotales(totalesDia);
            } else if (inicial.movimientos) {
                esperadosSistema = mapaEsperadosDesdeMovimientos(inicial.movimientos);
            }
            if (linkComprobante && inicial.url_comprobante_cierre) {
                linkComprobante.href = inicial.url_comprobante_cierre;
                linkComprobante.classList.remove('d-none');
            }
            renderMovimientos(inicial.movimientos || []);
            recalcularDiferencias();
            habilitarVerificacionCajero(true);
        } else {
            initEdicionLocal();
        }
    } else if (esJornada() && inpJornadaId && inpJornadaId.value) {
        cargarJornada(inpJornadaId.value);
    } else if (inpTurnoId && inpTurnoId.value) {
        if (lblTurnoSeleccionado && inicial.turno_etiqueta) {
            lblTurnoSeleccionado.textContent = inicial.turno_etiqueta;
        }
        cargarTurno(inpTurnoId.value);
    }
})();
