(function ($) {
    'use strict';

    function renumerarFilas() {
        $('#tabla-tramos-escala tbody .fila-tramo').each(function (idx) {
            $(this).find('.nro-tramo').text(idx + 1);
            $(this).find('input').each(function () {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/tramos\[\d+\]/, 'tramos[' + idx + ']'));
                }
            });
        });
    }

    function plantillaFila(idx) {
        return '<tr class="fila-tramo">' +
            '<td class="text-center align-middle nro-tramo">' + (idx + 1) + '</td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tramos[' + idx + '][desde]" required value="0"/></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tramos[' + idx + '][hasta]" placeholder="En adelante"/></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tramos[' + idx + '][fijo]" required value="0"/></td>' +
            '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="tramos[' + idx + '][alicuota]" required value="0"/></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tramos[' + idx + '][excedente]" required value="0"/></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-tramo" title="Quitar tramo"><i class="fa fa-times"></i></button></td>' +
            '</tr>';
    }

    $(function () {
        if (!$('#tabla-tramos-escala').length) {
            return;
        }

        $('#btn-agregar-tramo').on('click', function () {
            var idx = $('#tabla-tramos-escala tbody .fila-tramo').length;
            $('#tabla-tramos-escala tbody').append(plantillaFila(idx));
        });

        $('#tabla-tramos-escala').on('click', '.btn-quitar-tramo', function () {
            var $filas = $('#tabla-tramos-escala tbody .fila-tramo');
            if ($filas.length <= 1) {
                return;
            }
            $(this).closest('tr').remove();
            renumerarFilas();
        });
    });
})(jQuery);
