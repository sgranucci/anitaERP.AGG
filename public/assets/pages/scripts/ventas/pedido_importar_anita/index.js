(function () {
    'use strict';

    var overlay = document.getElementById('importar-pedido-anita-overlay');
    var tituloEl = document.getElementById('importar-pedido-anita-titulo');

    function mostrar(titulo) {
        if (!overlay) {
            return;
        }
        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
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

    var formConsultar = document.getElementById('form-importar-pedido-anita-consultar');
    if (formConsultar) {
        formConsultar.addEventListener('submit', function (e) {
            if (typeof formConsultar.checkValidity === 'function' && !formConsultar.checkValidity()) {
                return;
            }
            mostrar('Consultando pedidos en Anita…');
        });
    }

    var formImportar = document.getElementById('form-importar-pedido-anita-ejecutar');
    if (formImportar) {
        formImportar.addEventListener('submit', function () {
            // El confirm del onsubmit ya se evaluó; si llegamos acá, continúa.
            mostrar('Importando pedidos desde Anita…');
        });
    }

    window.addEventListener('pageshow', ocultar);
})();
