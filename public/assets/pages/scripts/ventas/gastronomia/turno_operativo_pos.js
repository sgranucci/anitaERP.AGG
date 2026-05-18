(function () {
    'use strict';

    var G = window.GASTRONOMIA || {};
    if (!G.requiereHabilitacionTurno || !G.rutasTurno) {
        return;
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderTotalesHtml(totales, titulo) {
        if (!totales) {
            return '';
        }
        var html = '<p><strong>' + titulo + '</strong> — Total: $' + fmt(totales.total_general) + '</p>';
        html += '<div class="row"><div class="col-md-6"><em>Por mozo</em><ul class="pl-3 mb-2">';
        (totales.por_mozo || []).forEach(function (m) {
            html += '<li>' + (m.mozo_nombre || '—') + ': $' + fmt(m.total) + ' (' + m.cantidad + ' comp.)</li>';
        });
        html += '</ul></div><div class="col-md-6"><em>Por medio de pago</em><ul class="pl-3">';
        (totales.por_medio_pago || []).forEach(function (p) {
            html += '<li>' + (p.nombre || p.codigo) + ': $' + fmt(p.total) + '</li>';
        });
        html += '</ul></div></div>';
        return html;
    }

    function actualizarAlertaTurno(estado) {
        var el = document.getElementById('gastro-alerta-turno');
        if (!el || !estado) {
            return;
        }
        G.turnoOperativo = estado;
        if (estado.turno_habilitado) {
            el.className = 'alert alert-info py-2 mb-3';
            el.innerHTML = 'Turno <strong>' + (estado.turno_nombre || '') + '</strong>'
                + ' — ' + (estado.usuario_habilitado || '')
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
                    box.innerHTML = renderTotalesHtml(estado.totales_turno, 'Facturación del turno (actual)');
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
                totBox.innerHTML = renderTotalesHtml(estado.totales_turno, 'Facturación del turno')
                    + renderTotalesHtml(estado.totales_dia, 'Acumulado del día (esta PC)');
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
            if (!confirm('¿Confirma el cierre definitivo del turno?')) {
                return;
            }
            postJson(G.rutasTurno.cerrar, {
                redondeo_invitaciones: document.getElementById('pos-redondeo-invitaciones').value,
                redondeo_turno: document.getElementById('pos-redondeo-turno').value,
                sobrante_faltante: document.getElementById('pos-sobrante-faltante').value,
                observacion_cierre: document.getElementById('pos-observacion-cierre').value,
            }).then(function (data) {
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
})();
