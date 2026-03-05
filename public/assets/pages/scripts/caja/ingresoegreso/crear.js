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
   
    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		flCrear = document.getElementById("crear");
		flModificaAsiento = false;

		buscaTipoTransaccionCaja();
		activa_eventos(true);

		$("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".formasientoexterno").hide();
			$(".form6").hide();
        });
		$("#botonform2").click(function(){
			$(".form1").hide();
            $(".form2").show();
			$(".form3").hide();
			$(".form4").hide();
			$(".formasientoexterno").hide();
			$(".form6").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform3").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").show();
			$(".form4").hide();
			$(".formasientoexterno").hide();
			$(".form6").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform4").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").show();
			$(".formasientoexterno").hide();
			$(".form6").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform5").click(function(){
			// Solo genera el asiento cuando se crea la operacion
			if (flCrear || flModificaAsiento)
				generaAsientoContable();

			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".formasientoexterno").show();
			$(".form6").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });
		$("#botonform6").click(function(){
			$(".form1").hide();
            $(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".formasientoexterno").hide();
			$(".form6").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Principal");
        });

		// copia ingresoegreso
		$("#botonform3").click(function(){
			$('#copiaringresoegresoModal').modal('show');
        });

		$('#aceptacopiaringresoegresoModal').on('click', function () {

			$('#copiaringresoegresoModal').modal('hide');

			let url = '/anitaERP/public/caja/copiar_ingresoegreso';

			$.post(url, {_token: $('input[name=_token]').val(), 
						id: $('#id').val(),
						fecha: $('#fechacopia').val()}, function(data)
						{ 
							alert("TRANSACCION DE CAJA COPIADA CORRECTAMENTE GENERO EL ID:"+data.caja_movimiento_id+" NUMERO: "+data.numerotransaccion); 
						});
    	});

		$('#cierracopiaringresoegresoModal').on('click', function () {
			$('#copiaringresoegresoModal').modal('hide');
		});

		// revierte ingresoegreso
		$("#botonform4").click(function(){
			$('#revertiringresoegresoModal').modal('show');
        });

		$('#aceptarevertiringresoegresoModal').on('click', function () {

			$('#revertiringresoegresoModal').modal('hide');

			let url = '/anitaERP/public/contable/copiar_ingresoegreso';

			$.post(url, {_token: $('input[name=_token]').val(), 
						id: $('#id').val(),
						fecha: $('#fechacopia').val(),
						revierte: 1}, function(data)
						{ 
							alert("TRANSACCION DE CAJA REVERTIDA CORRECTAMENTE GENERO EL ID:"+data.caja_movimiento_id+" NUMERO: "+data.numerotransaccion); 
						});
    	});

		$('#cierrarevertiringresoegresoModal').on('click', function () {
			$('#revertiringresoegresoModal').modal('hide');
		});

		// Agrega el primer renglon si esta creando una transaccion
		if (flCrear)
			agregaUnRenglon();

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
		}, 300);

		$( "#botonform0" ).click(function() {
			let flError = false;
	
			$("#tbody-cuenta-table .moneda").each(function() {
				if ($(this).val() === '')
				{
					alert("Debe ingresar moneda");
					flError = true;
				}
			});
	
			// Valida montos asiento
			sumaMontoAsiento();

			totalDebeAsiento = $("#totaldebeasiento").val();
			totalHaberAsiento = $("#totalhaberasiento").val();

			if (totalDebeAsiento != totalHaberAsiento || totalDebeAsiento == 0)
			{
				alert('Problemas en el asiento, no coincide el debe con el haber');
				flError = true;
				muestraVentanaAsiento();
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
				// Valida el ingreso de los centros de costo
				$("#cuenta-asiento-table .item-cuenta-asiento").each(function() {
					centrocostoasiento_id = $(this).find(".centrocostoasiento").val();
	
					if (!$.isNumeric(centrocostoasiento_id))
						flError = true;
				});
	
				if (flError)
				{
					alert('No puede grabar sin cargar los centros de costo');
					muestraVentanaAsiento();
				}
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
			$('.consultacuenta').off('click');
			$('.consultacuentacaja').off('click');
			$('.codigo').off('change');
			$('.monto').off('change');
			$('.moneda').off('change');
			$('#tipotransaccion_caja_id').off('change');
			$('#proveedor_id').off('change');
			$('#servicioterrestre_id').off('change');
			$('.tipocomision').off('change');
			$('.cotizacion').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultaproveedor();
		activa_eventos_consultaconceptogasto();

		$('#tipotransaccion_caja_id').on('change', function (event) {
			event.preventDefault();

			buscaTipoTransaccionCaja();
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
	}

	function muestraVentanaAsiento()
	{
		if (totalDebeAsiento == 0 && totalHaberAsiento == 0)
			generaAsientoContable();

		$(".form1").hide();
		$(".form2").hide();
		$(".form3").hide();
		$(".form4").hide();
		$(".formasientoexterno").show();
		$(".form6").hide();
	}

    function agregaRenglonCuenta(event){
    	event.preventDefault();

		agregaUnRenglon();
	}

	function agregaUnRenglon()
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

	function sumaMonto()
	{
		let monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val();
		var wrapper = $(".totales-por-moneda");

		totalDebe = 0;
		totalHaber = 0;

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cuenta-table .monto").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			let cotizacion = $(this).parents("tr").find('.cotizacion').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

            if (valor >= 0)
                totalDebe += valor * coef;
			else
			{
				if (valor > -999999999999 && valor < 0)
					totalHaber += Math.abs(valor) * coef;
			}

			totalMoneda[moneda] += valor;
        });

		$("#totaldebe").val(totalDebe.toFixed(2));
		$("#totalhaber").val(totalHaber.toFixed(2));

		if (monedaDefault > 0)
		{
			let label = "Total Debe en "+descripcionMoneda[monedaDefault];
			$("#labeltotaldebe").html(label);

			label = "Total Haber en "+descripcionMoneda[monedaDefault];
			$("#labeltotalhaber").html(label);
		}

		// Muestra totales por moneda
		$(wrapper).empty();

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total '+descripcionMoneda[moneda];

			if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0) 
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="'+totalMoneda[moneda].toFixed(2)+'" />');
			}
		});
		
	}

	function generaAsientoContable()
	{
		let token = $("meta[name='csrf-token']").attr("content");
		let datosCuentasCaja=[];
		let datosCuentasContables=[];
		var wrapper = $(".container-asiento");
		let tipotransaccion_caja_id = $("#tipotransaccion_caja_id").val();
		let conceptogasto_id = $("#conceptogasto_id").val();
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

		// Genera datos de las cuentas de caja cargadas
		$("#cuenta-table .item-cuenta").each(function() {
			cuentacaja_ids = $(this).find(".cuentacaja_id").val();
			moneda_ids = $(this).find(".moneda").val();

			montos = $(this).find(".monto").val();

			debes = haberes = ' ';
			if ($(this).find(".monto").val() > 0)
				debes = $(this).find(".monto").val();

			if ($(this).find(".monto").val() < 0)
				haberes = Math.abs($(this).find(".monto").val());

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
		
		let url = "/anitaERP/public/caja/generaasientocontable_ingresoegreso";

		$.ajax({
			type: "POST",
			url: url,
			data: {
				tipotransaccion_caja_id: tipotransaccion_caja_id,
				conceptogasto_id: conceptogasto_id,
				empresa_id: empresa_id,
				datoscaja: datosCuentasCaja,
				datoscontables: datosCuentasContables
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
								'<input type="number" name="debeasientos[]" class="form-control debeasiento" value="'+debe+'">'+
							'</td>'+
							'<td>'+
								'<input type="number" name="haberasientos[]" class="form-control haberasiento" value="'+haber+'">'+
							'</td>'+
							'<td>'+
								'<input type="number" name="cotizacionasientos[]" class="form-control cotizacionasiento" value="'+cotizacion+'">'+
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

		if (id != '')
			url = "/anitaERP/public/caja/actualizaringresoegreso/"+id;
		else
			url = "/anitaERP/public/caja/ingresoegreso";

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

				if (origen == 'movimientocaja')
					var listarUri = "/anitaERP/public/caja/movimientocaja";
				else
					var listarUri = "/anitaERP/public/caja/ingresoegreso";

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

	function buscaTipoTransaccionCaja()
	{
		let tipotransaccion_caja_id = $('#tipotransaccion_caja_id').val();
		let url = '/anitaERP/public/caja/leertipotransaccion_caja/'+tipotransaccion_caja_id;

		$.get(url, function(data){
			if (data.id > 0)
			{
				if (data.signo == 'E')
				{
					$("#div-ordenservicio").show();
					$("#div-conceptogasto").show();
					$("#div-proveedor").show();
				}
				else
				{
					$("#div-ordenservicio").hide();
					$("#div-conceptogasto").hide();
					$("#div-proveedor").hide();
					$('#ordenservicio_id').val('');
					$('#conceptogasto_id').val('');
					$('#proveedor_id').val('');
				}
			}
			else
			{
				alert("No existe el tipo de transaccion de caja");
				return;
			}
		});
	}

		


