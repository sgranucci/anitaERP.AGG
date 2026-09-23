(function ($) {
    'use strict';

    function mostrarOverlay(id) {
        var overlay = document.getElementById(id || 'importar-pedido-anita-overlay');
        if (!overlay) {
            overlay = document.getElementById('overlay-importar-pedido-anita');
        }
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        ['importar-pedido-anita-overlay', 'overlay-importar-pedido-anita'].forEach(function (id) {
            var overlay = document.getElementById(id);
            if (!overlay) {
                return;
            }
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        });
    }

    $(function () {
        var formConsultar = document.getElementById('form-importar-pedido-anita-consultar');
        if (formConsultar) {
            formConsultar.addEventListener('submit', function () {
                if (typeof formConsultar.checkValidity === 'function' && !formConsultar.checkValidity()) {
                    return;
                }
                mostrarOverlay('importar-pedido-anita-overlay');
            });
        }

        var formImportar = document.getElementById('form-importar-pedido-anita-ejecutar');
        if (formImportar) {
            formImportar.addEventListener('submit', function () {
                mostrarOverlay('importar-pedido-anita-overlay');
            });
        }

        var $formIndex = $('#form-importar-pedido-anita-index');
        if ($formIndex.length) {
            $formIndex.on('submit', function (e) {
                if (!$formIndex[0].checkValidity()) {
                    return;
                }
                var fecha = $.trim($('#import_anita_fecha').val() || '');
                var tipo = $.trim($('#import_anita_tipo').val() || 'TODOS');
                var msg = 'Se importarán/actualizarán los pedidos Anita con fecha ' + fecha
                    + ' (tipo ' + tipo + '). ¿Continuar?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                    return;
                }
                $('#modalImportarPedidoAnita').modal('hide');
                mostrarOverlay('overlay-importar-pedido-anita');
            });
        }

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
