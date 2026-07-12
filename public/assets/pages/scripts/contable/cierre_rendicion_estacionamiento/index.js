(function () {
    'use strict';

    var cfg = window.CIERRE_REND_EST || {};
    var grupoActual = null;

    function tokenCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function formatoNumero(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function leerGrupoDesdeFila(tr) {
        if (!tr) {
            return null;
        }
        return {
            empresa_id: parseInt(tr.getAttribute('data-empresa-id') || '0', 10),
            fecha_dia: tr.getAttribute('data-fecha-dia') || '',
            puntoventa_cae_id: parseInt(tr.getAttribute('data-puntoventa-cae-id') || '0', 10),
        };
    }

    function mostrarPreview(preview) {
        var tbody = document.querySelector('#tabla-preview-asiento tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        (preview.lineas || []).forEach(function (ln) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (ln.concepto || '') + '</td>'
                + '<td class="text-right">' + (ln.debe > 0 ? formatoNumero(ln.debe) : '') + '</td>'
                + '<td class="text-right">' + (ln.haber > 0 ? formatoNumero(ln.haber) : '') + '</td>';
            tbody.appendChild(tr);
        });
        document.getElementById('preview-total-debe').textContent = formatoNumero(preview.resumen_debe);
        document.getElementById('preview-total-haber').textContent = formatoNumero(preview.resumen_haber);
        document.getElementById('modal-preview-asiento-titulo').textContent = preview.titulo || 'Preview asiento';

        var fechaAsiento = document.getElementById('modal-preview-fecha-asiento');
        if (fechaAsiento) {
            fechaAsiento.value = preview.fecha_asiento || '';
        }

        var advBox = document.getElementById('modal-preview-asiento-advertencias');
        if (advBox) {
            var adv = preview.advertencias || [];
            if (adv.length) {
                advBox.classList.remove('d-none');
                advBox.innerHTML = adv.map(function (a) { return '<div>' + a + '</div>'; }).join('');
            } else {
                advBox.classList.add('d-none');
                advBox.innerHTML = '';
            }
        }

        var infoGrupo = document.getElementById('modal-preview-grupo-info');
        if (infoGrupo) {
            var cant = preview.cantidad_rendiciones || (preview.rendicion_ids || []).length;
            if (cant > 0) {
                infoGrupo.textContent = cant + ' rendición(es) en el grupo.';
                infoGrupo.classList.remove('d-none');
            } else {
                infoGrupo.classList.add('d-none');
                infoGrupo.textContent = '';
            }
        }
    }

    function abrirPreviewGrupo(grupo) {
        grupoActual = grupo;
        fetch(cfg.urlPreview, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(grupo),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.mensaje || 'No se pudo generar el preview.');
                    return;
                }
                mostrarPreview(data.preview || {});
                $('#modal-preview-asiento-cierre-rend').modal('show');
            })
            .catch(function () {
                alert('Error de comunicación al generar el preview.');
            });
    }

    function ejecutarCierre() {
        if (!grupoActual) {
            return;
        }
        fetch(cfg.urlEjecutar, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(grupoActual),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.mensaje || 'No se pudo ejecutar el cierre.');
                    return;
                }
                $('#modal-preview-asiento-cierre-rend').modal('hide');
                window.location.reload();
            })
            .catch(function () {
                alert('Error de comunicación al ejecutar el cierre.');
            });
    }

    function anularCierreGrupo(grupo) {
        if (!confirm('¿Anular el cierre contable del grupo? Se eliminará el asiento en ERP y ctamov para todas las rendiciones vinculadas.')) {
            return;
        }
        fetch(cfg.urlAnular, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(Object.assign({}, grupo, { confirmar: true })),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.mensaje || 'No se pudo anular el cierre.');
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                alert('Error de comunicación al anular el cierre.');
            });
    }

    function toggleDetalleGrupo(btn) {
        var target = btn.getAttribute('data-target');
        if (!target) {
            return;
        }
        var panel = document.querySelector(target);
        if (!panel) {
            return;
        }
        var icon = btn.querySelector('.fa');
        var visible = panel.classList.contains('show');
        if (visible) {
            panel.classList.remove('show');
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        } else {
            panel.classList.add('show');
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
    }

    function limpiarPreviewRango() {
        var box = document.getElementById('rango-preview-box');
        var err = document.getElementById('rango-error-box');
        var btnEj = document.getElementById('btn-rango-ejecutar');
        var porDiaBox = document.getElementById('rango-preview-por-dia-box');
        if (box) {
            box.classList.add('d-none');
        }
        if (err) {
            err.classList.add('d-none');
            err.textContent = '';
        }
        if (btnEj) {
            btnEj.classList.add('d-none');
        }
        if (porDiaBox) {
            porDiaBox.classList.add('d-none');
        }
    }

    function formatoFechaIso(iso) {
        if (!iso || iso.length !== 10) {
            return iso || '';
        }
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function toggleDetalleRango(btn) {
        var target = btn.getAttribute('data-target');
        if (!target) {
            return;
        }
        var panel = document.querySelector(target);
        if (!panel) {
            return;
        }
        var icon = btn.querySelector('.fa');
        var visible = panel.classList.contains('show');
        if (visible) {
            panel.classList.remove('show');
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        } else {
            panel.classList.add('show');
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
    }

    function mostrarPreviewRango(preview) {
        var box = document.getElementById('rango-preview-box');
        var resumen = document.getElementById('rango-preview-resumen');
        var tbody = document.getElementById('rango-preview-tbody');
        var btnEj = document.getElementById('btn-rango-ejecutar');
        var porDiaBox = document.getElementById('rango-preview-por-dia-box');
        var porDiaTbody = document.getElementById('rango-preview-por-dia-tbody');
        if (!box || !resumen || !tbody) {
            return;
        }
        var cantGrupos = preview.cantidad_grupos || 0;
        resumen.textContent = (preview.cantidad || 0) + ' rendición(es) pendiente(s) → '
            + cantGrupos + ' asiento(s) a generar. Total cobrado: '
            + formatoNumero(preview.total_cobrado || 0);

        tbody.innerHTML = '';
        (preview.grupos || []).forEach(function (g, idx) {
            var detalleId = 'rango-detalle-' + idx;
            var rends = g.rendiciones || [];
            var tr = document.createElement('tr');
            var pvHtml = '<small>' + escapeHtml(g.puntoventa_label || '—') + '</small>';
            if (rends.length === 1) {
                pvHtml += '<br><span class="text-muted small">#' + escapeHtml(rends[0].id)
                    + ' · ' + escapeHtml(rends[0].codigo || '—') + '</span>';
            }
            tr.innerHTML = '<td class="text-center align-middle">'
                + (rends.length > 1
                    ? '<button type="button" class="btn btn-link btn-sm p-0 js-toggle-rango-detalle" data-target="#'
                        + detalleId + '" title="Ver rendiciones"><i class="fa fa-chevron-down"></i></button>'
                    : '')
                + '</td>'
                + '<td>' + escapeHtml(g.fecha_dia_fmt || formatoFechaIso(g.fecha_dia)) + '</td>'
                + '<td>' + pvHtml + '</td>'
                + '<td class="text-center">' + (g.cantidad_rendiciones || 0) + '</td>'
                + '<td class="text-right">' + formatoNumero(g.total_cobrado || 0) + '</td>';
            tbody.appendChild(tr);

            if (rends.length > 1) {
                var detalleHtml = rends.map(function (r) {
                    return '<tr>'
                        + '<td>' + escapeHtml(r.id) + '</td>'
                        + '<td>' + escapeHtml(r.codigo || '—') + '</td>'
                        + '<td>' + escapeHtml(r.fecharendicion_fmt || '—') + '</td>'
                        + '<td class="text-right">' + formatoNumero(r.total_cobrado || 0) + '</td>'
                        + '</tr>';
                }).join('');

                var trDet = document.createElement('tr');
                trDet.className = 'rango-grupo-detalle collapse';
                trDet.id = detalleId;
                trDet.innerHTML = '<td colspan="5" class="p-0 bg-light">'
                    + '<table class="table table-sm table-bordered mb-0">'
                    + '<thead class="thead-light"><tr>'
                    + '<th>ID</th><th>Ticket</th><th>Fecha rend.</th><th class="text-right">Cobrado</th>'
                    + '</tr></thead><tbody>' + detalleHtml + '</tbody></table></td>';
                tbody.appendChild(trDet);
            }
        });

        tbody.querySelectorAll('.js-toggle-rango-detalle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                toggleDetalleRango(btn);
            });
        });

        if (porDiaTbody && porDiaBox) {
            var porDia = preview.por_dia || [];
            if (porDia.length > 1) {
                porDiaTbody.innerHTML = '';
                porDia.forEach(function (d) {
                    var trDia = document.createElement('tr');
                    trDia.innerHTML = '<td>' + escapeHtml(formatoFechaIso(d.fecha_jornada)) + '</td>'
                        + '<td class="text-center">' + (d.cantidad || 0) + '</td>'
                        + '<td class="text-center">' + (d.cantidad_grupos || 0) + '</td>'
                        + '<td class="text-right">' + formatoNumero(d.total_cobrado || 0) + '</td>';
                    porDiaTbody.appendChild(trDia);
                });
                porDiaBox.classList.remove('d-none');
            } else {
                porDiaBox.classList.add('d-none');
                porDiaTbody.innerHTML = '';
            }
        }

        box.classList.remove('d-none');
        if (btnEj && cantGrupos > 0) {
            btnEj.classList.remove('d-none');
        }
    }

    function previewCierreRango() {
        limpiarPreviewRango();
        var empresaId = parseInt((document.getElementById('rango-empresa-id') || {}).value || '0', 10);
        var desde = (document.getElementById('rango-fecha-desde') || {}).value || '';
        var hasta = (document.getElementById('rango-fecha-hasta') || {}).value || '';
        if (empresaId <= 0 || !desde || !hasta) {
            alert('Indique empresa y rango de fechas.');
            return;
        }
        fetch(cfg.urlPreviewRango, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                empresa_id: empresaId,
                fecha_desde: desde,
                fecha_hasta: hasta,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    var err = document.getElementById('rango-error-box');
                    if (err) {
                        err.textContent = data.mensaje || 'No se pudo obtener el preview.';
                        err.classList.remove('d-none');
                    }
                    return;
                }
                mostrarPreviewRango(data.preview || {});
            })
            .catch(function () {
                alert('Error de comunicación al consultar pendientes.');
            });
    }

    function ejecutarCierreRango() {
        var empresaId = parseInt((document.getElementById('rango-empresa-id') || {}).value || '0', 10);
        var desde = (document.getElementById('rango-fecha-desde') || {}).value || '';
        var hasta = (document.getElementById('rango-fecha-hasta') || {}).value || '';
        if (empresaId <= 0 || !desde || !hasta) {
            return;
        }
        if (!confirm('¿Confirmar cierre contable masivo por grupos (fecha + punto de venta) en el rango?')) {
            return;
        }
        fetch(cfg.urlEjecutarRango, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                empresa_id: empresaId,
                fecha_desde: desde,
                fecha_hasta: hasta,
                confirmar: true,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.mensaje || 'No se pudo ejecutar el cierre masivo.');
                    return;
                }
                var msg = data.mensaje || 'Cierre masivo completado.';
                var errores = (data.resultado && data.resultado.errores) ? data.resultado.errores : [];
                if (errores.length) {
                    msg += '\n\nErrores:\n' + errores.map(function (e) {
                        return (e.grupo_clave || '?') + ': ' + e.mensaje;
                    }).join('\n');
                }
                alert(msg);
                $('#modal-cierre-rango-rend-est').modal('hide');
                window.location.reload();
            })
            .catch(function () {
                alert('Error de comunicación al ejecutar el cierre masivo.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-toggle-grupo-detalle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                toggleDetalleGrupo(btn);
            });
        });

        document.querySelectorAll('.js-cerrar-grupo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tr = btn.closest('tr.grupo-resumen');
                var grupo = leerGrupoDesdeFila(tr);
                if (grupo && grupo.empresa_id > 0 && grupo.fecha_dia && grupo.puntoventa_cae_id > 0) {
                    abrirPreviewGrupo(grupo);
                }
            });
        });

        document.querySelectorAll('.js-anular-grupo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tr = btn.closest('tr.grupo-resumen');
                var grupo = leerGrupoDesdeFila(tr);
                if (grupo && grupo.empresa_id > 0 && grupo.fecha_dia && grupo.puntoventa_cae_id > 0) {
                    anularCierreGrupo(grupo);
                }
            });
        });

        var btnConfirmar = document.getElementById('btn-confirmar-cierre-rend');
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', ejecutarCierre);
        }

        var btnAbrirRango = document.getElementById('btn-abrir-cierre-rango');
        if (btnAbrirRango) {
            btnAbrirRango.addEventListener('click', function () {
                limpiarPreviewRango();
                $('#modal-cierre-rango-rend-est').modal('show');
            });
        }

        var btnPreviewRango = document.getElementById('btn-rango-preview');
        if (btnPreviewRango) {
            btnPreviewRango.addEventListener('click', previewCierreRango);
        }

        var btnEjecutarRango = document.getElementById('btn-rango-ejecutar');
        if (btnEjecutarRango) {
            btnEjecutarRango.addEventListener('click', ejecutarCierreRango);
        }
    });
})();
