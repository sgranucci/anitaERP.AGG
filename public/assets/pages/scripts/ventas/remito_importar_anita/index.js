(function () {
    'use strict';

    var overlay = document.getElementById('importar-remito-anita-overlay');
    var tituloEl = document.getElementById('importar-remito-anita-titulo');

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

    var formConsultar = document.getElementById('form-importar-remito-anita-consultar');
    if (formConsultar) {
        formConsultar.addEventListener('submit', function () {
            if (typeof formConsultar.checkValidity === 'function' && !formConsultar.checkValidity()) {
                return;
            }
            mostrar('Consultando remitos en Anita…');
        });
    }

    var formImportar = document.getElementById('form-importar-remito-anita-ejecutar');
    if (formImportar) {
        formImportar.addEventListener('submit', function () {
            mostrar('Importando remitos desde Anita…');
        });
    }

    window.addEventListener('pageshow', ocultar);
})();
