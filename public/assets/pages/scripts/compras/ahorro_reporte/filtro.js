/**
 * Reporte de ahorros: exportación (Pdf / Excel / Csv) con fetch+blob.
 * El aviso se oculta al terminar la generación, no al abrir el archivo.
 */
(function ($) {
    'use strict';

    var abortCtl = null;

    function overlay() { return document.getElementById('ahorro-export-overlay'); }

    function mostrar(titulo, subtitulo) {
        var el = overlay();
        if (!el) { return; }
        $('#ahorro-export-overlay-titulo').text(titulo);
        $('#ahorro-export-overlay-subtitulo').text(subtitulo);
        el.classList.remove('d-none');
        el.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        var el = overlay();
        if (!el) { return; }
        el.classList.add('d-none');
        el.setAttribute('aria-hidden', 'true');
    }

    function nombreDesdeDisposition(header, porDefecto) {
        if (!header) { return porDefecto; }
        var m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);
        return m ? decodeURIComponent(m[1]) : porDefecto;
    }

    function descargar(blob, nombre) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { window.URL.revokeObjectURL(url); }, 1000);
    }

    function exportar(href) {
        var etiqueta = /\/PDF\b/i.test(href) ? 'PDF' : (/\/CSV\b/i.test(href) ? 'CSV' : 'Excel');
        var porDefecto = 'ahorro_compras.' + (etiqueta === 'PDF' ? 'pdf' : (etiqueta === 'CSV' ? 'csv' : 'xlsx'));
        abortCtl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        mostrar('Exportando…', 'Generando ' + etiqueta + '… Pulse Esc para cerrar este aviso.');
        fetch(href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: abortCtl ? abortCtl.signal : undefined
        })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.blob().then(function (blob) {
                    return { blob: blob, nombre: nombreDesdeDisposition(res.headers.get('Content-Disposition'), porDefecto) };
                });
            })
            .then(function (pack) {
                descargar(pack.blob, pack.nombre);
                ocultar();
            })
            .catch(function (err) {
                ocultar();
                if (err && err.name === 'AbortError') { return; }
                window.alert((err && err.message) || 'No se pudo exportar');
            });
    }

    $(function () {
        $(document)
            .off('click.ahorroexport', 'a[href*="listar-ahorro-reporte"]')
            .on('click.ahorroexport', 'a[href*="listar-ahorro-reporte"]', function (e) {
                var href = $(this).attr('href') || '';
                if (!href || href === '#') { return; }
                e.preventDefault();
                exportar(href);
            });
        $(document).off('keydown.ahorroexport').on('keydown.ahorroexport', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if (abortCtl) { try { abortCtl.abort(); } catch (err) {} }
                ocultar();
            }
        });
        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
    });
})(jQuery);
