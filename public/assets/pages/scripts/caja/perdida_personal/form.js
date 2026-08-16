(function ($) {
    'use strict';

    function conceptosConMaquina() {
        var cfg = window.perdidaPersonalConceptosMaquina;
        if (Array.isArray(cfg) && cfg.length) {
            return cfg.map(function (n) { return parseInt(n, 10); });
        }
        return [6, 8];
    }

    /**
     * Tras Enter (o elegir en modal) en un campo de consulta, pasa el foco
     * al siguiente input/select editable del formulario.
     */
    function focusSiguienteCampoPerdidaPersonal(desde) {
        var $desde = $(desde);
        if (!$desde.length) {
            return;
        }
        var $form = $desde.closest('#form-general');
        if (!$form.length) {
            $form = $desde.closest('form');
        }
        if (!$form.length) {
            return;
        }

        var $focusables = $form
            .find('input, select, textarea')
            .filter(function () {
                var $el = $(this);
                if (!$el.is(':visible')) {
                    return false;
                }
                if ($el.is(':disabled') || $el.prop('readonly')) {
                    return false;
                }
                var type = String($el.attr('type') || '').toLowerCase();
                if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') {
                    return false;
                }
                return true;
            });

        var idx = $focusables.index($desde);
        if (idx < 0 || idx >= $focusables.length - 1) {
            return;
        }

        setTimeout(function () {
            var $next = $focusables.eq(idx + 1);
            $next.trigger('focus');
            if ($next.is('input:not([type=checkbox]):not([type=radio]), textarea')) {
                $next.trigger('select');
            }
        }, 0);
    }

    window.focusSiguienteCampoPerdidaPersonal = focusSiguienteCampoPerdidaPersonal;

    function actualizarMaquina() {
        var $concepto = $('#concepto_perdida_id');
        var $maq = $('#maquina');
        var $fila = $('#perdida-personal-campo-maquina');
        if (!$concepto.length || !$maq.length) {
            return;
        }
        var codigo = parseInt($concepto.attr('data-codigo') || $concepto.data('codigo') || '0', 10);
        var requiere = conceptosConMaquina().indexOf(codigo) !== -1;
        if (requiere) {
            $fila.removeClass('d-none');
            $maq.prop('disabled', false);
            $maq.attr('required', 'required');
        } else {
            $fila.addClass('d-none');
            $maq.prop('disabled', true);
            $maq.removeAttr('required');
            $maq.val('');
        }
    }

    $(function () {
        if (!$('#form-general').length || !$('#concepto_perdida_id').length) {
            return;
        }

        // Centro de costo: Enter exitoso → siguiente campo (hook desde consulta.js)
        window.afterCentrocostoEnterOk = function (data, input) {
            if (!data || !data.id || !input) {
                return;
            }
            if (!$(input).closest('#form-general').length) {
                return;
            }
            focusSiguienteCampoPerdidaPersonal(input);
        };

        $(document).on('change', '#empresa_id', function () {
            if (typeof window.limpiarCatalogosPerdidaPersonalPorEmpresa === 'function') {
                window.limpiarCatalogosPerdidaPersonalPorEmpresa();
            }
            if (typeof window.aplicarImputacionDefaultPerdidaPersonal === 'function') {
                window.aplicarImputacionDefaultPerdidaPersonal();
            }
            actualizarMaquina();
        });

        $('#concepto_perdida_id').on('change', function () {
            actualizarMaquina();
        });

        // Campos disabled no se envían: habilitar máquina al submit (vacía si no aplica).
        $('#form-general').on('submit', function () {
            actualizarMaquina();
            $('#maquina').prop('disabled', false);
        });

        actualizarMaquina();
    });
})(jQuery);
