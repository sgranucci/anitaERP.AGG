/* global activa_eventos_consultatarea, activa_eventos_consultaempleado */
(function () {
    'use strict';

    function esEnter(e) {
        return e.key === 'Enter' || e.keyCode === 13;
    }

    function enfocar(selector) {
        var $el = $(selector).first();
        if ($el.length) {
            $el.trigger('focus');
        }
    }

    function pintarOperacion() {
        $('#movot-operacion .movot-etiqueta').each(function () {
            var activa = $(this).find('input').prop('checked');
            $(this)
                .toggleClass('btn-primary is-active', activa)
                .toggleClass('btn-outline-secondary', !activa);
        });
    }

    function enfocarOperacion() {
        var checked = document.querySelector('#movot-operacion input:checked');
        var target = checked || document.querySelector('#movot-operacion input');
        if (target) {
            target.focus();
        }
    }

    $(function () {
        if (typeof activa_eventos_consultatarea === 'function') {
            activa_eventos_consultatarea();
        }
        if (typeof activa_eventos_consultaempleado === 'function') {
            activa_eventos_consultaempleado();
        }

        pintarOperacion();

        $('#movot-operacion').on('change', 'input', pintarOperacion);

        $('#movot-operacion').on('click', '.movot-etiqueta', function () {
            var input = this.querySelector('input');
            if (input) {
                input.checked = true;
            }
            pintarOperacion();
            setTimeout(function () {
                enfocar('#empleado_movot_id_codigo');
            }, 0);
        });

        $('#movot-operacion-foco').on('focus', function () {
            enfocarOperacion();
        });

        $('#movot-operacion').on('keydown', 'input', function (e) {
            if (!esEnter(e)) {
                return;
            }
            e.preventDefault();
            enfocar('#empleado_movot_id_codigo');
        });

        $('#fecha').on('keydown', function (e) {
            if (!esEnter(e)) {
                return;
            }
            e.preventDefault();
            enfocar('#tarea_movot_id_codigo');
        });

        $('#ordenestrabajo').on('keydown', function (e) {
            if (!esEnter(e)) {
                return;
            }
            e.preventDefault();
            enfocar('#form-general .botonsubmit');
        });

        $('#tarea_movot_id_nombre').on('keydown', function (e) {
            if (!esEnter(e)) {
                return;
            }
            e.preventDefault();
            enfocarOperacion();
        });

        $('#empleado_movot_id_nombre').on('keydown', function (e) {
            if (!esEnter(e)) {
                return;
            }
            e.preventDefault();
            enfocar('#ordenestrabajo');
        });

        var foco = ($('#form-general').attr('data-foco-inicial') || '').trim();
        if (foco) {
            enfocar(foco);
        }
    });
})();
