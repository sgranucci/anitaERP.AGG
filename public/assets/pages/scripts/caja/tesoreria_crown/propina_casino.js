(function () {
    var overlay = document.getElementById('propina-casino-overlay');
    var exportSafetyTimer = null;
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('propina-casino-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('propina-casino-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        if (!overlay) {
            return;
        }
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
            exportSafetyTimer = null;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivoDesdeDisposition(disposition, fallback) {
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
        a.download = filename || 'reporte_propina_casino';
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
        var fallback = 'reporte_propina_casino';
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
            'Exportando…',
            'Generando ' + formato + '… Puede demorar según el período. Pulse Esc para cerrar este aviso.'
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
                if (!res.ok) {
                    throw new Error('Error HTTP ' + res.status + ' al exportar.');
                }
                var filename = nombreArchivoDesdeDisposition(
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
                if (err && err.name === 'AbortError') {
                    ocultar();
                    return;
                }
                ocultar();
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

    var form = document.getElementById('form-propina-casino');
    if (form) {
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrar(
                'Consultando propinas…',
                'Puede demorar según el período. No cierre la página.'
            );
        });
    }

    document.querySelectorAll('.js-export-propina-casino a[href*="listar-tesoreria-crown-propina-casino"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            descargarExportacion(a.href);
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
})();
