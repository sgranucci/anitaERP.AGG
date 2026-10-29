(function () {
    if (typeof activa_eventos_consultavendedor === 'function') {
        activa_eventos_consultavendedor();
    }
    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    var form = document.getElementById('form-analisis-entregas');
    var overlay = document.getElementById('analisis-entregas-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('analisis-entregas-titulo');
        var s = document.getElementById('analisis-entregas-subtitulo');
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
        if (!overlay) {
            return;
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

    if (form) {
        form.addEventListener('submit', function () {
            mostrar('Consultando…', 'Puede demorar según el período. No cierre la página.');
        });
        form.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.matches('.codigocliente, .codigoarticulo, .codigovendedor')) {
                e.preventDefault();
            }
        });
    }

    document.querySelectorAll('a[href*="listar-analisis-entregas"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            if (exportAbort) {
                exportAbort.abort();
            }
            exportAbort = new AbortController();
            var href = a.href;
            var lower = href.toLowerCase();
            var formato = 'archivo';
            var fallback = 'analisis_entregas';
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
            mostrar('Exportando…', 'Generando ' + formato + '…');
            fetch(href, {
                credentials: 'same-origin',
                signal: exportAbort.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('No se pudo exportar');
                }
                return res.blob().then(function (blob) {
                    return { blob: blob, filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback) };
                });
            }).then(function (pack) {
                var url = window.URL.createObjectURL(pack.blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = pack.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1500);
                ocultar();
            }).catch(function (err) {
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert((err && err.message) || 'No se pudo exportar');
            });
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && exportAbort) {
            exportAbort.abort();
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
})();
