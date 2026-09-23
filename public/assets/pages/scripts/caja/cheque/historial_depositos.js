/**
 * Historial de depósitos CHT: expandir detalle + overlay export fetch/blob.
 */
(function () {
    'use strict';

    function initToggle() {
        document.querySelectorAll('.hist-dep-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sel = btn.getAttribute('data-target');
                if (!sel) {
                    return;
                }
                var row = document.querySelector(sel);
                if (!row) {
                    return;
                }
                var icon = btn.querySelector('i');
                var abierto = !row.classList.contains('d-none');
                if (abierto) {
                    row.classList.add('d-none');
                    btn.setAttribute('aria-expanded', 'false');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-right');
                    }
                } else {
                    row.classList.remove('d-none');
                    btn.setAttribute('aria-expanded', 'true');
                    if (icon) {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-down');
                    }
                }
            });
        });
    }

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

    function initExportOverlay() {
        var overlay = document.getElementById('historial-deposito-export-overlay');
        if (!overlay) {
            return;
        }
        var tituloEl = document.getElementById('historial-deposito-export-titulo');
        var subEl = document.getElementById('historial-deposito-export-subtitulo');
        var abortCtrl = null;

        function mostrar(titulo, sub) {
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
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('a[href*="listahistorialdepositocheque"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (abortCtrl) {
                    abortCtrl.abort();
                }
                abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var formato = 'archivo';
                if (/\/PDF/i.test(a.href)) {
                    formato = 'PDF';
                } else if (/\/EXCEL/i.test(a.href)) {
                    formato = 'Excel';
                } else if (/\/CSV/i.test(a.href)) {
                    formato = 'CSV';
                }
                mostrar('Exportando ' + formato + '…', 'Generando archivo. Pulse Esc para cerrar este aviso.');
                var opts = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
                if (abortCtrl) {
                    opts.signal = abortCtrl.signal;
                }
                fetch(a.href, opts)
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('HTTP ' + res.status);
                        }
                        return res.blob().then(function (blob) {
                            return {
                                blob: blob,
                                filename: nombreDesdeDisposition(
                                    res.headers.get('Content-Disposition'),
                                    'historial_depositos_cheque.' + (formato === 'CSV' ? 'csv' : formato === 'PDF' ? 'pdf' : 'xlsx')
                                )
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
            });
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !overlay.classList.contains('d-none')) {
                if (abortCtrl) {
                    abortCtrl.abort();
                }
                ocultar();
            }
        });
        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initToggle();
        initExportOverlay();
    });
})();
