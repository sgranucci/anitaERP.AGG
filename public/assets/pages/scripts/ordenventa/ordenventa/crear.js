var ticketTarea_id;
var nombreTareaTicket;
var ptrAbreNovedad;
var totalCuota;

	$(function () {
		$('#agrega_renglon_ordenventa_cuota').on('click', agregaRenglonOrdenventa_Cuota);
        $(document).on('click', '.eliminar_ordenventa_cuota', borraRenglonOrdenventa_Cuota);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		activa_eventos(true);
		sumaCuota();
		
		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			let monto = $("#monto").val();

			$("#montoordenventa").val(monto);
        });
		$("#botonform3").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").show();
			$(".form4").hide();
			$(".form5").hide();

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
			$(".form5").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });

		$("#botonform5").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			// lee arbol
			leeArbol();
        });

		$("#botonaltacliente").click(function(event){
			event.preventDefault();

			let id = $('#id').val();

			let url = urlCreaCliente;
			url = url.replace(':id', id);
			document.location.href=url;

        });
		
		$( ".botonsubmit" ).click(function() {
			// Suma totales
			sumaCuota();

			let totalCuota = $("#totalcuota").val();
			let totalOrdenventa = $("#monto").val();
			let flError = false;
			let total = totalCuota - totalOrdenventa;

			if (Math.abs(total) > 0.09)
			{
				alert('No coincide total de cuotas a facturar con monto total de orden de venta');
				flError = true;
			}
	
			if (!flError)
				$( "#form-general" ).submit();
		});

		let cliente_id = $('#cliente_id').val();

		if (cliente_id == '')
			$(".boton-alta-cliente").show();
		else
			$(".boton-alta-cliente").hide();
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.montofactura').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultacliente();

		$('.montofactura').on('change', function (event) {
			event.preventDefault();

			sumaCuota();
		});

	}

	function agregaRenglonOrdenventa_Cuota(event){
		event.preventDefault();
		
		agregaUnRenglonOrdenventa_Cuota();
	}

	function agregaUnRenglonOrdenventa_Cuota()
	{
    	let renglon = $('#template-renglon-ordenventa-cuota').html();

    	$("#tbody-ordenventa-cuota-table").append(renglon);
    	actualizaRenglonesOrdenventa_Cuota();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-ordenventa-cuota-table tr:last");
		$(ptrUltimoRenglon).find('.fechafactura').focus();

		activa_eventos(false);
    }

	function borraRenglonOrdenventa_Cuota(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesOrdenventa_Cuota();
		
		sumaCuota();
    }

    function actualizaRenglonesOrdenventa_Cuota() {
    	var item = 1;

    	$("#tbody-ordenventa-cuota-table .iicuota").each(function() {
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
		let ordenventa_id = $("#id").val();

		let url = '/anitaERP/public/ordenventa/leer_historia_ordenventa/'+ordenventa_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				fecha = value.fecha;

				$(wrapper).append('<tr class="item-ordenventa-historia">'+
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

	function leeArbol()
	{
		var wrapper = $(".container-arbol");
		let ordenventa_id = $("#id").val();

		let url = '/anitaERP/public/arbolaprobacion/leer_movimiento_aprobacion/OV/'+ordenventa_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				fecha = value.fechaenvio;
				if (value.fechaproceso != null)
					fechaproceso = value.fechaproceso;
				else	
					fechaproceso = '';

				$(wrapper).append('<tr class="item-ordenventa-arbol">'+
                            '<td>'+
                                '<input type="datetime-local" class="form-control arbolfecha" value="'+fecha.substring(0,19)+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" class="form-control estadousuario" value="'+value.enviousuarios.nombre+'" readonly>'+
                            '</td>'+	
                            '<td>'+
                                '<input type="text" class="form-control nivel" value="'+value.nivel+'" readonly>'+
                            '</td>'+													
                            '<td>'+
                                '<input type="text" class="form-control estado" value="'+value.estado+'" readonly>'+
                            '</td>'+
							(fechaproceso == '' ? 
                            '<td>'+
                                '<input type="text" class="form-control arbolfecha" value="" readonly>'+
                            '</td>' :
							'<td>'+
                                '<input type="datetime-local" class="form-control arbolfecha" value="'+fechaproceso.substring(0,19)+'" readonly>'+
                            '</td>'
							)+			
                            '<td>'+
                                '<input type="text" class="form-control destinatariousuario" value="'+value.destinatariousuarios.nombre+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" class="form-control estadoobservacion" value="'+value.observacion+'" readonly>'+
                            '</td>'+
                        '</tr>');
			});
		});
	}

	function sumaCuota()
	{
		var wrapper = $(".totales-por-cuota");
		let descripcionMoneda = $('select[name="moneda_id"] option:selected').text();
		let detalleLabel = 'Total '+descripcionMoneda;

		// Inicializa total
		totalCuota = 0;

		$("#tbody-ordenventa-cuota-table .montofactura").each(function() {
            let valor = parseFloat($(this).val());

			totalCuota += valor;
        });

		$(wrapper).empty();

		if (totalCuota != 0)
		{
			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');
			if (totalCuota == 0)
				$(wrapper).append('<input type="text" id="totalcuota" name="totalcuota" class="form-control col-lg-2" readonly value="" />');
			else
				$(wrapper).append('<input type="text" id="totalcuota" name="totalcuota" class="form-control col-lg-2" readonly value="'+totalCuota.toFixed(2)+'" />');
		}
	}

	function generaFactura()
	{
		flFactura = true;
		//$("#generaFactura").show();

		$('#estado').val('FACTURADA');
	}

	function cobraFactura()
	{

		$('#estado').val('COBRADA');
	}	


