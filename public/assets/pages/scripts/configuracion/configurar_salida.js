
    $(function () {

        imprimirSalida();

    });

    function imprimirSalida()
    {
        buscarSalida("");

        setTimeout(() => {
            $("#nombresalida").text(" - Imprime en: "+nombreSalida);
        }, 300);
    }
    
    function configurarSalida()
    {
        var programa = "";

        url = url.replace(':programa', programa);
        location.href = url;
    }
