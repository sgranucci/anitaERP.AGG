(function ($) {
    'use strict';

    var $modal = $('#modal-reporte-tasas-iibb');
    if (! $modal.length) {
        return;
    }

    var $btn = $('#btn-consultar-tasas-iibb');
    var $error = $('#reporte-tasas-iibb-error');
    var $cargando = $('#reporte-tasas-iibb-cargando');
    var $resultado = $('#reporte-tasas-iibb-resultado');
    var cargando = false;

    function mostrarError(mensaje) {
        $error.text(mensaje || 'No se pudo generar la vista previa.').removeClass('d-none');
    }

    function consultar() {
        if (cargando) {
            return;
        }

        var url = $modal.data('preview-url');
        if (! url) {
            mostrarError('Falta la ruta de vista previa.');
            return;
        }

        cargando = true;
        $error.addClass('d-none').text('');
        $resultado.empty();
        $cargando.removeClass('d-none');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Consultando…');

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'html',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        }).done(function (html) {
            $resultado.html(html);
        }).fail(function (xhr) {
            var msg = 'No se pudo generar la vista previa.';
            if (xhr.status === 403) {
                msg = 'No tiene permiso para listar provincias.';
            } else if (xhr.status === 419) {
                msg = 'Sesión expirada. Recargue la página e intente de nuevo.';
            }
            mostrarError(msg);
        }).always(function () {
            cargando = false;
            $cargando.addClass('d-none');
            $btn.prop('disabled', false).html('<i class="fa fa-search"></i> Consultar');
        });
    }

    $btn.on('click', function () {
        consultar();
    });

    $modal.on('shown.bs.modal', function () {
        if ($resultado.children().length === 0 && ! cargando) {
            consultar();
        }
    });

    $modal.on('hidden.bs.modal', function () {
        $error.addClass('d-none').text('');
        $cargando.addClass('d-none');
        $resultado.empty();
        $btn.prop('disabled', false).html('<i class="fa fa-search"></i> Consultar');
        cargando = false;
    });
})(jQuery);
