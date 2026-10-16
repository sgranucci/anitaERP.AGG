(function () {
    'use strict';

    var form = document.getElementById('form-control-ganancias');
    if (!form) {
        return;
    }

    var overlay = document.getElementById('control-ganancias-overlay');

    function mostrarOverlay(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('control-ganancias-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('control-ganancias-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    form.addEventListener('submit', function () {
        var arca = document.getElementById('consultar_arca');
        var limite = document.getElementById('limite');
        var n = limite ? parseInt(limite.value || '0', 10) : 0;
        var sub = 'Puede demorar según el límite. No cierre la página.';
        if (arca && arca.checked && n > 20) {
            sub = 'Consultando ARCA hasta ' + n + ' CUITs (con caché). No cierre la página.';
        }
        mostrarOverlay('Generando control de ganancias…', sub);
    });
})();
