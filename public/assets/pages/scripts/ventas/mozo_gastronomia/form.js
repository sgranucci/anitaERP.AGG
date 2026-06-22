(function ($) {
    'use strict';

    function actualizarProximoCodigoLocal() {
        var url = window.mozoGastronomiaProximoCodigoUrl;
        var empresaId = $('#empresa_id').val();
        if (!url || !empresaId) {
            return;
        }

        $.get(url, { empresa_id: empresaId })
            .done(function (data) {
                if (data && data.codigo) {
                    $('#codigo').val(data.codigo);
                }
            });
    }

    $(function () {
        if (!window.mozoGastronomiaProximoCodigoUrl) {
            return;
        }

        $('#empresa_id').on('change', actualizarProximoCodigoLocal);
    });
}(jQuery));
