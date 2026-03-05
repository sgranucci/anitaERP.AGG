    $(function () {
        $('#agrega_renglon_cotizacion').on('click', agregaRenglonCotizacion);
        $(document).on('click', '.eliminar_cotizacion', borraRenglonCotizacion);

		activa_eventos(true);

    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}
	}

    function agregaRenglonCotizacion(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-cotizacion').html();

    	$("#tbody-cotizacion-table").append(renglon);
    	actualizaRenglonesCotizacion();

		activa_eventos(false);
    }

    function borraRenglonCotizacion() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCotizacion();
		sumaMonto();
    }

    function actualizaRenglonesCotizacion() {
    	var item = 1;

    	$("#tbody-cotizacion-table .iicotizacion").each(function() {
    		$(this).val(item++);
    	});
    }




		


