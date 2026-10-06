(function () {
    var overlay = document.getElementById('vip-emita-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('vip-emita-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('vip-emita-subtitulo');
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
        a.download = filename || 'cliente_vip_emita';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    var form = document.getElementById('form-consulta-vip-emita');
    if (form) {
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrar('Consultando Emita…', 'Puede demorar unos segundos. No cierre la página.');
        });
    }

    document.querySelectorAll('a[href*="lista-cliente-vip-emita"]').forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            e.preventDefault();
            if (exportAbort) {
                exportAbort.abort();
            }
            exportAbort = new AbortController();
            var href = enlace.href;
            var lower = href.toLowerCase();
            var formato = 'archivo';
            var fallback = 'cliente_vip_emita';
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
            mostrar('Exportando…', 'Generando ' + formato + '. Pulse Esc para cerrar este aviso.');
            fetch(href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: exportAbort.signal
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('No se pudo exportar');
                    }
                    return res.blob().then(function (blob) {
                        return {
                            blob: blob,
                            filename: nombreArchivoDesdeDisposition(res.headers.get('Content-Disposition'), fallback)
                        };
                    });
                })
                .then(function (pack) {
                    dispararDescargaBlob(pack.blob, pack.filename);
                    ocultar();
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        ocultar();
                        return;
                    }
                    ocultar();
                    alert((err && err.message) || 'No se pudo exportar');
                });
        });
    });

    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && exportAbort) {
            exportAbort.abort();
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
})();
