
    $(function () {
        $('#agrega_renglon_arbolaprobacion_nivel').on('click', agregaRenglonArbolaprobacion_Nivel);
        $(document).on('click', '.eliminar_arbolaprobacion_nivel', borraRenglonArbolaprobacion_Nivel);

		activa_eventos(true);

		// Verifica campos recordatorio
		let recordatorio = $(this).val();

		if (recordatorio == 'S')
		{
				$(".div-diasinrespuesta").show();
				$(".div-diavencimientorecordatorio").show();			
		}

		$('#recordatorio').on('change', function (event) {
			event.preventDefault();
			let recordatorio = $(this).val();

			if (recordatorio == 'S')
			{
				$(".div-diasinrespuesta").show();
				$(".div-diavencimientorecordatorio").show();
			}
			else
			{
				$(".div-diasinrespuesta").hide();
				$(".div-diavencimientorecordatorio").hide();
			}
		});

		$('#filtro_centrocosto_id').on('change', function (event) {
			event.preventDefault();
			let centrocosto_id = $(this).val();

			$("#tbody-arbolaprobacion-nivel-table .iiarbolaprobacion_nivel").each(function() {
				if (centrocosto_id > 0)
				{
					if ($(this).parents('tr').find('.centrocosto').val() != centrocosto_id)
						$(this).closest('tr').hide();
					else
						$(this).closest('tr').show();
				}
				else
					$(this).closest('tr').show();
    		});

		});		

		$( ".botonsubmit" ).on('click', function() {
			$( "#form-general" ).submit();
		});
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('consultausuario').off('click');
		}

		// Activa eventos de consulta
		activa_eventos_consultausuario();
	}

    function agregaRenglonArbolaprobacion_Nivel(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-arbolaprobacion-nivel').html();

    	$("#tbody-arbolaprobacion-nivel-table").append(renglon);
    	actualizaRenglonesArbolaprobacion_Nivel();

		activa_eventos(false);
    }

    function borraRenglonArbolaprobacion_Nivel() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesArbolaprobacion_Nivel();
    }

    function actualizaRenglonesArbolaprobacion_Nivel() {
    	var item = 1;

    	$("#tbody-arbolaprobacion-nivel-table .iiarbolaprobacion_nivel").each(function() {
    		$(this).val(item++);
    	});
    }


