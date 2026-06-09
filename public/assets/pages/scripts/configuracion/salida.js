var salida_id;
var nombreSalida;
var comandoSalida;

$(function () {

});

function textoImpresoraSeteada(salida)
{
    if (!salida || !salida.nombre) {
        return '';
    }

    if (salida.ubicacion) {
        return salida.nombre + ' (' + salida.ubicacion + ')';
    }

    return salida.nombre;
}

function buscarSalida(programa)
{
    var codigo = programa || '';
    var listarUri = carpetaBase + '/configuracion/buscarsalida/' + encodeURIComponent(codigo);

    $.get(listarUri, function(data){
        if (data.id > 0 && data.salidas) {
            nombreSalida = textoImpresoraSeteada(data.salidas);
        } else {
            nombreSalida = (data.salidas && data.salidas.nombre) ? data.salidas.nombre : 'Sin impresora seteada';
        }
    });
}
