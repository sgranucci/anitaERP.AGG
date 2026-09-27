/**
 * Guardar vista: actualizar la abierta o crear otra, sin destildar un checkbox.
 */
(function (window, $) {
    'use strict';

    function aplicarModo($form, modo) {
        var nombreOriginal = $form.attr('data-vista-nombre') || '';
        var $id = $form.find('#lw-vista-id-guardar');
        var $nombre = $form.find('#vista_nombre_grilla');
        var $btn = $form.find('#btn-lw-guardar-vista');
        var $eliminar = $('#btn-lw-eliminar-vista-ref');
        var $hint = $form.find('#lw-vista-modo-hint');

        $form.find('.lw-vista-modo-btn').removeClass('is-active');
        $form.find('.lw-vista-modo-btn[data-modo="' + modo + '"]').addClass('is-active');

        if (modo === 'nueva') {
            $id.prop('disabled', true);
            if (($nombre.val() || '') === nombreOriginal) {
                $nombre.val('');
            }
            $nombre.attr('placeholder', 'Nombre de la vista nueva');
            $btn.html('<i class="fa fa-plus"></i> Crear vista');
            $eliminar.addClass('d-none');
            $hint.text('Se crea una vista nueva. «' + nombreOriginal + '» queda igual.');
            return;
        }

        $id.prop('disabled', false);
        if (($nombre.val() || '') === '') {
            $nombre.val(nombreOriginal);
        }
        $nombre.attr('placeholder', nombreOriginal);
        $btn.html('<i class="fa fa-save"></i> Actualizar vista');
        $eliminar.removeClass('d-none');
        $hint.text('Se actualiza «' + nombreOriginal + '». El nombre se puede corregir.');
    }

    function initListadoVistaGuardar() {
        var $form = $('#form-lw-grilla-vista');
        if (!$form.length || !$form.find('.lw-vista-modo').length) {
            return;
        }

        $form.on('click', '.lw-vista-modo-btn', function () {
            var modo = $(this).data('modo') === 'nueva' ? 'nueva' : 'actualizar';
            aplicarModo($form, modo);
            if (modo === 'nueva') {
                $form.find('#vista_nombre_grilla').trigger('focus');
            }
        });
    }

    window.initListadoVistaGuardar = initListadoVistaGuardar;
    $(initListadoVistaGuardar);
})(window, jQuery);
