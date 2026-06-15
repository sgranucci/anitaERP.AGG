var nombreModeloEtiqueta;

function buscarModeloEtiqueta(programa)
{
    var codigo = programa || '';
    var listarUri = carpetaBase + '/configuracion/buscarmodeloetiqueta/' + encodeURIComponent(codigo);

    $.get(listarUri, function (data) {
        if (data.id > 0 && data.modeloetiquetas) {
            nombreModeloEtiqueta = data.modeloetiquetas.nombre;
        } else {
            nombreModeloEtiqueta = (data.modeloetiquetas && data.modeloetiquetas.nombre)
                ? data.modeloetiquetas.nombre
                : 'Sin modelo de etiqueta seteado';
        }
    }).fail(function () {
        nombreModeloEtiqueta = 'Sin modelo de etiqueta seteado';
    });
}

function buscarModeloetiqueta(programa)
{
    buscarModeloEtiqueta(programa);
}
