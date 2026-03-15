    var nombreModeloEtiqueta;

    $(function () {

        //setInterval(imprimirModeloEtiqueta, 2000);

        imprimirModeloEtiqueta();

    });

    function imprimirModeloEtiqueta()
    {
        buscarModeloEtiqueta("");

        setTimeout(() => {
            $("#nombremodeloetiqueta").text(" - Usa etiqueta: "+nombreModeloEtiqueta);
        }, 300);
    }
    
    function configurarModeloEtiqueta()
    {
        var programa = "";

        let urlConfigurarModeloetiqueta = route('configurar_modeloetiqueta', ':programa');
        let url = urlConfigurarModeloetiqueta;
        url = url.replace(':programa', programa);
        document.location.href=url;        
    }

    
    function buscarModeloEtiqueta(programa)
    {
        // Actualiza configuracion de salida
        var listarUri = "/anitaERP/public/configuracion/buscarmodeloetiqueta/"+programa;

        $.get(listarUri, function(data){
            if (data.id > 0)
            {
                nombreModeloEtiqueta = data.modeloetiquetas.nombre;
            }

        });
    }
