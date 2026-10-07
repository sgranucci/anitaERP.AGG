(function () {
    'use strict';

    function nombreDesdeDisposition(header, fallback) {
        if (!header) {
            return fallback;
        }
        var m = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(header);
        if (!m) {
            return fallback;
        }
        try {
            return decodeURIComponent(m[1].replace(/"/g, '').trim());
        } catch (e) {
            return m[1].replace(/"/g, '').trim() || fallback;
        }
    }

    function dispararDescargaBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'export';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            a.remove();
        }, 1500);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var overlay = document.getElementById('percepcion-sufrida-overlay');
        var tituloEl = document.getElementById('percepcion-sufrida-titulo');
        var subEl = document.getElementById('percepcion-sufrida-subtitulo');
        var abortCtrl = null;

        function mostrar(titulo, sub) {
            if (!overlay) {
                return;
            }
            if (titulo && tituloEl) {
                tituloEl.textContent = titulo;
            }
            if (sub && subEl) {
                subEl.textContent = sub;
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

        var form = document.getElementById('form-percepcion-sufrida');
        if (form) {
            form.addEventListener('submit', function () {
                mostrar('Consultando…', 'Puede demorar. No cierre la página.');
            });
        }

        function enlazarDescarga(a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (abortCtrl) {
                    abortCtrl.abort();
                }
                abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var formato = 'archivo';
                if (/\/PDF/i.test(a.href) || /formato=PDF/i.test(a.href)) {
                    formato = 'PDF';
                } else if (/\/EXCEL/i.test(a.href) || /formato=EXCEL/i.test(a.href)) {
                    formato = 'Excel';
                } else if (/\/CSV/i.test(a.href) || /formato=CSV/i.test(a.href)) {
                    formato = 'CSV';
                }
                mostrar('Generando ' + formato + '…', 'Pulse Esc para cancelar.');
                var opts = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
                if (abortCtrl) {
                    opts.signal = abortCtrl.signal;
                }
                fetch(a.href, opts)
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('HTTP ' + res.status);
                        }
                        var fallback = 'export';
                        if (formato === 'PDF') {
                            fallback = 'diferencias.pdf';
                        } else if (formato === 'Excel') {
                            fallback = 'diferencias.xlsx';
                        } else if (formato === 'CSV') {
                            fallback = 'diferencias.csv';
                        }
                        return res.blob().then(function (blob) {
                            return {
                                blob: blob,
                                filename: nombreDesdeDisposition(res.headers.get('Content-Disposition'), fallback)
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
                        alert((err && err.message) || 'No se pudo generar el archivo');
                    });
            });
        }

        document.querySelectorAll('a.js-percepcion-descarga, a[href*="listar-sifere"], a[href*="listar-percepciones-iva"]').forEach(enlazarDescarga);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            if (abortCtrl) {
                abortCtrl.abort();
                abortCtrl = null;
            }
            ocultar();
        });
    });
})();
