(function () {
    var overlay = document.getElementById('stkmov-articulo-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('stkmov-articulo-titulo');
        var s = document.getElementById('stkmov-articulo-subtitulo');
        if (titulo && t) {
            t.textContent = titulo;
        }
        if (subtitulo && s) {
            s.textContent = subtitulo;
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
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    var form = document.getElementById('form-stkmov-articulo');
    if (form) {
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrar('Consultando movimientos…', 'Puede demorar según el período. No cierre la página.');
        });
    }

    document.querySelectorAll('a[href*="listar-reporte-movimientos-stock-articulo"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var href = a.href;
            var lower = href.toLowerCase();
            var formato = 'archivo';
            var fallback = 'movimientos_stock_articulo';
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
            mostrar('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');
            exportAbort = new AbortController();
            fetch(href, {
                credentials: 'same-origin',
                signal: exportAbort.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('No se pudo exportar');
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback)
                    };
                });
            }).then(function (pack) {
                var url = window.URL.createObjectURL(pack.blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = pack.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.setTimeout(function () {
                    window.URL.revokeObjectURL(url);
                }, 1500);
                exportAbort = null;
                ocultar();
            }).catch(function (err) {
                exportAbort = null;
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert((err && err.message) || 'No se pudo exportar');
            });
        });
    });

    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
})();
