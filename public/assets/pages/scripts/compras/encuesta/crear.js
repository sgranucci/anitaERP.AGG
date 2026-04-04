
    $(function () {
        $('#agrega_renglon_encuesta_pregunta').on('click', agregaRenglonEncuesta_Pregunta);
        $(document).on('click', '.eliminar_encuesta_pregunta', borraRenglonEncuesta_Pregunta);

		activa_eventos(true);

		$( ".botonsubmit" ).on('click', function() {
			$( "#form-general" ).submit();
		});
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}
	}

    function agregaRenglonEncuesta_Pregunta(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-encuesta-pregunta').html();

    	$("#tbody-encuesta-pregunta-table").append(renglon);
    	actualizaRenglonesEncuesta_Pregunta();
		
		$('#encuesta-pregunta-table').find('tr').last().find('.nombre').focus();
		activa_eventos(false);
    }

    function borraRenglonEncuesta_Pregunta() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesEncuesta_Pregunta();
    }

    function actualizaRenglonesEncuesta_Pregunta() {
    	var item = 1;

    	$("#tbody-encuesta-pregunta-table .iiencuesta_pregunta").each(function() {
    		$(this).val(item++);
    	});
    }


