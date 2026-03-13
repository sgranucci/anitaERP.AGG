	var proveedor_id;
	var nombreProveedor;
	var modalActivo;
	var id_activo_partida;
	var item_activo_partida;
	var ptr_partida;
	
	$(function () {
		$('#agrega_renglon_partidagasto_monto').on('click', agregaRenglonPartidagasto_Monto);
        $(document).on('click', '.eliminar_partidagasto_monto', borraRenglonPartidagasto_Monto);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		activa_eventos(true);
		
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

			// lee historia
			leeHistoria();

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
        });

		$("#botonform4").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			leeOrdenCompra();
        });

		$( ".botonsubmit" ).click(function() {

			$("#form-general").submit();

		});

		// Suma partidas
		sumaPartida();

		// Muestra boton de anulacion
		let estadoPartidaGasto = $('#estado').val();

		muestraBotonAnulacion(estadoPartidaGasto);
		
		completarEscenario();
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.periodo').off('change');
			$('.monto').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultaproveedor();
		activa_eventos_consultaarticulo();
		activa_eventos_consulta_cuentacontable();

		$('#presupuesto_id').on('change', function(event) {
			completarEscenario();
		});		

		$('.periodo').on('change', function(event) {
			var periodo = $(this).val();

			// Regex: 01-09 o 10-12, seguido de / o - y 4 dígitos de año
			//var regex = /^(0[1-9]|1[0-2])\/\d{4}$/; 
			var regex = /^\d{4}\-(0[1-9]|1[0-2])$/; 

			if (!regex.test(periodo) && periodo != '') {
				alert("Formato inválido. Use AAAA-MM");

				// Blanquea y retorna el foco
				$(this).val('');
				$(this).focus();
			}
		});

		$('.monto').on('change', function(event) {
			sumaPartida();
		});
	}

	function agregaRenglonPartidagasto_Monto(event){
		event.preventDefault();
		
		agregaUnRenglonPartidagasto_Monto();
	}

	function agregaUnRenglonPartidagasto_Monto()
	{
    	let renglon = $('#template-renglon-partidagasto-monto').html();

    	$("#tbody-partidagasto-monto-table").append(renglon);
    	actualizaRenglonesPartidagasto_Monto();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-partidagasto-monto-table tr:last");

		$(ptrUltimoRenglon).find('.periodo').focus();

		activa_eventos(false);
    }

	function borraRenglonPartidagasto_Monto(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesPartidagasto_Monto();
		sumaPartida();
    }

    function actualizaRenglonesPartidagasto_Monto() {
    	var item = 1;

    	$("#tbody-partidagasto-monto-table .item").each(function() {
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
		let partidagasto_id = $("#partidagasto_id").val();

		let url = '/anitaERP/public/presupuesto/leerhistoriapartidagasto/'+partidagasto_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				fecha = value.fecha;

				$(wrapper).append('<tr class="item-partidagasto-historia">'+
                            '<td>'+
                                '<input type="hidden" name="estadofechas[]" class="form-control estadofecha" value="'+value.fecha+'" readonly>'+
                                '<input type="date" name="estadocreated[]" class="form-control estadofecha" value="'+fecha.substring(0,10)+'" readonly>'+
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

	function leeOrdenCompra()
	{
		var wrapper = $(".container-ordencompra");
		let partidagasto_id = $("#partidagasto_id").val();

		let url = '/anitaERP/public/presupuesto/leerordencompra/'+partidagasto_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				let total = parseFloat(value.total);
				let cotizacion = parseFloat(value.cotizacion);
				let nombremoneda;
				let fecha = value.fechaordencompra;
				let anio = fecha.substring(0, 4);
				let mes = fecha.substr(4, 2);
				let dia = fecha.substr(6, 2);
				let fechaFormateada = dia+"/"+mes+"/"+anio;

				switch(value.moneda_id)
				{
					case 1:
						nombremoneda = 'PESOS';
						break;
					case 2:
						nombremoneda = 'DOLARES';
						break;						
					case 3:
						nombremoneda = 'EUROS';
						break;
					default:
						nombremoneda = 'PESOS';
						break;
				}

				$(wrapper).append(
					    '<tr class="item-ordenventa-comprobante">'+
							'<td>'+
								'<input type="text" name="fechaordencompra[]" class="form-control comprobante" value="'+fechaFormateada+'" readonly/>'+
							'</td>'+							
							'<td>'+
								'<input type="text" name="numeroordencompra[]" class="form-control fechafactura" value="'+value.movp_tipo+'-'+value.movp_nro+'" readonly>'+
							'</td>'+
							'<td>'+
								'<input type="text" name="proveedorordencompra[]" style="text-align: left;" class="form-control proveedorordencompra" value="'+value.nombreproveedor+'" readonly>'+
							'</td>'+  
							'<td>'+
								'<input type="text" style="text-align: left;" name="mesordencompra[]" class="form-control mesordencompra" value="'+value.mes+'" readonly>'+
							'</td>'+	
							'<td>'+
								'<input type="text" style="text-align: left;" name="monedaordencompra[]" class="form-control monedaordencompra" value="'+nombremoneda+'" readonly>'+
							'</td>'+								
							'<td>'+
								'<input type="text" style="text-align: right;" name="cotizacionordencompra[]" class="form-control cotizacionordencompra" value="'+cotizacion.toFixed(2)+'" readonly>'+
							'</td>'+													
							'<td>'+
								'<input type="text" style="text-align: right;" name="montoordencompra[]" class="form-control montoordencompra" value="'+total.toFixed(2)+'" readonly>'+
							'</td>'+
							'<td>'+
								'<input type="text" name="detalleordencompra[]" class="form-control detalleordencompra" value="'+value.stkm_desc+'" readonly>'+
							'</td>'+
							'<td>'+
								'<a href="#" class="btn-accion-tabla tooltipsC editaordencompra" title="Editar la Orden de Compra">'+
									'<i class="fa fa-edit editaordencompra"></i>'+
								'</a>'+
								'<a href="#" class="btn-accion-tabla tooltipsC listaordencompra" title="Listar la Orden de Compra">'+
									'<i class="fa fa-print"></i>'+
								'</a>'+
							'</td>'+
						'</tr>');

				activa_eventos(false);
			});
		});
	}

	function anulaPartidaMonto()
	{
		if (confirm("¿Desea cambiar el estado de la Partida?"))
		{
			let estadoActualCapex = $('#estado').val();

			switch(estadoActualCapex)
			{
				case 'ANULADA':
					$('#estado').val('ACTIVA');	
					break;
				case 'ACTIVA':
				case 'CERRADA':
					$('#estado').val('ANULADA');
					break;
			}

			// Actualiza estado de la orden de venta
			let estadoPartidaGasto = $('#estado').val();
			let partidagasto_id = $('#partidagasto_id').val();

			let listarUri = "/anitaERP/public/presupuesto/actualizaestadopartidagasto/"+estadoPartidaGasto+"/"+partidagasto_id;

			$.get(listarUri)
				.done(function(data){
					alert('Capex actualizado con éxito');

					muestraBotonAnulacion(estadoPartidaGasto);
				})
				.fail(function(jqXHR, textStatus, errorThrown) {
					alert("Error en la petición: "+textStatus+errorThrown);
					alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
				});
		}
	}

	function cierraCapex()
	{
		if (confirm("¿Desea cambiar el estado del Capex?"))
		{		
			let estadoActualCapex = $('#estado').val();

			if (estadoActualCapex != 'ACTIVO' && estadoActualCapex != 'CERRADO')
			{
				alert("No se puede cambiar el estado del Capex")
				return;
			}
			switch(estadoActualCapex)
			{
				case 'ACTIVO':
					$('#estado').val('CERRADO');
					break;
				case 'CERRADO':
					$('#estado').val('ACTIVO');
					break;				
			}

			// Actualiza estado de la orden de venta
			let estadoPartidaGasto = $('#estado').val();
			let partidagasto_id = $('#partidagasto_id').val();

			let listarUri = "/anitaERP/public/presupuesto/actualizaestadopartidagasto/"+estadoPartidaGasto+"/"+partidagasto_id;

			$.get(listarUri)
				.done(function(data){
					alert('Capex actualizado con éxito');

					muestraBotonAnulacion(estadoPartidaGasto);
				})
				.fail(function(jqXHR, textStatus, errorThrown) {
					alert("Error en la petición: "+textStatus+errorThrown);
					alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
				});
		}
	}

	function muestraBotonAnulacion(estadoPartidaGasto)
	{
		switch(estadoPartidaGasto)
		{
			case 'ANULADA':
				$('#anulapartidagasto').hide();
				$('#activapartidagasto').show();
				$('#abrepartidagasto').hide();
				$('#cierrapartidagasto').hide();
				break;
			case 'CERRADA':
				$('#anulapartidagasto').hide();
				$('#activapartidagasto').hide();
				$('#abrepartidagasto').show();
				$('#cierrapartidagasto').hide();				
				break;
			case 'ACTIVA':
				$('#anulapartidagasto').show();
				$('#activapartidagasto').hide();
				$('#abrepartidagasto').hide();
				$('#cierrapartidagasto').show();				
				break;
		}
	}

	function sumaPartida()
	{
		let totalConcepto = 0;

		$("#tbody-partidagasto-monto-table .monto").each(function() {
            let valor = parseFloat($(this).val());

			if (valor != 0)
				totalConcepto += (valor);
        });

		$('#totalpartida').val(totalConcepto.toFixed(2));
		$('#montototal').val(totalConcepto.toFixed(2));
	}

	function completarEscenario(){
		let presupuesto_id = $("#presupuesto_id").val();

		// Si marca boton de todas las combinaciones trae sin filtrar las activas o esta leyendo todos los articulos sin filtrar
		let url = '/anitaERP/public/presupuesto/leerescenario/'+presupuesto_id;

        $.get(url, function(data){
            let comb = $.map(data, function(value, index){
                return [value];
            });
			$("#presupuesto_escenario_id").empty();
            $.each(comb, function(index,value){
               	$("#presupuesto_escenario_id").append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
            });
        });
    }


