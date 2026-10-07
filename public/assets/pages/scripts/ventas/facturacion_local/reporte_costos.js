(function () {
    var overlay = document.getElementById('fl-costos-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('fl-costos-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('fl-costos-subtitulo');
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
        if (exportAbort) {
            exportAbort.abort();
            exportAbort = null;
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
        a.download = filename || 'facturacion_local_costos';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultamventa === 'function') {
            activa_eventos_consultamventa();
        }

        var form = document.getElementById('form-fl-costos-local');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                mostrar('Calculando costos…', 'Puede demorar según la cantidad de SKU. No cierre la página.');
            });
        }

        document.querySelectorAll('a[href*="listar-facturacion-local-costos"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (exportAbort) {
                    exportAbort.abort();
                }
                exportAbort = new AbortController();
                var href = a.href;
                var lower = String(href).toLowerCase();
                var formato = 'archivo';
                var fallback = 'facturacion_local_costos';
                if (lower.indexOf('excel') !== -1) {
                    formato = 'Excel';
                    fallback += '.xlsx';
                } else if (lower.indexOf('pdf') !== -1) {
                    formato = 'PDF';
                    fallback += '.pdf';
                } else if (lower.indexOf('csv') !== -1) {
                    formato = 'CSV';
                    fallback += '.csv';
                }
                mostrar('Exportando ' + formato + '…', 'Generando el archivo. Pulse Esc para cancelar.');
                fetch(href, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: exportAbort.signal,
                })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('No se pudo exportar (' + res.status + ')');
                        }
                        return res.blob().then(function (blob) {
                            return {
                                blob: blob,
                                filename: nombreArchivoDesdeDisposition(res.headers.get('Content-Disposition'), fallback),
                            };
                        });
                    })
                    .then(function (pack) {
                        dispararDescargaBlob(pack.blob, pack.filename);
                        ocultar();
                    })
                    .catch(function (err) {
                        if (err && err.name === 'AbortError') {
                            return;
                        }
                        ocultar();
                        window.alert((err && err.message) || 'No se pudo exportar');
                    });
            });
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
})();
