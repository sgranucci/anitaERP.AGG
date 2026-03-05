	var ticketTarea_id;
	var nombreTareaTicket;
	var ptrAbreNovedad;
	var totalCuota;

	var cliente_id;
	var nombrecliente;
	var preciosfactura_txt=[]; 
	var titulofactura_txt=[];
	var offFactura;
	var modalActivo;
	var descuentoCliente;
	
	$(function () {
		$('#agrega_renglon_ordenventa_cuota').on('click', agregaRenglonOrdenventa_Cuota);
        $(document).on('click', '.eliminar_ordenventa_cuota', borraRenglonOrdenventa_Cuota);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
		$('#agrega_renglon_ordenventa_concepto').on('click', agregaRenglonOrdenventa_Concepto);
        $(document).on('click', '.eliminar_ordenventa_concepto', borraRenglonOrdenventa_Concepto);

		activa_eventos(true);
		sumaCuota();
		
		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".form6").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".form6").hide();

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
			$(".form6").hide();

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
			$(".form6").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			// lee arbol
			leeArbol();
        });
		
		$("#botonform6").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".form6").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			// lee comprobantes
			leeComprobante();
        });

		$('#puntoventa_id').on('change', function (event) {
			event.preventDefault();
			var puntoventa_id = $(this).val();

			var listarUri = "/anitaERP/public/ventas/leeunpuntoventa/"+puntoventa_id;

			$.get(listarUri, function(data){
				$("#actividad_arca_id").val(data.actividad_arca_id);

				if (data.actividad_arca_id > 0)
					$('#actividad_arca_id').attr('readonly', true);
				else
					$('#actividad_arca_id').attr('readonly', false);
			});
        });

		$("#botonaltacliente").click(function(event){
			event.preventDefault();

			let id = $('#ordenventa_id').val();

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
			{
				// Controla conceptos
				let totalConcepto = $("#totalconcepto").val();
				let controlConcepto = totalConcepto - totalOrdenventa;

				if (Math.abs(controlConcepto) > 0.09)
				{
					alert('No coincide total de conceptos con monto total de orden de venta');
					flError = true;
				}

				if (!flError)
					$("#form-general").submit();
			}
		});

		let cliente_id = $('#cliente_id').val();

		if (cliente_id == '')
			$(".boton-alta-cliente").show();
		else
			$(".boton-alta-cliente").hide();

		if (cliente_id > 0)
			$(".editarcliente").show();
		else
			$(".editarcliente").hide();
		let valorOriginal = $('#nombrecliente').val();

		setInterval(function() {
			let valorActual = $('#nombrecliente').val();
			if (valorActual !== valorOriginal) {

				valorOriginal = valorActual; // Actualiza para futuras comparaciones

				// Asigna atributo para editar cliente
				let urlEditarCliente = route('editar_cliente', ':id');
				let cliente_id = $('#cliente_id').val();

				let url = urlEditarCliente;
            	url = url.replace(':id', cliente_id);

				$(".editarcliente").attr("href", url);

				if (cliente_id > 0)
					$(".editarcliente").show();
				else
					$(".editarcliente").hide();
			}
		}, 500); // Revisa cada 500 milisegundos	

		// Suma conceptos
		sumaConcepto();

		// Muestra boton de anulacion
		let estadoOrdenVenta = $('#estado').val();

		muestraBotonAnulacion(estadoOrdenVenta);

    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.montofactura').off('change');
			$('.montoconcepto').off('change');
			$('.cantidadconcepto').off('change');
			$('.editacomprobante').off('click');
			$('.generanotadecredito').off('click');
			$('.listacomprobante').off('click');
			$('.listacobranza').off('click');
			$('.cobracomprobante').off('click');
		}

		// Activa eventos de consulta
		activa_eventos_consultacliente();

		$('.montofactura').on('change', function (event) {
			event.preventDefault();

			sumaCuota();
		});

		$('.montoconcepto').on('change', function (event) {
			event.preventDefault();

			sumaConcepto();
		});

		$('.cantidadconcepto').on('change', function (event) {
			event.preventDefault();

			sumaConcepto();
		});

		$('.editacomprobante').on('click', function (event) {
			event.preventDefault();
			
			let id = $(this).parents("tr").find('.idcomprobante').val();

			if (id > 0)
			{
				let urlConsultaComprobante = route('editar_factura', ':id');
				let url = urlConsultaComprobante;
				url = url.replace(':id', id);
				document.location.href=url;
			}
		});

		$('.listacomprobante').on('click', function (event) {
			event.preventDefault();
			
			let id = $(this).parents("tr").find('.idcomprobante').val();

			if (id > 0)
			{
				let urlConsultaComprobante = route('lista_una_factura', ':id');
				let url = urlConsultaComprobante;
				url = url.replace(':id', id);
				document.location.href=url;
			}
		});

		$('.listacobranza').on('click', function (event) {
			event.preventDefault();
			
			let stringCobranza = $(this).parents("tr").find('.cobranza').val();
			var arrayCobranza = stringCobranza.split(",");

			$.each(arrayCobranza, function(index, value) {
				if (value > 0)
				{
					let urlListadoCobranza = route('listar_una_cobranza', ':id');
					let url = urlListadoCobranza;
					url = url.replace(':id', value);
					document.location.href=url;
				}
			});
		});

		$('.generanotadecredito').on('click', function (event) {
			event.preventDefault();
			
			let id = $(this).parents("tr").find('.idcomprobante').val();

			if (id > 0)
			{
				let urlGeneraNotaDeCredito = route('generar_notadecredito', ':id');
				let url = urlGeneraNotaDeCredito;
				url = url.replace(':id', id);
				document.location.href=url;
			}
		});

		$('.cobracomprobante').on('click', function (event) {
			event.preventDefault();
			
			let id = $(this).parents("tr").find('.idcomprobante').val();

			if (id > 0)
			{
				let urlConsultaComprobante = route('crear_cobranza', ':id');
				let url = urlConsultaComprobante;
				url = url.replace(':id', id);
				document.location.href=url;
			}
		});		
	}

	function agregaRenglonOrdenventa_Concepto(event){
		event.preventDefault();
		
		agregaUnRenglonOrdenventa_Concepto();
	}

	function agregaUnRenglonOrdenventa_Concepto()
	{
    	let renglon = $('#template-renglon-ordenventa-concepto').html();

    	$("#tbody-ordenventa-concepto-table").append(renglon);
    	actualizaRenglonesOrdenventa_Concepto();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-ordenventa-concepto-table tr:last");
		$(ptrUltimoRenglon).find('.concepto_ordenventa_id').focus();

		activa_eventos(false);
		
    }

	function borraRenglonOrdenventa_Concepto(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesOrdenventa_Cuota();
		
		sumaCuota();

		sumaConcepto();
    }

    function actualizaRenglonesOrdenventa_Concepto() {
    	var item = 1;

    	$("#tbody-ordenventa-concepto-table .iiconcepto").each(function() {
    		$(this).val(item++);
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
		let ordenventa_id = $("#ordenventa_id").val();

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

	function leeArbol()
	{
		var wrapper = $(".container-arbol");
		let ordenventa_id = $("#ordenventa_id").val();

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

	function leeComprobante()
	{
		var wrapper = $(".container-comprobante");
		let ordenventa_id = $("#ordenventa_id").val();

		let url = '/anitaERP/public/ordenventa/leer_comprobantes_ordenventa/'+ordenventa_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				let total = parseFloat(value.total);
				let aplicado = parseFloat(value.aplicado);

				let aplicaciones = value.cliente_cuentacorrientes;

				// Arma string de las cobranzas de cada factura para luego poder imprimir los recibos
				let cobranzas = '';
				let separador = '';
				$.each(aplicaciones, function(index, value) {
					if (value.cobranza_id > 0)
					{
						cobranzas += separador + value.cobranza_id;
						separador = ',';
					}
				});

				$(wrapper).append(
					    '<tr class="item-ordenventa-comprobante">'+
        					'<td>'+
            					'<input type="text" name="idcomprobantes[]" class="form-control idcomprobante" value="'+value.id+'" readonly/>'+
        					'</td>'+
							'<td>'+
								'<input type="text" name="comprobantes[]" class="form-control comprobante" value="'+value.codigo+'" readonly/>'+
							'</td>'+							
							'<td>'+
								'<input type="date" name="fechafacturas[]" class="form-control fechafactura" value="'+value.fecha+'" readonly>'+
							'</td>'+
							'<td>'+
								'<input type="date" name="fechavencimientos[]" class="form-control fechavencimiento" value="'+value.fechavencimiento+'" readonly>'+
							'</td>'+
							'<td>'+
								'<input type="text" name="monedafacturas[]" style="text-align: right;" class="form-control monedafactura" value="'+value.moneda+'" readonly>'+
							'</td>'+  
							'<td>'+
								'<input type="text" style="text-align: right;" name="montofacturas[]" class="form-control montofactura" value="'+total.toFixed(2)+'" readonly>'+
							'</td>'+
							'<td>'+
								'<input type="text" name="estadofacturas[]" class="form-control estadofactura" value="'+
								(Math.abs(aplicado) >= total ? 'COBRADA' : 'IMPAGA')+'" readonly>'+
								'<input type="hidden" style="text-align: right;" name="cobranzas[]" class="form-control cobranza" value="'+cobranzas+'"'+
							'</td>'+
							'<td>'+
								'<a href="#" class="btn-accion-tabla tooltipsC editacomprobante" title="Editar el Comprobante">'+
									'<i class="fa fa-edit editacomprobante"></i>'+
								'</a>'+
								(total > 0 ?
								'<a href="#" class="btn-accion-tabla tooltipsC generanotadecredito" title="Genera Nota de Crédito">'+
									'<i class="fa fa-undo text-danger"></i>' : '')+
								'</a>'+								
								'<a href="#" class="btn-accion-tabla tooltipsC listacomprobante" title="Listar el Comprobante">'+
									'<i class="fa fa-print"></i>'+
								'</a>'+
								(Math.abs(aplicado) < total || Number.isNaN(aplicado) ?
								'<a href="#" class="btn-accion-tabla tooltipsC cobracomprobante" title="Cobrar el Comprobante">'+
									'<i class="fa fa-cash-register"></i>' : 
									'<a href="#" class="btn-accion-tabla tooltipsC listacobranza" title="Listar el Recibo">'+
									'<i class="fa fa-cash-register text-success"></i>')+
								'</a>'+
							'</td>'+
						'</tr>');

				activa_eventos(false);
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

		preciosfactura_txt = [];
		titulofactura_txt = [];
		pedido_articulo_ids = [];
		offFactura = 0;

		cliente_id = $("#cliente_id").val();
		nombrecliente = $("#nombrecliente").val();
		descuentoCliente = $('#descuento').val();

		setTimeout(() => {
			$("#facturarOrdenventaModal").modal('show');
		}, 300);
	}

	// Carga modal de facturacion
	$(document).on('shown.bs.modal', '#facturarOrdenventaModal', function() {
		var modal = $(this);

		//modal.find('#tbody-tabla-factura').empty();
		modal.find('#tbody-tabla-total-factura').empty();

		modalActivo = "facturarOrdenventaModal";

		let numeroOrdenventa = $('#numeroordenventa').val();
		let sel_puntoventa = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let sel_puntoventaremito = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let sel_puntoventa_facturacion = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa_facturacion);
		let selectPuntoVenta = $('#puntoventa_id');
		let selectPuntoVentaRemito = $('#puntoventaremito_id');
		let puntoVentaDefault = $('#puntoventadefault_id').val();
		let puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
		let sel_tipotransaccion = JSON.parse(document.querySelector('#datosfactura').dataset.tipotransaccion);
		let selectTipoTransaccion = $('#tipotransaccion_id');
		let tipoTransaccionDefault = $('#tipotransacciondefault_id').val();

		if (document.querySelector('#datosfactura').dataset.incoterm !== '')
		{
			var sel_incoterm = JSON.parse(document.querySelector('#datosfactura').dataset.incoterm);
			var selectIncoterm = $('#incoterm_id');
		}
			
		if (document.querySelector('#datosfactura').dataset.formapago !== '')
		{
			var sel_formapago = JSON.parse(document.querySelector('#datosfactura').dataset.formapago);
			var selectFormapago = $('#formapago_id');
		}
	
		const tiempoTranscurrido = Date.now();
		const hoy = new Date(tiempoTranscurrido);

		modal.find('#fechafactura').val(hoy.toISOString().substring(0,10));
		modal.find('#nombrecliente').val(nombrecliente);
		modal.find('.modal-title').text('Factura ORDEN DE VENTA '+numeroOrdenventa);
		modal.find('#descuentopie').val(descuentoCliente);

    	// Lee punto de venta si es de exportacion
		if (puntoVentaDefault > 0)
    		leePuntoVenta(puntoVentaDefault);

		// Arma select de tipos de transacciones
		selectTipoTransaccion.empty();
		selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
		$.each(sel_tipotransaccion, function(obj, item) {
			if (tipoTransaccionDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
			selectTipoTransaccion.append('<option value="' + item.id + '"'+op+'>' + item.abreviatura + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta
		selectPuntoVenta.empty();
		selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventa, function(obj, item) {
			if (puntoVentaDefault == item.codigo)
				op = 'selected="selected"';
			else
				op = '';
			selectPuntoVenta.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta del remito
		selectPuntoVentaRemito.empty();
		selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventaremito, function(obj, item) {
			if (puntoVentaRemitoDefault == item.codigo)
				op = 'selected="selected"';
			else
				op = '';
			selectPuntoVentaRemito.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de incoterms
		if (document.querySelector('#datosfactura').dataset.incoterm !== '')
		{
			selectIncoterm.empty();
			selectIncoterm.append('<option value="">-- Seleccionar incoterm --</option>');
			$.each(sel_incoterm, function(obj, item) {
				selectIncoterm.append('<option value="' + item.id + '">' + item.nombre + '</option>');
			});
		}

		// Arma select de formas de pago
		if (document.querySelector('#datosfactura').dataset.formapago !== '')
		{
			selectFormapago.empty();
			selectFormapago.append('<option value="">-- Seleccionar forma de pago --</option>');
			$.each(sel_formapago, function(obj, item) {
				selectFormapago.append('<option value="' + item.id + '">' + item.nombre + '</option>');
			});
		}

		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var tipotransaccion_id = $('#tipotransaccion_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var fechafactura = $('#fechafactura').val();
		var leyendafactura = $('#leyendafactura').val();
		var formapago_id = $('#formapago_id').val();
		var cliente_id = $('#cliente_id').val();
		let ordenventa_id = $('#ordenventa_id').val();
		let empresa_id = $('#empresa_id').val();
		let nombreempresa = $('#empresa_id').find('option:selected').text();

		// Asigna el punto de venta segun la empresa
		let offPuntoVenta = parseInt(empresa_id) - 1;

		$.each(sel_puntoventa, function(obj, item) {
			if (sel_puntoventa_facturacion[offPuntoVenta] == parseInt(item.codigo))
				$('#puntoventa_id').val(item.id);
		});

		$('#nombreempresa').val(nombreempresa);

		// Completa actividad
		var puntoventa_id = $("#puntoventa_id").val();

		var listarUri = "/anitaERP/public/ventas/leeunpuntoventa/"+puntoventa_id;

		$.get(listarUri, function(data){
			$("#actividad_arca_id").val(data.actividad_arca_id);

			if (data.actividad_arca_id > 0)
				$('#actividad_arca_id').attr('readonly', true);
			else
				$('#actividad_arca_id').attr('readonly', false);
		});

		// Calcula factura
		$.post("/anitaERP/public/ventas/calculafacturaporordenventa",
		{
			ordenventa_id: ordenventa_id,
			cliente_id: cliente_id,
			fechafactura: fechafactura,
			descuentopie: descuentopie,
			descuentolinea: 0,
			descuentoimportepie: descuentoimportepie,
			formapago_id: formapago_id,
			puntoventa_id: puntoventa_id,
			_token: token
		},
		function(data, status){
			if (data.error)
				alert(data.error);
			else
			{
				if (data.numerocuota > 0)
				{
					$('#numerocuota').val('Cuota Nro.: '+data.numerocuota+' de '+data.cantidadcuota);
				}

				$.each(data.conceptostotales, function(index, item) {
					// index es la posición del elemento en el array
					// item es el elemento en sí
					if (item.importe != 0)
					{
						agregaRenglonTotalFactura();

						$('#total-factura-ordenventa-table').find('tr').last().find('.conceptototal').val(item.concepto);
						if (item.tasa > 0)
							$('#total-factura-ordenventa-table').find('tr').last().find('.tasatotal').val(parseFloat(item.tasa).toFixed(2));

						$('#total-factura-ordenventa-table').find('tr').last().find('.importetotal').val(item.importe.toFixed(2));

						if (item.concepto == "Total")
						{
							$('#montototalfactura').val(item.importe.toFixed(2));
							$('#total-factura-ordenventa-table').find('tr').last().find('.conceptototal').css('fontWeight', 'bold');
							$('#total-factura-ordenventa-table').find('tr').last().find('.importetotal').css('fontWeight', 'bold');
						}
					}
				});
				$('.tasatotal').css('text-align', 'right');
				$('.importetotal').css('text-align', 'right');
			}
		});

	});

	// Agrega renglon factura
    function agregaRenglonFactura(){
        var renglon = $('#template-renglon-factura').html();

		$("#tbody-tabla-factura").append(renglon);
    }

	// Agrega renglon totales de factura
    function agregaRenglonTotalFactura(){
        var renglon = $('#template-renglon-total-factura').html();

		$("#tbody-tabla-total-factura").append(renglon);
    }

	// Acepta modal
	$('#aceptaFacturarOrdenTrabajoModal').on('click', function () {
		// Factura el item
		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var tipotransaccion_id = $('#tipotransaccion_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var descuentolinea = $('#descuentolinea').val();
		var fechafactura = $('#fechafactura').val();
		var leyendafactura = $('#leyendafactura').val();
		var formapago_id = $('#formapago_id').val();
		let ordenventa_id = $('#ordenventa_id').val();
		var cliente_id = $('#cliente_id').val();
		let numeroOrdenventa = $('#numeroordenventa').val();
		let centrocosto = $('select[name="centrocosto_id"] option:selected').text();
		let codigocentrocosto = centrocosto.split("-");
		let centrocosto_id = $('#centrocosto_id').val();
		let actividad_arca_id = $('#actividad_arca_id').val();

		if (actividad_arca_id == '')
		{
			alert('No puede facturar sin asignar actividad ARCA');
			$('#facturarOrdenventaModal').modal('hide');
			return;
		}
		
		if (tipotransaccion_id == '')
		{
			alert("No puede facturar sin elegir tipo de transacción");
			$('#facturarOrdenventaModal').modal('hide');
			return;
		}

		$('#facturarOrdenventaModal').modal('hide');

		$.post("/anitaERP/public/ventas/facturarordenventa",
				{
					ordenventa_id: ordenventa_id,
					codigocentrocosto: codigocentrocosto[0],
					centrocosto_id: centrocosto_id,
					cliente_id: cliente_id,
					tipotransaccion_id: tipotransaccion_id,
					puntoventa_id: puntoventa_id,
					fechafactura: fechafactura,
					descuentopie: descuentopie,
					descuentoimportepie: descuentoimportepie,
					descuentolinea: descuentolinea,
					leyendafactura: leyendafactura,
					formapago_id: formapago_id,
					numeroordenventa: numeroOrdenventa,
					actividad_arca_id: actividad_arca_id,
					_token: token
				},
				function(data, status){
					if (data.error != '')
                 	   alert(data.error);
                	else
                	{
						$('#estado').val('FACTURADA');
						$('#venta_id').val(data.venta_id);

						// Agrega renglon de historia
						leeHistoria();

						// Agrega factura a solapa de comprobantes
						leeComprobante();

						alert("Factura Número: " + data.factura + "\nEstado: " + status);
					}
				});
	});

	// Cierra modal medidas
	$('#cierraFacturarOrdenTrabajoModal').on('click', function () {
		tallesfactura_txt = [];
		medidasfactura_txt = [];
		preciosfactura_txt = [];
		tallesidfactura_txt = [];
		titulofactura_txt = [];
		offFactura = 0;
		$('#facturarOrdenventaModal').modal('hide');
	});
	
	function listaFactura()
	{
	  	let venta_id = $("#venta_id").val();

		let listarUri = "/anitaERP/public/ventas/listaunafactura"+"/"+venta_id;
		document.location.href= listarUri;				
	}

	function cobraFactura()
	{
		$('#estado').val('COBRADA');
	}	

	function anulaOrdenVenta()
	{
		let estadoActualOrdenVenta = $('#estado').val();

		if (estadoActualOrdenVenta != 'ANULADA' && estadoActualOrdenVenta != 'PENDIENTE')
		{
			alert("No se puede cambiar el estado de la orden de venta")
			return;
		}
		switch(estadoActualOrdenVenta)
		{
			case 'ANULADA':
				$('#estado').val('PENDIENTE');	
				break;
			case 'PENDIENTE':
				$('#estado').val('ANULADA');
				break;
		}

		// Actualiza estado de la orden de venta
		let estadoOrdenVenta = $('#estado').val();
		let ordenventa_id = $('#ordenventa_id').val();

		let listarUri = "/anitaERP/public/ordenventa/actualizasoloordenventa/"+estadoOrdenVenta+"/"+ordenventa_id;

		$.get(listarUri)
			.done(function(data){
				alert('Orden de Venta actualizada con éxito');

				muestraBotonAnulacion(estadoOrdenVenta);
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				alert("Error en la petición: "+textStatus+errorThrown);
				alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
			});
	}

	function muestraBotonAnulacion(estadoOrdenVenta)
	{
		switch(estadoOrdenVenta)
		{
			case 'ANULADA':
				$('#anulaordenventa').html('<i class="fas fa-check"></i>Activar la Orden de Venta');
				$( "#anulaordenventa" ).css( "background-color", "green" ); 
				break;
			case 'PENDIENTE':
				$('#anulaordenventa').html('<i class="fas fa-cross"></i>Anular la Orden de Venta');
				$( "#anulaordenventa" ).css( "background-color", "yellow" ); 
				break;
		}
	}

	function sumaConcepto()
	{
		let totalConcepto = 0;

		$("#tbody-ordenventa-concepto-table .montoconcepto").each(function() {
            let valor = parseFloat($(this).val());
			let cantidad = parseFloat($(this).parents("tr").find(".cantidadconcepto").val());

			if (valor > 0 && cantidad > 0)
				totalConcepto += (valor * cantidad);
        });

		$('#totalconcepto').val(totalConcepto.toFixed(2));
	}

