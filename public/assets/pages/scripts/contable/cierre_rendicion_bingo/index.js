(function () {
    'use strict';

    var cfg = window.CIERRE_REND_BINGO || {};
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
            var pv = preview.puntoventa_fbi || '';
            if (monto > 0) {
                infoFbi.textContent = 'Se emitirá FBI exenta letra B por $ '
                    + formatoNumero(monto) + ' (PV ' + pv + ').';
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
                $('#modal-preview-asiento-cierre-rend-bingo').modal('hide');
                window.location.reload();
            })
            .catch(function () {
                alert('Error de comunicación al ejecutar el cierre.');
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
    });
})();
