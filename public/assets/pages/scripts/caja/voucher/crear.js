	var reservaxcodigo;   
	var servicioterrestrexcodigo;
	var proveedorxcodigo;
	var ptrReservaActual;
	var totalMoneda=[];
	var idMoneda=[];
	var descripcionMoneda=[];
 
 	$(function () {
        $('#agrega_renglon_guia').on('click', agregaRenglonGuia);
        $(document).on('click', '.eliminar_guia', borraRenglonGuia);
        $('#agrega_renglon_voucher_reserva').on('click', agregaRenglonReserva);
        $(document).on('click', '.eliminar_voucher_reserva', borraRenglonReserva);
        $('#agrega_renglon_voucher_formapago').on('click', agregaRenglonFormaPago);
        $(document).on('click', '.eliminar_voucher_formapago', borraRenglonFormaPago);

		activa_eventos(true);

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

		// Muestra sumatoria de montos del ingreso egreso
		setTimeout(() => {
			sumaMonto();
			sumaPasajero();
			calculaMontoEmpresa();
		}, 300);

		// Maneja los botones de las solapas
		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
			$(".form3").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
			$(".form3").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform3").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });		
		$( ".botonsubmit" ).click(function() {
			$( "#form-general" ).submit();
		});

		$(document).keydown(function(event) { if (event.key === "Enter") { event.preventDefault(); }});
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultareserva').off('click');
			$('#consultareservaModal').off('shown.bs.modal');
			$('#aceptaconsultareservaModal').off('click');
			$('.eligeconsultareserva').off('click');
			$('.consultacuentacaja').off('click');
			$('.pax').off('change');
			$('.free').off('change');
			$('.incluido').off('change');
			$('.opcional').off('change');
			$('.monto').off('change');
			$('.cotizacion').off('change');
			$('#servicioterrestre_id').off('change');
			$('.codigoservicioterrestre').off('change');
			$('#proveedor_id').off('change');
			$('.montocomision').off('change');
			$('.codigoreserva').off('change');
			$('.codigo').off('change');
			$('.porcentajecomision').off('change');
			$('#montoproveedor').off('change');
			$('.tipocomision').off('change');
		}
		
		activa_eventos_consultaguia();

		$('.consultareserva').on('click', function (event) {
        	reservaxcodigo = $(this).parents("tr").find(".reserva_id");
			ptrReservaActual = this;

        	// Abre modal de consulta
			$("#consultareservaModal").modal('show');
    	});

		$('#consultareservaModal').on('shown.bs.modal', function () {
			$(this).find('[autofocus]').focus();
		})

    	$('#aceptaconsultareservaModal').on('click', function () {
        	$('#consultareservaModal').modal('hide');
    	});

		$(document).on('click', '.eligeconsultareserva', function () {
			let seleccion = $(this).parents("tr").children().html();
			let pasajero = $(this).parents("tr").find(".nombrepasajero").html();
			let fechaarribo = $(this).parents("tr").find(".fechaarribo").html();
			let fechapartida = $(this).parents("tr").find(".fechapartida").html();
			let pasajero_id = $(this).parents("tr").find(".pasajero_id").html();

			// Asigna a grilla los valores devueltos por consulta
			$(reservaxcodigo).val(seleccion);
			
			// Convierte fechas a formato date para input
			let separador = '-';
			var fechaUno = [fechaarribo.slice(0, 4), separador, fechaarribo.slice(4)].join('');
			fechaarribo = [fechaUno.slice(0, 7), separador, fechaUno.slice(7)].join('');

			fechaUno = [fechapartida.slice(0, 4), separador, fechapartida.slice(4)].join('');
			fechapartida = [fechaUno.slice(0, 7), separador, fechaUno.slice(7)].join('');

			// Asigna nueva reserva
			let reservaOriginal = $(ptrReservaActual).parents("tr").find(".codigoreserva").val();

			if (seleccion != reservaOriginal)
			{
				$(ptrReservaActual).parents("tr").find(".codigoreserva").val(seleccion);
				$(ptrReservaActual).parents("tr").find(".reserva_id").val(seleccion);
				$(ptrReservaActual).parents("tr").find(".pasajero_id").val(pasajero_id);
				$(ptrReservaActual).parents("tr").find(".nombrepasajero").val(pasajero);
				$(ptrReservaActual).parents("tr").find(".fechaarribo").val(fechaarribo);
				$(ptrReservaActual).parents("tr").find(".fechapartida").val(fechapartida);

				// Lee reserva
				let codigoservicioterrestre = $("#codigoservicioterrestre").val();
				let url_res = '/anitaERP/public/receptivo/leereservaporidservicioterrestre/'+seleccion+"/"+codigoservicioterrestre;
				
				$.get(url_res, function(data){
					{
						if (data[0])
						{
							$(ptrReservaActual).parents("tr").find(".pax").val(data[0].cantidadpasajero);
							$(ptrReservaActual).parents("tr").find(".pax").attr("max",data[0].cantidadpasajero);
							$(ptrReservaActual).parents("tr").find(".limitepax").val(data[0].cantidadpasajero);
							$(ptrReservaActual).parents("tr").find(".free").val(data[0].cantidadgratis);
							$(ptrReservaActual).parents("tr").find(".free").attr("max",data[0].cantidadpasajero);
							$(ptrReservaActual).parents("tr").find(".limitefree").val(data[0].cantidadgratis);
							if (data[0].cantidadincluido != NaN && data[0].cantidadincluido != '')
								$(ptrReservaActual).parents("tr").find(".incluido").val(parseInt(data[0].cantidadincluido));
							else
								$(ptrReservaActual).parents("tr").find(".incluido").val(0);
							if (data[0].cantidadopcional != NaN && data[0].cantidadopcional != '')
								$(ptrReservaActual).parents("tr").find(".opcional").val(parseInt(data[0].cantidadopcional));
							else
								$(ptrReservaActual).parents("tr").find(".opcional").val(0);

							sumaPasajero();
						}
					}
				});

				$('#consultareservaModal').modal('hide');
			}
		});

		$(document).on('change', '.codigoreserva', function () {
			let reserva_id = $(this).val();
			let ptrReservaActual = this;
			
			// Asigna la reserva ingresada manualmente
			$(ptrReservaActual).parents("tr").find(".reserva_id").val(reserva_id);

			// Lee reserva
			let codigoservicioterrestre = $("#codigoservicioterrestre").val();
			let url_res = '/anitaERP/public/receptivo/leereservaporidservicioterrestre/'+reserva_id+"/"+codigoservicioterrestre;

			$.get(url_res, function(data){
				{
					if (data[0])
					{
						// Convierte fechas a formato date para input
						let separador = '-';
						var fechaUno = [data[0].fechaarribo.slice(0, 4), separador, data[0].fechaarribo.slice(4)].join('');
						fechaarribo = [fechaUno.slice(0, 7), separador, fechaUno.slice(7)].join('');

						fechaUno = [data[0].fechapartida.slice(0, 4), separador, data[0].fechapartida.slice(4)].join('');
						fechapartida = [fechaUno.slice(0, 7), separador, fechaUno.slice(7)].join('');

						$(ptrReservaActual).parents("tr").find(".pasajero_id").val(data[0].pasajero_id);
						$(ptrReservaActual).parents("tr").find(".nombrepasajero").val(data[0].nombrepasajero);
						$(ptrReservaActual).parents("tr").find(".fechaarribo").val(fechaarribo);
						$(ptrReservaActual).parents("tr").find(".fechapartida").val(fechapartida);
						$(ptrReservaActual).parents("tr").find(".pax").val(data[0].cantidadpasajero);
						$(ptrReservaActual).parents("tr").find(".pax").attr("max",data[0].cantidadpasajero);
						$(ptrReservaActual).parents("tr").find(".limitepax").val(data[0].cantidadpasajero);
						$(ptrReservaActual).parents("tr").find(".free").val(data[0].cantidadgratis);
						$(ptrReservaActual).parents("tr").find(".free").attr("max",data[0].cantidadpasajero);
						$(ptrReservaActual).parents("tr").find(".limitefree").val(data[0].cantidadgratis);
						if (data[0].cantidadincluido != NaN && data[0].cantidadincluido != '')
							$(ptrReservaActual).parents("tr").find(".incluido").val(parseInt(data[0].cantidadincluido));
						else
							$(ptrReservaActual).parents("tr").find(".incluido").val(0);
						if (data[0].cantidadopcional != NaN && data[0].cantidadopcional != '')
							$(ptrReservaActual).parents("tr").find(".opcional").val(parseInt(data[0].cantidadopcional));
						else
							$(ptrReservaActual).parents("tr").find(".opcional").val(0);

						sumaPasajero();
					}
				}
			});
		});

		// Consulta de servicios
		$('.consultaservicioterrestre').on('click', function (event) {
        	servicioterrestrexcodigo = $(this).parents("tr").find(".servicioterrestre_id");

        	// Abre modal de consulta
			$("#consultaservicioterrestreModal").modal('show');
    	});

		$('#consultaservicioterrestreModal').on('shown.bs.modal', function () {
			$(this).find('[autofocus]').focus();
		})

    	$('#aceptaconsultaservicioterrestreModal').on('click', function () {
        	$('#consultaservicioterrestreModal').modal('hide');
    	});

		$(document).on('click', '.eligeconsultaservicioterrestre', function () {
			let seleccion = $(this).parents("tr").children().html();
			let descripcion = $(this).parents("tr").find(".descripcion").html();
			let codigo = $(this).parents("tr").find(".codigo").html();

			$(servicioterrestrexcodigo).val(seleccion);

			$("#servicioterrestre_id").val(seleccion);
			$("#nombreservicioterrestre").val(descripcion);
			$("#servicioterrestre").val(descripcion);
			$("#codigoservicioterrestre").val(codigo);

			// Lee el proveedor si existe y lo trae
			let url = '/anitaERP/public/receptivo/leerproveedor_servicioterrestre/'+seleccion;
			$.get(url, function(data){
				if (data[0])
				{
					$("#proveedor_id").val(data[0].proveedor_id);
					$("#proveedor").val(data[0].nombreproveedor);
				}
			});

			calculaMontoProveedor();
			calculaMontoEmpresa();

			$('#consultaservicioterrestreModal').modal('hide');
		});

		// Consulta de proveedores
		$('.consultaproveedor').on('click', function (event) {
        	proveedorxcodigo = $(this).parents("tr").find(".proveedor_id");

        	// Abre modal de consulta
			$("#consultaproveedorModal").modal('show');
    	});

		$('#consultaproveedorModal').on('shown.bs.modal', function () {
			$(this).find('[autofocus]').focus();
		})

    	$('#aceptaconsultaproveedorModal').on('click', function () {
        	$('#consultaproveedorModal').modal('hide');
    	});

		$(document).on('click', '.eligeconsultaproveedor', function () {
			let seleccion = $(this).parents("tr").children().html();
			let descripcion = $(this).parents("tr").find(".nombreproveedor").html();

			// Asigna a grilla los valores devueltos por consulta
			$(proveedorxcodigo).val(seleccion);

			// Asigna nueva reserva
			$("#proveedor_id").val(seleccion);
			$("#nombreproveedor").val(descripcion);
			$("#proveedor").val(descripcion);

			calculaMontoProveedor();
			calculaMontoEmpresa();

			$('#consultaproveedorModal').modal('hide');
		});

		$(document).on('click', '.consultaproveedor', function () {
			let id = $(this).parents("tr").children().html();

			if (id > 0)
			{
				let url = urlConsultaProveedor;
				url = url.replace(':id', id);
				document.location.href=url;
			}
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
		});

		$(document).on('change', '.codigo', function () {
			let codigo = $(this).val();
			let moneda_id = $(this).parents("tr").find(".moneda");
			cuentacajaxcodigo = $(this).parents("tr").find(".cuentacaja_id");
			nombrexcodigo = $(this).parents("tr").find(".nombre");
			codigoxcodigo = $(this).parents("tr").find(".codigo");

			// Lee cuenta de caja
			let url = '/anitaERP/public/caja/cuentacaja/leercuentacajaporcodigo/'+codigo;
			$.get(url, function(data){
				// Asigna a grilla los valores devueltos por consulta
				$(cuentacajaxcodigo).val(data.id);
				$(nombrexcodigo).val(data.nombre);
				$(codigoxcodigo).val(data.codigo);
				$(moneda_id).val(data.moneda_id)
			});
		});

		$('.pax').on('change', function (event) {
			event.preventDefault();
			chequeaTotalPasajero(this);
			sumaPasajero();
		});
		$('.free').on('change', function (event) {
			event.preventDefault();
			chequeaTotalPasajero(this);
			sumaPasajero();
		});		
		$('.incluido').on('change', function (event) {
			event.preventDefault();
			chequeaTotalPasajero(this);
			sumaPasajero();
		});

		$('.opcional').on('change', function (event) {
			event.preventDefault();
			chequeaTotalPasajero(this);
			sumaPasajero();
		});
		
		$('.monto').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			sumaMonto();
		});

		$('.cotizacion').on('change', function (event) {
			event.preventDefault();
			sumaMonto();
		});

		$('.tipocomision').on('change', function (event) {
			event.preventDefault();

			// Busca comision por servicio
			buscaComisionPorServicio(this);

			calculaMontoProveedor();
			calculaMontoEmpresa();
		});

		$('.porcentajecomision').on('change', function (event) {
			event.preventDefault();
			calculaPorcentajeComisionGuia(this);
		});

		$('.montocomision').on('change', function (event) {
			event.preventDefault();
			calculaMontoProveedor();
			calculaMontoEmpresa();
		});

		$('#montoproveedor').on('change', function (event) {
			event.preventDefault();
			calculaMontoEmpresa();
		});

		$('#servicioterrestre_id').on('change', function (event) {
			event.preventDefault();

			// Busca tabla de comisiones por servicio
			procesaComisionPorServicio();

			calculaMontoProveedor();
			calculaMontoEmpresa();
		});
		
		$('#codigoservicioterrestre').on('change', function (event) {
			event.preventDefault();

			// Lee servicio terrestre por codigo
			let codigoservicioterrestre = $("#codigoservicioterrestre").val();
			let url_res = '/anitaERP/public/receptivo/leerservicioterrestre/'+codigoservicioterrestre;

			$.get(url_res, function(data){
				{
					if (data)
					{
						$("#servicioterrestre_id").val(data.id);
						$("#nombreservicioterrestre").val(data.nombre);
						$("#servicioterrestre").val(data.nombre);
						$("#codigoservicioterrestre").val(data.codigo);
					}
				}
			});
		});

		$('#proveedor_id').on('change', function (event) {
			event.preventDefault();
			
			// Lee servicio terrestre por codigo
			let proveedor_id = $("#proveedor_id").val();
			let url_res = '/anitaERP/public/compras/leerproveedor/'+proveedor_id;

			$.get(url_res, function(data){
				if (data)
				{
					$("#proveedor_id").val(data.id);
					$("#nombreproveedor").val(data.nombre);
					$("#proveedor").val(data.nombre);
				}
			});
			calculaMontoProveedor();
			calculaMontoEmpresa();
		});
	}

	function calculaPorcentajeComisionGuia(ptr)
	{
		let porcentajeComision = $(ptr).parents('tr').find(".porcentajecomision").val();
		let montoVoucher = $("#montovoucher").val();
		let montoComision = 0;

		montoComision = parseFloat(montoVoucher) * parseFloat(porcentajeComision) / 100;
		$(ptr).parents('tr').find('.montocomision').val(montoComision);
		calculaMontoEmpresa();
	}
	function chequeaTotalPasajero(ptrElemento)
	{
		let pax = parseInt($(ptrElemento).parents('tr').find(".pax").val());
		let limitePax = parseInt($(ptrElemento).parents('tr').find(".limitepax").val());
		let free = parseInt($(ptrElemento).parents('tr').find(".free").val());
		let incluido = parseInt($(ptrElemento).parents('tr').find(".incluido").val());
		let opcional = parseInt($(ptrElemento).parents('tr').find(".opcional").val());

		if (free + incluido + opcional > pax)
		{
			alert("No puede superar máximo de pasajeros de la reserva ("+pax+" Pax)");

			$(ptrElemento).val(0);
		}
	}

	function sumaPasajero()
	{
		let totalPax = 0;
		let totalFree = 0;
		let totalIncluido = 0;
		let totalOpcional = 0;

		// Inicializa totales
		$("#tbody-voucher-reserva-table tr").each(function(index, element) {
			totalPax += parseInt($(element).find(".pax").val());
			totalFree += parseInt($(element).find(".free").val());
			totalIncluido += parseInt($(element).find(".incluido").val());
			totalOpcional += parseInt($(element).find(".opcional").val());
		});

		$("#totalpaxvoucher").val(totalPax);
		$("#pax").val(totalPax);
		$("#totalfreevoucher").val(totalFree);
		$("#paxfree").val(totalFree);
		$("#totalincluidovoucher").val(totalIncluido);
		$("#incluido").val(totalIncluido);
		$("#totalopcionalvoucher").val(totalOpcional);
		$("#opcional").val(totalOpcional);
	}

    function agregaRenglonGuia(event){
    	event.preventDefault();

		agregaUnRenglonGuia();
	}

	function agregaUnRenglonGuia()
	{
    	let renglon = $('#template-renglon-guia').html();

    	$("#tbody-guia-table").append(renglon);
    	actualizaRenglonesGuia();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-guia-table tr:last");
		$(ptrUltimoRenglon).find('.guia_id').focus();

		activa_eventos(false);
    }

    function borraRenglonGuia(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesGuia();
		calculaMontoEmpresa();
    }

    function actualizaRenglonesGuia() {
    	var item = 1;

    	$("#tbody-guia-table .iiguia").each(function() {
    		$(this).val(item++);
    	});
    }

	function agregaRenglonReserva(event){
    	event.preventDefault();

		agregaUnRenglonReserva();
	}

	function agregaUnRenglonReserva()
	{
    	let renglon = $('#template-renglon-voucher-reserva').html();

    	$("#tbody-voucher-reserva-table").append(renglon);
    	actualizaRenglonesReserva();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-voucher-reserva-table tr:last");
		$(ptrUltimoRenglon).find('.codigoreserva').focus();

		activa_eventos(false);
    }

	function borraRenglonReserva(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesReserva();
		sumaPasajero();
    }

    function actualizaRenglonesReserva() {
    	var item = 1;

    	$("#tbody-voucher-reserva-table .iireserva").each(function() {
    		$(this).val(item++);
    	});
    }

	function agregaRenglonFormaPago(event){
    	event.preventDefault();

		agregaUnRenglonFormaPago();
	}

	function agregaUnRenglonFormaPago()
	{
    	let renglon = $('#template-renglon-voucher-formapago').html();

    	$("#tbody-voucher-formapago-table").append(renglon);
    	actualizaRenglonesFormaPago();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-voucher-formapago-table tr:last");
		$(ptrUltimoRenglon).find('.codigo').focus();

		activa_eventos(false);
    }

	function borraRenglonFormaPago(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesFormaPago();
		sumaMonto();
    }

    function actualizaRenglonesFormaPago() {
    	var item = 1;

    	$("#tbody-voucher-formapago-table .iiformapago").each(function() {
    		$(this).val(item++);
    	});
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

	function sumaMonto()
	{
		//let monedaDefault = $("#tbody-voucher-formapago-table").children(':first').find('.moneda').val();
		let monedaDefault = $("#moneda_default_id").val();
		var wrapper = $(".totales-por-moneda");
		let totalMonto = 0;

		// Inicializa totales por moneda
		totalMoneda.length = 0;
		$("#tbody-voucher-formapago-table .moneda").each(function() {
			let moneda = $(this).val();
			totalMoneda[moneda] = 0;
		});

		$("#tbody-voucher-formapago-table .monto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);
			totalMoneda[moneda] += valor;
			if (moneda > 1)
				totalMonto += (valor * coef);
			else	
				totalMonto += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			if (totalMoneda[moneda] !== undefined)
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});
		$("#montovoucher").val(totalMonto);

		calculaMontoEmpresa();
	}

	function calculaMontoProveedor()
	{
		let servicioterrestre_id = $("#servicioterrestre_id").val();
		let proveedor_id = $("#proveedor_id").val();

		// Busca costo del servicio
		if (servicioterrestre_id > 0 && proveedor_id > 0)
		{
			let url = '/anitaERP/public/receptivo/leercostoproveedor_servicioterrestre/'+servicioterrestre_id+'/'+proveedor_id;
			
			$.get(url, function(data){
				let montoProveedor = parseFloat(data.costo);
				let moneda_id = data.moneda_id;
				let totalPax = $("#totalpaxvoucher").val();

				if (isNaN(montoProveedor))
					montoProveedor = 0;

				montoProveedor = montoProveedor * totalPax;

				if (moneda_id > 1)
				{
					let fecha = $('#fecha').val();

					let url_cot = '/anitaERP/public/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
				
					$.get(url_cot, function(data){
						montoProveedor = montoProveedor * data.cotizacionventa;
					});
				}
				
				$("#montoproveedor").val(montoProveedor);

				calculaMontoEmpresa();
			});
		}
	}
		
	function calculaMontoEmpresa()
	{
		let montoProveedor = $("#montoproveedor").val();
		let montoVoucher = $("#montovoucher").val();
		let montoEmpresa = 0;
		let montoGuia = 0;

		$("#tbody-guia-table .montocomision").each(function() {
			let monto = $(this).val();
			
			montoGuia += parseFloat(monto);
		});
		montoEmpresa = parseFloat(montoVoucher) - parseFloat(montoGuia) - parseFloat(montoProveedor);

		$("#montoempresa").val(montoEmpresa);
	}

	function procesaComisionPorServicio()
	{
		// Recorre tabla de guias para calcular comisiones
		$("#tbody-guia-table .tipocomision").each(function(index, element) {
			buscaComisionPorServicio(element);
		});
	}
	
	function buscaComisionPorServicio(ptr)
	{
		let servicioterrestre_id = $('#servicioterrestre_id').val();
		let tipocomision = $(ptr).parents("tr").find(".tipocomision").val();

		let url = '/anitaERP/public/caja/leercomision_servicioterrestre/'+servicioterrestre_id+'/'+tipocomision;
		$.get(url, function(data){
			if (data.porcentajecomision)
			{
				$(ptr).parents("tr").find(".porcentajecomision").val(data.porcentajecomision);

				calculaPorcentajeComisionGuia(ptr);
			}
		});
	}
