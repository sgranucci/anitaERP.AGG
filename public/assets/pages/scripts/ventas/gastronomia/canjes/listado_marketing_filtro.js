(function () {
    'use strict';

    $(function () {
        var $empresa = $('#empresa_id');
        var $form = $('#form-filtros-canje-marketing');

        if ($empresa.length && $form.length) {
            $empresa.on('change', function () {
                var val = $(this).val();
                var url = (window.CANJE_MARKETING_LISTADO || {}).urlIndex || $form.attr('action');
                if (!url) {
                    return;
                }
                var params = new URLSearchParams();
                if (val) {
                    params.set('empresa_id', val);
                }
                window.location.href = url + (params.toString() ? '?' + params.toString() : '');
            });
        }
    });
})();
