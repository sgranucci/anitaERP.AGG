(function ($) {
    'use strict';

    function mostrarOverlay() {
        var overlay = document.getElementById('overlay-importar-pedido-l8');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('overlay-importar-pedido-l8');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    $(function () {
        var $form = $('#form-importar-pedido-l8-index');
        if ($form.length) {
            $form.on('submit', function () {
                if (!$form[0].checkValidity()) {
                    return;
                }
                if (!window.confirm('¿Importar desde L8 los pedidos que faltan en L12?')) {
                    return false;
                }
                mostrarOverlay();
            });
        }

        var $formTareas = $('#form-importar-tareas-l8');
        if ($formTareas.length) {
            $formTareas.on('submit', function () {
                if (!window.confirm('¿Importar desde L8 las tareas faltantes de las OT de este pedido?')) {
                    return false;
                }
                var overlay = document.getElementById('overlay-importar-tareas-l8');
                if (overlay) {
                    overlay.classList.remove('d-none');
                    overlay.style.display = 'flex';
                    overlay.setAttribute('aria-hidden', 'false');
                }
            });
        }

        window.addEventListener('pageshow', function () {
            ocultarOverlay();
            var overlayT = document.getElementById('overlay-importar-tareas-l8');
            if (overlayT) {
                overlayT.classList.add('d-none');
                overlayT.style.display = '';
                overlayT.setAttribute('aria-hidden', 'true');
            }
        });
    });
})(jQuery);
