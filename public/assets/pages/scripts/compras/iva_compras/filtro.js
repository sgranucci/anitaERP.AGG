(function () {
    'use strict';

    var form = document.getElementById('form-iva-compras');
    if (!form) {
        return;
    }

    var overlay = document.getElementById('iva-compras-overlay');

    function mostrarOverlay(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('iva-compras-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('iva-compras-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    form.addEventListener('submit', function () {
        if (form.reportValidity && !form.reportValidity()) {
            return;
        }
        var btn = document.getElementById('btn-consultar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Consultando…';
        }
        mostrarOverlay('Consultando IVA compras…', 'Puede demorar según el período. No cierre la página.');
    });

    function nombreDesdeDisposition(header, fallback) {
        if (!header) {
            return fallback;
        }
        var m = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(header);
        if (m && m[1]) {
            try {
                return decodeURIComponent(m[1].replace(/"/g, '').trim());
            } catch (e) {
                return m[1].replace(/"/g, '').trim();
            }
        }
        return fallback;
    }

    function dispararDescargaBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            a.remove();
        }, 1000);
    }

    var exportAbort = null;
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href*="listar-iva-compras"]');
        if (!a || !a.href) {
            return;
        }
        e.preventDefault();
        if (exportAbort) {
            exportAbort.abort();
        }
        exportAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
        mostrarOverlay('Exportando…', 'Generando archivo. Pulse Esc para cerrar este aviso.');
        fetch(a.href, {
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
                        filename: nombreDesdeDisposition(res.headers.get('Content-Disposition'), 'iva_compras.xlsx'),
                    };
                });
            })
            .then(function (pack) {
                dispararDescargaBlob(pack.blob, pack.filename);
                ocultarOverlay();
            })
            .catch(function (err) {
                ocultarOverlay();
                if (err && err.name === 'AbortError') {
                    return;
                }
                alert((err && err.message) || 'No se pudo exportar');
            });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && exportAbort) {
            exportAbort.abort();
            ocultarOverlay();
        }
    });

    window.addEventListener('pageshow', ocultarOverlay);
    window.addEventListener('pagehide', ocultarOverlay);
})();
