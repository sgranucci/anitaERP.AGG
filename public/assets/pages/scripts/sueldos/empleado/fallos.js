(function ($) {
    'use strict';

    var cargado = false;

    function host() {
        return $('#host-fallos');
    }

    function cargarPanel(params) {
        var url = host().data('url');
        if (!url) {
            return;
        }
        $.get(url, params || {}).done(function (resp) {
            host().html(resp.html || '');
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de fallos.</div>');
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-fallos"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('submit', '#form-fallos-empleado-filtro', function (e) {
        e.preventDefault();
        var data = $(this).serializeArray();
        var params = {};
        data.forEach(function (item) {
            params[item.name] = item.value;
        });
        cargarPanel(params);
    });
})(jQuery);
