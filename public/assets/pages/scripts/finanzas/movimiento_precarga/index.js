(function () {
    function bootQbe() {
        if (typeof window.initListadoQbeGrupos === 'function') {
            window.initListadoQbeGrupos();
        }
        if (typeof window.initListadoOrden === 'function') {
            window.initListadoOrden();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootQbe);
    } else {
        bootQbe();
    }

    var overlay = document.getElementById('precarga-export-overlay');
    var titulo = document.getElementById('precarga-export-titulo');
    var subtitulo = document.getElementById('precarga-export-subtitulo');
    var abortar = null;

    function mostrar(texto) {
        if (!overlay) return;
        if (titulo) titulo.textContent = texto || 'Exportando…';
        if (subtitulo) subtitulo.textContent = 'Generando el archivo. Pulse Esc para cerrar este aviso.';
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (abortar) {
            abortar.abort();
            abortar = null;
        }
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    function nombreArchivo(header, fallback) {
        var match = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(header || '');
        if (match) {
            try { return decodeURIComponent(match[1] || match[2]); } catch (e) { return match[1] || match[2]; }
        }
        return fallback;
    }

    document.querySelectorAll('a[href*="lista-movimiento-precarga"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            mostrar('Exportando…');
            abortar = new AbortController();
            fetch(a.href, { credentials: 'same-origin', signal: abortar.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudo exportar');
                    return res.blob().then(function (blob) {
                        return { blob: blob, filename: nombreArchivo(res.headers.get('Content-Disposition'), 'precargas') };
                    });
                })
                .then(function (pack) {
                    var url = URL.createObjectURL(pack.blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = pack.filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    abortar = null;
                    ocultar();
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    ocultar();
                    alert(err.message || 'No se pudo exportar');
                });
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ocultar();
    });
    window.addEventListener('pageshow', ocultar);
})();
