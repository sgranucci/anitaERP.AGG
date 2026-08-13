    $(function () {
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		activa_eventos(true);

		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
        });
    });

	function activa_eventos(flInicio)
	{
		if (typeof activa_eventos_consultacategoria_ticket === 'function') {
			activa_eventos_consultacategoria_ticket();
		}
	}

	function agregaRenglonArchivo(event){
    	if (event) {
			event.preventDefault();
		}
    	var renglon = $('#template-renglon-archivo').html();

    	$("#tbody-tabla-archivo").append(renglon);
    }

    function borraRenglonArchivo(event) {
    	if (event) {
			event.preventDefault();
		}
    	$(this).parents('tr').remove();
    }

    function actualizaArchivo(elem) {
	  	var fn = $(elem).val();
		var filename = fn.match(/[^\\/]*$/)[0]; // remove C:\fakename

		$(elem).parents("tr").find(".nombresanteriores").val(filename);
	}
