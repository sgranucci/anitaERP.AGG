
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
        if (window.seteoSalidaConfigurarUrl) {
            return window.seteoSalidaConfigurarUrl;
        }

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
        var url = urlConfigurarSalida().replace(':programa', encodeURIComponent(programa));
        var retorno = encodeURIComponent(window.location.href);

        location.href = url + '?retorno=' + retorno;
    }
