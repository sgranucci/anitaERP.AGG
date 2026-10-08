/* global jQuery */
(function ($) {
    'use strict';

    function esEnter(e) {
        return e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13;
    }

    function modalConsultaAbierto() {
        return $('#consultaempleado_sueldosModal').hasClass('show')
            || $('#consultaconcepto_sueldosModal').hasClass('show');
    }

    function esCampoConsulta(el) {
        return el.classList.contains('codigoempleado_sueldos')
            || el.classList.contains('codigoconcepto_sueldos');
    }

    function camposEditables($form) {
        return $form.find('input, select, textarea').filter(function () {
            var $el = $(this);
            if (!$el.is(':visible') || $el.is(':disabled') || $el.prop('readonly')) {
                return false;
            }
            var type = String($el.attr('type') || '').toLowerCase();
            return type !== 'hidden' && type !== 'submit' && type !== 'button' && type !== 'reset' && type !== 'file';
        });
    }

    function focusSiguiente(desde) {
        var $desde = $(desde);
        var $form = $desde.closest('#form-general');
        if (!$form.length) {
            return;
        }
        var $lista = camposEditables($form);
        var indice = $lista.index($desde);
        if (indice < 0) {
            return;
        }

        setTimeout(function () {
            if (indice >= $lista.length - 1) {
                var $boton = $form.find('button[type="submit"], input[type="submit"]').filter(':visible').first();
                if ($boton.length) {
                    $boton.trigger('focus');
                }
                return;
            }
            var $siguiente = $lista.eq(indice + 1);
            $siguiente.trigger('focus');
            if ($siguiente.is('input, textarea')) {
                var nodo = $siguiente.get(0);
                if (nodo && typeof nodo.select === 'function') {
                    nodo.select();
                }
            }
        }, 0);
    }

    function validarCampo(el) {
        if (el.id === 'fecha_hasta') {
            var desde = document.getElementById('fecha_desde');
            if (desde && desde.value && el.value && el.value < desde.value) {
                el.setCustomValidity('Vigente hasta no puede ser anterior a vigente desde.');
            } else {
                el.setCustomValidity('');
            }
        }
        if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
            if (typeof el.reportValidity === 'function') {
                el.reportValidity();
            }
            return false;
        }
        return true;
    }

    function avisarConceptoObligatorio() {
        var codigo = document.getElementById('concepto_sueldos_id_codigo');
        if (!codigo) {
            return;
        }
        codigo.setCustomValidity('Indicá el concepto.');
        if (typeof codigo.reportValidity === 'function') {
            codigo.reportValidity();
        }
        codigo.setCustomValidity('');
        codigo.focus();
    }

    window.addEventListener('keydown', function (e) {
        if (!esEnter(e) || !e.target || !e.target.closest) {
            return;
        }
        if (!e.target.closest('#form-general')) {
            return;
        }
        if (!e.target.classList || !e.target.classList.contains('codigoconcepto_sueldos')) {
            return;
        }
        window.__novedadAvanzarTrasConcepto = true;
        window.__novedadConceptoEnterVacio = String(e.target.value || '').trim() === '';
    }, true);

    $(function () {
        var $form = $('#form-general');
        if (!$form.length || !$form.find('#valor1').length) {
            return;
        }

        $form.on('keydown.novedadEnter', 'input, select, textarea', function (e) {
            if (!esEnter(e) || modalConsultaAbierto()) {
                return;
            }
            if (esCampoConsulta(this) || this.readOnly || this.disabled) {
                return;
            }
            var type = String(this.type || '').toLowerCase();
            if (type === 'submit' || type === 'button' || type === 'hidden') {
                return;
            }
            e.preventDefault();
            if (!validarCampo(this)) {
                return;
            }
            focusSiguiente(this);
        });

        $(document).on('change.novedadConcepto', '#form-general #concepto_sueldos_id', function () {
            if (!window.__novedadAvanzarTrasConcepto) {
                return;
            }
            var id = String($(this).val() || '').trim();
            var codigo = String($('#concepto_sueldos_id_codigo').val() || '').trim();
            if (id) {
                window.__novedadAvanzarTrasConcepto = false;
                window.__novedadConceptoEnterVacio = false;
                focusSiguiente(document.getElementById('concepto_sueldos_id_codigo'));
                return;
            }
            if (window.__novedadConceptoEnterVacio && codigo === '') {
                window.__novedadAvanzarTrasConcepto = false;
                window.__novedadConceptoEnterVacio = false;
                avisarConceptoObligatorio();
                return;
            }
            if (codigo === '') {
                window.__novedadAvanzarTrasConcepto = false;
            }
        });

        $(document).on('click.novedadConceptoElegir', '.eligeconsultaconcepto_sueldos', function () {
            if ($('#form-general .tm-concepto-sueldos-campo').length) {
                window.__novedadAvanzarTrasConcepto = true;
                window.__novedadConceptoEnterVacio = false;
            }
        });

        $('#fecha_desde, #fecha_hasta, #concepto_sueldos_id_codigo').on('input.novedadEnter', function () {
            if (typeof this.setCustomValidity === 'function') {
                this.setCustomValidity('');
            }
        });
    });
})(jQuery);
