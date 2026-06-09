function codigoSeteoSalida()
{
    if (typeof window.resolverSeteoSalidaPrograma === 'function') {
        return String(window.resolverSeteoSalidaPrograma());
    }

    if (typeof window.seteoSalidaPrograma !== 'undefined' && window.seteoSalidaPrograma !== null) {
        return String(window.seteoSalidaPrograma);
    }

    return '';
}

function urlConfigurarSalida()
{
    return carpetaBase + '/configuracion/configurarsalida/:programa';
}

$(function () {
    imprimirSalida();
    setTimeout(function () {
        imprimirSalida();
    }, 300);
});

function imprimirSalida()
{
    buscarSalida(codigoSeteoSalida());

    setTimeout(function () {
        var texto = nombreSalida || 'Sin impresora seteada';
        $("#nombresalida").text(" - Imprime en: " + texto);
    }, 300);
}

function configurarSalida()
{
    var programa = codigoSeteoSalida();
    if (!programa) {
        alert('No se pudo determinar el programa de impresión para esta pantalla.');
        return false;
    }

    var url = urlConfigurarSalida().replace(':programa', encodeURIComponent(programa));
    var retorno = encodeURIComponent(window.location.href);

    window.location.href = url + '?retorno=' + retorno;

    return false;
}
