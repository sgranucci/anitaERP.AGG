(function ($) {
    'use strict';

    var cargado = false;

    function host() {
        return $('#host-siradig');
    }

    function cargarPanel() {
        var url = host().data('url');
        if (!url) {
            return;
        }
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de SiRADIG.</div>');
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-siradig"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });
})(jQuery);
