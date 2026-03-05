var cuentacajaxcodigo;
var nombrexcodigo;
var codigoxcodigo;
var totalDebe = 0;
var totalHaber = 0;
var totalDebeAsiento = 0;
var totalHaberAsiento = 0;
var totalMoneda=[];
var idMoneda=[];
var descripcionMoneda=[];
var flCrear;
var flModificaAsiento;
var totalFinalCobranza = 0;
var saldoFinalCobranza = 0;
   
    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);
		$('#agrega_renglon_cheque').on('click', agregaRenglonCheque);
        $(document).on('click', '.eliminar_cobranza_cheque', borraRenglonCheque);	
		$('#agrega_renglon_retencion').on('click', agregaRenglonRetencion);
        $(document).on('click', '.eliminar_cobranza_retencion', borraRenglonRetencion);	
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		flCrear = document.getElementById("crear");
		flModificaAsiento = false;

		buscaTipoTransaccionCaja();
		activa_eventos(true);

		const estado = $('#estado').val();

		if (estado == 'PRE CARGA')
			flModificaAsiento = true;

		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".formasientoexterno").hide();
			$(".form7").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".formasientoexterno").hide();
			$(".form7").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform3").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").show();
			$(".form4").hide();
			$(".form5").hide();
			$(".formasientoexterno").hide();
			$(".form7").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform4").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").show();
			$(".form5").hide();
			$(".formasientoexterno").hide();
			$(".form7").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform5").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").show();
			$(".formasientoexterno").hide();
			$(".form7").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");

			// lee historia
			leeHistoria();
        });
		$("#botonform6").click(function(){
			// Solo genera el asiento cuando se crea la operacion
			if (flCrear || flModificaAsiento)
				generaAsientoContable();

			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".formasientoexterno").show();
			$(".form7").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform7").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".formasientoexterno").hide();
			$(".form7").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });

		// revierte cobranza
		$("#botonrevertir").click(function(){
			$('#revertircobranzaModal').modal('show');
        });

		// revierte cobranza
		$("#botonconfirmar").click(function(){
			let estado = $('#estado').val();

			if (estado == "PRE CARGA")
			{
				$('#estado').val('CONFIRMADA');	

				//$( "#form-general" ).submit();
			}
        });

		$('#aceptarevertircobranzaModal').on('click', function () {

			$('#revertircobranzaModal').modal('hide');

			let url = '/anitaERP/public/caja/copiar_cobranza';

			$.post(url, {_token: $('input[name=_token]').val(), 
						id: $('#id').val(),
						fecha: $('#fechacopia').val(),
						revierte: 1}, function(data)
						{ 
							alert("COBRANZA REVERTIDA CORRECTAMENTE GENERO EL ID:"+data.caja_movimiento_id+" NUMERO: "+data.numerotransaccion); 
						});
    	});

		$('#cierrarevertircobranzaModal').on('click', function () {
			$('#revertircobranzaModal').modal('hide');
		});

		// Lee monedas
		$.get('/anitaERP/public/configuracion/leermoneda', function(data){
			var monedas = $.map(data, function(value, index){
				return [value];
			});
			$.each(monedas, function(index,value){
				idMoneda.push(value.id);
				descripcionMoneda[value.id] = value.abreviatura;
			});
		});

		// Verifica si envia parametro de factura a cobrar directo
		let venta_id = $('#venta_id').val();

		if (venta_id > 0)
			leeCuentaCorriente();

		// Muestra sumatoria de montos del ingreso egreso
		setTimeout(() => {
			sumaMonto();
			sumaMontoCheque();
			sumaMontoComprobante();
			sumaMontoRetencion();
			sumaCobranza();
		}, 300);

		if (cliente_id > 0)
			$(".editarcliente").show();
		else
			$(".editarcliente").hide();

		let valorOriginal = $('#nombrecliente').val();

		setInterval(function() {
			let valorActual = $('#nombrecliente').val();
			if (valorActual !== valorOriginal) {

				if (valorOriginal == '' && !(venta_id > 0))
					leeCuentaCorriente();
				else
				{
					if (valorOriginal != '')
						leeCuentaCorriente();
				}

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

		$('#empresa_id').focus();

		$( "#botonform0" ).click(function() {
			let flError = false;
	
			$("#tbody-cuenta-table .moneda").each(function() {
				if ($(this).val() === '')
				{
					alert("Debe ingresar moneda");
					flError = true;
				}
			});

			// Valida que no tenga pago de menos
			if (saldoFinalCobranza > 0)
			{
				alert("No puede grabar una cobranza con faltante");
				flError = true;
			}
	
			// Valida montos asiento
			if (!flError)
			{
				if (totalDebeAsiento == 0 && totalHaberAsiento == 0)
				{
					flModificaAsiento = true;
					generaAsientoContable();
				}

				sumaMontoAsiento();

				totalDebeAsiento = $("#totaldebeasiento").val();
				totalHaberAsiento = $("#totalhaberasiento").val();

				if (totalDebeAsiento != totalHaberAsiento || totalDebeAsiento == 0)
				{
					if (totalDebeAsiento != totalHaberAsiento)
						alert('Problemas en el asiento, no coincide el debe con el haber');

					flError = true;
					muestraVentanaAsiento();
				}
			}
		
			if (!flError)
			{
				// Controla total de la operacion contra el total del asiento
				if (totalDebe != 0 || totalHaber != 0)
				{
					let totalOperacion;

					if (totalDebe > totalHaber)
						totalOperacion = totalDebe;
					else
						totalOperacion = totalHaber;

					if (totalOperacion != totalDebeAsiento)
					{
						alert('Problemas en el asiento, no coincide el monto total de la operación con el asiento contable');
						flError = true;
						muestraVentanaAsiento();						
					}
				}
			}

			if (!flError)
			{
				if (controlaCentroCosto())
				{
					alert('No puede grabar sin cargar los centros de costo');
					muestraVentanaAsiento();
					flError = true;
				}
			}
	
			if (!flError)
				$( "#form-general" ).submit();
		});

		// Evita que enter ejecute algo
		$(document).on("keydown", "form", function(event) { 
			return event.key != "Enter";
		});		
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuenta').off('click');
			$('.consultacuentacaja').off('click');
			$('.codigo').off('change');
			$('.monto').off('change');
			$('.moneda').off('change');
			$('#empresa_id').off('change');
			$('#tipotransaccion_caja_id').off('change');
			$('#proveedor_id').off('change');
			$('#servicioterrestre_id').off('change');
			$('.tipocomision').off('change');
			$('.cotizacion').off('change');
			$('.montocheque').off('change');
			$('.monedacheque_id').off('change');
			$('.cotizacioncheque').off('change');			
			$('.montoretencion').off('change');
			$('.monedaretencion_id').off('change');
			$('.cotizacionretencion').off('change');
			$('.editarfactura').off('click');
			$('.checkaplicacion').off('change');
			$('.montoaplicadocomprobante').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultacliente();
		activa_eventos_consultabanco();

		$('#tipotransaccion_caja_id').on('change', function (event) {
			event.preventDefault();

			buscaTipoTransaccionCaja();
		});
		
		$('#empresa_id').on('change', function (event) {
			leeCuentaCorriente();
		});

		$('.codigo').on('change', function (event) {
			event.preventDefault();
			var codigo = $(this);
			var codigo_ant = $(this).parents("tr").find(".codigo_previo").val();
			var codigo_nuevo = codigo.val();
			let empresa_id = $('#empresa_id').val();

			let url_cta = '/anitaERP/public/caja/cuentacaja/leercuentacajaporcodigo/'+codigo_nuevo;

			$.get(url_cta, function(data){
				if (data.id > 0)
				{
					$(codigo).parents("tr").find('.cuentacaja_id').val(data.id);
					$(codigo).parents("tr").find(".cuentacaja_id_previa").val(data.id);
					$(codigo).parents("tr").find(".nombre").val(data.nombre);
					$(codigo).parents("tr").find(".moneda").val(data.moneda_id);
					
					flModificaAsiento = true;

					// Hace focus sobre el primer elemento de la tabla
					let ptrUltimoRenglon = $("#tbody-cuenta-table tr:last");
					$(ptrUltimoRenglon).find('.monto').focus();
				}
				else
				{
					alert("No existe la cuenta de caja");

					// Borra el renglon
					$(codigo).parents('tr').remove();
					return;
				}
			});
		});

		$('.consultacuentacaja').on('click', function (event) {
        	cuentacajaxcodigo = $(this).parents("tr").find(".cuentacaja_id");
			nombrexcodigo = $(this).parents("tr").find(".nombre");
			codigoxcodigo = $(this).parents("tr").find(".codigo");
			let empresa_id = $('#empresa_id').val();

        	// Abre modal de consulta
			if (empresa_id)
				$("#consultacuentacajaModal").modal('show');
			else	
				alert('Debe ingresar empresa');
    	});

		$('#consultacuentacajaModal').on('shown.bs.modal', function () {
			$(this).find('[autofocus]').focus();
		})

    	$('#aceptaconsultacuentacajaModal').on('click', function () {
        	$('#consultacuentacajaModal').modal('hide');
    	});

		$(document).on('click', '.eligeconsultacuentacaja', function () {
			var seleccion = $(this).parents("tr").children().html();
			var nombre = $(this).parents("tr").find(".nombre").html();
			var codigo = $(this).parents("tr").find(".codigo").html();
			var moneda_id = $(this).parents("tr").find(".moneda_id").html();
		
			// Asigna a grilla los valores devueltos por consulta
			$(cuentacajaxcodigo).val(seleccion);
			$(nombrexcodigo).val(nombre);
			$(codigoxcodigo).val(codigo);

			//* Asigna nueva cuentacaja
			$(cuentacajaxcodigo).parents("tr").find(".cuentacaja_id_previa").val($(cuentacajaxcodigo).val());
			$(cuentacajaxcodigo).parents("tr").find(".moneda").val(moneda_id);
		
			$('#consultacuentacajaModal').modal('hide');
			flModificaAsiento = true;

			// Hace focus sobre el primer elemento de la tabla
			let ptrUltimoRenglon = $("#tbody-cuenta-table tr:last");
			$(ptrUltimoRenglon).find('.monto').focus();
		});

		$('.monto').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			sumaMonto();
			flModificaAsiento = true;
		});

		$('.moneda').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			flModificaAsiento = true;
		});

		$('.cotizacion').on('change', function (event) {
			event.preventDefault();
			sumaMonto();
			flModificaAsiento = true;
		});

		$('.montocheque').on('change', function (event) {
			event.preventDefault();
			leeCotizacionCheque(this);
			sumaMontoCheque();
			flModificaAsiento = true;
		});

		$('.monedacheque_id').on('change', function (event) {
			event.preventDefault();
			leeCotizacionCheque(this);
			sumaMontoCheque();
			flModificaAsiento = true;
		});

		$('.cotizacioncheque').on('change', function (event) {
			event.preventDefault();
			sumaMontoCheque();
			flModificaAsiento = true;
		});

		$('.montoretencion').on('change', function (event) {
			event.preventDefault();
			leeCotizacionRetencion(this);
			sumaMontoRetencion();
			flModificaAsiento = true;
		});

		$('.monedaretencion_id').on('change', function (event) {
			event.preventDefault();
			leeCotizacionRetencion(this);
			sumaMontoRetencion();
			flModificaAsiento = true;
		});

		$('.cotizacionretencion').on('change', function (event) {
			event.preventDefault();
			sumaMontoRetencion();
			flModificaAsiento = true;
		});

		$('.checkaplicacion').on('change', function (event) {
			event.preventDefault();
			
			if ($(this).prop("checked"))
			{
				let saldo = $(this).parents("tr").find('.saldocomprobante').val();

				$(this).parents("tr").find('.montoaplicadocomprobante').val(saldo);
				$(this).parents("tr").find('.montoaplicadocomprobante').focus();
			}
			else
			{
				$(this).parents("tr").find('.montoaplicadocomprobante').val('');
			}

			// Actualiza saldo
			actualizaSaldoComprobante(this);

			// Suma el total aplicado del recibo
			sumaMontoComprobante();
		});		

		$('.montoaplicadocomprobante').on('change', function(event) {
			event.preventDefault();

			actualizaSaldoComprobante(this);

			// Suma el total aplicado del recibo
			sumaMontoComprobante();

			// Marca el comprobante
			$(this).parents("tr").find('.checkaplicacion').prop('checked', true);
		})
	}

	function actualizaSaldoComprobante(ptr)
	{
		let monto = $(ptr).parents("tr").find('.montocomprobante').val();
		let aplicado = $(ptr).parents("tr").find('.montoaplicadocomprobante').val();

		if (aplicado !== '')
			saldo = parseFloat(monto) - parseFloat(aplicado);
		else
			saldo = parseFloat(monto);

		$(ptr).parents("tr").find('.saldocomprobante').val(saldo.toFixed(2));
	}

	function muestraVentanaAsiento()
	{
		if (totalDebeAsiento == 0 && totalHaberAsiento == 0)
			generaAsientoContable();

		$(".form1").hide();
		$(".form2").hide();
		$(".form3").hide();
		$(".form4").hide();
		$(".form5").hide();
		$(".formasientoexterno").show();
		$(".form7").hide();
	}

    function agregaRenglonCuenta(event){
    	event.preventDefault();

		agregaUnRenglonCuenta();
	}

	function agregaUnRenglonCuenta()
	{
    	let renglon = $('#template-renglon-cuenta').html();
		let monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val();

    	$("#tbody-cuenta-table").append(renglon);
    	actualizaRenglonesCuenta();

		//let ptrUltimoRenglon = $("#tbody-cuenta-table").last().find('.moneda');

		// Lee cotizacion de la moneda
		//leeCotizacion(ptrUltimoRenglon);

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-cuenta-table tr:last");
		$(ptrUltimoRenglon).find('.codigo').focus();

		activa_eventos(false);

		flModificaAsiento = true;
    }

    function borraRenglonCuenta(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuenta();
		sumaMonto();
		flModificaAsiento = true;
    }

    function actualizaRenglonesCuenta() {
    	var item = 1;

    	$("#tbody-cuenta-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonCheque(event){
    	event.preventDefault();

    	let renglon = $('#template-renglon-cheque').html();
		let monedaDefault = $("#tbody-cobranza-cheque-table").children(':first').find('.moneda').val();

    	$("#tbody-cobranza-cheque-table").append(renglon);

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-cobranza-cheque-table tr:last");
		$(ptrUltimoRenglon).find('.fechapago').focus();

		activa_eventos(false);

		flModificaAsiento = true;
    }

    function borraRenglonCheque(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
		sumaMontoCheque();
		flModificaAsiento = true;
    }

    function actualizaRenglonesCuenta() {
    	var item = 1;

    	$("#tbody-cuenta-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonRetencion(event){
    	event.preventDefault();

    	let renglon = $('#template-renglon-retencion').html();

    	$("#tbody-cobranza-retencion-table").append(renglon);

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-cobranza-retencion-table tr:last");
		$(ptrUltimoRenglon).find('.retencion_cobranza_id').focus();

		activa_eventos(false);

		flModificaAsiento = true;
    }

    function borraRenglonRetencion(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
		sumaMontoRetencion();
		flModificaAsiento = true;
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

	function leeCotizacion(ptr)
	{
		let fecha = $('#fecha').val();
		let moneda_id = $(ptr).parents("tr").find('.moneda').val();

		if (moneda_id > 0)
		{
			let url_cot = '/anitaERP/public/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
			$.get(url_cot, function(data){
				$(ptr).parents("tr").find('.cotizacion').val(data.cotizacionventa);
				sumaMonto();
			});
		}
	}

	function leeCotizacionCheque(ptr)
	{
		let fecha = $('#fecha').val();
		let moneda_id = $(ptr).parents("tr").find('.monedacheque_id').val();

		if (moneda_id > 0)
		{
			let url_cot = '/anitaERP/public/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
			$.get(url_cot, function(data){
				$(ptr).parents("tr").find('.cotizacioncheque').val(data.cotizacionventa);
				sumaMontoCheque();
			});
		}
	}

	function leeCotizacionRetencion(ptr)
	{
		let fecha = $('#fecha').val();
		let moneda_id = $(ptr).parents("tr").find('.monedaretencion_id').val();

		if (moneda_id > 0)
		{
			let url_cot = '/anitaERP/public/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
			$.get(url_cot, function(data){
				$(ptr).parents("tr").find('.cotizacionretencion').val(data.cotizacionventa);
				sumaMontoRetencion();
			});
		}
	}

	function leeCuentaCorriente()
	{
		let cliente_id = $("#cliente_id").val();
		let empresa_id = $("#empresa_id").val();
		let venta_id = $('#venta_id').val();

		if (venta_id > 0)
			var url = '/anitaERP/public/ventas/cliente/consultadeuda/0/0/'+venta_id;
		else
			var url = '/anitaERP/public/ventas/cliente/consultadeuda/'+cliente_id+'/'+empresa_id;
	
		$('#tbody-comprobante-table').empty();

		$.get(url, function(data){
			$.each(data, function(index, item) {
				agregaRenglonComprobante();

				$('#comprobante-table').find('tr').last().find('.idventa').val(item.idventa);
				$('#comprobante-table').find('tr').last().find('.idcuentacorriente').val(item.idcuentacorriente);
				$('#comprobante-table').find('tr').last().find('.codigocomprobante').val(item.codigo);
				$('#comprobante-table').find('tr').last().find('.fechacomprobante').val(item.fecha);
				$('#comprobante-table').find('tr').last().find('.fechavencimientocomprobante').val(item.fechavencimiento);
				$('#comprobante-table').find('tr').last().find('.monedacomprobante').val(item.moneda_id);
				$('#comprobante-table').find('tr').last().find('.cotizacioncomprobante').val(item.cotizacion.toFixed(4));
				$('#comprobante-table').find('tr').last().find('.montocomprobante').val(parseFloat(item.total).toFixed(2));
				$('#comprobante-table').find('tr').last().find('.montoaplicadocomprobante').val(parseFloat(item.aplicado).toFixed(2));

				const saldo = parseFloat(item.total) - (item.aplicado === null ? 0 : parseFloat(item.aplicado));

				$('#comprobante-table').find('tr').last().find('.saldocomprobante').val(saldo.toFixed(2));

				let urlEditarFactura = route('editar_factura', ':id');
				let urlGenerarNotaDeCredito = route('generar_notadecredito', ':id');
				let urlListarFactura = route('lista_una_factura', ':id');

            	let url = urlEditarFactura;
            	url = url.replace(':id', item.idventa);

				$("#comprobante-table").find('tr').last().find('.editarfactura').attr("href", url);

            	let url2 = urlGenerarNotaDeCredito;
            	url2 = url2.replace(':id', item.idventa);

				$("#comprobante-table").find('tr').last().find('.generarnotadecredito').attr("href", url2);				

            	let url3 = urlListarFactura;
            	url3 = url3.replace(':id', item.idventa);

				$("#comprobante-table").find('tr').last().find('.listarfactura').attr("href", url3);

				// Asigna empresa y cliente
				if (venta_id > 0)
				{
					$('#empresa_id').val(item.empresa_id);
					$('#cliente_id').val(item.cliente_id);
					$('#nombrecliente').val(item.nombrecliente);
				}
			});
		}).done(function(data, textStatus, jqXHR) {
			activa_eventos(false);
		});
	}

	// Agrega renglon factura
    function agregaRenglonComprobante(){
        var renglon = $('#template-renglon-comprobante').html();

		$("#tbody-comprobante-table").append(renglon);
    }

	function sumaMontoComprobante()
	{
		let monedaDefault = $("#tbody-comprobante-table").children(':first').find('.monedacomprobante').val();
		var wrapper = $(".totales-por-comprobante");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-comprobante-table .montoaplicadocomprobante").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedacomprobante').val();
			let cotizacion = $(this).parents("tr").find('.cotizacioncomprobante').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			if (!Number.isNaN(valor))
				totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total a cobrar '+descripcionMoneda[moneda];

			if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0) 
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="hidden" name="monedacomprobantes[]" class="form-control col-lg-1" readonly value="'+moneda+'" />');
				$(wrapper).append('<input type="text" name="totalcomprobantes[]" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});

		sumaCobranza();
	}

	function sumaMonto()
	{
		let monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val();
		var wrapper = $(".totales-por-moneda");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cuenta-table .monto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total cuentas '+descripcionMoneda[moneda];

			if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0) 
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="text" name="totalcuentas[]" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});
		sumaCobranza();
	}

	function sumaMontoCheque()
	{
		let monedaDefault = $("#tbody-cobranza-cheque-table").children(':first').find('.monedacheque_id').val();
		var wrapper = $(".totales-por-moneda-cheque");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cobranza-cheque-table .montocheque").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedacheque_id').val();
			let cotizacion = $(this).parents("tr").find('.cotizacioncheque').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total cheques '+descripcionMoneda[moneda];

			if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0) 
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="text" name="totalcheques[]" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});
		sumaCobranza();
	}

	function sumaMontoRetencion()
	{
		let monedaDefault = $("#tbody-cobranza-retencion-table").children(':first').find('.monedaretencion_id').val();
		var wrapper = $(".totales-por-moneda-retencion");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cobranza-retencion-table .montoretencion").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedaretencion_id').val();
			let cotizacion = $(this).parents("tr").find('.cotizacionretencion').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total retenciones '+descripcionMoneda[moneda];

			if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0) 
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="text" name="totalretenciones[]" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});
		sumaCobranza();
	}

	function sumaCobranza()
	{
		var wrapper = $(".totales-cobranza");
		let monedaDefault = $("#tbody-comprobante-table").children(':first').find('.monedacomprobante').val();
		let flMovimientoMoneda = [];

		saldoFinalCobranza = 0;
		totalFinalCobranza = 0;

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
			flMovimientoMoneda[moneda] = false;
		});

		$("#tbody-comprobante-table .montoaplicadocomprobante").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedacomprobante').val();
			let cotizacion = $(this).parents("tr").find('.cotizacioncomprobante').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			if (!Number.isNaN(valor))
			{
				totalMoneda[moneda] += valor;
				flMovimientoMoneda[moneda] = true;
				saldoFinalCobranza += (valor * coef);
			}
        });

		$("#tbody-cuenta-table .monto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] -= valor;
			flMovimientoMoneda[moneda] = true;
			saldoFinalCobranza -= (valor * coef);
			totalFinalCobranza += (valor * coef);
        });

		$("#tbody-cobranza-cheque-table .montocheque").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedacheque_id').val();
			let cotizacion = $(this).parents("tr").find('.cotizacioncheque').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] -= valor;
			flMovimientoMoneda[moneda] = true;
			saldoFinalCobranza -= (valor * coef);
			totalFinalCobranza += (valor * coef);
        });

		$("#tbody-cobranza-retencion-table .montoretencion").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedaretencion_id').val();
			let cotizacion = $(this).parents("tr").find('.cotizacionretencion').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] -= valor;
			flMovimientoMoneda[moneda] = true;
			saldoFinalCobranza -= (valor * coef);
			totalFinalCobranza += (valor * coef);
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Saldo cobranza '+descripcionMoneda[moneda];

			if (flMovimientoMoneda[moneda])
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');

				$(wrapper).append('<input type="hidden" name="moneda_cobranza_ids[]" class="form-control col-lg-1" readonly value="'+moneda+'" />');
				if (totalMoneda[moneda] == 0)
					$(wrapper).append('<input type="text" name="totalcobranzas[]" class="form-control col-lg-1 totalcobranza" readonly value="" />');
				else
					$(wrapper).append('<input type="text" name="totalcobranzas[]" class="form-control col-lg-1 totalcobranza" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});

		// Agrega saldo final en moneda de la cobranza
		if (monedaDefault != null)
		{
			detalleLabel = 'Saldo final cobranza '+descripcionMoneda[monedaDefault];
			$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
			$(wrapper).append('<input type="text" name="saldofinalcobranzas[]" class="form-control col-lg-1 totalfinalcobranza" readonly value="'+saldoFinalCobranza.toFixed(2)+'" />');
		
			// Agrega total final en moneda de la cobranza
			detalleLabel = 'Total final cobranza '+descripcionMoneda[monedaDefault];
			$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
			$(wrapper).append('<input type="hidden" name="monedafinalcobranza_id" class="form-control col-lg-1" readonly value="'+monedaDefault+'" />');
			$(wrapper).append('<input type="text" name="totalfinalcobranza" class="form-control col-lg-1 totalfinalcobranza" readonly value="'+totalFinalCobranza.toFixed(2)+'" />');		
		}
	}

	function generaAsientoContable()
	{
		let token = $("meta[name='csrf-token']").attr("content");
		let datosCuentasCaja=[];
		let datosCuentasContables=[];
		let datosCheques=[];
		let datosRetenciones=[];
		let datosComprobantes=[];
		var wrapper = $(".container-asiento");
		let tipotransaccion_caja_id = $("#tipotransaccion_caja_id").val();
		let empresa_id = $('#empresa_id').val();

		if (!empresa_id)
		{
			alert("Debe asignar empresa");
			return;
		}

		if (!tipotransaccion_caja_id)
		{
			alert("Debe asignar tipo de transaccion de caja");
			return;
		}

		// Carga los comprobantes aplicados
		$("#comprobante-table .codigocomprobante").each(function() {
			moneda_ids = $(this).parents("tr").find(".monedacomprobante").val();

			montos = $(this).parents("tr").find(".montoaplicadocomprobante").val();
			cotizaciones = $(this).parents("tr").find(".cotizacioncomprobante").val();

			if (montos != 0)
				datosComprobantes.push({
					moneda_ids,
					montos,
					cotizaciones
				});			
		});
		datosComprobantes = JSON.stringify(datosComprobantes);

		// Genera datos de las cuentas de caja cargadas
		$("#cuenta-table .item-cuenta").each(function() {
			cuentacaja_ids = $(this).find(".cuentacaja_id").val();
			moneda_ids = $(this).find(".moneda").val();

			montos = $(this).find(".monto").val();

			debes = haberes = ' ';
			debes = $(this).find(".monto").val();

			cotizaciones = $(this).find(".cotizacion").val();
			observaciones = $(this).find(".observacion").val();

			datosCuentasCaja.push({
				cuentacaja_ids,
				moneda_ids,
				montos,
				debes,
				haberes,
				cotizaciones,
				observaciones
			});
		});
		datosCuentasCaja = JSON.stringify(datosCuentasCaja);

		// Agrega cheques
		$("#cobranza-cheque-table .fechapago").each(function() {
			moneda_ids = $(this).parents("tr").find(".monedacheque_id").val();

			montos = $(this).parents("tr").find(".montocheque").val();
			cotizaciones = $(this).parents("tr").find(".cotizacioncheque").val();

			datosCheques.push({
				moneda_ids,
				montos,
				cotizaciones
			});			
		});
		datosCheques = JSON.stringify(datosCheques);

		// Agrega retenciones
		$("#cobranza-retencion-table .retencion_cobranza_id").each(function() {
			cuenta_retencion_ids = $(this).parents("tr").find(".retencion_cobranza_id").val();
			moneda_ids = $(this).parents("tr").find(".monedaretencion_id").val();

			montos = $(this).parents("tr").find(".montoretencion").val();
			cotizaciones = $(this).parents("tr").find(".cotizacionretencion").val();

			datosRetenciones.push({
				cuenta_retencion_ids,
				moneda_ids,
				montos,
				cotizaciones
			});			
		});
		datosRetenciones = JSON.stringify(datosRetenciones);

		// Genera datos de las cuentas de caja contables actualmente cargadas
		if (!flModificaAsiento)
		{
			$("#cuenta-asiento-table .item-cuenta-asiento").each(function() {
				cuentacontable_ids = $(this).find(".cuentacontable_id").val();
				centrocostoasiento_ids = $(this).find(".centrocostoasiento").val();
				monedaasiento_ids = $(this).find(".monedaasiento").val();
				debeasientos = $(this).find(".debeasiento").val();
				haberasientos = $(this).find(".haberasiento").val();
				cotizacionasientos = $(this).find(".cotizacionasiento").val();
				observacionasientos = $(this).find(".observacionasiento").val();
				carga_cuentacontable_manuales = $(this).find(".carga_cuentacontable_manual").val();

				datosCuentasContables.push({
					cuentacontable_ids,
					centrocostoasiento_ids,
					monedaasiento_ids,
					debeasientos,
					haberasientos,
					cotizacionasientos,
					observacionasientos,
					carga_cuentacontable_manuales
				});
			});
		}
		datosCuentasContables = JSON.stringify(datosCuentasContables);
		
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		});
		
		let url = "/anitaERP/public/caja/generaasientocontable_cobranza";

		$.ajax({
			type: "POST",
			url: url,
			async: false,
			data: {
				tipotransaccion_caja_id: tipotransaccion_caja_id,
				empresa_id: empresa_id,
				datoscaja: datosCuentasCaja,
				datoscontables: datosCuentasContables,
				datoscheques: datosCheques,
				datosretenciones: datosRetenciones,
				datoscomprobantes: datosComprobantes
			},
			success: function (data) {
				if (data.mensaje == 'ok')
				{
					$(wrapper).empty();

					$.each(data.asiento, function(index,value){
						let nombreCuentaContable = value.nombre;
						let cuentaContableId = value.cuentacontable_id;
						let cuentaContableCodigo = value.codigo;
						let centroCosto = value.centrocosto_id;
						let monedaId = value.moneda_id;
						let cotizacion = value.cotizacion;
						let debe = value.debe;
						let haber = value.haber;
						let observacion = value.observacion;
						let cargaCuentacontableManual = value.carga_cuentacontable_manual;

						$(wrapper).append('<tr class="item-cuenta-asiento">'+
							'<td>'+
								'<div class="form-group row" id="cuentacontable">'+
								'<input type="hidden" name="cuenta[]" class="form-control iicuentacontable" readonly value="{{ $loop->index+1 }}" />'+
								'<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="'+cuentaContableId+'" >'+
								'<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="'+cuentaContableId+'" >'+
								'<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">'+
									'<i class="fa fa-search text-primary"></i>'+
								'</button>'+
								'<input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoasiento form-control" name="codigoasientos[]" value="'+cuentaContableCodigo+'" >'+
								'<input type="hidden" class="codigo_previo_cuentacontable" name="codigo_previo_cuentacontables[]" value="" >'+
								'<input type="hidden" class="carga_cuentacontable_manual" name="carga_cuentacontable_manuales[]" value="'+cargaCuentacontableManual+'" >'+
								'</div>'+
							'</td>'+				
                        	'<td>'+
                            	'<input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="'+nombreCuentaContable+'" readonly>'+
                        	'</td>'+
                        	'<td>'+
                            	'<select name="centrocostoasiento_ids[]" data-placeholder="Centro de costo" class="centrocostoasiento form-control" data-fouc>'+
                            	'</select>'+
                            	'<input type="hidden" class="centrocostoasiento_id_previo" name="centrocostoasiento_id_previo[]" value="'+centroCosto+'" >'+
                        	'</td>'+
							'<td>'+
								'<select name="monedaasiento_ids[]" data-placeholder="Moneda" class="monedaasiento form-control required" required data-fouc>'+
								'</select>'+
								'<input type="hidden" class="monedaasiento_id_previo" name="monedaasiento_id_previo[]" value="'+monedaId+'" >'+
							'</td>'+
							'<td>'+
								'<input type="number" style="text-align: right;" name="debeasientos[]" class="form-control debeasiento" value="'+debe+'">'+
							'</td>'+
							'<td>'+
								'<input type="number" style="text-align: right;" name="haberasientos[]" class="form-control haberasiento" value="'+haber+'">'+
							'</td>'+
							'<td>'+
								'<input type="number" style="text-align: right;" name="cotizacionasientos[]" class="form-control cotizacionasiento" value="'+cotizacion+'">'+
							'</td>'+
							'<td>'+
								'<input type="text" name="observacionasientos[]" class="form-control observacionasiento" value="'+observacion+'">'+
							'</td>'+
							'<td>'+
								'<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta_asiento tooltipsC">'+
									'<i class="fa fa-times-circle text-danger"></i>'+
								'</button>'+
							'</td>'+
						'</tr>'
						);
					});

					// Rellena select de moneda
					$("#cuenta-asiento-table .item-cuenta-asiento").each(function() {
						armaSelectMoneda(this);

						codigocontablexcodigo = $(this).find(".codigoasiento");

						leeCentroCostoAsiento(codigocontablexcodigo);
					});

					// Suma totales del asiento
					sumaMontoAsiento();

					totalDebeAsiento = $("#totaldebeasiento").val();
					totalHaberAsiento = $("#totalhaberasiento").val();
				}
				else
					alert("Error en generación del asiento contable");
			},
			error: function (r) {
				alert("Error grave en generación del asiento contable");
			}
		});
	}

	function leeHistoria()
	{
		var wrapper = $(".container-historia");
		let cobranza_id = $("#cobranza_id").val();

		let url = '/anitaERP/public/caja/leer_historia_cobranza/'+cobranza_id;

		$.get(url, function(historia){

			$(wrapper).empty();

			var hist = $.map(historia, function(value, index){
				return [value];
			});
			$.each(hist, function(index,value){
				fecha = value.created_at;
				var fechaObjeto = new Date(fecha);
				//result = fechaObjeto.toLocaleTimeString().slice(0, 16);

				$(wrapper).append('<tr class="item-cobranza-historia">'+
                            '<td>'+
                                '<input type="datetime" name="estadofechas[]" class="form-control estadofecha" value="'+fechaObjeto+'" readonly>'+
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

	function armaSelectMoneda(ptrrenglon)
	{
		var select = $(ptrrenglon).find('.monedaasiento');
		var moneda_id = $(ptrrenglon).find('.monedaasiento_id_previo').val();

		select.empty();
		select.append('<option value="">-- Seleccionar --</option>');

		// Lee monedas
		//$.get('/anitaERP/public/configuracion/leermoneda', function(data){
        //    var monedas = $.map(data, function(value, index){
        //        return [value];
        //    });
        //    $.each(monedas, function(index,value){
		//		if (value.id != moneda_id)
        //       	select.append('<option value="'+value.id+'">'+value.abreviatura+'</option>');
		//		else
        //       	select.append('<option value="'+value.id+'" selected>'+value.abreviatura+'</option>');
        //    });
		//});

		idMoneda.forEach(function(moneda, indice, array) {
			if (moneda != moneda_id)
				select.append('<option value="'+moneda+'">'+descripcionMoneda[moneda]+'</option>');
			else
				select.append('<option value="'+moneda+'" selected>'+descripcionMoneda[moneda]+'</option>');
		});

		if (moneda_id > 0)
		{
			select.value = moneda_id;

			select.children().filter(function(){
   				return this.text == moneda_id;
			}).prop('selected', true);
		}
	}

	$("#form-general").submit(function (e) {
		e.preventDefault();
		let token = $("meta[name='csrf-token']").attr("content");
		let id = $("#id").val();
		var url;

		$.ajaxSetup({
			beforeSend: BeforeSend,
			complete: CompleteFunc,
		});

		var parametros=new FormData($(this)[0])

		parametros.append('_token', token);

		// Esconde boton de actualizar
		$( "#botonform0" ).hide();

		if (id != '')
			url = "/anitaERP/public/caja/actualizarcobranza/"+id;
		else
			url = "/anitaERP/public/caja/cobranza";

		//realizamos la petición ajax con la función de jquery
		$.ajax({
			type: "POST",
			url: url,
			data: parametros,
			contentType: false, //importante enviar este parametro en false
			processData: false, //importante enviar este parametro en false
			success: function (data) {
				if (data.mensaje == 'ok')
					alert("Se grabó transacción de caja con éxito");
				else
					alert("Error de grabacion");

				let origen = $('#origen').val();

				switch(origen)
				{
					case 'movimientocaja':
						var listarUri = "/anitaERP/public/caja/movimientocaja";
						break;
					case 'cobranza':
						var listarUri = "/anitaERP/public/caja/cobranza";
						break;
					case 'ordenventa':
						var listarUri = $('#referer').val();
				}

				window.location.href = listarUri;
			},
			error :function( data ) {
				if( data.status === 422 ) {
					alert("error de grabacion, verifique los datos")
				}
				else
				{
					alert("error de grabacion "+data.status);
				}
				$( "#botonform0" ).show();
			}
		});
	});
	
	function BeforeSend()
	{
		$("#loading").show();
	}
	
	function CompleteFunc()
	{
		$("#loading").hide();
	}

	function buscaTipoTransaccionCaja()
	{
		let tipotransaccion_caja_id = $('#tipotransaccion_caja_id').val();
		let url = '/anitaERP/public/caja/leertipotransaccion_caja/'+tipotransaccion_caja_id;

		$.get(url, function(data){
			if (data.id > 0)
			{
			}
			else
			{
				alert("No existe el tipo de transaccion de caja");
				return;
			}
		});
	}

		


