(function ($) {
    'use strict';

    $(function () {
        var $nombre = $('#nombre');
        var $localidad = $('#dest_localidad');
        if (!$nombre.length || !$localidad.length) {
            return;
        }

        var localidadEditada = false;
        $localidad.on('input', function () {
            localidadEditada = $.trim($localidad.val()) !== $.trim($nombre.val());
        });

        $nombre.on('input', function () {
            if (!localidadEditada) {
                $localidad.val($nombre.val());
            }
        });
    });
})(jQuery);
