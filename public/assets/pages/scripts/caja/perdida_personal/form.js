(function ($) {
    'use strict';

    function conceptosConMaquina() {
        var cfg = window.perdidaPersonalConceptosMaquina;
        if (Array.isArray(cfg) && cfg.length) {
            return cfg.map(function (n) { return parseInt(n, 10); });
        }
        return [6, 8];
    }

    function tokenCsrf() {
        var $t = $('input[name="_token"]').first();
        return $t.length ? $t.val() : '';
    }

    function urlEmpleados() {
        var base = window.perdidaPersonalEmpleadosUrl || '';
        if (base) {
            return base;
        }
        var carpeta = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase)
            ? String(window.carpetaBase).replace(/\/$/, '')
            : '';
        return carpeta + '/caja/perdida-personal/empleados-empresa';
    }

    function leerEmpresaId() {
        var $el = $('#empresa_id');
        if (!$el.length) {
            return 0;
        }
        return parseInt($el.val(), 10) || 0;
    }

    function rellenarSelectEmpleados($select, items, selectedId) {
        var html = '<option value="">-- Elija --</option>';
        (items || []).forEach(function (item) {
            var sel = (selectedId && parseInt(item.id, 10) === parseInt(selectedId, 10)) ? ' selected' : '';
            html += '<option value="' + item.id + '"' + sel + '>'
                + item.legajo + ' — ' + $('<div>').text(item.nombre || '').html()
                + '</option>';
        });
        $select.html(html);
    }

    function cargarEmpleados(limpiarSeleccion) {
        var empresaId = leerEmpresaId();
        var $emp = $('#empleado_sueldos_id');
        var $sup = $('#supervisor_empleado_sueldos_id');
        if (!$emp.length || !$sup.length) {
            return;
        }

        var prevEmp = limpiarSeleccion ? 0 : ($emp.val() || 0);
        var prevSup = limpiarSeleccion ? 0 : ($sup.val() || 0);

        if (empresaId <= 0) {
            rellenarSelectEmpleados($emp, [], 0);
            rellenarSelectEmpleados($sup, [], 0);
            return;
        }

        $.ajax({
            url: urlEmpleados(),
            method: 'GET',
            dataType: 'json',
            data: {
                empresa_id: empresaId,
                _token: tokenCsrf()
            }
        }).done(function (data) {
            var items = Array.isArray(data) ? data : [];
            rellenarSelectEmpleados($emp, items, prevEmp);
            rellenarSelectEmpleados($sup, items, prevSup);
        }).fail(function () {
            rellenarSelectEmpleados($emp, [], 0);
            rellenarSelectEmpleados($sup, [], 0);
        });
    }

    function actualizarMaquina() {
        var $concepto = $('#concepto_perdida_id');
        var $maq = $('#maquina');
        if (!$concepto.length || !$maq.length) {
            return;
        }
        var codigo = parseInt($concepto.find('option:selected').attr('data-codigo') || '0', 10);
        var requiere = conceptosConMaquina().indexOf(codigo) !== -1;
        if (requiere) {
            $maq.prop('disabled', false);
            $maq.attr('required', 'required');
        } else {
            $maq.prop('disabled', true);
            $maq.removeAttr('required');
            $maq.val('');
        }
    }

    $(function () {
        if (!$('#form-general').length || !$('#concepto_perdida_id').length) {
            return;
        }

        $(document).on('change', '#empresa_id', function () {
            cargarEmpleados(true);
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
