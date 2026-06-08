(function () {
    'use strict';

    var G = window.GASTRONOMIA || {};
    if (!G.requiereHabilitacionTurno || !G.rutasTurno) {
        return;
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseDecimalCierrePos(str) {
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

    function enlazarAutoSobranteCierrePos(root) {
        var inpSf = document.getElementById('pos-sobrante-faltante');
        if (!inpSf || !root || !root.querySelector('.js-medio-contado-cierre')) {
            return;
        }
        var sobranteBase = Math.round((parseFloat(inpSf.value) || 0) * 100) / 100;
        var contadoInicialPorMedio = {};
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
            if (ccId > 0) {
                contadoInicialPorMedio[ccId] = parseDecimalCierrePos(inp.value);
            }
        });

        function sync() {
            var sumaCompensacion = 0;
            root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
                var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
                if (ccId <= 0) {
                    return;
                }
                var contado = parseDecimalCierrePos(inp.value);
                var base = Object.prototype.hasOwnProperty.call(contadoInicialPorMedio, ccId)
                    ? contadoInicialPorMedio[ccId]
                    : contado;
                sumaCompensacion += base - contado;
            });
            var nuevo = Math.round((sobranteBase + sumaCompensacion) * 100) / 100;
            inpSf.setAttribute('data-syncing-sobrante', '1');
            inpSf.value = String(nuevo);
            inpSf.removeAttribute('data-syncing-sobrante');
            inpSf.classList.add('gastro-campo-auto-actualizado');
            window.setTimeout(function () { inpSf.classList.remove('gastro-campo-auto-actualizado'); }, 1800);
        }

        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            if (inp.getAttribute('data-auto-sobrante-bound') === '1') {
                return;
            }
            inp.setAttribute('data-auto-sobrante-bound', '1');
            inp.addEventListener('input', sync);
            inp.addEventListener('blur', sync);
        });
        sync();
    }

    function renderTotalesHtml(totales, titulo, opciones) {
        if (window.GastronomiaTotalesTurnoRender) {
            return window.GastronomiaTotalesTurnoRender.renderTotalesHtml(totales, titulo, opciones);
        }
        return '';
    }

    function renderNumeracionFiscalHtml(estado) {
        if (window.GastronomiaTotalesTurnoRender && estado && estado.numeracion_fiscal) {
            return window.GastronomiaTotalesTurnoRender.renderNumeracionFiscalHtml(estado.numeracion_fiscal);
        }
        return '';
    }

    function jornadaAbiertaEnPos(estado) {
        if (estado && estado.jornada_abierta === true) {
            return true;
        }
        return !!(G.jornada && G.jornada.jornada_abierta);
    }

    function actualizarAlertaTurno(estado) {
        var el = document.getElementById('gastro-alerta-turno');
        if (!estado) {
            return;
        }
        G.turnoOperativo = estado;
        if (estado.jornada_abierta === true || estado.jornada_abierta === false) {
            if (!G.jornada) {
                G.jornada = {};
            }
            G.jornada.jornada_abierta = estado.jornada_abierta;
        }
        if (!el) {
            return;
        }
        if (!jornadaAbiertaEnPos(estado) && !estado.turno_habilitado) {
            el.classList.add('d-none');
            return;
        }
        el.classList.remove('d-none');
        if (estado.turno_habilitado) {
            el.className = 'alert alert-secondary py-2 mb-3';
            el.innerHTML = 'Turno <strong>' + (estado.turno_nombre || '') + '</strong>'
                + ' — ' + (estado.usuario_habilitado || '')
                + ' — Jornada <strong>' + (estado.fecha_jornada_fmt || estado.fecha_jornada || '—') + '</strong>'
                + ' — Habilitado ' + (estado.habilitacion_en_fmt || estado.habilitacion_en || '—')
                + ' — parciales: ' + (estado.cierres_parciales || 0);
        } else {
            el.className = 'alert alert-danger py-2 mb-3';
            el.innerHTML = 'No hay <strong>turno habilitado</strong> en esta terminal. '
                + '<a href="' + (G.urlHabilitacionTurno || '#') + '">Habilitar turno</a> antes de facturar.';
        }
    }

    function abrirComprobantePdf(url) {
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': G.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({}, body, { _token: G.csrf })),
        }).then(function (r) {
            return r.json();
        });
    }

    function refrescarEstadoTurno() {
        if (!G.rutasTurno.estado) {
            return Promise.resolve();
        }
        return fetch(G.rutasTurno.estado, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }).then(function (r) {
            return r.json();
        }).then(function (data) {
            if (data.ok) {
                actualizarAlertaTurno(data);
            }
        });
    }

    var btnParcial = document.getElementById('btn-cierre-parcial-turno');
    if (btnParcial) {
        btnParcial.addEventListener('click', function () {
            refrescarEstadoTurno().then(function () {
                var estado = G.turnoOperativo;
                if (!estado || !estado.turno_habilitado) {
                    alert('No hay turno habilitado.');
                    return;
                }
                var box = document.getElementById('modal-cierre-parcial-totales');
                if (box) {
                    box.innerHTML = renderTotalesHtml(estado.totales_turno, 'Facturación del turno (actual)')
                        + renderNumeracionFiscalHtml(estado);
                }
                if (typeof jQuery !== 'undefined') {
                    jQuery('#modal-cierre-parcial-turno').modal('show');
                }
            });
        });
    }

    var btnConfirmParcial = document.getElementById('modal-cierre-parcial-confirmar');
    if (btnConfirmParcial) {
        btnConfirmParcial.addEventListener('click', function () {
            postJson(G.rutasTurno.cierreParcial, {}).then(function (data) {
                if (data.ok) {
                    alert(data.mensaje || 'Cierre parcial registrado');
                    abrirComprobantePdf(data.url_comprobante_pdf);
                    if (typeof jQuery !== 'undefined') {
                        jQuery('#modal-cierre-parcial-turno').modal('hide');
                    }
                    refrescarEstadoTurno();
                } else {
                    alert(data.error || 'Error');
                }
            });
        });
    }

    var btnCerrar = document.getElementById('btn-cerrar-turno-pos');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            refrescarEstadoTurno().then(function () {
            var estado = G.turnoOperativo;
            if (!estado || !estado.turno_habilitado) {
                alert('No hay turno habilitado.');
                return;
            }
            var totBox = document.getElementById('modal-cerrar-turno-totales');
            if (totBox) {
                totBox.innerHTML = renderTotalesHtml(estado.totales_turno, 'Facturación del turno', {
                    arqueoEfectivo: true,
                    arqueoMediosCierre: true,
                    cuentacaja_efectivo_id: estado.cuentacaja_efectivo_id || G.cuentacajaEfectivoIdConfig || 0,
                })
                    + renderTotalesHtml(estado.totales_dia, 'Acumulado del día (esta PC)')
                    + renderNumeracionFiscalHtml(estado);
                if (window.GastronomiaTotalesTurnoRender && window.GastronomiaTotalesTurnoRender.enlazarArqueoEfectivoCierre) {
                    window.GastronomiaTotalesTurnoRender.enlazarArqueoEfectivoCierre(totBox);
                }
                enlazarAutoSobranteCierrePos(totBox);
            }
            var errBox = document.getElementById('modal-cerrar-turno-errores');
            var errs = estado.errores_cierre || [];
            if (errBox) {
                if (errs.length) {
                    errBox.classList.remove('d-none');
                    errBox.innerHTML = errs.join('<br>');
                } else {
                    errBox.classList.add('d-none');
                    errBox.innerHTML = '';
                }
            }
            var inpInv = document.getElementById('pos-redondeo-invitaciones');
            if (inpInv && estado.totales_turno) {
                inpInv.value = estado.totales_turno.redondeo_invitaciones_sugerido || 0;
            }
            if (typeof jQuery !== 'undefined') {
                jQuery('#modal-cerrar-turno-pos').modal('show');
            }
            });
        });
    }

    var btnConfirmCerrar = document.getElementById('modal-cerrar-turno-confirmar');
    if (btnConfirmCerrar) {
        btnConfirmCerrar.addEventListener('click', function () {
            var estado = G.turnoOperativo;
            if (estado && estado.totales_turno && !estado.totales_turno.conciliacion_ok) {
                var diffC = Number(estado.totales_turno.diferencia_cobranza || 0);
                var sug = Number(estado.totales_turno.redondeo_invitaciones_sugerido || 0);
                var inpInvVal = parseFloat(document.getElementById('pos-redondeo-invitaciones').value) || 0;
                var inpSf = parseFloat(document.getElementById('pos-sobrante-faltante').value) || 0;
                var inpRt = parseFloat(document.getElementById('pos-redondeo-turno').value) || 0;
                var baseInv = Number(estado.totales_turno.total_invitaciones || 0);
                var residual = diffC - (inpInvVal - baseInv) - inpRt - inpSf;
                if (Math.abs(residual) >= 0.02) {
                    alert(
                        'Hay diferencia de conciliación ($' + Math.abs(diffC).toFixed(2) + '). '
                        + 'Use redondeo invitaciones sugerido ($' + sug.toFixed(2) + ') o sobrante/faltante hasta cuadrar.'
                    );
                    return;
                }
            }
            if (!confirm('¿Confirma el cierre definitivo del turno?')) {
                return;
            }
            var payloadCierre = {
                redondeo_invitaciones: document.getElementById('pos-redondeo-invitaciones').value,
                redondeo_turno: document.getElementById('pos-redondeo-turno').value,
                sobrante_faltante: document.getElementById('pos-sobrante-faltante').value,
                observacion_cierre: document.getElementById('pos-observacion-cierre').value,
            };
            var totBoxCierre = document.getElementById('modal-cerrar-turno-totales');
            if (totBoxCierre && window.GastronomiaTotalesTurnoRender
                && window.GastronomiaTotalesTurnoRender.recolectarMediosContadoCierreDesdeRoot) {
                var mediosContadoPos = window.GastronomiaTotalesTurnoRender
                    .recolectarMediosContadoCierreDesdeRoot(totBoxCierre);
                if (mediosContadoPos.length) {
                    payloadCierre.medios_contado = mediosContadoPos;
                }
            }
            postJson(G.rutasTurno.cerrar, payloadCierre).then(function (data) {
                if (data.ok) {
                    alert(data.mensaje || 'Turno cerrado');
                    abrirComprobantePdf(data.url_comprobante_pdf);
                    if (typeof jQuery !== 'undefined') {
                        jQuery('#modal-cerrar-turno-pos').modal('hide');
                    }
                    refrescarEstadoTurno();
                } else {
                    alert(data.error || 'Error');
                }
            });
        });
    }

    window.gastroRefrescarEstadoTurno = refrescarEstadoTurno;
    window.gastroActualizarAlertaTurno = actualizarAlertaTurno;
})();
