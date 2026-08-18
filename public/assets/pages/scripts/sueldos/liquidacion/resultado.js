(function () {
    'use strict';

    var planActual = null;
    var exportAbort = null;

    function overlayEls() {
        return {
            overlay: document.getElementById('liq-recibos-overlay'),
            tituloEl: document.getElementById('liq-recibos-overlay-titulo'),
            subtituloEl: document.getElementById('liq-recibos-overlay-subtitulo'),
        };
    }

    function mostrarOverlay(titulo, subtitulo) {
        var e = overlayEls();
        if (!e.overlay) return;
        if (titulo && e.tituloEl) e.tituloEl.textContent = titulo;
        if (subtitulo && e.subtituloEl) e.subtituloEl.textContent = subtitulo;
        e.overlay.classList.remove('d-none');
        e.overlay.style.display = 'flex';
        e.overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function ocultarOverlay() {
        var e = overlayEls();
        if (!e.overlay) return;
        e.overlay.classList.add('d-none');
        e.overlay.style.display = '';
        e.overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function csrfToken() {
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }

    function syncBatchHref() {
        var chk = document.getElementById('chk-multiempresa-emision');
        var btn = document.getElementById('btn-pdf-corrida-completa');
        if (!btn) return;
        var base = btn.getAttribute('data-base') || btn.href.split('?')[0];
        var on = chk && chk.checked ? '1' : '0';
        btn.href = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'multiempresa=' + on;
    }

    function descargarBatch(ev) {
        var btn = document.getElementById('btn-pdf-corrida-completa');
        if (!btn || !ev.currentTarget || ev.currentTarget !== btn) return;
        ev.preventDefault();
        syncBatchHref();
        var href = btn.href;
        mostrarOverlay('Generando PDF de la corrida…', 'Puede demorar según la cantidad de recibos. No cierre la página.');
        if (exportAbort) exportAbort.abort();
        exportAbort = new AbortController();
        fetch(href, {
            credentials: 'same-origin',
            signal: exportAbort.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/pdf, application/json',
            },
        })
            .then(function (resp) {
                if (!resp.ok) {
                    return resp.json().catch(function () { return {}; }).then(function (body) {
                        throw new Error(body.mensaje || 'No se pudo generar el PDF (HTTP ' + resp.status + ').');
                    });
                }
                var disposition = resp.headers.get('Content-Disposition') || '';
                var match = /filename="?([^"]+)"?/i.exec(disposition);
                var filename = match ? match[1] : 'recibos_corrida.pdf';
                return resp.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (data) {
                var url = window.URL.createObjectURL(data.blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1500);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                window.alert(err && err.message ? err.message : 'Error al generar el PDF.');
            })
            .finally(function () {
                ocultarOverlay();
            });
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderPlan(plan) {
        planActual = plan;
        var resumen = document.getElementById('confidencial-resumen');
        var tbody = document.getElementById('confidencial-detalle');
        var bloq = document.getElementById('confidencial-bloqueantes');
        var btnEjec = document.getElementById('btn-ejecutar-confidencial');
        if (!resumen || !tbody || !bloq || !btnEjec) return;

        resumen.innerHTML =
            '<div class="row text-center">' +
            '<div class="col"><small class="text-muted d-block">Fuente</small><strong>' + (plan.fuente || '-') + '</strong></div>' +
            '<div class="col"><small class="text-muted d-block">Filas</small><strong>' + (plan.filas_leidas || 0) + '</strong></div>' +
            '<div class="col"><small class="text-muted d-block">Crear</small><strong>' + (plan.recibos_crear || 0) + '</strong></div>' +
            '<div class="col"><small class="text-muted d-block">Actualizar</small><strong>' + (plan.recibos_actualizar || 0) + '</strong></div>' +
            '<div class="col"><small class="text-muted d-block">Iguales</small><strong>' + (plan.recibos_iguales || 0) + '</strong></div>' +
            '<div class="col"><small class="text-muted d-block">Marcar conf.</small><strong>' + (plan.empleados_marcar_confidencial || 0) + '</strong></div>' +
            '</div>';

        tbody.innerHTML = '';
        (plan.detalle || []).forEach(function (d) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + d.legajo + '</td>' +
                '<td>' + d.accion + '</td>' +
                '<td class="text-right">' + d.lineas + '</td>' +
                '<td class="text-right">' + fmt(d.neto) + '</td>';
            tbody.appendChild(tr);
        });

        var bloqueantes = plan.bloqueantes || [];
        if (bloqueantes.length) {
            bloq.classList.remove('d-none');
            bloq.innerHTML = '<ul class="mb-0">' + bloqueantes.map(function (b) {
                return '<li>' + b + '</li>';
            }).join('') + '</ul>';
        } else {
            bloq.classList.add('d-none');
            bloq.innerHTML = '';
        }

        btnEjec.disabled = !plan.puede_ejecutar;
        btnEjec.setAttribute('data-hash', plan.plan_hash || '');
    }

    function analizarConfidencial() {
        var btn = document.getElementById('btn-analizar-confidencial');
        if (!btn) return;
        var url = btn.getAttribute('data-url');
        mostrarOverlay('Analizando nómina confidencial…', 'Consulta Anita auxconf/auxconfh sin modificar datos.');
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ fuente: 'auto' }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j.ok) {
                    throw new Error((res.j && res.j.mensaje) || 'No se pudo analizar.');
                }
                renderPlan(res.j.plan);
                $('#modal-import-confidencial').modal('show');
            })
            .catch(function (err) {
                window.alert(err.message || 'Error al analizar.');
            })
            .finally(ocultarOverlay);
    }

    function ejecutarConfidencial() {
        var btn = document.getElementById('btn-ejecutar-confidencial');
        if (!btn || btn.disabled || !planActual) return;
        if (!window.confirm('¿Confirmar importación de nómina confidencial a esta corrida?')) return;
        var url = (document.getElementById('btn-analizar-confidencial').getAttribute('data-url') || '')
            .replace('/analizar', '/ejecutar');
        mostrarOverlay('Importando nómina confidencial…', 'Persistiendo recibos. No cierre la página.');
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                plan_hash: planActual.plan_hash,
                eliminar_ausentes: false,
            }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j.ok) {
                    throw new Error((res.j && res.j.mensaje) || 'No se pudo importar.');
                }
                $('#modal-import-confidencial').modal('hide');
                window.location.reload();
            })
            .catch(function (err) {
                window.alert(err.message || 'Error al importar.');
            })
            .finally(ocultarOverlay);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var chk = document.getElementById('chk-multiempresa-emision');
        if (chk) {
            chk.addEventListener('change', syncBatchHref);
            syncBatchHref();
        }
        var btnPdf = document.getElementById('btn-pdf-corrida-completa');
        if (btnPdf) {
            btnPdf.addEventListener('click', descargarBatch);
        }
        var btnAnalizar = document.getElementById('btn-analizar-confidencial');
        if (btnAnalizar) {
            btnAnalizar.addEventListener('click', analizarConfidencial);
        }
        var btnEjec = document.getElementById('btn-ejecutar-confidencial');
        if (btnEjec) {
            btnEjec.addEventListener('click', ejecutarConfidencial);
        }
        window.addEventListener('pageshow', ocultarOverlay);
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                if (exportAbort) exportAbort.abort();
                ocultarOverlay();
            }
        });
    });
})();
