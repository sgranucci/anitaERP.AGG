(function ($) {
    'use strict';

    var ALCANCE_CONCEPTO = 'concepto';
    var ALCANCE_RUBRO = 'rubro';
    var ALCANCE_TIPO = 'tipo';

    function actualizarBloquesAlcance() {
        var alcance = $('#alcance').val() || ALCANCE_TIPO;
        $('#bloque-alcance-concepto').toggleClass('d-none', alcance !== ALCANCE_CONCEPTO);
        $('#bloque-alcance-rubro').toggleClass('d-none', alcance !== ALCANCE_RUBRO);
        $('#bloque-alcance-tipo').toggleClass('d-none', alcance !== ALCANCE_TIPO);

        $('#concepto_sueldos_id').prop('required', alcance === ALCANCE_CONCEPTO);
        $('#rubro').prop('required', alcance === ALCANCE_RUBRO);
        $('#tipo').prop('required', alcance === ALCANCE_TIPO);

        if (alcance !== ALCANCE_CONCEPTO) {
            $('#concepto_sueldos_id').val('');
            $('.tm-concepto-sueldos-campo .codigoconcepto_sueldos').val('');
            $('.tm-concepto-sueldos-campo .nombreconcepto_sueldos').val('');
        }
        if (alcance !== ALCANCE_RUBRO) {
            $('#rubro').val('');
        }
        if (alcance !== ALCANCE_TIPO) {
            $('#tipo').val('');
        }
    }

    $(function () {
        if (!$('#form-general').length || !$('#alcance').length) {
            return;
        }

        $('#alcance').on('change', actualizarBloquesAlcance);
        actualizarBloquesAlcance();
    });
})(jQuery);
