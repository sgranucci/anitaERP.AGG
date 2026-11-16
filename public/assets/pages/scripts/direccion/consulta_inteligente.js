(function () {
    'use strict';

    var exportAbort = null;
    var exportSafetyTimer = null;

    function overlay() {
        return document.getElementById('ci-proceso-overlay');
    }

    function mostrar(titulo) {
        var el = overlay();
        if (!el) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('ci-proceso-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        el.classList.remove('d-none');
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        var el = overlay();
        if (!el) {
            return;
        }
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
            exportSafetyTimer = null;
        }
        el.classList.add('d-none');
        el.style.display = '';
        el.setAttribute('aria-hidden', 'true');
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
        a.download = filename || 'consulta_inteligente';
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
        var fallback = 'consulta_inteligente';
        if (lower.indexOf('/excel') !== -1 || lower.indexOf('formato=excel') !== -1) {
            formato = 'Excel';
            fallback += '.xlsx';
        } else if (lower.indexOf('/pdf') !== -1 || lower.indexOf('formato=pdf') !== -1) {
            formato = 'PDF';
            fallback += '.pdf';
        } else if (lower.indexOf('/csv') !== -1 || lower.indexOf('formato=csv') !== -1) {
            formato = 'CSV';
            fallback += '.csv';
        }

        if (exportAbort) {
            try {
                exportAbort.abort();
            } catch (e) {}
        }
        exportAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;

        mostrar('Generando ' + formato + '…');
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
        }
        exportSafetyTimer = setTimeout(function () {
            if (exportAbort) {
                try {
                    exportAbort.abort();
                } catch (e) {}
            }
            ocultar();
        }, 600000);

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
        if (typeof window.initListadoQbeGrupos === 'function') {
            window.initListadoQbeGrupos();
        }

        var form = document.getElementById('ci-form-explorar');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof window.lwQbeBeforeSubmit === 'function') {
                    window.lwQbeBeforeSubmit();
                }
                mostrar('Consultando…');
            });
        }

        var vistaSel = document.getElementById('ci-vista-sel');
        if (vistaSel) {
            vistaSel.addEventListener('change', function () {
                var id = vistaSel.value;
                var base = vistaSel.getAttribute('data-base') || window.location.pathname;
                if (!id) {
                    window.location.href = base;
                    return;
                }
                mostrar('Cargando vista…');
                window.location.href = base + '?vista_id=' + encodeURIComponent(id) + '&consultar=1';
            });
            vistaSel.setAttribute('data-base', window.location.pathname);
        }

        document.querySelectorAll('a[href*="/listar/"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                descargarExportacion(a.href);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && exportAbort) {
                try {
                    exportAbort.abort();
                } catch (err) {}
                ocultar();
            }
        });

        var formExplorar = document.getElementById('ci-form-explorar');
        function clonarFiltrosEn(formDestino) {
            if (!formExplorar || !formDestino) {
                return;
            }
            formDestino.querySelectorAll('input[data-ci-clone="1"]').forEach(function (n) {
                n.remove();
            });
            Array.prototype.forEach.call(formExplorar.elements, function (el) {
                if (!el.name || el.disabled) {
                    return;
                }
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (!el.checked) {
                        return;
                    }
                }
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = el.name;
                h.value = el.value;
                h.setAttribute('data-ci-clone', '1');
                formDestino.appendChild(h);
            });
        }
        var formVista = document.getElementById('ci-form-guardar-vista');
        if (formVista) {
            formVista.addEventListener('submit', function () {
                clonarFiltrosEn(formVista);
            });
        }
        var formEnvio = document.getElementById('ci-form-envio');
        if (formEnvio) {
            formEnvio.addEventListener('submit', function () {
                clonarFiltrosEn(formEnvio);
            });
        }

        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
    });
})();
