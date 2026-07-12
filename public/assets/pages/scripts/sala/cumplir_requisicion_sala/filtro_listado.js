(function ($) {
    'use strict';

    var operadoresPorCampo = window.cumplimientoReqSalaFiltroOperadores || {};

    function refrescarOperadores() {
        var campo = $('#filtro_campo').val();
        var ops = operadoresPorCampo[campo] || {};
        var html = '';
        $.each(ops, function (k, v) {
            html += '<option value="' + k + '">' + v + '</option>';
        });
        var sel = $('#filtro_operador').val();
        $('#filtro_operador').html(html);
        if (sel && ops[sel]) {
            $('#filtro_operador').val(sel);
        }
    }

    $(function () {
        refrescarOperadores();
        $('#filtro_campo').on('change', refrescarOperadores);
    });
}(jQuery));
