(function () {
    'use strict';

    var overlay = document.getElementById('venta-hora-overlay');
    var tituloEl = document.getElementById('venta-hora-overlay-titulo');
    var subtituloEl = document.getElementById('venta-hora-overlay-subtitulo');
    var exportAbort = null;
    var exportSafetyTimer = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }

        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
        }
        if (subtitulo && subtituloEl) {
            subtituloEl.textContent = subtitulo;
        }

        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        if (!overlay) {
            return;
        }

        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivoDesdeContentDisposition(disposition, fallback) {
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
        a.download = filename || 'venta_hora_por_hora_gastronomia';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function descargarExportacion(href) {
        var lower = String(href).toLowerCase();
        var formato = 'archivo';
        var fallback = 'venta_hora_por_hora_gastronomia';

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

        mostrar(
            'Preparando exportación…',
            'Generando ' + formato + '. Puede demorar según el período. No cierre la página.'
        );

        if (exportAbort) {
            try {
                exportAbort.abort();
            } catch (e) {}
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        exportAbort = controller;

        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
        }
        exportSafetyTimer = setTimeout(ocultar, 600000);

        fetch(href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: '*/*',
            },
        })
            .then(function (res) {
                if (res.status === 419) {
                    throw new Error('Sesión expirada. Recargue la página (F5) e intente de nuevo.');
                }
                if (res.redirected && res.url && res.url.indexOf('listar-gastronomia-venta-hora-reporte') === -1) {
                    throw new Error('No se pudo generar la exportación. Consulte el reporte e intente de nuevo.');
                }
                if (!res.ok) {
                    throw new Error('Error HTTP ' + res.status + ' al exportar.');
                }

                var filename = nombreArchivoDesdeContentDisposition(
                    res.headers.get('Content-Disposition'),
                    fallback
                );

                return res.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (pack) {
                if (!pack || !pack.blob || pack.blob.size === 0) {
                    throw new Error('La exportación vino vacía. Reintente.');
                }
                if (pack.blob.type && pack.blob.type.indexOf('text/html') !== -1) {
                    throw new Error('La sesión o el permiso fallaron al exportar. Recargue e intente de nuevo.');
                }
                dispararDescargaBlob(pack.blob, pack.filename);
                ocultar();
            })
            .catch(function (err) {
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert(err && err.message ? err.message : 'No se pudo descargar la exportación.');
            })
            .finally(function () {
                if (exportSafetyTimer) {
                    clearTimeout(exportSafetyTimer);
                    exportSafetyTimer = null;
                }
                exportAbort = null;
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-venta-hora-reporte');
        if (form) {
            form.addEventListener('submit', function () {
                if (form.checkValidity()) {
                    mostrar(
                        'Calculando venta hora por hora…',
                        'Puede demorar según el período. No cierre la página.'
                    );
                }
            });
        }
    });

    document.addEventListener(
        'click',
        function (event) {
            var enlace = event.target && event.target.closest ? event.target.closest('a[href]') : null;
            if (!enlace) {
                return;
            }

            var href = enlace.getAttribute('href') || enlace.href || '';
            if (href.indexOf('listar-gastronomia-venta-hora-reporte') === -1) {
                return;
            }

            // Descarga sin navegar: ocultamos el overlay al terminar el fetch.
            event.preventDefault();
            event.stopPropagation();
            descargarExportacion(href);
        },
        true
    );

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (exportAbort) {
                try {
                    exportAbort.abort();
                } catch (e) {}
            }
            ocultar();
        }
    });

    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
}());
