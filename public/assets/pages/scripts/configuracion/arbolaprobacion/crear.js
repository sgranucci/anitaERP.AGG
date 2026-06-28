
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

		$('#tipoarbol').on('change', function () {
			actualizarPanelOcArbol();
		});
		actualizarPanelOcArbol();

		$('#agrega_oc_trigger').on('click', agregaFilaOcTrigger);
		$(document).on('click', '.eliminar_oc_trigger', function (e) {
			e.preventDefault();
			$(this).closest('tr.fila-oc-trigger').remove();
		});
		$(document).on('change', '.oc-trigger-tipo', function () {
			actualizarCamposOcTrigger($(this).closest('tr'));
		});
		$('#tbody-oc-triggers .fila-oc-trigger').each(function () {
			actualizarCamposOcTrigger($(this));
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

	function actualizarPanelOcArbol() {
		var tipo = $('#tipoarbol').val() || '';
		if (tipo === 'Ordenes de compra') {
			$('#oc-triggers-panel').show();
		} else {
			$('#oc-triggers-panel').hide();
		}
	}

	function agregaFilaOcTrigger(e) {
		e.preventDefault();
		var idx = $('#tbody-oc-triggers tr.fila-oc-trigger').length;
		var html = $('#template-oc-trigger-fila').html().replace(/__IDX__/g, String(idx));
		$('#tbody-oc-triggers').append(html);
		actualizarCamposOcTrigger($('#tbody-oc-triggers tr.fila-oc-trigger').last());
	}

	function actualizarCamposOcTrigger($row) {
		var tipo = ($row.find('.oc-trigger-tipo').val() || '').toUpperCase();
		if (tipo === 'EVENTO') {
			$row.find('.oc-trigger-evento').prop('disabled', false).show();
			$row.find('.oc-trigger-evaluador').prop('disabled', true).hide().val('');
		} else if (tipo === 'CONDICION') {
			$row.find('.oc-trigger-evento').prop('disabled', true).hide().val('');
			$row.find('.oc-trigger-evaluador').prop('disabled', false).show();
		}
	}

