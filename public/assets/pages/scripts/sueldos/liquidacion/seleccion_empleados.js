/* global carpetaBase */
(function ($) {
    'use strict';

    function bloque() {
        return $('#bloque-seleccion-empleados');
    }

    function modoSeleccion() {
        return $('input[name="empleados_modo"]:checked').val() === 'seleccion';
    }

    function syncModo() {
        $('#panel-empleados-seleccion').toggle(modoSeleccion());
        actualizarContadores();
        actualizarHintTipo();
    }

    function normSearch(s) {
        return String(s || '').toLowerCase().trim();
    }

    function empSearchKey(emp) {
        return normSearch((emp.legajo != null ? emp.legajo : '') + ' ' + (emp.nombre || ''));
    }

    function egresoTxt(emp) {
        return emp.fecha_egreso_fmt || emp.fecha_egreso || '—';
    }

    function idsSeleccionados() {
        var ids = {};
        $('#tbody-seleccionados-liq tr[data-empleado-id]').each(function () {
            ids[String($(this).data('empleado-id'))] = true;
        });
        return ids;
    }

    function idsCandidatos() {
        var ids = {};
        $('#tbody-candidatos-liq tr[data-empleado-id]').each(function () {
            ids[String($(this).data('empleado-id'))] = true;
        });
        return ids;
    }

    function actualizarVacios() {
        $('#vacio-candidatos-liq').toggle($('#tbody-candidatos-liq tr').length === 0);
        $('#vacio-seleccionados-liq').toggle($('#tbody-seleccionados-liq tr').length === 0);
    }

    function actualizarContadores() {
        var nCand = $('#tbody-candidatos-liq tr').length;
        var nCandVis = $('#tbody-candidatos-liq tr:visible').length;
        var nSel = $('#tbody-seleccionados-liq tr').length;
        var nSelVis = $('#tbody-seleccionados-liq tr:visible').length;
        $('#contador-candidatos-liq').text(
            nCand && nCandVis !== nCand ? (nCandVis + '/' + nCand) : String(nCand)
        );
        $('#contador-seleccionados-liq').text(
            nSel && nSelVis !== nSel ? (nSelVis + '/' + nSel) : String(nSel)
        );
        actualizarVacios();
    }

    function aplicarFiltro($tbody, query) {
        var q = normSearch(query);
        $tbody.find('tr').each(function () {
            var hay = !q || String($(this).attr('data-search') || '').indexOf(q) !== -1;
            $(this).toggle(hay);
        });
        actualizarContadores();
    }

    function filtrarCandidatos() {
        aplicarFiltro($('#tbody-candidatos-liq'), $('#filtro-candidatos-liq').val());
    }

    function filtrarSeleccionados() {
        aplicarFiltro($('#tbody-seleccionados-liq'), $('#filtro-seleccionados-liq').val());
    }

    function filaCandidato(emp) {
        var id = parseInt(emp.id, 10);
        var egreso = egresoTxt(emp);
        var tr = $('<tr/>')
            .attr('data-empleado-id', id)
            .attr('data-legajo', emp.legajo != null ? emp.legajo : '')
            .attr('data-nombre', emp.nombre || '')
            .attr('data-egreso', egreso)
            .attr('data-search', empSearchKey(emp));
        tr.append($('<td/>').text(emp.legajo != null ? emp.legajo : ''));
        tr.append($('<td/>').text(emp.nombre || ''));
        tr.append($('<td/>').text(egreso));
        tr.append($('<td class="text-center"/>').append(
            $('<button type="button" class="btn btn-xs btn-outline-primary btn-pasar-empleado-liq" title="Pasar a seleccionados"/>')
                .html('<i class="fa fa-angle-right"></i>')
        ));
        return tr;
    }

    function filaSeleccionado(emp) {
        var id = parseInt(emp.id, 10);
        var egreso = egresoTxt(emp);
        var tr = $('<tr/>')
            .attr('data-empleado-id', id)
            .attr('data-legajo', emp.legajo != null ? emp.legajo : '')
            .attr('data-nombre', emp.nombre || '')
            .attr('data-egreso', egreso)
            .attr('data-search', empSearchKey(emp));
        tr.append(
            $('<td/>')
                .text(emp.legajo != null ? emp.legajo : '')
                .append($('<input type="hidden" name="empleado_ids[]"/>').val(id))
        );
        tr.append($('<td/>').text(emp.nombre || ''));
        tr.append($('<td/>').text(egreso));
        tr.append($('<td class="text-center"/>').append(
            $('<button type="button" class="btn btn-xs btn-outline-secondary btn-devolver-empleado-liq" title="Devolver a candidatos"/>')
                .html('<i class="fa fa-angle-left"></i>')
        ));
        return tr;
    }

    function empDesdeTr($tr) {
        return {
            id: parseInt($tr.attr('data-empleado-id'), 10),
            legajo: $tr.attr('data-legajo'),
            nombre: $tr.attr('data-nombre'),
            fecha_egreso_fmt: $tr.attr('data-egreso')
        };
    }

    function agregarCandidato(emp) {
        var id = parseInt(emp.id, 10);
        if (!id) {
            return;
        }
        if (idsSeleccionados()[String(id)] || idsCandidatos()[String(id)]) {
            return;
        }
        $('#tbody-candidatos-liq').append(filaCandidato(emp));
        filtrarCandidatos();
    }

    function agregarSeleccionado(emp) {
        var id = parseInt(emp.id, 10);
        if (!id) {
            return;
        }
        // Si estaba en candidatos, lo saco
        $('#tbody-candidatos-liq tr[data-empleado-id="' + id + '"]').remove();
        if (idsSeleccionados()[String(id)]) {
            filtrarCandidatos();
            filtrarSeleccionados();
            return;
        }
        $('#tbody-seleccionados-liq').append(filaSeleccionado(emp));
        $('input[name="empleados_modo"][value="seleccion"]').prop('checked', true);
        syncModo();
        filtrarCandidatos();
        filtrarSeleccionados();
    }

    function pasarTrASeleccionado($tr) {
        if (!$tr.length) {
            return;
        }
        agregarSeleccionado(empDesdeTr($tr));
    }

    function devolverTrACandidato($tr) {
        if (!$tr.length) {
            return;
        }
        var emp = empDesdeTr($tr);
        $tr.remove();
        if (!idsCandidatos()[String(emp.id)]) {
            $('#tbody-candidatos-liq').append(filaCandidato(emp));
        }
        filtrarCandidatos();
        filtrarSeleccionados();
    }

    function paramsCandidatos() {
        var empresa = $('input[name="empresa_id"]').val() || $('#empresa_id').val() || '';
        return {
            empresa_id: empresa,
            tipo: $('#tipo_liquidacion').val() || 'mensual',
            periodo: $('input[name="periodo"]').val() || '',
            periodo_desde: $('input[name="periodo_desde"]').val() || '',
            periodo_hasta: $('input[name="periodo_hasta"]').val() || '',
            motivoegreso_id: $('input[name="motivoegreso_id"]').val() || ''
        };
    }

    function actualizarHintTipo() {
        var tipo = ($('#tipo_liquidacion').val() || 'mensual').toLowerCase();
        var txt = tipo === 'final'
            ? 'Tipo final → candidatos = egresos del período'
            : 'Tipo ' + tipo + ' → candidatos = activos';
        $('#hint-tipo-candidatos-liq').text(txt);
    }

    function cargarCandidatos() {
        var url = bloque().attr('data-url-candidatos');
        if (!url) {
            return;
        }
        var p = paramsCandidatos();
        if (!p.empresa_id) {
            window.alert('Seleccioná la empresa antes de cargar candidatos.');
            return;
        }
        if (p.tipo === 'final' && !p.periodo && !p.periodo_desde) {
            window.alert('Completá el período (o la ventana de egreso) antes de cargar.');
            return;
        }
        var $btn = $('#btn-cargar-candidatos-liq').prop('disabled', true);
        $.getJSON(url, p)
            .done(function (resp) {
                var data = (resp && resp.data) ? resp.data : [];
                if (!data.length) {
                    window.alert(p.tipo === 'final'
                        ? 'No hay egresos en ese período/empresa.'
                        : 'No hay empleados activos para esa empresa.');
                    return;
                }
                // Reemplaza el pool de candidatos; no toca los ya seleccionados.
                $('#tbody-candidatos-liq').empty();
                data.forEach(function (emp) {
                    var id = String(parseInt(emp.id, 10));
                    if (!idsSeleccionados()[id]) {
                        agregarCandidato(emp);
                    }
                });
                $('input[name="empleados_modo"][value="seleccion"]').prop('checked', true);
                syncModo();
                filtrarCandidatos();
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudieron cargar candidatos.';
                window.alert(msg);
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    function avisoTipoVsDescripcion() {
        var tipo = ($('#tipo_liquidacion').val() || '').toLowerCase();
        var desc = (($('input[name="descripcion"]').val() || '') + '').toUpperCase();
        var $aviso = $('#aviso-tipo-final-desc');
        if (!$aviso.length) {
            $aviso = $('<div id="aviso-tipo-final-desc" class="alert alert-warning py-1 px-2 small mt-1" style="display:none"/>');
            $('#tipo_liquidacion').closest('.form-group').append($aviso);
        }
        if (tipo !== 'final' && /FINAL|LIQ\.?\s*FINAL|LIQUIDACI[OÓ]N\s+FINAL/.test(desc)) {
            $aviso.html('La descripción parece una liquidación final, pero el <strong>tipo</strong> no es «Liquidacion final». '
                + 'Si dejás tipo Mensual, se liquidan todos los activos.').show();
        } else {
            $aviso.hide();
        }
        actualizarHintTipo();
    }

    $(function () {
        if (!bloque().length) {
            return;
        }

        $(document).on('change', 'input[name="empleados_modo"]', syncModo);

        $(document).on('click', '.btn-pasar-empleado-liq', function () {
            pasarTrASeleccionado($(this).closest('tr'));
        });
        $(document).on('click', '.btn-devolver-empleado-liq', function () {
            devolverTrACandidato($(this).closest('tr'));
        });
        // Doble click = pasar / devolver
        $(document).on('dblclick', '#tbody-candidatos-liq tr', function () {
            pasarTrASeleccionado($(this));
        });
        $(document).on('dblclick', '#tbody-seleccionados-liq tr', function () {
            devolverTrACandidato($(this));
        });

        $('#btn-cargar-candidatos-liq').on('click', cargarCandidatos);

        $('#btn-pasar-visibles-liq').on('click', function () {
            $('#tbody-candidatos-liq tr:visible').each(function () {
                pasarTrASeleccionado($(this));
            });
        });
        $('#btn-pasar-todos-cand-liq').on('click', function () {
            $('#tbody-candidatos-liq tr').each(function () {
                pasarTrASeleccionado($(this));
            });
        });
        $('#btn-devolver-visibles-liq').on('click', function () {
            $('#tbody-seleccionados-liq tr:visible').each(function () {
                devolverTrACandidato($(this));
            });
        });
        $('#btn-quitar-todos-sel-liq').on('click', function () {
            $('#tbody-seleccionados-liq tr').each(function () {
                devolverTrACandidato($(this));
            });
        });

        $('#filtro-candidatos-liq').on('input', filtrarCandidatos);
        $('#filtro-seleccionados-liq').on('input', filtrarSeleccionados);

        function campoAgregar() {
            return $('.tm-empleado-sueldos-campo').filter(function () {
                return $(this).find('#liq_sel_empleado_id').length;
            }).first();
        }

        function tomarEmpleadoDelCampo() {
            var $campo = campoAgregar();
            if (!$campo.length) {
                return;
            }
            var id = parseInt($campo.find('#liq_sel_empleado_id').val() || '0', 10);
            if (!id) {
                return;
            }
            agregarSeleccionado({
                id: id,
                legajo: $campo.find('.codigoempleado_sueldos').val(),
                nombre: $campo.find('.nombreempleado_sueldos').val(),
                fecha_egreso_fmt: '—'
            });
            $campo.find('#liq_sel_empleado_id').val('');
            $campo.find('.codigoempleado_sueldos').val('');
            $campo.find('.nombreempleado_sueldos').val('');
        }

        $('#btn-agregar-empleado-liq').on('click', function () {
            var $campo = campoAgregar();
            if (!$campo.length) {
                return;
            }
            $campo.find('.consultaempleado_sueldos').trigger('click');
        });

        $(document).on('click', '.eligeconsultaempleado_sueldos', function () {
            window.setTimeout(tomarEmpleadoDelCampo, 50);
        });
        $('#consultaempleado_sueldosModal').on('hidden.bs.modal', function () {
            window.setTimeout(tomarEmpleadoDelCampo, 50);
        });

        $('#tipo_liquidacion, input[name="descripcion"]').on('change input', avisoTipoVsDescripcion);
        syncModo();
        avisoTipoVsDescripcion();
        actualizarContadores();
    });
})(jQuery);
