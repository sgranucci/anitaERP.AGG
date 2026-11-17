/**
 * Overlay + export fetch/blob del control de conversión de recepciones.
 */
(function () {
    'use strict';

    var exportAbort = null;

    function overlayEl() {
        return document.getElementById('rcc-overlay');
    }

    function mostrar(titulo, subtitulo) {
        var overlay = overlayEl();
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('rcc-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('rcc-overlay-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.setProperty('display', 'flex', 'important');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function ocultar() {
        var overlay = overlayEl();
        if (!overlay) {
            document.body.style.overflow = '';
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.setProperty('display', 'none', 'important');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function nombreDesdeDisposition(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return fallback;
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'control_conversion_recepcion';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function descargar(href) {
        var lower = String(href).toLowerCase();
        var fallback = 'control_conversion_recepcion';
        var formato = 'archivo';
        if (lower.indexOf('/excel') !== -1) {
            formato = 'Excel';
            fallback += '.xlsx';
        } else if (lower.indexOf('/pdf') !== -1) {
            formato = 'PDF';
            fallback += '.pdf';
        } else if (lower.indexOf('/csv') !== -1) {
            formato = 'CSV';
            fallback += '.csv';
        }

        if (exportAbort) {
            try {
                exportAbort.abort();
            } catch (e) {}
        }
        exportAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;

        mostrar('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');

        fetch(href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: exportAbort ? exportAbort.signal : undefined,
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreDesdeDisposition(res.headers.get('Content-Disposition'), fallback),
                    };
                });
            })
            .then(function (pack) {
                dispararDescargaBlob(pack.blob, pack.filename);
                ocultar();
            })
            .catch(function (err) {
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                alert((err && err.message) || 'No se pudo exportar');
            });
    }

    function init() {
        ocultar();

        var form = document.getElementById('form-recepcion-conversion-control');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                mostrar(
                    'Consultando conversiones…',
                    'Puede demorar según el período. No cierre la página.'
                );
            });
        }

        document.querySelectorAll('a[href*="listar-reporte-control-conversion-recepcion"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                descargar(a.href);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (exportAbort) {
                    try {
                        exportAbort.abort();
                    } catch (err) {}
                }
                ocultar();
            }
        });

        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
