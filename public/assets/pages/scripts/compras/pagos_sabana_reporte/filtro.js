(function () {
    'use strict';

    var form = document.getElementById('form-pagos-sabana');
    var overlay = document.getElementById('pagos-sabana-overlay');

    function mostrarOverlay(titulo) {
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('pagos-sabana-titulo');
            if (t) {
                t.textContent = titulo;
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

    if (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                return;
            }
            mostrarOverlay('Consultando pagos…');
        });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href*="listar-pagos-sabana"]');
        if (!a) {
            return;
        }
        mostrarOverlay('Exportando…');
        var sub = document.getElementById('pagos-sabana-subtitulo');
        if (sub) {
            sub.textContent = 'Pulse Esc si la descarga ya terminó y el aviso sigue visible.';
        }
        var once = function () {
            ocultarOverlay();
            window.removeEventListener('focus', once);
        };
        window.addEventListener('focus', once);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ocultarOverlay();
        }
    });

    window.addEventListener('pageshow', ocultarOverlay);
    window.addEventListener('pagehide', ocultarOverlay);
})();
