
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
        $(document).on('click', '.eliminar-archivo-premio-uif', borraTarjetaArchivoPremioUif);

        activa_eventos(true);

        var $foto = $('#foto');
        var previewUrl = ($foto.data('initial-preview') || '').toString().trim();
        var tienePreviewReal = previewUrl !== ''
            && previewUrl.indexOf('user2-160x160') === -1;

        $foto.fileinput({
            language: 'es',
            allowedFileExtensions: ['jpg', 'jpeg', 'png'],
            maxFileSize: 2000,
            showUpload: false,
            showClose: false,
            initialPreviewAsData: true,
            initialPreview: tienePreviewReal ? [previewUrl] : [],
            previewSettings: { image: { 'max-height': '180px', 'max-width': '100%' } },
            dropZoneEnabled: false,
            theme: 'fa',
            browseClass: 'btn btn-outline-primary btn-sm',
            removeClass: 'btn btn-outline-danger btn-sm',
        });        
    });
	
    function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}
    }

    function agregaRenglonArchivo(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-archivo').html();

    	$("#tbody-tabla-archivo").append(renglon);
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function borraTarjetaArchivoPremioUif(event) {
        event.preventDefault();
        var $wrap = $(this).closest('.cliente-premio-uif-archivo-item');
        if ($wrap.length) {
            $wrap.remove();
            return;
        }
        $(this).closest('.col-md-6').remove();
    }

    function actualizaArchivo(elem) {
	  	var fn = $(elem).val();
		var filename = fn.match(/[^\\/]*$/)[0]; // remove C:\fakename

		$(elem).parents("tr").find(".nombresanteriores").val(filename);
    }