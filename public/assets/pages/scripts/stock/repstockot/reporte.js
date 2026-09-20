/**
 * Stock por OT: overlay de descarga.
 * El POST descarga attachment sin navegar: se usa fetch+blob para ocultar el aviso al terminar.
 */
(function () {
    'use strict';

    var OVERLAY_ID = 'repstockot-overlay';
    var TITULO_ID = 'repstockot-overlay-titulo';
    var SUBTITULO_ID = 'repstockot-overlay-subtitulo';
    var hintTimer = null;
    var abortController = null;

    function overlay() {
        return document.getElementById(OVERLAY_ID);
    }

    function mostrar(titulo, subtitulo) {
        var ov = overlay();
        if (!ov) {
            return;
        }
        var t = document.getElementById(TITULO_ID);
        var s = document.getElementById(SUBTITULO_ID);
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
        ov.classList.remove('d-none');
        ov.style.setProperty('display', 'flex', 'important');
        ov.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (hintTimer) {
            clearTimeout(hintTimer);
        }
        hintTimer = setTimeout(function () {
            var sub = document.getElementById(SUBTITULO_ID);
            if (sub && ov.getAttribute('aria-hidden') === 'false') {
                sub.textContent = 'Sigue en curso… Pulse Esc para cerrar el aviso (no cancela la descarga).';
            }
        }, 90000);
    }

    function ocultar() {
        if (hintTimer) {
            clearTimeout(hintTimer);
            hintTimer = null;
        }
        var ov = overlay();
        if (!ov) {
            document.body.style.overflow = '';
            return;
        }
        ov.classList.add('d-none');
        ov.style.setProperty('display', 'none', 'important');
        ov.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function overlayVisible() {
        var ov = overlay();
        return ov && !ov.classList.contains('d-none');
    }

    function nombreDesdeContentDisposition(header) {
        if (!header) {
            return null;
        }
        var star = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header);
        if (star && star[1]) {
            try {
                return decodeURIComponent(star[1].trim().replace(/"/g, ''));
            } catch (e) {
                return star[1].trim().replace(/"/g, '');
            }
        }
        var plain = /filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i.exec(header);
        if (plain) {
            return (plain[1] || plain[2] || '').trim();
        }
        return null;
    }

    function extensionPorBoton(valor) {
        if (!valor) {
            return 'xlsx';
        }
        if (valor.indexOf('PDF') !== -1) {
            return 'pdf';
        }
        if (valor.indexOf('CSV') !== -1) {
            return 'csv';
        }
        return 'xlsx';
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'stockot.xlsx';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            window.URL.revokeObjectURL(url);
            if (a.parentNode) {
                a.parentNode.removeChild(a);
            }
        }, 1500);
    }

    function descargarReporte(form, submitter) {
        if (abortController) {
            try {
                abortController.abort();
            } catch (e) {}
        }
        abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        var fd = new FormData(form);
        var extensionValor = 'Genera Reporte en Excel';
        if (submitter && submitter.name) {
            fd.set(submitter.name, submitter.value);
            extensionValor = submitter.value || extensionValor;
        } else if (!fd.has('extension')) {
            fd.set('extension', extensionValor);
        } else {
            extensionValor = fd.get('extension') || extensionValor;
        }

        var ext = extensionPorBoton(String(extensionValor));
        mostrar(
            'Generando reporte…',
            'Puede demorar según los filtros. Pulse Esc si la descarga ya terminó.'
        );

        var opts = {
            method: (form.getAttribute('method') || 'POST').toUpperCase(),
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/octet-stream, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, */*',
            },
        };
        if (abortController) {
            opts.signal = abortController.signal;
        }

        return fetch(form.action, opts)
            .then(function (response) {
                var ctype = (response.headers.get('Content-Type') || '').toLowerCase();
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                if (ctype.indexOf('text/html') !== -1) {
                    throw new Error('La respuesta no es un archivo (posible error de sesión o permisos).');
                }
                var filename = nombreDesdeContentDisposition(response.headers.get('Content-Disposition'))
                    || ('stockot.' + ext);
                return response.blob().then(function (blob) {
                    if (!blob || blob.size === 0) {
                        throw new Error('El archivo llegó vacío.');
                    }
                    dispararDescargaBlob(blob, filename);
                });
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert(err && err.message ? err.message : 'No se pudo generar el reporte.');
            })
            .finally(function () {
                abortController = null;
                ocultar();
            });
    }

    function init() {
        ocultar();

        var form = document.getElementById('form-general');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                descargarReporte(form, e.submitter || null);
            });
        }

        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && overlayVisible()) {
                if (abortController) {
                    try {
                        abortController.abort();
                    } catch (err) {}
                }
                ocultar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
