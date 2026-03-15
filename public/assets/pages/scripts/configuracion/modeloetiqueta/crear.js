	$(function () {
		// Muestra boton de anulacion
		let estadoModeloetiqueta = $('#estado').val();

		muestraBotonAnulacion(estadoModeloetiqueta);

    });

	function anulaModeloetiqueta()
	{
		let estadoActualModeloetiqueta = $('#estado').val();

		if (estadoActualModeloetiqueta != 'BAJA' && estadoActualModeloetiqueta != 'ACTIVO')
		{
			alert("No se puede cambiar el estado del modelo de etiqueta")
			return;
		}
		switch(estadoActualModeloetiqueta)
		{
			case 'BAJA':
				$('#estado').val('ACTIVO');	
				break;
			case 'ACTIVO':
				$('#estado').val('BAJA');
				break;
		}

		// Actualiza estado del modelo de etiqueta
		let estadoModeloetiqueta = $('#estado').val();
		let modeloetiqueta_id = $('#modeloetiqueta_id').val();

		let listarUri = "/anitaERP/public/configuracion/actualizarestadomodeloetiqueta/"+estadoModeloetiqueta+"/"+modeloetiqueta_id;

		$.get(listarUri)
			.done(function(data){
				alert('Modelo de etiqueta actualizado con éxito');

				muestraBotonAnulacion(estadoModeloetiqueta);
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				alert("Error en la petición: "+textStatus+errorThrown);
				alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
			});
	}

	function muestraBotonAnulacion(estadoModeloetiqueta)
	{
		switch(estadoModeloetiqueta)
		{
			case 'BAJA':
				$('#anulamodeloetiqueta').html('<i class="fas fa-check"></i>Activar el Modelo de Etiqueta');
				$( "#anulamodeloetiqueta" ).css( "background-color", "green" ); 
				break;
			case 'ACTIVO':
				$('#anulamodeloetiqueta').html('<i class="fas fa-cross"></i>Anular el Modelo de Etiqueta');
				$( "#anulamodeloetiqueta" ).css( "background-color", "yellow" ); 
				break;
		}
	}

