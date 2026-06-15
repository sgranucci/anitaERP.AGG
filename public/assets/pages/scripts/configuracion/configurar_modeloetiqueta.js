function codigoSeteoModeloEtiqueta()
{
    if (typeof window.resolverSeteoModeloEtiquetaPrograma === 'function') {
        return String(window.resolverSeteoModeloEtiquetaPrograma());
    }

    if (typeof window.seteoModeloEtiquetaPrograma !== 'undefined' && window.seteoModeloEtiquetaPrograma !== null) {
        return String(window.seteoModeloEtiquetaPrograma);
    }

    return '';
}

function urlConfigurarModeloEtiqueta()
{
    return carpetaBase + '/configuracion/configurarmodeloetiqueta/:programa';
}

$(function () {
    imprimirModeloEtiqueta();
    setTimeout(function () {
        imprimirModeloEtiqueta();
    }, 300);
});

function imprimirModeloEtiqueta()
{
    buscarModeloEtiqueta(codigoSeteoModeloEtiqueta());

    setTimeout(function () {
        var texto = nombreModeloEtiqueta || 'Sin modelo de etiqueta seteado';
        $('#nombremodeloetiqueta').text(' - Usa etiqueta: ' + texto);
    }, 300);
}

function configurarModeloEtiqueta()
{
    var programa = codigoSeteoModeloEtiqueta();
    if (!programa) {
        alert('No se pudo determinar el programa de etiqueta para esta pantalla.');
        return false;
    }

    var url = urlConfigurarModeloEtiqueta().replace(':programa', encodeURIComponent(programa));
    var retorno = encodeURIComponent(window.location.href);

    window.location.href = url + '?retorno=' + retorno;

    return false;
}

