(function ($) {
    'use strict';

    function mostrarOverlay() {
        var overlay = document.getElementById('overlay-importar-remito-anita');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('overlay-importar-remito-anita');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    $(function () {
        var $form = $('#form-importar-remito-anita-index');
        if (!$form.length) {
            return;
        }

        $form.on('submit', function (e) {
            if (!$form[0].checkValidity()) {
                return;
            }
            var fecha = $.trim($('#import_anita_remito_fecha').val() || '');
            var reparto = $.trim($('#import_anita_remito_filtro_reparto').val() || '');
            var fuente = $.trim($('#import_anita_remito_fuente').val() || 'bierzo');
            var etiqueta = fuente === 'surmar' ? 'Surmar (REM R 6)' : 'Bierzo (REM R 1)';
            var msg = 'Se importarán/actualizarán los remitos Anita ' + etiqueta + ' de la fecha ' + fecha;
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
            $('#modalImportarRemitoAnita').modal('hide');
            mostrarOverlay();
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
