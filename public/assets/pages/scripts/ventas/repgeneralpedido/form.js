/**
 * El listado general se descarga como attachment: la página no navega y el
 * banner global de "Grabando…" quedaba tapando la pantalla. Acá se pide el
 * archivo, se baja y se cierra el aviso al terminar.
 */
(function () {
    'use strict';

    var form = document.getElementById('form-general');
    var overlay = document.getElementById('repgeneralpedido-overlay');
    if (!form || !overlay) {
        return;
    }

    var abortActual = null;
    var enCurso = false;

    function mostrar(titulo, subtitulo) {
        var t = document.getElementById('repgeneralpedido-overlay-titulo');
        var s = document.getElementById('repgeneralpedido-overlay-subtitulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivo(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return fallback;
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, '')) || fallback;
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function dispararDescarga(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function fallbackNombre(extension) {
        if (extension.indexOf('PDF') !== -1) {
            return 'pedido.pdf';
        }
        if (extension.indexOf('CSV') !== -1) {
            return 'pedido.csv';
        }
        return 'pedido.xlsx';
    }

    function mensajeError(texto) {
        var plano = String(texto || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (plano.length > 280) {
            plano = plano.slice(0, 280) + '…';
        }
        return plano || 'No se pudo generar el listado.';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (enCurso) {
            return;
        }
        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            return;
        }

        var submitter = event.submitter;
        var extension = submitter && submitter.value ? submitter.value : 'Genera Reporte en Excel';
        var datos = new FormData(form);
        datos.set('extension', extension);

        if (abortActual) {
            abortActual.abort();
        }
        abortActual = new AbortController();
        enCurso = true;
        mostrar(
            'Generando el listado…',
            'Puede demorar según el período. Pulse Esc para cancelar el aviso.'
        );

        fetch(form.action, {
            method: 'POST',
            body: datos,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/octet-stream, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/pdf, text/csv, text/html'
            },
            signal: abortActual.signal
        }).then(function (res) {
            var tipo = (res.headers.get('Content-Type') || '').toLowerCase();
            if (!res.ok || tipo.indexOf('text/html') !== -1) {
                return res.text().then(function (texto) {
                    throw new Error(mensajeError(texto));
                });
            }
            return res.blob().then(function (blob) {
                return {
                    blob: blob,
                    filename: nombreArchivo(res.headers.get('Content-Disposition'), fallbackNombre(extension))
                };
            });
        }).then(function (pack) {
            dispararDescarga(pack.blob, pack.filename);
            ocultar();
        }).catch(function (err) {
            ocultar();
            if (err && err.name === 'AbortError') {
                return;
            }
            window.alert((err && err.message) ? err.message : 'No se pudo generar el listado.');
        }).then(function () {
            enCurso = false;
            abortActual = null;
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && abortActual) {
            abortActual.abort();
            ocultar();
        }
    });

    window.addEventListener('pageshow', ocultar);
})();
