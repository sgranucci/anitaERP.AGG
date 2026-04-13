	var proveedor_id;
	var nombreProveedor;
	var modalActivo;
	var id_activo_partida;
	var item_activo_partida;
	var ptr_partida;
	var array_capex_partida_monto = [];
	
	$(function () {
		$('#agrega_renglon_capex_partida').on('click', agregaRenglonCapex_Partida);
        $(document).on('click', '.eliminar_capex_partida', borraRenglonCapex_Partida);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
		$(document).on('click', '#agregar_renglon_partida_monto', agregaRenglonPartidaMonto);
		$(document).on('click', '.eliminar_renglon_partida_monto', borraRenglonPartidaMonto);

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

			var wrapper = $(".capex-partida-monto-armado-table");

			$(wrapper).empty();
			
			// Debe armar tabla html para enviar los montos en el formulario
			for (var i = 0; i < array_capex_partida_monto.length; i++)
			{
				let monto = parseFloat(array_capex_partida_monto[i].monto);

				$(wrapper).append('<tr class="item-capex-partida-monto">'+
						'<td>'+
							'<input type="hidden" name="item_monto_armados[]" value="'+array_capex_partida_monto[i].item_monto+'">'+
							'<input type="hidden" name="capex_partida_id_monto_armados[]" value="'+array_capex_partida_monto[i].capex_partida_id_monto+'">'+
							'<input type="hidden" name="periodo_monto_armados[]" value="'+array_capex_partida_monto[i].periodo+'">'+
							'<input type="hidden" name="monto_armados[]" value="'+monto.toFixed(2)+'">'+
							'<input type="hidden" name="creousuario_id_monto_armados[]" value="'+array_capex_partida_monto[i].creousuario_id_monto+'">'+
						'</td>'+
					'</tr>');
			}

			$("#form-general").submit();

		});

		// Setea moneda total de todo el capex con la moneda de la primer partida
		let ptrUltimoRenglon = $("#tbody-capex-partida-table tr:last");
		let monedatotal_id = $(ptrUltimoRenglon).find('.moneda_id').val();

		$("#monedatotal_id").val(monedatotal_id).trigger('change');

		// Suma partidas
		sumaPartida();

		// Muestra boton de anulacion
		let estadoCapex = $('#estado').val();

		muestraBotonAnulacion(estadoCapex);

    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.carga_partida_monto').off('click');
			$('#monedatotal_id').off('change');
			$('.periodo').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultaproveedor();

		$('.carga_partida_monto').on('click', function (event) {
			event.preventDefault();
			
			// Carga el id y el item al cual pertecenen los montos que se van a cargar
			id_activo_partida = $(this).parents("tr").find('.capex_partida_id').val();
			item_activo_partida = $(this).parents("tr").find('.item').val();
			ptr_partida = this;

			if (item_activo_partida > 0)
			{
				$("#partidaMontoModal").modal('show');
			}
		});

		$('#monedatotal_id').on('change', function (event) {
			event.preventDefault();

			let monedatotal_id = $('#monedatotal_id').val();

			$('.moneda_id').each(function(){
				$(this).val(monedatotal_id);
			});
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
	}

	function agregaRenglonCapex_Partida(event){
		event.preventDefault();
		
		agregaUnRenglonCapex_Partida();
	}

	function agregaUnRenglonCapex_Partida()
	{
    	let renglon = $('#template-renglon-capex-partida').html();

    	$("#tbody-capex-partida-table").append(renglon);
    	actualizaRenglonesCapex_Partida();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-capex-partida-table tr:last");
		let monedatotal_id = $('#monedatotal_id').val();

		$(ptrUltimoRenglon).find('.moneda_id').val(monedatotal_id);
		$(ptrUltimoRenglon).find('.nombre').focus();

		activa_eventos(false);
    }

	function borraRenglonCapex_Partida(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCapex_Partida();
    }

    function actualizaRenglonesCapex_Partida() {
    	var item = 1;

    	$("#tbody-capex-partida-table .item").each(function() {
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
		let capex_id = $("#capex_id").val();

		let url = carpetaBase+'/presupuesto/leerhistoriacapex/'+capex_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				fecha = value.fecha;

				$(wrapper).append('<tr class="item-capex-historia">'+
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
		let capex_id = $("#capex_id").val();

		let url = carpetaBase+'/presupuesto/leerordencompra/'+capex_id;

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
					case '1':
						nombremoneda = 'PESOS';
						break;
					case '2':
						nombremoneda = 'DOLARES';
						break;						
					case '3':
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

	// Carga modal de montos de cada partida
	$(document).on('shown.bs.modal', '#partidaMontoModal', function() {
		var modal = $(this);

		modal.find('#tbody-capex-partida-monto-table').empty();

		modalActivo = "partidaMontoModal";

		var token = $('#csrf_token').val();

		// lee montos de la partida
		if (!buscaItemPartidaMonto())
			leeMontoPartida();
		else
		{
			for (var i = 0; i < array_capex_partida_monto.length; i++)
			{
				if (array_capex_partida_monto[i].item_monto == item_activo_partida)
				{
					agregaRenglonPartidaMonto();

					let monto = parseFloat(array_capex_partida_monto[i].monto);

					$('#capex-partida-monto-table').find('tr').last().find('.item_monto').val(array_capex_partida_monto[i].item_monto);
					$('#capex-partida-monto-table').find('tr').last().find('.capex_partida_id_monto').val(array_capex_partida_monto[i].capex_partida_id_monto);
					$('#capex-partida-monto-table').find('tr').last().find('.periodo').val(array_capex_partida_monto[i].periodo);
					$('#capex-partida-monto-table').find('tr').last().find('.monto').val(monto.toFixed(2));
					$('#capex-partida-monto-table').find('tr').last().find('.creousuario_id_monto').val(array_capex_partida_monto[i].creousuario_id_monto);
				}
			}
		}
	});
	
	function leeMontoPartida()
	{
		var wrapper = $(".container-partida-monto");

		let url = carpetaBase+'/presupuesto/leercapexpartidamonto/'+id_activo_partida;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				agregaRenglonPartidaMonto();

				let monto = parseFloat(value.monto);

				$('#capex-partida-monto-table').find('tr').last().find('.item_monto').val(item_activo_partida);
				$('#capex-partida-monto-table').find('tr').last().find('.capex_partida_id_monto').val(id_activo_partida);
				$('#capex-partida-monto-table').find('tr').last().find('.periodo').val(value.periodo);
				$('#capex-partida-monto-table').find('tr').last().find('.monto').val(monto.toFixed(2));
				$('#capex-partida-monto-table').find('tr').last().find('.creousuario_id_monto').val(value.creousuario_id);
			});

			// Agrega items a tabla en memoria
			agregaItemPartidaMonto();
		});
	}

	// Agrega renglon factura
    function agregaRenglonPartidaMonto(event){
		if (event != undefined)
			event.preventDefault();

        var renglon = $('#template-renglon-partida-monto').html();

		$("#tbody-capex-partida-monto-table").append(renglon);
		actualizaRenglonesPartidaMonto();

		activa_eventos(false);

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-capex-partida-monto-table tr:last");
		$(ptrUltimoRenglon).find('.periodo').focus();
    }

    function borraRenglonPartidaMonto(event) {
		if (event != undefined)
			event.preventDefault();

		let item_a_borrar = $(this).parents('tr').find('.item_monto').val();

		// Busca el item en la tabla para reemplazar los valores
		for (var i = 0; i < array_capex_partida_monto.length; i++)
		{
			if (array_capex_partida_monto[i].item_monto == item_a_borrar)
				array_capex_partida_monto.splice(i, 1)
		}

		setTimeout(() => {
			if (confirm("¿Desea borrar renglon?"))
			{
				$(this).parents('tr').remove();
				actualizaRenglonesPartidaMonto();
			}
		}, 300);
	}

	function actualizaRenglonesPartidaMonto() {
    	var item = 1;

    	$("#tbody-capex-partida-monto-table .item_monto").each(function() {
    		$(this).val(item++);
    	});
    }

	// Acepta modal
	$('#aceptaPartidaMontoModal').on('click', function () {

		// Al aceptar el modal reemplaza los valores anteriores por los nuevos pertenecientes a la partida
		agregaItemPartidaMonto();

		$('#partidaMontoModal').modal('hide');

		sumaMontoPartida(item_activo_partida);
	});

	function sumaMontoPartida(item)
	{
		// Calcula monto total de la partida
		let totalPartida = 0;

		for (var i = 0; i < array_capex_partida_monto.length; i++)
		{
			if (array_capex_partida_monto[i].item_monto == item)
				totalPartida += parseFloat(array_capex_partida_monto[i].monto);
		}

		$(ptr_partida).parents('tr').find('.montopartida').val(totalPartida.toFixed(2));

		sumaPartida();
	}

	function buscaItemPartidaMonto()
	{
		let flRetorno = false;

		// Busca el item en la tabla 
		for (var i = 0; i < array_capex_partida_monto.length; i++)
		{
			if (array_capex_partida_monto[i].item_monto == item_activo_partida)
				flRetorno = true;
		}
		return flRetorno;
	}
	
	function agregaItemPartidaMonto()
	{
		// Busca el item en la tabla para reemplazar los valores
		for (var i = 0; i < array_capex_partida_monto.length; i++)
		{
			if (array_capex_partida_monto[i].item_monto == item_activo_partida)
				array_capex_partida_monto.splice(i, 1)
		}
		// Agrega valores de la tabla
		$(".item_monto").each(function() {
			let periodo = $(this).parents('tr').find('.periodo').val();
			let monto = $(this).parents('tr').find('.monto').val();
			let creousuario_id_monto = $(this).parents('tr').find('.creousuario_id_monto').val();

			array_capex_partida_monto.push({"item_monto":item_activo_partida,"capex_partida_id_monto":id_activo_partida,"periodo":periodo,
											"monto":monto,"creousuario_id_monto":creousuario_id_monto});
		});
	}

	// Cierra modal medidas
	$('#cierraPartidaMontoModal').on('click', function () {
		$('#partidaMontoModal').modal('hide');
	});
	
	function anulaCapex()
	{
		if (confirm("¿Desea cambiar el estado del Capex?"))
		{
			let estadoActualCapex = $('#estado').val();

			switch(estadoActualCapex)
			{
				case 'ANULADO':
					$('#estado').val('ACTIVO');	
					break;
				case 'ACTIVO':
				case 'CERRADO':
					$('#estado').val('ANULADO');
					break;
			}

			// Actualiza estado de la orden de venta
			let estadoCapex = $('#estado').val();
			let capex_id = $('#capex_id').val();

			let listarUri = carpetaBase+"/presupuesto/actualizaestadocapex/"+estadoCapex+"/"+capex_id;

			$.get(listarUri)
				.done(function(data){
					alert('Capex actualizado con éxito');

					muestraBotonAnulacion(estadoCapex);
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
			let estadoCapex = $('#estado').val();
			let capex_id = $('#capex_id').val();

			let listarUri = carpetaBase+"/presupuesto/actualizaestadocapex/"+estadoCapex+"/"+capex_id;

			$.get(listarUri)
				.done(function(data){
					alert('Capex actualizado con éxito');

					muestraBotonAnulacion(estadoCapex);
				})
				.fail(function(jqXHR, textStatus, errorThrown) {
					alert("Error en la petición: "+textStatus+errorThrown);
					alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
				});
		}
	}

	function muestraBotonAnulacion(estadoCapex)
	{
		switch(estadoCapex)
		{
			case 'ANULADO':
				$('#anulacapex').hide();
				$('#activacapex').show();
				$('#abrecapex').hide();
				$('#cierracapex').hide();
				break;
			case 'CERRADO':
				$('#anulacapex').hide();
				$('#activacapex').hide();
				$('#abrecapex').show();
				$('#cierracapex').hide();				
				break;
			case 'ACTIVO':
				$('#anulacapex').show();
				$('#activacapex').hide();
				$('#abrecapex').hide();
				$('#cierracapex').show();				
				break;
		}
	}

	function sumaPartida()
	{
		let totalConcepto = 0;

		$("#tbody-capex-partida-table .montopartida").each(function() {
            let valor = parseFloat($(this).val());

			if (valor > 0)
				totalConcepto += (valor);
        });

		$('#totalpartida').val(totalConcepto.toFixed(2));
		$('#montototal').val(totalConcepto.toFixed(2));
	}

