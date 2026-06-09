(function ($) {
    'use strict';

    function normalizarTexto(valor) {
        return String(valor || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function textoSalida(nombre, ubicacion) {
        if (!nombre) {
            return '';
        }
        return ubicacion ? nombre + ' — ' + ubicacion : nombre;
    }

    function marcarFilaSeleccionada() {
        var id = $('#salida_id').val();
        $('#modal_salida_lista tr.fila-salida-modal').removeClass('table-info');
        if (!id) {
            return;
        }
        $('#modal_salida_lista tr.fila-salida-modal[data-id="' + id + '"]').addClass('table-info');
    }

    function filtrarSalidasModal() {
        var termino = normalizarTexto($('#modal_salida_busqueda').val().trim());
        var visibles = 0;

        $('#modal_salida_lista tr.fila-salida-modal').each(function () {
            var $fila = $(this);
            var nombre = normalizarTexto($fila.data('nombre'));
            var ubicacion = normalizarTexto($fila.data('ubicacion'));
            var coincide = termino === '' || nombre.indexOf(termino) !== -1 || ubicacion.indexOf(termino) !== -1;
            $fila.toggle(coincide);
            if (coincide) {
                visibles++;
            }
        });

        $('#modal_salida_sin_resultados').toggleClass('d-none', visibles > 0);
    }

    function elegirSalida(id, nombre, ubicacion) {
        $('#salida_id').val(id);
        $('#salida_seleccionada_texto').val(textoSalida(nombre, ubicacion));

        var $hint = $('#salida_ubicacion_hint');
        if (ubicacion) {
            $hint.text('Ubicación: ' + ubicacion).removeClass('d-none');
        } else {
            $hint.addClass('d-none').text('');
        }

        $('#modalSeleccionSalida').modal('hide');
    }

    $(function () {
        $('#btn_abrir_modal_salida').on('click', function () {
            $('#modal_salida_busqueda').val('');
            filtrarSalidasModal();
            marcarFilaSeleccionada();
            $('#modalSeleccionSalida').modal('show');
        });

        $('#modalSeleccionSalida').on('shown.bs.modal', function () {
            $('#modal_salida_busqueda').trigger('focus');
        });

        $('#modal_salida_busqueda').on('input', filtrarSalidasModal);

        $(document).on('click', '.btn-elegir-salida', function () {
            var $fila = $(this).closest('tr.fila-salida-modal');
            elegirSalida($fila.data('id'), $fila.data('nombre'), $fila.data('ubicacion'));
        });

        $(document).on('dblclick', '#modal_salida_lista tr.fila-salida-modal:visible', function () {
            var $fila = $(this);
            elegirSalida($fila.data('id'), $fila.data('nombre'), $fila.data('ubicacion'));
        });
    });
})(jQuery);
