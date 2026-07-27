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

		activa_eventos(true);
		leeEstadoTarea();
		calculaEstadoTicket();

		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
			$(".form3").hide();
			$(".form4").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform3").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").show();
			$(".form4").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			// lee historia
			leeHistoria();
        });

		$("#botonform4").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").show();

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
			$('.finalizatarea').off('click');
			$('.tiempoinsumido').off('change');
			$('.nombretecnico_ticket').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultatarea_ticket();
		activa_eventos_consultatecnico_ticket();
		activa_eventos_consultacategoria_ticket();
		activa_eventos_consultasubcategoria_ticket();
		activa_eventos_consultaarticulo();

		$(document).on('change', '.nombretecnico_ticket', function (event) {
			event.preventDefault();
			let tareaTicket_id = $(this).parents("tr").find(".tarea_ticket_id").val();
			let tecnicoTicket_id = $(this).parents("tr").find(".tecnico_ticket_id").val();
		});

		$('.finalizatarea').on('click', function (event) {
			let fechaFinalizacion = $(this).parents("tr").find(".fechafinalizacion").val();

			if (fechaFinalizacion.length != 0)
				alert("No puede dar nuevamente finalizacion de tarea");

			$(this).parents("tr").find(".tiempoinsumido").attr('readonly', false);
			$(this).parents("tr").find(".tiempoinsumido").attr('required', 'required');
			$(this).parents("tr").find(".tiempoinsumido").focus();
		});

		$(document).on('change', '.tiempoinsumido', function (event) {
			let fecha = new Date();
			let day = fecha.getDate();
			let month = fecha.getMonth() + 1;
			let year = fecha.getFullYear();

			if (month < 10)
				var formateada = year + '-0' + month + '-' + day;
			else
				var formateada = year + '-' + month + '-' + day;

			$(this).parents("tr").find(".fechafinalizacion").val(formateada);
			let ticket_tarea_id = $(this).parents("tr").find(".ticket_tarea_id").val();
			let fechafinalizacion = $(this).parents("tr").find(".fechafinalizacion").val();
			let tiempoinsumido = $(this).parents("tr").find(".tiempoinsumido").val();
			let $estado = $(this).parents("tr").find(".estadotarea");

			if (tiempoinsumido > 0)
			{
				$estado.val("Finalizada");
				$estado.attr('data-estado-previo', 'Finalizada');

				let url = carpetaBase+'/ticket/finalizar_tarea/'+ticket_tarea_id+'/'+fechafinalizacion+'/'+tiempoinsumido;

				$.get(url, function(data, textStatus){
					if (textStatus == 'success')
					{
						calculaEstadoTicket();
						alert('Tarea finalizada con éxito')
					}
					else
						alert('Ha ocurrido un error finalizando la tarea')
				});
			}
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
				let ultimoEstado = "Pendiente";
				$.each(nov, function(index,value){
					ultimoEstado = value.estado;
				});

				$estado.val(ultimoEstado);
				$estado.attr('data-estado-previo', ultimoEstado);
			});
		});
	}

	function cambioTecnico(ticket_tarea_id, tecnico_ticket_id)
	{
		let url = carpetaBase+'/ticket/cambiar_tecnico/'+ticket_tarea_id+'/'+tecnico_ticket_id;

		$.get(url, function(data, textStatus){
			if (textStatus == 'success')
				alert('Técnico reasignado')
			else
				alert('Ha ocurrido un error reasignando el técnico')
		});
	}

	function calculaEstadoTicket()
	{
		let estadoTicket = $('#estado_ticket').val();

		if (estadoTicket != 'Baja' && estadoTicket != 'Suspendido')
		{
			// Verifica si tiene tareas
			$("#tarea-ticket-table .item-tarea-ticket").each(function() {
				let ticket_tarea_id = $(this).find('.ticket_tarea_id').val();
				let estadotarea = $(this).find('.estadotarea').val();
				let tiempoinsumido = $(this).find(".tiempoinsumido").val();
				let fechafinalizacion = $(this).find(".fechafinalizacion").val();

				if (fechafinalizacion != '')
				{
					estadotarea = 'Finalizada';
					$(this).find(".estadotarea").val("Finalizada");
				}

				if (estadotarea != 'Finalizada')
					estadoTicket = 'Pendiente';
			});
			$('#estado_ticket').val(estadoTicket);
		}
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
