$(function()
{
    $('#agrega_renglon_costo').on('click', agregaRenglonCosto);
    $('.eliminarCosto').on('click', borraRenglonCosto);
});

function agregaRenglonCosto() 
{
    if (event != undefined)
        event.preventDefault();

    var renglon = $('#template-renglon-costo').html();

    $("#costos_table").append(renglon);

    // Reactiva eventos
    $('.eliminarCosto').off('click');
    $('.eliminarCosto').on('click', borraRenglonCosto);
}

function borraRenglonCosto() 
{
    event.preventDefault();
    if (confirm("Desea borrar renglon?"))
    {
        $(this).parents('tr').remove();
    }
}

