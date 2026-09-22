(function () {
    var overlay = document.getElementById('comision-vendedor-overlay');
    var exportSafetyTimer = null;
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('comision-vendedor-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('comision-vendedor-subtitulo');
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
        a.download = filename || 'comision_vendedor';
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
        var fallback = 'comision_vendedor';
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
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
        }
        exportSafetyTimer = setTimeout(ocultar, 10 * 60 * 1000);

        var opts = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        };
        if (exportAbort) {
            opts.signal = exportAbort.signal;
        }

        fetch(href, opts)
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreArchivoDesdeDisposition(
                            res.headers.get('Content-Disposition'),
                            fallback
                        ),
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

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-comision-vendedor');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    return;
                }
                mostrar(
                    'Consultando comisiones…',
                    'Puede demorar según el período. No cierre la página.'
                );
            });
        }

        document.querySelectorAll('a[href*="listar-comision-vendedor"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                descargarExportacion(a.href);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay && !overlay.classList.contains('d-none')) {
                if (exportAbort) {
                    try {
                        exportAbort.abort();
                    } catch (err) {}
                }
                ocultar();
            }
        });

        document.querySelectorAll('#form-comision-vendedor .codigovendedor').forEach(function (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                    var next = document.getElementById('tipotransaccion_id') || document.getElementById('btn-consultar');
                    if (next) {
                        next.focus();
                    }
                }
                if (e.key === 'F1') {
                    e.preventDefault();
                    var btn = input.closest('.tm-vendedor-campo');
                    var lupa = btn ? btn.querySelector('.consultavendedor') : null;
                    if (lupa) {
                        lupa.click();
                    }
                }
            });
        });

        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);

        if (typeof window.activa_eventos_consultavendedor === 'function') {
            window.activa_eventos_consultavendedor();
        }
    });
})();
