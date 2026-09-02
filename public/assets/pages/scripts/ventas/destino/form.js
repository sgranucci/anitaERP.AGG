(function ($) {
    'use strict';

    function sincronizarCodigoDesdeZona(data) {
        var codigo = data && data.codigo != null ? String(data.codigo) : $.trim($('#codigozonavta').val() || '');
        $('#codigo').val(codigo);
    }

    var aplicarOrig = window.aplicarZonavtaEnContexto;
    if (typeof aplicarOrig === 'function') {
        window.aplicarZonavtaEnContexto = function ($ctx, data) {
            aplicarOrig($ctx, data);
            sincronizarCodigoDesdeZona(data);
        };
    }

    $(function () {
        $('#codigozonavta').on('change blur', function () {
            sincronizarCodigoDesdeZona();
        });
    });
})(jQuery);
