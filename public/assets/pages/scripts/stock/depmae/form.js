(function ($) {
    'use strict';

    function esFormularioDepmaeAbm() {
        return $('#form-general').length && $('#codigo[name="codigo"]').length;
    }

    /**
     * Aplica depósito en crear/editar. Si ya existe otro registro, redirige a editar.
     * @returns {boolean} true si hubo redirección
     */
    window.aplicarDepositoEnFormularioAbm = function (data) {
        if (!esFormularioDepmaeAbm() || !data || !data.id) {
            return false;
        }

        var editUrl = window.DEPMAE_EDITAR_URL || '';
        var currentId = String($('#deposito_id').val() || '').trim();
        var nuevoId = String(data.id);

        if (editUrl && nuevoId !== currentId) {
            window.location.href = editUrl.replace('__ID__', nuevoId);

            return true;
        }

        $('#deposito_id').val(data.id);
        $('#codigo').val(data.codigo || '');
        $('#nombre').val(data.descripcion || data.nombre || '');
        if (data.tipodeposito) {
            $('#tipodeposito').val(data.tipodeposito);
        }

        return false;
    };

    $(function () {
        if (!esFormularioDepmaeAbm()) {
            return;
        }
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
    });
})(jQuery);
