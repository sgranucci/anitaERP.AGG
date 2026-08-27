(function () {
    'use strict';

    var cfg = window.CIERRE_REND_BINGO || {};
    var grupoActual = null;
    var cierreGrupoEjecutando = false;
    var rangoEjecutando = false;
    var anularRangoEjecutando = false;

    function tokenCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function overlayCierre() {
        return document.getElementById('cierre-rend-bingo-overlay');
    }

    function mostrarOverlayCierre(titulo, subtitulo) {
        var overlay = overlayCierre();
        if (!overlay) {
            return;
        }
        var tituloEl = document.getElementById('cierre-rend-bingo-overlay-titulo');
        var subEl = document.getElementById('cierre-rend-bingo-overlay-subtitulo');
        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
        }
        if (subtitulo && subEl) {
            subEl.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlayCierre() {
        var overlay = overlayCierre();
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
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
            if (ln.separador) {
                tr.className = 'table-secondary font-weight-bold';
            }
            tr.innerHTML = '<td>' + (ln.concepto || '') + '</td>'
                + '<td class="text-right">' + (ln.debe > 0 ? formatoNumero(ln.debe) : '') + '</td>'
                + '<td class="text-right">' + (ln.haber > 0 ? formatoNumero(ln.haber) : '') + '</td>';
            tbody.appendChild(tr);
        });
        document.getElementById('preview-total-debe').textContent = formatoNumero(preview.resumen_debe);
        document.getElementById('preview-total-haber').textContent = formatoNumero(preview.resumen_haber);
        document.getElementById('modal-preview-asiento-titulo').textContent = preview.titulo || 'Preview cierre bingo';

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

        var infoFbi = document.getElementById('modal-preview-fbi-info');
        if (infoFbi) {
            var monto = preview.fbi_monto || 0;
            if (monto > 0) {
                infoFbi.textContent = 'Se emitirá FBI B (100% exento) en ventas ERP por $ '
                    + formatoNumero(monto) + ' (PV ' + (preview.puntoventa_fbi || '') + ').';
                infoFbi.classList.remove('d-none');
            } else {
                infoFbi.classList.add('d-none');
                infoFbi.textContent = '';
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
                $('#modal-preview-asiento-cierre-rend-bingo').modal('show');
            })
            .catch(function () {
                alert('Error de comunicación al generar el preview.');
            });
    }

    function ejecutarCierre() {
        if (!grupoActual || cierreGrupoEjecutando) {
            return;
        }
        cierreGrupoEjecutando = true;
        var btn = document.getElementById('btn-confirmar-cierre-rend-bingo');
        if (btn) {
            btn.disabled = true;
        }
        mostrarOverlayCierre(
            'Cerrando bingo…',
            'Escribe en Anita. No cierre la página ni vuelva a confirmar.'
        );
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
                    cierreGrupoEjecutando = false;
                    if (btn) {
                        btn.disabled = false;
                    }
                    ocultarOverlayCierre();
                    alert(data.mensaje || 'No se pudo ejecutar el cierre.');
                    return;
                }
                $('#modal-preview-asiento-cierre-rend-bingo').modal('hide');
                window.location.reload();
            })
            .catch(function () {
                cierreGrupoEjecutando = false;
                if (btn) {
                    btn.disabled = false;
                }
                ocultarOverlayCierre();
                alert('Error de comunicación al ejecutar el cierre. Recargue la pantalla antes de reintentar: el asiento puede haberse grabado en Anita.');
            });
    }

    function anularCierreGrupo(grupo) {
        if (!confirm('¿Anular el cierre contable del día? Se eliminarán los asientos en ERP y ctamov.')) {
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

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatoFechaIso(iso) {
        if (!iso || iso.length !== 10) {
            return iso || '';
        }
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
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
            + cantGrupos + ' cierre(s) a generar. Recaudación: '
            + formatoNumero(preview.total_cobrado || 0);

        tbody.innerHTML = '';
        (preview.grupos || []).forEach(function (g, idx) {
            var detalleId = 'rango-detalle-bingo-' + idx;
            var rends = g.rendiciones || [];
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center align-middle">'
                + (rends.length > 1
                    ? '<button type="button" class="btn btn-link btn-sm p-0 js-toggle-rango-detalle" data-target="#'
                        + detalleId + '" title="Ver rendiciones"><i class="fa fa-chevron-down"></i></button>'
                    : '')
                + '</td>'
                + '<td>' + escapeHtml(g.fecha_dia_fmt || formatoFechaIso(g.fecha_dia)) + '</td>'
                + '<td><small>' + escapeHtml(g.puntoventa_label || 'Cierre diario') + '</small></td>'
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
                    + '<th>ID</th><th>Ticket</th><th>Fecha rend.</th><th class="text-right">Recaudación</th>'
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

    function setBotonEjecutarRango(procesando) {
        var btn = document.getElementById('btn-rango-ejecutar');
        if (!btn) {
            return;
        }
        btn.disabled = procesando;
        if (procesando) {
            if (!btn.getAttribute('data-label-original')) {
                btn.setAttribute('data-label-original', btn.innerHTML);
            }
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
        } else if (btn.getAttribute('data-label-original')) {
            btn.innerHTML = btn.getAttribute('data-label-original');
        }
    }

    function ejecutarCierreRango() {
        if (rangoEjecutando) {
            return;
        }
        var empresaId = parseInt((document.getElementById('rango-empresa-id') || {}).value || '0', 10);
        var desde = (document.getElementById('rango-fecha-desde') || {}).value || '';
        var hasta = (document.getElementById('rango-fecha-hasta') || {}).value || '';
        if (empresaId <= 0 || !desde || !hasta) {
            return;
        }
        if (!confirm('¿Confirmar el cierre contable del rango? Debe ser correlativo desde la jornada pendiente más antigua.')) {
            return;
        }
        rangoEjecutando = true;
        setBotonEjecutarRango(true);
        mostrarOverlayCierre(
            'Cerrando rango de bingo…',
            'Puede demorar varios minutos (un asiento por día). No cierre la página ni vuelva a confirmar.'
        );
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
                    rangoEjecutando = false;
                    setBotonEjecutarRango(false);
                    ocultarOverlayCierre();
                    alert(data.mensaje || 'No se pudo ejecutar el cierre del rango.');
                    return;
                }
                var msg = data.mensaje || 'Cierre del rango completado.';
                var errores = (data.resultado && data.resultado.errores) ? data.resultado.errores : [];
                if (errores.length) {
                    msg += '\n\nErrores:\n' + errores.map(function (e) {
                        return (e.grupo_clave || '?') + ': ' + e.mensaje;
                    }).join('\n');
                }
                ocultarOverlayCierre();
                alert(msg);
                $('#modal-cierre-rango-rend-bingo').modal('hide');
                window.location.reload();
            })
            .catch(function () {
                rangoEjecutando = false;
                setBotonEjecutarRango(false);
                ocultarOverlayCierre();
                alert('Error de comunicación al ejecutar el cierre del rango. Recargue la pantalla antes de reintentar: el asiento puede haberse grabado en Anita.');
            });
    }

    function limpiarPreviewAnularRango() {
        var box = document.getElementById('anular-rango-preview-box');
        var err = document.getElementById('anular-rango-error-box');
        var btnEj = document.getElementById('btn-anular-rango-ejecutar');
        var porDiaBox = document.getElementById('anular-rango-preview-por-dia-box');
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

    function mostrarPreviewAnularRango(preview) {
        var box = document.getElementById('anular-rango-preview-box');
        var resumen = document.getElementById('anular-rango-preview-resumen');
        var tbody = document.getElementById('anular-rango-preview-tbody');
        var btnEj = document.getElementById('btn-anular-rango-ejecutar');
        var porDiaBox = document.getElementById('anular-rango-preview-por-dia-box');
        var porDiaTbody = document.getElementById('anular-rango-preview-por-dia-tbody');
        if (!box || !resumen || !tbody) {
            return;
        }
        var cantGrupos = preview.cantidad_grupos || 0;
        resumen.textContent = (preview.cantidad || 0) + ' rendición(es) cerrada(s) → '
            + cantGrupos + ' cierre(s) a anular. Recaudación: '
            + formatoNumero(preview.total_cobrado || 0)
            + '. Se borran asientos en ERP y ctamov.';

        tbody.innerHTML = '';
        (preview.grupos || []).forEach(function (g, idx) {
            var detalleId = 'anular-rango-detalle-bingo-' + idx;
            var rends = g.rendiciones || [];
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center align-middle">'
                + (rends.length > 1
                    ? '<button type="button" class="btn btn-link btn-sm p-0 js-toggle-anular-rango-detalle" data-target="#'
                        + detalleId + '" title="Ver rendiciones"><i class="fa fa-chevron-down"></i></button>'
                    : '')
                + '</td>'
                + '<td>' + escapeHtml(g.fecha_dia_fmt || formatoFechaIso(g.fecha_dia)) + '</td>'
                + '<td><small>' + escapeHtml(g.puntoventa_label || g.asiento_numero || 'Cierre diario') + '</small></td>'
                + '<td class="text-center">' + (g.cantidad_rendiciones || 0) + '</td>'
                + '<td class="text-right">' + formatoNumero(g.total_cobrado || 0) + '</td>';
            tbody.appendChild(tr);

            if (rends.length > 1) {
                var detalleHtml = rends.map(function (r) {
                    return '<tr>'
                        + '<td>' + escapeHtml(r.id) + '</td>'
                        + '<td>' + escapeHtml(r.codigo || '—') + '</td>'
                        + '<td>' + escapeHtml(r.asiento_numero || '—') + '</td>'
                        + '<td class="text-right">' + formatoNumero(r.total_cobrado || 0) + '</td>'
                        + '</tr>';
                }).join('');
                var trDet = document.createElement('tr');
                trDet.className = 'rango-grupo-detalle collapse';
                trDet.id = detalleId;
                trDet.innerHTML = '<td colspan="5" class="p-0 bg-light">'
                    + '<table class="table table-sm table-bordered mb-0">'
                    + '<thead class="thead-light"><tr>'
                    + '<th>ID</th><th>Ticket</th><th>Asiento</th><th class="text-right">Recaudación</th>'
                    + '</tr></thead><tbody>' + detalleHtml + '</tbody></table></td>';
                tbody.appendChild(trDet);
            }
        });

        tbody.querySelectorAll('.js-toggle-anular-rango-detalle').forEach(function (btn) {
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

    function previewAnularCierreRango() {
        limpiarPreviewAnularRango();
        var empresaId = parseInt((document.getElementById('anular-rango-empresa-id') || {}).value || '0', 10);
        var desde = (document.getElementById('anular-rango-fecha-desde') || {}).value || '';
        var hasta = (document.getElementById('anular-rango-fecha-hasta') || {}).value || '';
        if (empresaId <= 0 || !desde || !hasta) {
            alert('Indique empresa y rango de fechas.');
            return;
        }
        if (!cfg.urlPreviewAnularRango) {
            alert('No hay permiso o ruta de anulación por rango.');
            return;
        }
        fetch(cfg.urlPreviewAnularRango, {
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
                    var err = document.getElementById('anular-rango-error-box');
                    if (err) {
                        err.textContent = data.mensaje || 'No se pudo obtener el preview.';
                        err.classList.remove('d-none');
                    }
                    return;
                }
                mostrarPreviewAnularRango(data.preview || {});
            })
            .catch(function () {
                alert('Error de comunicación al consultar cierres del rango.');
            });
    }

    function setBotonEjecutarAnularRango(procesando) {
        var btn = document.getElementById('btn-anular-rango-ejecutar');
        if (!btn) {
            return;
        }
        btn.disabled = procesando;
        if (procesando) {
            if (!btn.getAttribute('data-label-original')) {
                btn.setAttribute('data-label-original', btn.innerHTML);
            }
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Anulando…';
        } else if (btn.getAttribute('data-label-original')) {
            btn.innerHTML = btn.getAttribute('data-label-original');
        }
    }

    function ejecutarAnularCierreRango() {
        if (anularRangoEjecutando) {
            return;
        }
        var empresaId = parseInt((document.getElementById('anular-rango-empresa-id') || {}).value || '0', 10);
        var desde = (document.getElementById('anular-rango-fecha-desde') || {}).value || '';
        var hasta = (document.getElementById('anular-rango-fecha-hasta') || {}).value || '';
        if (empresaId <= 0 || !desde || !hasta) {
            return;
        }
        if (!confirm('¿Anular los cierres del rango? Se borran físicamente los asientos en ERP y ctamov, y el FBI.')) {
            return;
        }
        anularRangoEjecutando = true;
        setBotonEjecutarAnularRango(true);
        mostrarOverlayCierre(
            'Anulando rango de bingo…',
            'Borra asientos en ERP y ctamov. Puede demorar. No cierre la página ni vuelva a confirmar.'
        );
        fetch(cfg.urlAnularRango, {
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
                    anularRangoEjecutando = false;
                    setBotonEjecutarAnularRango(false);
                    ocultarOverlayCierre();
                    alert(data.mensaje || 'No se pudo anular el rango.');
                    return;
                }
                var msg = data.mensaje || 'Anulación del rango completada.';
                var errores = (data.resultado && data.resultado.errores) ? data.resultado.errores : [];
                if (errores.length) {
                    msg += '\n\nErrores:\n' + errores.map(function (e) {
                        return (e.grupo_clave || '?') + ': ' + e.mensaje;
                    }).join('\n');
                }
                ocultarOverlayCierre();
                alert(msg);
                $('#modal-anular-rango-rend-bingo').modal('hide');
                window.location.reload();
            })
            .catch(function () {
                anularRangoEjecutando = false;
                setBotonEjecutarAnularRango(false);
                ocultarOverlayCierre();
                alert('Error de comunicación al anular el rango. Recargue y verifique qué jornadas quedaron pendientes.');
            });
    }

    window.CIERRE_REND_abrirRangoDesdePendientes = function (empresaId, desde, hasta) {
        var empRango = document.getElementById('rango-empresa-id');
        var inputDesde = document.getElementById('rango-fecha-desde');
        var inputHasta = document.getElementById('rango-fecha-hasta');
        if (empRango) {
            empRango.value = String(empresaId || '');
        }
        if (inputDesde) {
            inputDesde.value = desde || '';
        }
        if (inputHasta) {
            inputHasta.value = hasta || '';
        }
        rangoEjecutando = false;
        setBotonEjecutarRango(false);
        limpiarPreviewRango();
        $('#modal-cierre-rango-rend-bingo').modal('show');
        previewCierreRango();
    };

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
                if (grupo && grupo.empresa_id > 0 && grupo.fecha_dia) {
                    abrirPreviewGrupo(grupo);
                }
            });
        });

        document.querySelectorAll('.js-anular-grupo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tr = btn.closest('tr.grupo-resumen');
                var grupo = leerGrupoDesdeFila(tr);
                if (grupo && grupo.empresa_id > 0 && grupo.fecha_dia) {
                    anularCierreGrupo(grupo);
                }
            });
        });

        var btnConfirmar = document.getElementById('btn-confirmar-cierre-rend-bingo');
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', ejecutarCierre);
        }

        var btnAbrirRango = document.getElementById('btn-abrir-cierre-rango');
        if (btnAbrirRango) {
            btnAbrirRango.addEventListener('click', function () {
                rangoEjecutando = false;
                setBotonEjecutarRango(false);
                limpiarPreviewRango();
                $('#modal-cierre-rango-rend-bingo').modal('show');
            });
        }

        var btnPreviewRango = document.getElementById('btn-rango-preview');
        if (btnPreviewRango) {
            btnPreviewRango.addEventListener('click', function () {
                this.blur();
                previewCierreRango();
            });
        }

        var btnEjecutarRango = document.getElementById('btn-rango-ejecutar');
        if (btnEjecutarRango) {
            btnEjecutarRango.addEventListener('click', ejecutarCierreRango);
        }

        var btnAbrirAnularRango = document.getElementById('btn-abrir-anular-rango');
        if (btnAbrirAnularRango) {
            btnAbrirAnularRango.addEventListener('click', function () {
                anularRangoEjecutando = false;
                setBotonEjecutarAnularRango(false);
                limpiarPreviewAnularRango();
                $('#modal-anular-rango-rend-bingo').modal('show');
            });
        }

        var btnPreviewAnularRango = document.getElementById('btn-anular-rango-preview');
        if (btnPreviewAnularRango) {
            btnPreviewAnularRango.addEventListener('click', function () {
                this.blur();
                previewAnularCierreRango();
            });
        }

        var btnEjecutarAnularRango = document.getElementById('btn-anular-rango-ejecutar');
        if (btnEjecutarAnularRango) {
            btnEjecutarAnularRango.addEventListener('click', ejecutarAnularCierreRango);
        }

        ['anular-rango-empresa-id', 'anular-rango-fecha-desde', 'anular-rango-fecha-hasta'].forEach(function (id) {
            var elAnular = document.getElementById(id);
            if (elAnular) {
                elAnular.addEventListener('change', limpiarPreviewAnularRango);
            }
        });

        ['rango-empresa-id', 'rango-fecha-desde', 'rango-fecha-hasta'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', limpiarPreviewRango);
            }
        });
    });
})();
