
    var ptrriesgo;

    $(function () {
        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
        });

        $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

        activa_eventos(true);

        $('#foto').fileinput({
            language: 'es',
            allowedFileExtensions: ['jpg', 'jpeg', 'png'],
            maxFileSize: 2000,
            showUpload: false,
            showClose: false,
            initialPreviewAsData: true,
            dropZoneEnabled: false,
            theme: "fa",
        });        
    });
	
    function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}
    }

    function agregaRenglonArchivo(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-archivo').html();

    	$("#tbody-tabla-archivo").append(renglon);
    }

    function borraRenglonArchivo() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function actualizaArchivo(elem) {
	  	var fn = $(elem).val();
		var filename = fn.match(/[^\\/]*$/)[0]; // remove C:\fakename

		$(elem).parents("tr").find(".nombresanteriores").val(filename);
	}