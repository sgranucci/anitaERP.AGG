var salida_id;
var nombreSalida;
var comandoSalida;

$(function () {
       
});

function buscarSalida(programa)
{
    // Actualiza configuracion de salida
    var listarUri = carpetaBase+"/configuracion/buscarsalida/"+programa;

    $.get(listarUri, function(data){
        if (data.id > 0)
        {
            nombreSalida = data.salidas.nombre;
        }

    });
}

