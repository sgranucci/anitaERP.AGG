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

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });

		$( ".botonsubmit" ).click(function() {
			$( "#form-general" ).submit();
		});
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}

		// Activa eventos de consulta
		activa_eventos_consultacategoria_ticket();
		activa_eventos_consultasubcategoria_ticket();
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


		


