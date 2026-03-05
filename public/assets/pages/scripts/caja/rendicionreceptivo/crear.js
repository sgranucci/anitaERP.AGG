var cuentacajaxcodigo;
var nombrexcodigo;
var codigoxcodigo;
var flCrear;
var totalMoneda=[];
var idMoneda=[];
var descripcionMoneda=[];
var monedaAdelanto=[];
var montoAdelanto=[];
var monedaComision=[];
var montoComision=[];
   
    $(function () {
        $('#agrega_renglon_rendicionreceptivo_gasto').on('click', agregaRenglonGasto);
        $(document).on('click', '.eliminar_rendicionreceptivo_gasto', borraRenglonGasto);
		$(document).on('click', '.eliminar_rendicionreceptivo_gastoanterior', borraRenglonGastoAnterior);
		$(document).on('click', '.eliminar_rendicionreceptivo_voucher', borraRenglonVoucher);

		flCrear = document.getElementById("crear");
		flModificaAsiento = false;

		document.getElementById("codigoguia").focus();

		activa_eventos(true);

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
        });
		$("#botonform4").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").show();
			$(".form5").hide();
			$(".form6").hide();

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

		// Muestra sumatoria de montos del ingreso egreso
		setTimeout(() => {
			sumaAdelanto();
			sumaMonto();
			sumaMontoGasto();
			sumaMontoVoucher();
			sumaComision();

			calculaTotalRendicion();
		}, 300);

		$( "#botonform0" ).click(function() {
			let flError = false;
	
			if (!flError)
			{
			}
	
			if (!flError)
				$( "#form-general" ).submit();
		});
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuentacaja').off('click');
			$('.codigo').off('change');
			$('.monto').off('change');
			$('.moneda').off('change');
			$('.cotizacion').off('change');
			$('#ordenservicio_id').off('change');
			$('#codigoguia').off('change');
			$('#codigomovil').off('change');
			$('#ordenservicio_id').off('change');
			$('.conceptogasto_id').off('change');
			$('#guia_id').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultaguia();
		activa_eventos_consultamovil();
		activa_eventos_consultaconceptogasto();

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
		});

		$('.monto').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			sumaMontoGasto();
		});

		$('.moneda').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			sumaMontoGasto();
		});

		$('.cotizacion').on('change', function (event) {
			event.preventDefault();
			sumaMontoGasto();
		});

		$('#codigoguia').on('change', function (event) {
			event.preventDefault();
			leeVoucher();
		});

		$('#ordenservicio_id').on('change', function (event) {
			event.preventDefault();
			let ordenservicio_id = $("#ordenservicio_id").val();
			var wrapperGastoAnterior = $(".container-gastoanterior");
			var wrapperAdelanto = $(".container-adelanto");
			let id = $("#tbody-rendicionreceptivo-gastoanterior-table").children(':first').find('.idgastoanterior').val();

			$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				}
			});
			
			let url = "/anitaERP/public/caja/rendicionreceptivo/leegastoanterior";

			$.ajax({
				type: "POST",
				url: url,
				data: {
					ordenservicio_id: ordenservicio_id
				},
				success: function (data) {
					if (data.mensaje == 'ok')
					{
						$(wrapperAdelanto).empty();
						$(wrapperGastoAnterior).empty();
						
						// Inicializa totales por moneda
						idMoneda.forEach(function(moneda, indice, array) {
							montoAdelanto[moneda] = 0;
						});

						// Lee los adelantos
						$.each(data.adelanto, function(index,value){
							let idadelanto = value.id;
							let nombreconceptoadelanto = value.nombregasto;
							let codigocuentacajaadelanto = value.codigocuentacaja;
							let nombrecuentacajaadelanto = value.nombrecuentacaja;
							let abreviaturamonedaadelanto = value.abreviaturamoneda;
							let monedaadelanto_id = value.moneda_id;
							let cotizacionadelanto = value.cotizacion;
							let montoadelanto = value.monto;

							montoAdelanto[value.moneda_id] += value.monto;
							monedaAdelanto[value.moneda_id] = value.moneda_id;

							$(wrapperAdelanto).append('<tr class="item-rendicionreceptivo-adelanto">'+
								'<td>'+
									'<input type="text" class="idadelanto form-control" name="idadelantos[]" value="'+idadelanto+'" readonly></input>'+
								'</td>'+				
								'<td>'+
									'<input type="text" class="nombreconceptoadelanto form-control" name="nombreconceptoadelantos[]" value="'+nombreconceptoadelanto+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="codigocuentacajaadelanto form-control" name="codigocuentacajaadelantos[]" value="'+codigocuentacajaadelanto+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="nombrecuentacajaadelanto form-control" name="nombrecuentacajaadelantos[]" value="'+nombrecuentacajaadelanto+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="abreviaturamonedaadelanto form-control" name="abreviaturamonedaadelantos[]" value="'+abreviaturamonedaadelanto+'" readonly></input>'+
									'<input type="hidden" class="monedaadelanto_id form-control" name="monedaadelanto_ids[]" value="'+monedaadelanto_id+'"></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" name="montoadelantoes[]" class="form-control montoadelanto" min="0" value="'+montoadelanto+'" readonly></input>'+
								'</td>'+
								'<td>'+
								    '<input type="text" name="cotizacionadelantoes[]" class="form-control cotizacionadelanto" value="'+cotizacionadelanto+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_adelanto tooltipsC">'+
										'<i class="fa fa-times-circle text-danger"></i>'+
									'</button>'+
								'</td>'+
							'</tr>'
							);
							// Suma adelanto
							sumaAdelanto();
						});

						// Lee los gastos ya cargados anteriormente a la rendicion
						$.each(data.gastoanterior, function(index,value){
							let idgastoanterior = value.id;
							let nombreconceptogastoanterior = value.nombregasto;
							let codigocuentacajagastoanterior = value.codigocuentacaja;
							let nombrecuentacajagastoanterior = value.nombrecuentacaja;
							let abreviaturamonedagastoanterior = value.abreviaturamoneda;
							let monedagastoanterior_id = value.moneda_id;
							let cotizaciongastoanterior = value.cotizacion;
							let montogastoanterior = value.monto;

							$(wrapperGastoAnterior).append('<tr class="item-rendicionreceptivo-gastoanterior">'+
								'<td>'+
									'<input type="text" class="idgastoanterior form-control" name="idgastoanteriores[]" value="'+idgastoanterior+'" readonly></input>'+
								'</td>'+				
								'<td>'+
									'<input type="text" class="nombreconceptogastoanterior form-control" name="nombreconceptogastoanteriores[]" value="'+nombreconceptogastoanterior+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="codigocuentacajagastoanterior form-control" name="codigocuentacajagastoanteriores[]" value="'+codigocuentacajagastoanterior+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="nombrecuentacajagastoanterior form-control" name="nombrecuentacajagastoanteriores[]" value="'+nombrecuentacajagastoanterior+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="abreviaturamonedagastoanterior form-control" name="abreviaturamonedagastoanteriores[]" value="'+abreviaturamonedagastoanterior+'" readonly></input>'+
									'<input type="hidden" class="monedagastoanterior_id form-control" name="monedagastoanterior_ids[]" value="'+monedagastoanterior_id+'"></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" name="montogastoanteriores[]" class="form-control montogastoanterior" min="0" value="'+montogastoanterior+'" readonly></input>'+
								'</td>'+
								'<td>'+
								    '<input type="text" name="cotizaciongastoanteriores[]" class="form-control cotizaciongastoanterior" value="'+cotizaciongastoanterior+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_gastoanterior tooltipsC">'+
										'<i class="fa fa-times-circle text-danger"></i>'+
									'</button>'+
								'</td>'+
							'</tr>'
							);
						});

						// Suma totales del gastos anteriores
						sumaMonto();

						// Lee los vouchers
						leeVoucher();
					}
					else
						alert("Error en lectura gastos anteriores");
				},
				error: function (r) {
					alert("Error grave en lectura gastos anteriores");
				}
			});		
		});
	}

    function agregaRenglonGasto(event){
    	event.preventDefault();

		agregaUnRenglonGasto();
	}

	function agregaUnRenglonGasto()
	{
    	let renglon = $('#template-renglon-rendicionreceptivo-gasto').html();

    	$("#tbody-rendicionreceptivo-gasto-table").append(renglon);
    	actualizaRenglonesGasto();

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-rendicionreceptivo-gasto-table tr:last");
		$(ptrUltimoRenglon).find('.conceptogasto_id').focus();

		activa_eventos(false);
    }

    function borraRenglonGasto(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesGasto();
		sumaMontoGasto();
    }

	function borraRenglonGastoAnterior(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
		sumaMonto();
    }

	function borraRenglonVoucher(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
		sumaMontoVoucher();
    }

    function actualizaRenglonesGasto() {
    	var item = 1;

    	$("#tbody-rendicionreceptivo-gasto-table .iiconceptogasto").each(function() {
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

	// Lee vouchers por guia y codigo de orden de servicio

	function leeVoucher()
	{
		let ordenservicio_id = $("#ordenservicio_id").val();
		let guia_id = $("#guia_id").val();
		var wrapperVoucher = $(".container-voucher");
		var wrapperComision = $(".container-comision");
		let id = $("#tbody-rendicionreceptivo-voucher").children(':first').find('.idvoucher').val();

		activa_eventos(guia_id);

		if (ordenservicio_id > 0 && guia_id > 0)
		{
			$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				}
			});
			
			let url = "/anitaERP/public/caja/rendicionreceptivo/leevoucher";

			$.ajax({
				type: "POST",
				url: url,
				data: {
					ordenservicio_id: ordenservicio_id,
					guia_id: guia_id
				},
				success: function (data) {
					if (data.mensaje == 'ok')
					{
						$(wrapperComision).empty();
						$(wrapperVoucher).empty();

						// Inicializa totales por moneda
						idMoneda.forEach(function(moneda, indice, array) {
							montoComision[moneda] = 0;
						});

						$.each(data.comision, function(index,value){
							let idvoucher = value.id;
							let fechavoucher = value.fecha;
							let cuentacajavoucher_id = value.cuentacaja_id;
							let codigocuentacajavoucher = value.codigocuentacaja;
							let nombrecuentacajavoucher = value.nombrecuentacaja;
							let abreviaturamonedavoucher = value.abreviaturamoneda;
							let monedavoucher_id = value.moneda_id;
							let cotizacionvoucher = value.cotizacion;
							let montovoucher = value.monto;

							montoComision[value.moneda_id] += value.monto;
							monedaComision[value.moneda_id] = value.moneda_id;

							$(wrapperComision).append('<tr class="item-rendicionreceptivo-comision">'+
								'<td>'+
									'<input type="text" class="vouchercomision_id form-control" name="vouchercomision_ids[]" value="'+idvoucher+'" readonly></input>'+
								'</td>'+				
								'<td>'+
									'<input type="date" class="fechacomision form-control" name="fechacomisiones[]" value="'+fechavoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="codigocuentacajacomision form-control" name="codigocuentacajacomisiones[]" value="'+codigocuentacajavoucher+'" readonly></input>'+
									'<input type="hidden" class="cuentacajacomision_id form-control" name="cuentacajacomision_ids[]" value="'+cuentacajavoucher_id+'"></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="nombrecuentacajacomision form-control" name="nombrecuentacajacomisiones[]" value="'+nombrecuentacajavoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="abreviaturamonedacomision form-control" name="abreviaturamonedacomisiones[]" value="'+abreviaturamonedavoucher+'" readonly></input>'+
									'<input type="hidden" class="monedacomision_id form-control" name="monedacomision_ids[]" value="'+monedavoucher_id+'"></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" name="montocomisiones[]" class="form-control montocomision" min="0" value="'+montovoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" name="cotizacioncomisiones[]" class="form-control cotizacioncomision" value="'+cotizacionvoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_comision tooltipsC">'+
										'<i class="fa fa-times-circle text-danger"></i>'+
									'</button>'+
								'</td>'+
							'</tr>'
							);

							// Suma totales de comisiones
							sumaComision();
						});						

						$.each(data.voucher, function(index,value){
							let idvoucher = value.id;
							let fechavoucher = value.fecha;
							let cuentacajavoucher_id = value.cuentacaja_id;
							let codigocuentacajavoucher = value.codigocuentacaja;
							let nombrecuentacajavoucher = value.nombrecuentacaja;
							let abreviaturamonedavoucher = value.abreviaturamoneda;
							let monedavoucher_id = value.moneda_id;
							let cotizacionvoucher = value.cotizacion;
							let montovoucher = value.monto;

							$(wrapperVoucher).append('<tr class="item-rendicionreceptivo-voucher">'+
								'<td>'+
									'<input type="text" class="idvoucher form-control" name="idvouchers[]" value="'+idvoucher+'" readonly></input>'+
								'</td>'+				
								'<td>'+
									'<input type="date" class="fechavoucher form-control" name="fechavouchers[]" value="'+fechavoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="codigocuentacajavoucher form-control" name="codigocuentacajavoucheres[]" value="'+codigocuentacajavoucher+'" readonly></input>'+
									'<input type="hidden" class="cuentacajavoucher_id form-control" name="cuentacajavoucher_ids[]" value="'+cuentacajavoucher_id+'"></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="nombrecuentacajavoucher form-control" name="nombrecuentacajavoucheres[]" value="'+nombrecuentacajavoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" class="abreviaturamonedavoucher form-control" name="abreviaturamonedavoucheres[]" value="'+abreviaturamonedavoucher+'" readonly></input>'+
									'<input type="hidden" class="monedavoucher_id form-control" name="monedavoucher_ids[]" value="'+monedavoucher_id+'"></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" name="montovoucheres[]" class="form-control montovoucher" min="0" value="'+montovoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<input type="text" name="cotizacionvoucheres[]" class="form-control cotizacionvoucher" value="'+cotizacionvoucher+'" readonly></input>'+
								'</td>'+
								'<td>'+
									'<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_voucher tooltipsC">'+
										'<i class="fa fa-times-circle text-danger"></i>'+
									'</button>'+
								'</td>'+
							'</tr>'
							);
						});

						// Suma totales del vouchers
						sumaMontoVoucher();
					}
					else
						alert("Error en lectura vouchers");
				},
				error: function (r) {
					alert("Error grave en lectura vouchers");
				}
			});	
		}
	}

	function sumaMonto()
	{
		let monedaDefault = $("#tbody-rendicionreceptivo-gastoanterior-table").children(':first').find('.monedagastoanterior_id').val();
		var wrapper = $(".totales-por-moneda-gastoanterior");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-rendicionreceptivo-gastoanterior-table .montogastoanterior").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedagastoanterior_id').val();
			let cotizacion = $(this).parents("tr").find('.cotizaciongastoanterior').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');

			if (totalMoneda[moneda] == 0)
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="" />');
			else
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			
		});
		calculaTotalRendicion();
	}

	function sumaMontoVoucher()
	{
		let monedaDefault = $("#tbody-rendicionreceptivo-voucher-table").children(':first').find('.monedavoucher_id').val();
		var wrapper = $(".totales-por-moneda-voucher");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-rendicionreceptivo-voucher-table .montovoucher").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedavoucher_id').val();
			let cotizacion = $(this).parents("tr").find('.cotizacionvoucher').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');

			if (totalMoneda[moneda] == 0)
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="" />');
			else
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');

		});
		calculaTotalRendicion();
	}

	function sumaMontoGasto()
	{
		let monedaDefault = $("#tbody-rendicionreceptivo-gasto-table").children(':first').find('.moneda').val();
		var wrapper = $(".totales-por-moneda-gasto");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-rendicionreceptivo-gasto-table .monto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			//let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			//let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');

			if (totalMoneda[moneda] == 0)
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="" />');
			else
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			
		});
		calculaTotalRendicion();
	}

	function sumaAdelanto()
	{
		var wrapper = $(".totales-por-moneda-adelanto");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-rendicionreceptivo-adelanto-table .montoadelanto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedaadelanto_id').val();
			//let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			//let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');

			if (totalMoneda[moneda] == 0)
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="" />');
			else
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
		});
		calculaTotalRendicion();
	}

	function sumaComision()
	{
		var wrapper = $(".totales-por-moneda-comision");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-rendicionreceptivo-comision-table .montocomision").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedacomision_id').val();
			//let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			//let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			totalMoneda[moneda] += valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');
			
			$(wrapper).append('<input type="hidden" name="monedacomision_ids[]" class="form-control col-lg-1" readonly value="'+moneda+'" />');
			if (totalMoneda[moneda] == 0)
				$(wrapper).append('<input type="text" name="montocomisiones[]" class="form-control col-lg-1" readonly value="" />');
			else
				$(wrapper).append('<input type="text" name="montocomisiones[]" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
		});
		calculaTotalRendicion();
	}

	function calculaTotalRendicion()
	{
		var wrapper = $(".totales-por-moneda-rendicion");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-rendicionreceptivo-gastoanterior-table .montogastoanterior").each(function() {
            let valor = parseFloat($(this).val());

			//totalMoneda[moneda] -= valor;
        });

		$("#tbody-rendicionreceptivo-voucher-table .montovoucher").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedavoucher_id').val();

			totalMoneda[moneda] += valor;
        });

		$("#tbody-rendicionreceptivo-gasto-table .monto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();

			totalMoneda[moneda] -= valor;
        });

		$("#tbody-rendicionreceptivo-adelanto-table .montoadelanto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedaadelanto_id').val();

			totalMoneda[moneda] += valor;
        });

		$("#tbody-rendicionreceptivo-comision-table .montocomision").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.monedacomision_id').val();

			totalMoneda[moneda] -= valor;
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			//if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0)
			$(wrapper).append('<label class="col-lg-1 col-form-label">'+detalleLabel+'</label>');

			$(wrapper).append('<input type="hidden" name="monedarendicion_ids[]" class="form-control col-lg-1" readonly value="'+moneda+'" />');
			if (totalMoneda[moneda] == 0)
				$(wrapper).append('<input type="text" name="montorendiciones[]" class="form-control col-lg-1" readonly value="" />');
			else
				$(wrapper).append('<input type="text" name="montorendiciones[]" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
		});
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

		if (id != '' && id != undefined)
			url = "/anitaERP/public/caja/actualizarrendicionreceptivo/"+id;
		else
			url = "/anitaERP/public/caja/rendicionreceptivo";

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
				var listarUri = "/anitaERP/public/caja/rendicionreceptivo";

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



