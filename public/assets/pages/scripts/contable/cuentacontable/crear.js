$(function () {
    Biblioteca.validacionGeneral('form-general');
    $('#icono').on('blur', function () {
        $('#mostrar-icono').removeClass().addClass('fa fa-fw ' + $(this).val());
    });

    $('#agrega_renglon_centrocosto').on('click', agregaRenglonCentrocosto);
    $(document).on('click', '.eliminar_centrocosto', borraRenglonCentrocosto);

    $('#manejaccosto').on('change', function (event) {
        event.preventDefault();
        var manejaccosto = $(this).val();

        if (manejaccosto === 'S')
        {
            $('#divcentrocosto').prop('hidden', false);
        }
        else
        {
            if (confirm("Este cambio va a borrar los centros de costos que tiene asignados, continua?")) {
                $('#divcentrocosto').prop('hidden', true);
                $('#tbody-centrocosto-table').empty();
            }
            else
                $('#manejaccosto').val('S');
        }
    });
});

function agregaRenglonCentrocosto(){
    event.preventDefault();
    let manejaccosto = $('#manejaccosto').val();

    if (manejaccosto === 'N')
        alert("No permite agregar centros de costo");
    else
    {
        var renglon = $('#template-renglon-centrocosto').html();
        $("#tbody-centrocosto-table").append(renglon);
    }
}

function borraRenglonCentrocosto() {
    event.preventDefault();
    $(this).parents('tr').remove();
}

