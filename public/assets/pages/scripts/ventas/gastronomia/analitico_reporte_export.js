(function () {
    'use strict';

    var exportAbort = null;
    var exportSafetyTimer = null;

    function els() {
        return {
            overlay: document.getElementById('analitico-gastro-overlay'),
            tituloEl: document.getElementById('analitico-gastro-overlay-titulo'),
            subtituloEl: document.getElementById('analitico-gastro-overlay-subtitulo'),
        };
    }

    function mostrar(titulo, subtitulo) {
        var e = els();
        if (!e.overlay) {
            return;
        }
        if (titulo && e.tituloEl) {
            e.tituloEl.textContent = titulo;
        }
        if (subtitulo && e.subtituloEl) {
            e.subtituloEl.textContent = subtitulo;
        }
        e.overlay.classList.remove('d-none');
        e.overlay.style.display = 'flex';
        e.overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function ocultar() {
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
            exportSafetyTimer = null;
        }
        var e = els();
        if (!e.overlay) {
            return;
        }
        e.overlay.classList.add('d-none');
        e.overlay.style.display = '';
        e.overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
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
        } catch (err) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'analitico_gastronomia';
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
        var fallback = 'analitico_gastronomia';

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
            'Generando ' + formato + '…',
            'Usa el snapshot de la consulta. El archivo se descarga al terminar. Escape cancela la espera.'
        );

        if (exportAbort) {
            try {
                exportAbort.abort();
            } catch (err) {}
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        exportAbort = controller;

        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
        }
        exportSafetyTimer = setTimeout(function () {
            ocultar();
            window.alert(
                'La exportación está demorando demasiado. Si el archivo no llegó, pruebe CSV o vuelva a consultar e intente de nuevo.'
            );
        }, 180000);

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
                var contentType = (res.headers.get('Content-Type') || '').toLowerCase();
                if (contentType.indexOf('text/html') !== -1) {
                    ocultar();
                    window.location.href = href;
                    return null;
                }
                if (res.status === 419) {
                    throw new Error('Sesión expirada. Recargue la página (F5) e intente de nuevo.');
                }
                if (res.redirected && res.url && res.url.indexOf('listar-gastronomia-analitico-reporte') === -1) {
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
                if (!pack) {
                    return;
                }
                if (!pack.blob || pack.blob.size === 0) {
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
        ocultar();

        var form = document.getElementById('form-analitico-gastro-reporte');
        if (form) {
            form.addEventListener('submit', function () {
                if (form.checkValidity && !form.checkValidity()) {
                    return;
                }
                mostrar(
                    'Consultando analítico…',
                    'Se arma el snapshot completo. Puede demorar según el período. No cierre la página.'
                );
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
            if (href.indexOf('listar-gastronomia-analitico-reporte') === -1) {
                return;
            }

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
                } catch (err) {}
            }
            ocultar();
        }
    });

    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
}());
