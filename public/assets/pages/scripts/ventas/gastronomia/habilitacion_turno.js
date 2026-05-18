(function () {
    'use strict';

    var app = document.getElementById('habilitacion-turno-app');
    if (!app || (window.HABILITACION_TURNO_GASTRONOMIA && window.HABILITACION_TURNO_GASTRONOMIA.modoCajaDirecto)) {
        return;
    }

    var apiEstado = app.getAttribute('data-api-estado') || '';
    var apiHabilitar = app.getAttribute('data-api-habilitar') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var puedeHabilitar = app.getAttribute('data-puede-habilitar') === '1';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1';

    function csrfToken() {
        if (window.HABILITACION_TURNO_GASTRONOMIA && window.HABILITACION_TURNO_GASTRONOMIA.csrf) {
            return String(window.HABILITACION_TURNO_GASTRONOMIA.csrf);
        }
        return app.getAttribute('data-csrf') || '';
    }

    function postJson(url, body) {
        var token = csrfToken();
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({}, body, { _token: token })),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
        });
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderTotalesHtml(totales, titulo) {
        if (!totales) {
            return '';
        }
        var html = '<h6>' + titulo + ' — Total: $' + fmt(totales.total_general) + '</h6>';
        html += '<div class="row"><div class="col-md-6"><strong>Por mozo</strong><ul class="mb-2">';
        (totales.por_mozo || []).forEach(function (m) {
            html += '<li>' + (m.mozo_nombre || '—') + ': $' + fmt(m.total) + ' (' + m.cantidad + ')</li>';
        });
        html += '</ul></div><div class="col-md-6"><strong>Por medio de pago</strong><ul>';
        (totales.por_medio_pago || []).forEach(function (p) {
            html += '<li>' + (p.nombre || p.codigo) + ': $' + fmt(p.total) + '</li>';
        });
        html += '</ul></div></div>';
        return html;
    }

    function actualizarUi(estado) {
        var panel = document.getElementById('panel-estado-turno');
        var cardHab = document.getElementById('card-habilitar');
        var cardCerr = document.getElementById('card-cerrar');
        var preview = document.getElementById('totales-cierre-preview');
        var erroresBox = document.getElementById('errores-cierre-turno');
        var inpInv = document.getElementById('redondeo_invitaciones');

        if (!panel) {
            return;
        }

        if (estado.turno_habilitado) {
            panel.innerHTML = '<div class="alert alert-success"><strong>Turno habilitado:</strong> '
                + (estado.turno_nombre || '') + ' — '
                + (estado.usuario_habilitado || '') + ' — desde ' + (estado.habilitacion_en || '')
                + ' — Habilitación: $' + fmt(estado.monto_habilitacion)
                + ' — Cierres parciales: ' + (estado.cierres_parciales || 0)
                + '</div>';
            if (cardHab) {
                cardHab.classList.add('d-none');
            }
            if (cardCerr) {
                cardCerr.classList.remove('d-none');
            }
            if (preview && estado.totales_turno) {
                preview.innerHTML = renderTotalesHtml(estado.totales_turno, 'Facturación del turno')
                    + renderTotalesHtml(estado.totales_dia, 'Acumulado del día (esta PC)');
            }
            if (inpInv && estado.totales_turno) {
                inpInv.value = estado.totales_turno.redondeo_invitaciones_sugerido || 0;
            }
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
        } else {
            panel.innerHTML = '<div class="alert alert-warning">Sin turno habilitado en esta terminal.</div>';
            if (cardHab && estado.puede_habilitar) {
                cardHab.classList.remove('d-none');
            } else if (cardHab) {
                cardHab.classList.add('d-none');
            }
            if (cardCerr) {
                cardCerr.classList.add('d-none');
            }
        }
    }

    function cargarEstado() {
        return fetch(apiEstado, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    actualizarUi(data);
                }
            });
    }

    if (puedeHabilitar) {
        var formHab = document.getElementById('form-habilitar-turno');
        if (formHab) {
            formHab.addEventListener('submit', function (e) {
                e.preventDefault();
                postJson(apiHabilitar, {
                    turno_gastronomia_id: document.getElementById('turno_gastronomia_id').value,
                    monto_habilitacion: document.getElementById('monto_habilitacion').value,
                    usuario_habilitado_id: document.getElementById('usuario_habilitado_id').value,
                    observacion: document.getElementById('observacion_habilitacion').value,
                }).then(function (res) {
                    if (res.data.ok) {
                        alert(res.data.mensaje || 'OK');
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
                if (!confirm('¿Confirma el cierre definitivo del turno en esta terminal?')) {
                    return;
                }
                postJson(apiCerrar, {
                    redondeo_invitaciones: document.getElementById('redondeo_invitaciones').value,
                    redondeo_turno: document.getElementById('redondeo_turno').value,
                    sobrante_faltante: document.getElementById('sobrante_faltante').value,
                    observacion_cierre: document.getElementById('observacion_cierre').value,
                }).then(function (res) {
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

    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }

    cargarEstado();
})();
