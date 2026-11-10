$(function () {
    Biblioteca.validacionGeneral('form-general');
    $('#icono').on('blur', function () {
        $('#mostrar-icono').removeClass().addClass('fa fa-fw ' + $(this).val());
    });

    $('#agrega_renglon_centrocosto').on('click', agregaRenglonCentrocosto);
    $(document).on('click', '.eliminar_centrocosto', borraRenglonCentrocosto);

    if ($('#manejaccosto').val() !== 'S') {
        $('#divcentrocosto').prop('hidden', true);
    }

    $('#manejaccosto').on('change', function (event) {
        event.preventDefault();
        var manejaccosto = $(this).val();

        if (manejaccosto === 'S') {
            $('#divcentrocosto').prop('hidden', false);
            if ($('#tbody-centrocosto-table tr').length === 0) {
                agregaRenglonCentrocosto();
            } else {
                $('#tbody-centrocosto-table tr').last().find('.codigocentrocosto').trigger('focus');
            }
        } else {
            if ($('#tbody-centrocosto-table tr').length === 0 || confirm('Este cambio va a borrar los centros de costos asignados. ¿Continúa?')) {
                $('#divcentrocosto').prop('hidden', true);
                $('#tbody-centrocosto-table').empty();
            } else {
                $('#manejaccosto').val('S');
            }
        }
    });

    window.afterCentrocostoEnterOk = function (data, inputCodigo) {
        if (!data || !data.id || !inputCodigo) {
            return;
        }
        var $input = $(inputCodigo);
        if (!$input.closest('#centrocosto-table').length) {
            return;
        }
        var $siguiente = $input.closest('tr').next('tr').find('.codigocentrocosto');
        if ($siguiente.length) {
            setTimeout(function () {
                $siguiente.trigger('focus').trigger('select');
            }, 0);
            return;
        }
        var $boton = $('#form-general').find('button[type="submit"]:visible').first();
        if ($boton.length) {
            setTimeout(function () {
                $boton.trigger('focus');
            }, 0);
        }
    };
});

function agregaRenglonCentrocosto(evento) {
    if (evento && typeof evento.preventDefault === 'function') {
        evento.preventDefault();
    }
    var manejaccosto = $('#manejaccosto').val();

    if (manejaccosto === 'N') {
        alert('No permite agregar centros de costo');
        return;
    }

    var renglon = $('#template-renglon-centrocosto').html();
    $('#tbody-centrocosto-table').append(renglon);
    $('#tbody-centrocosto-table tr').last().find('.codigocentrocosto').trigger('focus');
}

function borraRenglonCentrocosto() {
    event.preventDefault();
    $(this).parents('tr').remove();
}

