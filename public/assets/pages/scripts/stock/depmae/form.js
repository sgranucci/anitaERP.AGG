(function ($) {
    'use strict';

    var CODIGO_DEPOSITO_REGEX = /^[A-Za-z0-9._ -]+$/;

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

    function activarValidacionDepmaeAbm() {
        if (!esFormularioDepmaeAbm() || typeof Biblioteca === 'undefined') {
            return;
        }

        if (!$.validator.methods.codigoDepositoAlfanumerico) {
            $.validator.addMethod(
                'codigoDepositoAlfanumerico',
                function (value, element) {
                    var codigo = String(value || '').trim();

                    return this.optional(element) || CODIGO_DEPOSITO_REGEX.test(codigo);
                },
                'El código admite letras, números, espacios, punto, guión y guión bajo (máx. 10 caracteres).'
            );
        }

        Biblioteca.validacionGeneral('form-general', {
            codigo: {
                required: true,
                maxlength: 10,
                codigoDepositoAlfanumerico: true,
            },
        });
    }

    function esAltaDepmaeAbm() {
        return !document.getElementById('depmae_registro_id');
    }

    function enfocarCampoInicialDepmaeAbm() {
        var codigoEl = document.getElementById('codigo');
        var esAlta = esAltaDepmaeAbm();
        var empresaEl = document.getElementById('empresa_id');

        if (esAlta && empresaEl && String(empresaEl.tagName || '').toUpperCase() === 'SELECT') {
            empresaEl.focus();

            return;
        }

        if (esAlta && codigoEl) {
            codigoEl.focus();

            return;
        }

        if (codigoEl) {
            codigoEl.focus();

            return;
        }

        var nombreEl = document.getElementById('nombre');
        if (nombreEl) {
            nombreEl.focus();
        }
    }

    $(function () {
        activarValidacionDepmaeAbm();

        if (!esFormularioDepmaeAbm()) {
            return;
        }

        enfocarCampoInicialDepmaeAbm();

        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
    });
})(jQuery);
