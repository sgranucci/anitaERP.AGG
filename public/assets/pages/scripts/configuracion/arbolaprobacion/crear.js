
    $(function () {
        $('#agrega_renglon_arbolaprobacion_nivel').on('click', agregaRenglonArbolaprobacion_Nivel);
        $(document).on('click', '.eliminar_arbolaprobacion_nivel', borraRenglonArbolaprobacion_Nivel);
		$(document).on('change', '.doble_aprobacion_check', onCambioDobleAprobacion);
		$(document).on('change', '#tbody-arbolaprobacion-nivel-table .centrocosto', function () {
			actualizarVisibilidadDobleAprobacion();
		});

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
			actualizarPanelReCircuitoCuentas();
			actualizarVisibilidadDobleAprobacion();
		});
		actualizarPanelOcArbol();
		actualizarPanelReCircuitoCuentas();
		actualizarVisibilidadDobleAprobacion();

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

		$('#agrega_re_trigger').on('click', agregaFilaReTrigger);
		$(document).on('click', '.eliminar_re_trigger', function (e) {
			e.preventDefault();
			$(this).closest('tr.fila-re-trigger').remove();
			actualizarContadorReTriggers();
		});
		$(document).on('change', '.re-trigger-evaluador', function () {
			actualizarCamposReTrigger($(this).closest('tr'));
		});
		$(document).on('change', '.re-trigger-activo', function () {
			actualizarEstadoFilaReTrigger($(this).closest('tr'));
			actualizarContadorReTriggers();
		});
		$(document).on('click', '.ir-a-allowlist', function (e) {
			e.preventDefault();
			resaltarAllowlistDesdeTrigger($(this).closest('tr'));
		});
		$('#toggle-re-triggers').on('click', function (e) {
			e.preventDefault();
			toggleReTriggersCollapse();
		});
		$('#tbody-re-triggers .fila-re-trigger').each(function () {
			actualizarCamposReTrigger($(this));
			actualizarEstadoFilaReTrigger($(this));
		});
		actualizarContadorReTriggers();

		$('#agrega_re_exc').on('click', agregaFilaReExc);
		$(document).on('click', '.eliminar_re_exc', function (e) {
			e.preventDefault();
			$(this).closest('tr.fila-re-exc').remove();
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
		actualizarVisibilidadDobleAprobacion();

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

	function actualizarPanelReCircuitoCuentas() {
		var tipo = $('#tipoarbol').val() || '';
		var esReq = tipo === 'Requisiciones';
		$('#re-circuito-cuentas-panel').toggle(esReq);
		$('.col-rama-re').toggle(esReq);
		if (!esReq) {
			$('#tbody-arbolaprobacion-nivel-table tr.item-arbolaprobacion-nivel .rama-re').val('');
		}
	}

	function agregaFilaReTrigger(e) {
		e.preventDefault();
		abrirReTriggersCollapse();
		var html = $('#template-re-trigger-fila').html();
		$('#tbody-re-triggers').append(html);
		var $row = $('#tbody-re-triggers tr.fila-re-trigger').last();
		actualizarCamposReTrigger($row);
		actualizarEstadoFilaReTrigger($row);
		actualizarContadorReTriggers();
		if (typeof activa_eventos_consultacuentacontable === 'function') {
			activa_eventos_consultacuentacontable();
		}
	}

	function toggleReTriggersCollapse() {
		var $panel = $('#re-triggers-panel');
		if ($panel.hasClass('is-open')) {
			cerrarReTriggersCollapse();
		} else {
			abrirReTriggersCollapse();
		}
	}

	function abrirReTriggersCollapse() {
		var $panel = $('#re-triggers-panel');
		var $btn = $('#toggle-re-triggers');
		var $body = $('#re-triggers-collapse-body');
		$panel.addClass('is-open');
		$body.prop('hidden', false);
		$btn.attr('aria-expanded', 'true');
	}

	function cerrarReTriggersCollapse() {
		var $panel = $('#re-triggers-panel');
		var $btn = $('#toggle-re-triggers');
		var $body = $('#re-triggers-collapse-body');
		$panel.removeClass('is-open');
		$body.prop('hidden', true);
		$btn.attr('aria-expanded', 'false');
	}

	function actualizarContadorReTriggers() {
		var n = $('#tbody-re-triggers tr.fila-re-trigger').length;
		var activos = 0;
		$('#tbody-re-triggers tr.fila-re-trigger').each(function () {
			if (String($(this).find('.re-trigger-activo').val() || 'S') === 'S') {
				activos++;
			}
		});
		$('#re-triggers-count-chip').text(n === 1 ? '1 regla' : (n + ' reglas'));
		$('#re-triggers-activos-chip').text(activos === 1 ? '1 activa' : (activos + ' activas'));
	}

	function actualizarEstadoFilaReTrigger($row) {
		var activo = String($row.find('.re-trigger-activo').val() || 'S') === 'S';
		$row.toggleClass('is-activo', activo);
		$row.toggleClass('is-inactivo', !activo);
		$row.find('.re-trigger-estado-badge').text(activo ? 'Activo' : 'Inactivo');
	}

	function actualizarCamposReTrigger($row) {
		var $opt = $row.find('.re-trigger-evaluador option:selected');
		var usaAllowlist = String($opt.data('usa-allowlist') || '0') === '1';
		var usaMonto = String($opt.data('usa-monto') || '0') === '1';
		var usaCuenta = String($opt.data('usa-cuenta') || '0') === '1';
		var hint = String($opt.data('hint') || '');
		$row.find('.re-trigger-allowlist-hint').toggle(usaAllowlist);
		$row.find('.re-trigger-params-monto').toggle(usaMonto);
		$row.find('.re-trigger-params-cuenta').toggle(usaCuenta);
		var $hint = $row.find('.re-trigger-hint');
		if (hint) {
			$hint.text(hint).show();
		} else {
			$hint.hide().text('');
		}
	}

	function actualizarHintAllowlistTrigger($row) {
		actualizarCamposReTrigger($row);
	}

	function resaltarAllowlistDesdeTrigger($row) {
		var ccId = String($row.find('.re-trigger-cc').val() || '');
		var $panel = $('#re-cuenta-excepcion-panel');
		if (!$panel.length) {
			return;
		}
		$panel.addClass('is-highlight');
		$('#tbody-re-exc tr.fila-re-exc').removeClass('is-cc-match');
		if (ccId) {
			$('#tbody-re-exc tr.fila-re-exc').each(function () {
				var rowCc = String($(this).find('select[name="re_exc_centrocosto_ids[]"]').val() || '');
				if (rowCc === ccId) {
					$(this).addClass('is-cc-match');
				}
			});
		}
		if ($panel[0] && typeof $panel[0].scrollIntoView === 'function') {
			$panel[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
		window.setTimeout(function () {
			$panel.removeClass('is-highlight');
			$('#tbody-re-exc tr.fila-re-exc').removeClass('is-cc-match');
		}, 2200);
	}

	function actualizarVisibilidadDobleAprobacion() {
		var tipo = $('#tipoarbol').val() || '';
		var esReq = tipo === 'Requisiciones';
		$('.col-doble-aprobacion').toggle(esReq);
		if (!esReq) {
			$('#tbody-arbolaprobacion-nivel-table tr.item-arbolaprobacion-nivel').each(function () {
				$(this).find('.doble_aprobacion_valor').val('N');
				$(this).find('.doble_aprobacion_check').prop('checked', false);
			});
		}
	}

	function onCambioDobleAprobacion() {
		var $row = $(this).closest('tr');
		var valor = $(this).is(':checked') ? 'S' : 'N';
		$row.find('.doble_aprobacion_valor').val(valor);

		var ccId = String($row.find('.centrocosto').val() || '');
		if (!ccId) {
			return;
		}

		$('#tbody-arbolaprobacion-nivel-table tr.item-arbolaprobacion-nivel').each(function () {
			var $otra = $(this);
			if (String($otra.find('.centrocosto').val() || '') !== ccId) {
				return;
			}
			$otra.find('.doble_aprobacion_valor').val(valor);
			$otra.find('.doble_aprobacion_check').prop('checked', valor === 'S');
		});
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

	function agregaFilaReExc(e) {
		e.preventDefault();
		var html = $('#template-re-exc-fila').html();
		$('#tbody-re-exc').append(html);
		var $row = $('#tbody-re-exc tr.fila-re-exc').last();
		var emp = $('#empresa_id').val() || '';
		if (emp) {
			$row.find('select[name="re_exc_empresa_ids[]"]').val(emp);
		}
	}

