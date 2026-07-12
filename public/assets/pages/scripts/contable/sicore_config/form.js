$(function () {
    function actualizarCamposPorCriterio() {
        var criterio = $('#criterio').val();
        var esSueldos = criterio === 'sueldos';

        $('#row-sueldos-conceptos').toggleClass('d-none', !esSueldos);
        $('#concepto_retencion_sueldos').prop('required', esSueldos);

        if (esSueldos && !$('#codigo_impuesto').val()) {
            $('#codigo_impuesto').val(787);
        }
        if (esSueldos && !$('#codigo_regimen').val()) {
            $('#codigo_regimen').val(160);
        }
        if (criterio === 'ventas_perc_iva' || criterio === 'ventas_perc_no_categ') {
            if (!$('#codigo_impuesto').val()) {
                $('#codigo_impuesto').val(767);
            }
            $('#codigo_operacion').val(2);
        }
        if (criterio === 'compras_ganancias' && !$('#codigo_impuesto').val()) {
            $('#codigo_impuesto').val(217);
        }
        if (criterio === 'compras_iva' && !$('#codigo_impuesto').val()) {
            $('#codigo_impuesto').val(767);
        }
    }

    $('#criterio').on('change', actualizarCamposPorCriterio);
    actualizarCamposPorCriterio();

    $('#agrega_renglon_cuentacontable').on('click', function () {
        var tpl = document.getElementById('template-cuenta-sicore');
        if (!tpl || !tpl.content) {
            return;
        }
        var clone = document.importNode(tpl.content, true);
        $('#tbody-cuentacontable-table').append(clone);
        if (typeof activa_eventos_consultacuentacontable === 'function') {
            activa_eventos_consultacuentacontable();
        }
    });

    $(document).on('click', '.eliminar_cuentacontable', function () {
        var $rows = $('#tbody-cuentacontable-table tr.item-cuentacontable');
        if ($rows.length <= 1) {
            $(this).closest('tr').find('input').val('');
            return;
        }
        $(this).closest('tr').remove();
    });

    if (typeof activa_eventos_consultacuentacontable === 'function') {
        activa_eventos_consultacuentacontable();
    }
});
