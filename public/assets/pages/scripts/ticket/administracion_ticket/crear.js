	$(function () {
		$('#agrega_renglon_tarea_ticket').on('click', agregaRenglonTarea_Ticket);
        $(document).on('click', '.eliminar_tarea_ticket', borraRenglonTarea_Ticket);
		$('#agrega_renglon_ticket_articulo').on('click', agregaRenglonTicket_Articulo);
        $(document).on('click', '.eliminar_ticket_articulo', borraRenglonTicket_Articulo);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
		$(document).on('click', '.btn-agregar-comentario-tarea', guardarComentarioInline);
		$(document).on('change', '.estadotarea', cambiarEstadoTarea);

		$(document).on('show.bs.collapse hide.bs.collapse', '.panel-comentarios-tarea', function (event) {
			let $panel = $(this);
			let $toggle = $('[data-target="#' + $panel.attr('id') + '"]');
			$toggle.find('.toggle-icon')
				.toggleClass('fa-chevron-down', event.type === 'show')
				.toggleClass('fa-chevron-right', event.type === 'hide');
		});

		$(document).off('click.ticketAdminSubmit', '#btn-actualizar-administracion-ticket')
			.on('click.ticketAdminSubmit', '#btn-actualizar-administracion-ticket', enviarFormularioAdministracionTicket);

		activa_eventos(true);
		leeEstadoTarea();
		calculaEstadoTicket();
		if (typeof activa_eventos_consultausuario === 'function') {
			activa_eventos_consultausuario();
		}

		$('a[data-toggle="tab"][href="#tab-historia"]').on('shown.bs.tab', function () {
			if ($('#id').val()) {
				leeHistoria();
			}
		});

		$(document).on('change', '#estado_ticket', function () {
			let estado = $(this).val();
			if (estado === 'Finalizado') {
				sellarResolucionSiVacio();
			} else {
				$('#fecha_resolucion').val('');
				$('#hora_resolucion').val('');
				$('#panel-estadisticas-ticket .badge-success').remove();
			}
			actualizarTiempoInsumidoTotal();
		});
    });

	function enviarFormularioAdministracionTicket(event) {
		event.preventDefault();
		event.stopImmediatePropagation();

		if (! confirmarComentariosPendientesOAbortar()) {
			return false;
		}

		let form = document.getElementById('form-general');
		if (! form) {
			alert('No se encontró el formulario para guardar.');
			return false;
		}

		if (typeof validarCamposObligatoriosFormulario === 'function') {
			let resultado = validarCamposObligatoriosFormulario(form);
			if (! resultado.valido) {
				if (typeof mostrarSolapaDelPrimerCampoInvalido === 'function') {
					mostrarSolapaDelPrimerCampoInvalido(resultado.primerInvalido);
				}
				if (typeof notificarCamposObligatoriosPendientes === 'function') {
					notificarCamposObligatoriosPendientes(resultado.primerInvalido, resultado.cantidadInvalidos);
				}
				if (typeof enfocarCampoInvalido === 'function') {
					enfocarCampoInvalido(resultado.primerInvalido);
				}
				return false;
			}
		}

		// Native submit: jquery.validate + $('#form').submit() cortan el envío en silencio.
		HTMLFormElement.prototype.submit.call(form);
	}

	function hayComentariosSinEnviar() {
		let pendiente = false;
		$('.comentario-tarea-texto').each(function () {
			if ($.trim($(this).val()) !== '') {
				pendiente = true;
				return false;
			}
		});
		return pendiente;
	}

	function confirmarComentariosPendientesOAbortar() {
		if (! hayComentariosSinEnviar()) {
			return true;
		}
		let ok = window.confirm(
			'Hay comentarios escritos sin enviar. Si actualiza ahora se van a perder.\n\n¿Desea actualizar de todos modos?'
		);
		if (! ok) {
			enfocarPrimerComentarioPendiente();
			return false;
		}
		return true;
	}

	function enfocarPrimerComentarioPendiente() {
		$('.comentario-tarea-texto').each(function () {
			if ($.trim($(this).val()) === '') {
				return;
			}
			let $textarea = $(this);
			let $panel = $textarea.closest('.panel-comentarios-tarea');
			if ($panel.length && ! $panel.hasClass('show')) {
				$panel.collapse('show');
			}
			$textarea.focus();
			return false;
		});
	}

	function activa_eventos(flInicio)
	{
		// Activa eventos de consulta
		activa_eventos_consultatarea_ticket();
		activa_eventos_consultatecnico_ticket();
		activa_eventos_consultacategoria_ticket();
		activa_eventos_consultasubcategoria_ticket();
		activa_eventos_consultaarticulo();

		// Una sola vez: delegados con namespace (evita handlers apilados al agregar renglones)
		if (flInicio) {
			$(document).off('click.ticketFinaliza', '.finalizatarea')
				.on('click.ticketFinaliza', '.finalizatarea', iniciarFinalizacionTarea);
		}
	}

	function filaTareaDesde($el) {
		return $el.closest('tr.item-tarea-ticket');
	}

	function fechaHoyYmd() {
		let fecha = new Date();
		let day = String(fecha.getDate()).padStart(2, '0');
		let month = String(fecha.getMonth() + 1).padStart(2, '0');
		let year = fecha.getFullYear();
		return year + '-' + month + '-' + day;
	}

	function fechaHoyLegible() {
		let fecha = new Date();
		let day = String(fecha.getDate()).padStart(2, '0');
		let month = String(fecha.getMonth() + 1).padStart(2, '0');
		let year = fecha.getFullYear();
		return day + '/' + month + '/' + year;
	}

	function iniciarFinalizacionTarea(event) {
		event.preventDefault();

		let $tr = filaTareaDesde($(this));
		let fechaFinalizacion = $.trim($tr.find('.fechafinalizacion').val() || '');
		let ticketTareaId = $.trim($tr.find('.ticket_tarea_id').val() || '');
		let $minutos = $tr.find('.tiempoinsumido');

		if (fechaFinalizacion !== '') {
			alert('La tarea ya está finalizada el ' + fechaFinalizacion.split('-').reverse().join('/') + '.');
			return;
		}

		if (! ticketTareaId) {
			alert('Guarde el ticket antes de finalizar la tarea.');
			return;
		}

		$minutos.prop('readonly', false).removeAttr('readonly');
		let valorActual = $.trim($minutos.val() || '');
		let ingresado = window.prompt(
			'Ingrese los minutos insumidos para finalizar la tarea.\nSe grabará la fecha de finalización: ' + fechaHoyLegible(),
			valorActual !== '' ? valorActual : ''
		);

		if (ingresado === null) {
			if (valorActual === '') {
				$minutos.prop('readonly', true).attr('readonly', 'readonly');
			}
			return;
		}

		ingresado = $.trim(ingresado).replace(',', '.');
		let minutos = parseFloat(ingresado);
		if (! (minutos > 0)) {
			alert('Indique los minutos insumidos (mayor a 0).');
			$minutos.focus();
			return;
		}

		$minutos.val(ingresado);
		confirmarFinalizacionTarea($tr, ticketTareaId, ingresado);
	}

	function confirmarFinalizacionTarea($tr, ticketTareaId, tiempoinsumido) {
		let $estado = $tr.find('.estadotarea');
		let formateada = fechaHoyYmd();
		let legible = fechaHoyLegible();

		$tr.find('.fechafinalizacion').val(formateada);
		$estado.val('Finalizada');
		$estado.attr('data-estado-previo', 'Finalizada');

		let url = carpetaBase + '/ticket/finalizar_tarea/' + ticketTareaId + '/' + formateada + '/' + encodeURIComponent(tiempoinsumido);

		$.get(url)
			.done(function (data) {
				let body = (typeof data === 'string') ? $.trim(data) : String((data && data.mensaje) || '');
				if (body === 'ok') {
					$tr.find('.tiempoinsumido').prop('readonly', true).attr('readonly', 'readonly');
					$tr.find('.finalizatarea').prop('disabled', true).addClass('d-none');
					$tr.find('.fechafinalizacion')
						.addClass('border-success')
						.css({'background-color': '#d4edda', 'font-weight': 'bold'});
					aplicarEstadisticasDesdeRespuesta(data);
					calculaEstadoTicket();
					let extra = '';
					if (data && data.cerro_ticket) {
						extra = '\nEl ticket quedó Finalizado.';
					}
					alert('Tarea finalizada con éxito.\nFecha de finalización: ' + legible + '\nMinutos: ' + tiempoinsumido + extra);
					return;
				}
				$tr.find('.fechafinalizacion').val('');
				alert('Ha ocurrido un error finalizando la tarea');
			})
			.fail(function () {
				$tr.find('.fechafinalizacion').val('');
				alert('Ha ocurrido un error finalizando la tarea');
			});
	}

	function agregaRenglonTarea_Ticket(event){
    	event.preventDefault();

		agregaUnRenglonTarea_Ticket();
	}

	function agregaUnRenglonTarea_Ticket()
	{
    	let renglon = $('#template-renglon-tarea-ticket').html();

    	$("#tbody-tarea-ticket-table").append(renglon);
    	actualizaRenglonesTarea_Ticket();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-tarea-ticket-table tr:last");
		$(ptrUltimoRenglon).find('.tarea_ticket_id').focus();

		activa_eventos(false);
    }

	function borraRenglonTarea_Ticket(event) {
    	event.preventDefault();
		let $tr = $(this).closest('tr.item-tarea-ticket');
		$tr.next('.fila-comentarios-tarea-admin').remove();
    	$tr.remove();
    	actualizaRenglonesTarea_Ticket();
		calculaEstadoTicket();
    }

    function actualizaRenglonesTarea_Ticket() {
    	var item = 1;

    	$("#tbody-tarea-ticket-table .iitarea_ticket").each(function() {
    		$(this).val(item++);
    	});
    }

	function agregaRenglonTicket_Articulo(event){
		event.preventDefault();
		
		agregaUnRenglonTicket_Articulo();
	}

	function agregaUnRenglonTicket_Articulo()
	{
    	let renglon = $('#template-renglon-ticket-articulo').html();

    	$("#tbody-ticket-articulo-table").append(renglon);
    	actualizaRenglonesTicket_Articulo();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-tarea-ticket-table tr:last");
		$(ptrUltimoRenglon).find('.codigoarticulo').focus();

		activa_eventos(false);
    }

	function borraRenglonTicket_Articulo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesTicket_Articulo();
    }

    function actualizaRenglonesTicket_Articulo() {
    	var item = 1;

    	$("#tbody-ticket-articulo-table .iiarticulo").each(function() {
    		$(this).val(item++);
    	});
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

	function cambiarEstadoTarea() {
		let $select = $(this);
		let $tr = $select.closest('tr.item-tarea-ticket');
		let ticketTareaId = $tr.find('.ticket_tarea_id').val();
		let estado = $.trim($select.val());
		let estadoPrevio = $select.attr('data-estado-previo') || 'Pendiente';
		let urlBase = $('#url_cambia_estado_tarea').val();

		if (!estado) {
			$select.val(estadoPrevio);
			return;
		}

		if (!ticketTareaId || ticketTareaId === 'undefined') {
			alert('Guarde el ticket antes de cambiar el estado de la tarea.');
			$select.val(estadoPrevio);
			return;
		}

		if (estado === estadoPrevio) {
			return;
		}

		if (!urlBase) {
			alert('No se pudo determinar la URL para cambiar el estado.');
			$select.val(estadoPrevio);
			return;
		}

		$select.prop('disabled', true);

		$.ajax({
			url: urlBase + '/' + ticketTareaId,
			method: 'POST',
			data: {
				_token: $('#csrf_token').val(),
				estado: estado
			},
			success: function (resp) {
				if (resp.mensaje !== 'ok') {
					alert(resp.error || 'No se pudo cambiar el estado.');
					$select.val(estadoPrevio);
					return;
				}

				$select.attr('data-estado-previo', estado);
				calculaEstadoTicket();
			},
			error: function (xhr) {
				let msg = 'No se pudo cambiar el estado.';
				if (xhr.responseJSON && xhr.responseJSON.error) {
					msg = xhr.responseJSON.error;
				}
				alert(msg);
				$select.val(estadoPrevio);
			},
			complete: function () {
				$select.prop('disabled', false);
			}
		});
	}

	function leeHistoria()
	{
		var wrapper = $(".container-historia");
		let ticket_id = $("#id").val();

		let url = carpetaBase+'/ticket/leer_historia_ticket/'+ticket_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				fecha = value.fecha;

				$(wrapper).append('<tr class="item-ticket-historia">'+
                            '<td>'+
                                '<input type="date" name="estadofechas[]" class="form-control estadofecha" value="'+fecha.substring(0,10)+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="estados[]" class="form-control estado" value="'+value.estado+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="estadousuarios[]" class="form-control estadousuario" value="'+value.usuarios.nombre+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="estadoobservaciones[]" class="form-control estadoobservacion" value="'+value.observacion+'" readonly>'+
                            '</td>'+
                        '</tr>');
			});
		});
	}

	function leeEstadoTarea()
	{
		let url = '';

		$("#tarea-ticket-table .item-tarea-ticket").each(function() {
			let ticket_tarea_id = $(this).find(".ticket_tarea_id").val();
			let ptrTarea = this;
			let $estado = $(ptrTarea).find(".estadotarea");

			if (!ticket_tarea_id || ticket_tarea_id === 'undefined') {
				return;
			}

			url = carpetaBase+'/ticket/leer_ticket_tarea_novedad/'+ticket_tarea_id;

			$.get(url, function(novedades){
				var nov = $.map(novedades, function(value, index){
					return [value];
				});
				let fechafinalizacion = $.trim($(ptrTarea).find(".fechafinalizacion").val() || '');
				let ultimoEstado = $estado.val() || "Pendiente";
				if (fechafinalizacion !== '') {
					ultimoEstado = "Finalizada";
				} else if (nov.length > 0) {
					$.each(nov, function(index,value){
						ultimoEstado = value.estado;
					});
				}

				$estado.val(ultimoEstado);
				$estado.attr('data-estado-previo', ultimoEstado);
				calculaEstadoTicket();
			});
		});
	}

	function cambioTecnico(ticket_tarea_id, tecnico_ticket_id)
	{
		if (!ticket_tarea_id || !tecnico_ticket_id || !$.isNumeric(tecnico_ticket_id)) {
			return;
		}

		let url = carpetaBase+'/ticket/cambiar_tecnico/'+ticket_tarea_id+'/'+tecnico_ticket_id;

		$.get(url, function(data, textStatus){
			if (textStatus == 'success' && data == 'ok')
				alert('Técnico reasignado');
			else
				alert('No se puede asignar: el técnico no existe o su usuario está suspendido');
		}).fail(function() {
			alert('No se puede asignar: el técnico no existe o su usuario está suspendido');
		});
	}

	function horaAhoraHhmm() {
		let fecha = new Date();
		let hh = String(fecha.getHours()).padStart(2, '0');
		let mm = String(fecha.getMinutes()).padStart(2, '0');
		return hh + ':' + mm;
	}

	function formatearMinutos(n) {
		if (! (n > 0) && n !== 0) {
			return '';
		}
		if (Math.abs(n - Math.round(n)) < 0.0001) {
			return String(Math.round(n));
		}
		return String(Math.round(n * 100) / 100).replace('.', ',');
	}

	function sellarResolucionSiVacio() {
		let fecha = $.trim($('#fecha_resolucion').val() || '');
		let hora = $.trim($('#hora_resolucion').val() || '');
		let hoy = fechaHoyYmd();
		if (fecha === '') {
			$('#fecha_resolucion').val(hoy);
			fecha = hoy;
		}
		if ((hora === '' || hora === '00:00') && fecha === hoy) {
			$('#hora_resolucion').val(horaAhoraHhmm());
		}
	}

	function actualizarTiempoInsumidoTotal() {
		let total = 0;
		$("#tarea-ticket-table .item-tarea-ticket .tiempoinsumido").each(function () {
			let n = parseFloat(String($(this).val() || '').replace(',', '.'));
			if (n > 0) {
				total += n;
			}
		});
		let texto = formatearMinutos(total);
		if (texto === '' && total === 0) {
			texto = '0';
		}
		$('#tiempo_insumido_total').val(texto);
		$('#tiempo-insumido-total-tareas').text(texto);
	}

	function aplicarEstadisticasDesdeRespuesta(data) {
		if (! data || typeof data !== 'object') {
			return;
		}
		if (data.estado_ticket) {
			$('#estado_ticket').val(data.estado_ticket);
		}
		if (data.fecha_resolucion) {
			$('#fecha_resolucion').val(data.fecha_resolucion);
		}
		if (data.hora_resolucion) {
			$('#hora_resolucion').val(String(data.hora_resolucion).substring(0, 5));
		}
		if (data.tiempo_insumido_total !== undefined && data.tiempo_insumido_total !== null) {
			$('#tiempo_insumido_total').val(formatearMinutos(parseFloat(data.tiempo_insumido_total)));
		}
	}

	function calculaEstadoTicket()
	{
		let estadoTicket = $('#estado_ticket').val();

		if (estadoTicket != 'Baja' && estadoTicket != 'Suspendido')
		{
			let hayTareas = false;
			let todasFinalizadas = true;

			$("#tarea-ticket-table .item-tarea-ticket").each(function() {
				hayTareas = true;
				let estadotarea = $(this).find('.estadotarea').val();
				let fechafinalizacion = $.trim($(this).find(".fechafinalizacion").val() || '');

				if (fechafinalizacion !== '')
				{
					estadotarea = 'Finalizada';
					$(this).find(".estadotarea").val("Finalizada");
				}

				if (estadotarea !== 'Finalizada') {
					todasFinalizadas = false;
				}
			});

			if (hayTareas && todasFinalizadas) {
				estadoTicket = 'Finalizado';
				sellarResolucionSiVacio();
			}

			$('#estado_ticket').val(estadoTicket);
		}

		actualizarTiempoInsumidoTotal();
	}

	function actualizarContadorComentarios(ticketTareaId, total) {
		let $fila = $('.fila-comentarios-tarea-admin[data-ticket-tarea-id="' + ticketTareaId + '"]');
		$fila.find('.contador-comentarios').text(total);
	}

	function mostrarBannerEnviandoComentario() {
		$('#ticket-comentario-enviando-overlay')
			.removeClass('d-none')
			.css('display', 'flex')
			.attr('aria-hidden', 'false');
		$('body').css('overflow', 'hidden');
	}

	function ocultarBannerEnviandoComentario() {
		$('#ticket-comentario-enviando-overlay')
			.addClass('d-none')
			.css('display', '')
			.attr('aria-hidden', 'true');
		$('body').css('overflow', '');
	}

	function guardarComentarioInline(event) {
		event.preventDefault();

		let urlBase = $('#url_guarda_comentario_tarea_admin').val();
		if (!urlBase) {
			alert('Guarde el ticket antes de agregar comentarios.');
			return;
		}

		let $btn = $(this);
		let ticketTareaId = $btn.data('ticket-tarea-id');
		let $panel = $btn.closest('.panel-comentarios-tarea');
		let $textarea = $panel.find('.comentario-tarea-texto');
		let comentario = $.trim($textarea.val());

		if (!comentario) {
			alert('Ingrese un comentario.');
			$textarea.focus();
			return;
		}

		$btn.prop('disabled', true);
		mostrarBannerEnviandoComentario();

		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val()
			}
		});

		$.ajax({
			url: urlBase + '/' + ticketTareaId + '/comentario',
			method: 'POST',
			data: {
				_token: $('#csrf_token').val(),
				comentario: comentario
			},
			success: function (resp) {
				if (resp.mensaje !== 'ok' || !resp.comentario) {
					alert(resp.error || 'No se pudo enviar el comentario.');
					return;
				}

				let $lista = $panel.find('.lista-comentarios-tarea[data-ticket-tarea-id="' + ticketTareaId + '"]');
				$lista.find('.sin-comentarios').remove();

				let html = '<div class="comentario-item border rounded bg-white p-2 mb-1">' +
					'<div class="d-flex justify-content-between flex-wrap">' +
						'<strong>' + $('<div>').text(resp.comentario.usuario || '').html() + '</strong>' +
						'<span class="text-muted">' + (resp.comentario.fecha || '') + '</span>' +
					'</div>' +
					'<div class="comentario-texto">' +
						$('<div>').text(resp.comentario.comentario || '').html() +
					'</div>' +
				'</div>';
				$lista.append(html);

				$textarea.val('');
				actualizarContadorComentarios(ticketTareaId, $lista.find('.comentario-item').length);
				alert('Comentario enviado. Se notificó por correo al usuario que generó el ticket.');
			},
			error: function (xhr) {
				let msg = 'No se pudo enviar el comentario.';
				if (xhr.responseJSON && xhr.responseJSON.error) {
					msg = xhr.responseJSON.error;
				}
				alert(msg);
			},
			complete: function () {
				ocultarBannerEnviandoComentario();
				$btn.prop('disabled', false);
			}
		});
	}
