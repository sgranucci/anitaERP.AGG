(function ($) {
    'use strict';

    function mostrarOverlay() {
        var overlay = document.getElementById('overlay-importar-pedido-anita');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('overlay-importar-pedido-anita');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    $(function () {
        var $form = $('#form-importar-pedido-anita-index');
        if (!$form.length) {
            return;
        }

        $form.on('submit', function (e) {
            if (!$form[0].checkValidity()) {
                return;
            }
            var fecha = $.trim($('#import_anita_fecha_entrega').val() || '');
            var reparto = $.trim($('#import_anita_filtro_reparto').val() || '');
            var msg = 'Se importarán/actualizarán los pedidos Anita con entrega ' + fecha;
            if (reparto !== '') {
                msg += ' y repartos ' + reparto;
            } else {
                msg += ' (todos los repartos)';
            }
            msg += '. ¿Continuar?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                return;
            }
            $('#modalImportarPedidoAnita').modal('hide');
            mostrarOverlay();
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
