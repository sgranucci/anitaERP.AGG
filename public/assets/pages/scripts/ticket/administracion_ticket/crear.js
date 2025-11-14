var ticketTarea_id;
var nombreTareaTicket;
var ptrAbreNovedad;

	$(function () {
		$('#agrega_renglon_tarea_ticket').on('click', agregaRenglonTarea_Ticket);
        $(document).on('click', '.eliminar_tarea_ticket', borraRenglonTarea_Ticket);
		$('#agrega_renglon_ticket_articulo').on('click', agregaRenglonTicket_Articulo);
        $(document).on('click', '.eliminar_ticket_articulo', borraRenglonTicket_Articulo);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
		$('#agrega_renglon_tarea_novedad').on('click', agregaRenglonTareaNovedad);
        $(document).on('click', '.eliminar_tarea_novedad', borraRenglonTareaNovedad);

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

		$('#carga_tarea_novedad_Modal').on('shown.bs.modal', function () {
		})

		$('#aceptacarga_tarea_novedadModal').on('click', function () {
			let datosNovedades=[];

			$('#carga_tarea_novedad_Modal').modal('hide');

			// Graba las novedades
			$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				}
			});
		
			let url = "/anitaERP/public/ticket/guardar_ticket_tarea_novedad";

			// Arma tabla de novedades para grabar
			$("#table-tarea-novedad .item-tarea-novedad").each(function() {
				ticket_tarea_id = ticketTarea_id;
				tarea_novedad = $(this).find(".iitarea_novedad").val();
				ticket_tarea_novedad_id = $(this).find(".ticket_tarea_novedad_id").val();
				desdefecha = $(this).find(".desdefecha").val();
				hastafecha = $(this).find(".hastafecha").val();
				comentario = $(this).find(".comentario").val();
				estado = $(this).find(".estado").val();
				usuario_id = $(this).find(".usuario_id").val();

				datosNovedades.push({
					ticket_tarea_id,
					ticket_tarea_novedad_id,
					tarea_novedad,
					desdefecha,
					hastafecha,
					comentario,
					estado,  
					usuario_id  
					});
			});
			datosNovedades = JSON.stringify(datosNovedades);

			$.ajax({
				type: "POST",
				url: url,
				data: {
					datosNovedades: datosNovedades,
				},
				success: function (data) {
					if (data.mensaje == 'ok')
					{
						alert("Novedades grabadas con éxito");

						// Leer estados
						leeEstadoTarea();
					}
				},
				error: function (r) {
					alert("Error en grabación de las novedades");
				}
			});
		});

    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.abrenovedad').off('click');
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

		$('.abrenovedad').on('click', function (event) {
			let tareaTicket_id = $(this).parents("tr").find(".tarea_ticket_id").val();
			ticketTarea_id = $(this).parents("tr").find(".ticket_tarea_id").val();
			nombreTareaTicket = $(this).parents("tr").find(".nombretarea_ticket").val();
			ptrAbreNovedad = $(this).parents("tr").find(".abrenovedad");
			
			llenaModal();

			setTimeout(() => {
			}, 300);

			// Abre modal de consulta
			$("#carga_tarea_novedad_Modal").modal('show');

			$("#novedad_nombre").val("Id "+tareaTicket_id+" - "+nombreTareaTicket);
		});

		$('.finalizatarea').on('click', function (event) {
			let fechaFinalizacion = $(this).parents("tr").find(".fechafinalizacion").val();

			if (fechaFinalizacion.length != 0)
				alert("No puede dar nuevamente finalizacion de tarea");

			$(this).parents("tr").find(".tiempoinsumido").attr('readonly', false);
			$(this).parents("tr").find(".tiempoinsumido").attr('required', 'required');
			$(this).parents("tr").find(".tiempoinsumido").focus();
		});

		// Previene Enter en comentario de novedades
		$( ".comentario" ).on( "keydown", function( event ) {
			if ( event.key === "Enter" ) {
				event.preventDefault();
			}
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

			if (tiempoinsumido > 0)
			{
				$(this).parents("tr").find(".estadotarea").val("Finalizada");

				let url = '/anitaERP/public/ticket/finalizar_tarea/'+ticket_tarea_id+'/'+fechafinalizacion+'/'+tiempoinsumido;

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
    	$(this).parents('tr').remove();
    	actualizaRenglonesTarea_Ticket();
    }

    function actualizaRenglonesTarea_Ticket() {
    	var item = 1;

    	$("#tbody-tarea-ticket-table .iitarea_ticket").each(function() {
    		$(this).val(item++);
    	});
    }

	function agregaRenglonTareaNovedad(event){
    	event.preventDefault();

		agregaUnRenglonTareaNovedad();
	}

	function agregaUnRenglonTareaNovedad()
	{
    	let renglon = $('#template-renglon-tarea-novedad').html();

    	$("#tbody-tarea-novedad-table").append(renglon);
    	actualizaRenglonesTareaNovedad();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-tarea-novedad-table tr:last");
		$(ptrUltimoRenglon).find('.desdefecha').focus();

		activa_eventos(false);
    }

	function borraRenglonTareaNovedad(event) {
    	event.preventDefault();
		$(this).parents('tr').find('.eliminar_tarea_novedad').attr('title','sss');
    	$(this).parents('tr').remove();
    	actualizaRenglonesTareaNovedad();
    }

    function actualizaRenglonesTareaNovedad() {
    	var item = 1;

    	$("#tbody-tarea-novedad-table .iitarea_novedad").each(function() {
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

	function llenaModal() {
		var wrapper = $(".container-novedad");

		ticket_tarea_id = ticketTarea_id;
		let url = '/anitaERP/public/ticket/leer_ticket_tarea_novedad/'+ticket_tarea_id;
	
		$.get(url, function(novedades){

			$(wrapper).empty();
			let offset = 0;

			var nov = $.map(novedades, function(value, index){
				return [value];
			});
			$.each(nov, function(index,value){
				desdeFecha = value.desdefecha;
				hastaFecha = value.hastafecha;
				offset = offset + 1;

				$(wrapper).append('<tr class="item-tarea-novedad">'+
									'<td>'+
										'<input type="hidden" name="tarea_novedad[]" class="form-control iitarea_novedad" value="'+offset+'">'+
										'<input type="hidden" name="ticket_tarea_ids[]" class="ticket_tarea_id" value="'+value.ticket_tarea_id+'">'+
										'<input type="hidden" name="ids[]" class="ticket_tarea_novedad_id" value="'+value.id+'">'+
										'<input type="date" name="desdefechas[]" class="desdefecha" value="'+desdeFecha.substring(0,10)+'">'+
									'</td>'+
									'<td>'+
										'<input type="date" name="hastafechas[]" class="hastafecha" value="'+hastaFecha.substring(0,10)+'">'+
									'</td>'+
									'<td>'+
										'<input type="text" style="WIDTH: 450px;HEIGHT: 29px" name="comentarios[]" class="comentario" value="'+value.comentario+'">'+
									'</td>	'+	
									'<td>'+
										'<input type="hidden" name="estadohidden[]" class="estadohidden" value="'+value.estado+'">'+
										'<div class="form-group row">'+
											'<select name="estados[]" style="WIDTH: 170px;HEIGHT: 29px" class="estado" required>'+
												'<option value="">-- Elija estado --</option>'+
											'</select>'+
										'</div>'+
									'</td>'+	
									'<td>'+
										'<input type="text" style="WIDTH: 80px;HEIGHT: 29px" name="nombreusuarios[]" class="nombreusuario" value="'+value.usuarios.usuario+'" readonly>'+
									'</td>'+																			
									'<td>'+
										//'<button style="width: 6%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tarea_novedad tooltipsC">'+
										//	'<i class="fa fa-times-circle text-danger"></i>'+
										//'</button>'+
										'<input type="hidden" name="usuario_ids[]" class="form-control usuario_id" value="'+value.usuario_id+'" />'+
									'</td>'+
								'</tr>'
							);
			});

			// Rellena select de estado
			$("#table-tarea-novedad .item-tarea-novedad").each(function() {
				armaSelectEstado(this);
			});

		});
	}

	function armaSelectEstado(ptrrenglon)
	{
		var select = $(ptrrenglon).find('.estado');
		var estado = $(ptrrenglon).find('.estadohidden').val();
		var estadoEnum = JSON.parse($("#estado_novedad_enum").val());
	
		select.empty();
		select.append('<option value="">-- Seleccionar Estado --</option>');

		estadoEnum.forEach(function(est, indice, array) {
			if (est.nombre != estado)
				select.append('<option value="'+est.nombre+'">'+est.nombre+'</option>');
			else
				select.append('<option value="'+est.nombre+'" selected>'+est.nombre+'</option>');
		});
	}

	function leeHistoria()
	{
		var wrapper = $(".container-historia");
		let ticket_id = $("#id").val();

		let url = '/anitaERP/public/ticket/leer_historia_ticket/'+ticket_id;

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

		// Rellena select de estado
		$("#tarea-ticket-table .item-tarea-ticket").each(function() {
			let ticket_tarea_id = $(this).find(".ticket_tarea_id").val();
			let ptrTarea = this;
			let ultimoEstado = '';

			// Busca estado de la tarea con la ultima novedad
			url = '/anitaERP/public/ticket/leer_ticket_tarea_novedad/'+ticket_tarea_id;
			
			$.get(url, function(novedades){

				var nov = $.map(novedades, function(value, index){
					return [value];
				});
				ultimoEstado = "Pendiente";
				$.each(nov, function(index,value){
					ultimoEstado = value.estado;
				});

				$(ptrTarea).find(".estadotarea").val(ultimoEstado);
			});
		});
	}

	function cambioTecnico(ticket_tarea_id, tecnico_ticket_id)
	{
		let url = '/anitaERP/public/ticket/cambiar_tecnico/'+ticket_tarea_id+'/'+tecnico_ticket_id;

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
			estadoTicket = 'Finalizado';

			// Verifica si tiene tareas
			$("#tarea-ticket-table .item-tarea-ticket").each(function() {
				let ticket_tarea_id = $(this).find('.ticket_tarea_id').val();
				let estadotarea = $(this).find('.estadotarea').val();
				let tiempoinsumido = $(this).find(".tiempoinsumido").val();

				if (tiempoinsumido > 0)
				{
					estadotarea = 'Finalizada';
					$(this).find(".estadotarea").val("Finalizada");
				}

				if (estadotarea != 'Finalizada')
					estadoTicket = 'Asignado';
			});		
			$('#estado_ticket').val(estadoTicket);
		}
	}
		


