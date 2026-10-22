(function () {
    var overlay = document.getElementById('cobranza-export-overlay');
    var titulo = document.getElementById('cobranza-export-titulo');
    var subtitulo = document.getElementById('cobranza-export-subtitulo');
    var caja = document.getElementById('exportar-cobranza');
    if (!overlay || !caja) {
        return;
    }

    var exportAbort = null;
    var exportSafetyTimer = null;

    function mostrar(textoTitulo, textoSub) {
        if (titulo && textoTitulo) {
            titulo.textContent = textoTitulo;
        }
        if (subtitulo && textoSub) {
            subtitulo.textContent = textoSub;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
            exportSafetyTimer = null;
        }
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
        a.download = filename || 'cobranza';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    caja.addEventListener('click', function (event) {
        var enlace = event.target.closest('a');
        if (!enlace || !caja.contains(enlace)) {
            return;
        }
        var href = enlace.getAttribute('href') || '';
        if (href.indexOf('listacobranza') === -1) {
            return;
        }
        event.preventDefault();

        var lower = href.toLowerCase();
        var formato = 'archivo';
        var fallback = 'cobranza';
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

        mostrar('Exportando…', 'Generando ' + formato + '… Puede demorar según el volumen. Pulse Esc para cerrar este aviso.');

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
                if (!res.ok) {
                    throw new Error('No se pudo exportar (HTTP ' + res.status + ').');
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
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert((err && err.message) || 'No se pudo exportar');
            });
    });

    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !overlay.classList.contains('d-none')) {
            if (exportAbort) {
                try {
                    exportAbort.abort();
                } catch (e) {}
            }
            ocultar();
        }
    });

    window.addEventListener('pageshow', ocultar);
})();
