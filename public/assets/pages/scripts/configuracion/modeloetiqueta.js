var salida_id;
var nombreSalida;

$(function () {
       
});

function buscarModeloetiqueta(programa)
{
    // Actualiza configuracion de salida
    var listarUri = "/anitaERP/public/configuracion/buscarmodeloetiqueta/"+programa;

    $.get(listarUri, function(data){
        if (data.id > 0)
        {
            nombreSalida = data.modeloetiquetas.nombre;
        }

    });
}

