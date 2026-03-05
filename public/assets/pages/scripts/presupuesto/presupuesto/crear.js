    $(function () {

        $('#agrega_renglon_escenario').on('click', agregaRenglonEscenario);
        $(document).on('click', '.eliminar_escenario', borraRenglonEscenario);

        $( ".botonsubmit" ).click(function() {
            $( "#form-general" ).submit();
        });

		activa_eventos(true);

        if ($('#escenario-table tbody tr').length === 0)
            agregaRenglonEscenario();
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}

	}

    function agregaRenglonEscenario(event){
        if (event) 
    	    event.preventDefault();
    	let renglon = $('#template-renglon-escenario').html();

        $("#tbody-escenario-table").append(renglon);
    	actualizaRenglonesEscenario();

        activa_eventos(false);
    }

    function borraRenglonEscenario(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesEscenario();
    }

    function actualizaRenglonesEscenario() {
    	var item = 1;

    	$("#tbody-escenario-table .iiescenario").each(function() {
    		$(this).val(item++);
    	});
    }
		
    


