(function () {
    var overlay = document.getElementById('tn-stock-subida-overlay');
    var exportSafetyTimer = null;
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) return;
        var t = document.getElementById('tn-stock-subida-titulo');
        var s = document.getElementById('tn-stock-subida-subtitulo');
        if (t && titulo) t.textContent = titulo;
        if (s && subtitulo) s.textContent = subtitulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        if (!overlay) return;
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
            exportSafetyTimer = null;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivo(disposition, fallback) {
        if (!disposition) return fallback;
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) return fallback;
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function dispararDescarga(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'previsualizacion_tiendanube';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1500);
    }

    function descargar(href) {
        var lower = String(href).toUpperCase();
        var formato = 'archivo';
        var fallback = 'previsualizacion_tiendanube';
        if (lower.indexOf('/EXCEL') !== -1) { formato = 'Excel'; fallback += '.xlsx'; }
        else if (lower.indexOf('/PDF') !== -1) { formato = 'PDF'; fallback += '.pdf'; }
        else if (lower.indexOf('/CSV') !== -1) { formato = 'CSV'; fallback += '.csv'; }

        mostrar('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');
        if (exportAbort) {
            try { exportAbort.abort(); } catch (e) {}
        }
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        exportAbort = controller;
        if (exportSafetyTimer) clearTimeout(exportSafetyTimer);
        exportSafetyTimer = setTimeout(ocultar, 600000);

        fetch(href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: '*/*' }
        }).then(function (res) {
            if (!res.ok) throw new Error('Error HTTP ' + res.status + ' al exportar.');
            var filename = nombreArchivo(res.headers.get('Content-Disposition'), fallback);
            return res.blob().then(function (blob) { return { blob: blob, filename: filename }; });
        }).then(function (pack) {
            if (!pack || !pack.blob || pack.blob.size === 0) throw new Error('La exportación vino vacía.');
            dispararDescarga(pack.blob, pack.filename);
            ocultar();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') { ocultar(); return; }
            ocultar();
            window.alert((err && err.message) || 'No se pudo exportar');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var box = document.getElementById('tn-stock-subida-export');
        if (!box) return;
        box.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                descargar(a.href);
            });
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (exportAbort) {
                try { exportAbort.abort(); } catch (err) {}
            }
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
})();
