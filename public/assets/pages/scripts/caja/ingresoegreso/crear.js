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
var ingresoEgresoAbrevTipo = '';
var ABREV_TRANSFERENCIA_IE = 'TRA';
   
    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);
		$('#ie-agrega-renglon-archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.ie-eliminararchivo', borraRenglonArchivo);
		$(document).on('click', '.eliminar-archivo-ie', function (e) {
			e.preventDefault();
			$(this).closest('.ie-archivo-item').remove();
		});

		flCrear = document.getElementById("crear");
		flModificaAsiento = false;

		buscaTipoTransaccionCaja();
		activa_eventos(true);
		if (typeof activaEventosChequesIngresoEgreso === 'function') {
			activaEventosChequesIngresoEgreso();
		}

		function marcarSolapaIeActiva($boton) {
			$('#tabs-ingresoegreso .nav-link').removeClass('active');
			$boton.addClass('active');
		}

		function ocultarSolapasIe() {
			$(".form1").hide();
			$(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".formasientoexterno").hide();
			$(".form6").hide();
		}

		$("#botonform1").click(function(e){
			e.preventDefault();
			ocultarSolapasIe();
			$(".form1").show();
			marcarSolapaIeActiva($(this));
        });
		$("#botonform2").click(function(e){
			e.preventDefault();
			ocultarSolapasIe();
			$(".form2").show();
			marcarSolapaIeActiva($(this));
        });
		$("#botonform3").click(function(e){
			e.preventDefault();
			ocultarSolapasIe();
			$(".form3").show();
			marcarSolapaIeActiva($(this));
        });
		$("#botonform4").click(function(e){
			e.preventDefault();
			ocultarSolapasIe();
			$(".form4").show();
			marcarSolapaIeActiva($(this));
        });
		$("#botonform5").click(function(e){
			e.preventDefault();
			// Solo genera el asiento cuando se crea la operacion
			if (flCrear || flModificaAsiento)
				generaAsientoContable();

			ocultarSolapasIe();
			$(".formasientoexterno").show();
			marcarSolapaIeActiva($(this));
        });
		$("#botonform6").click(function(e){
			e.preventDefault();
			ocultarSolapasIe();
			$(".form6").show();
			marcarSolapaIeActiva($(this));
        });

		$("#boton-copia-ie").click(function(){
			$('#copiaringresoegresoModal').modal('show');
        });

		$('#aceptacopiaringresoegresoModal').on('click', function () {

			$('#copiaringresoegresoModal').modal('hide');

			let url = carpetaBase+'/caja/copiar_ingresoegreso';

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
		$("#boton-revierte-ie").click(function(){
			$('#revertiringresoegresoModal').modal('show');
        });

		$('#aceptarevertiringresoegresoModal').on('click', function () {

			$('#revertiringresoegresoModal').modal('hide');

			let url = carpetaBase+'/contable/copiar_ingresoegreso';

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

		// Alta: 1 renglón vacío. Transferencia (TRA) pasa a 2 vía asegurarRenglonesCuentaPorTipo.
		if (flCrear && $("#tbody-cuenta-table .item-cuenta").length === 0) {
			agregaUnRenglon();
		}
		asegurarRenglonesCuentaPorTipo();

		// Lee monedas
		$.get(carpetaBase+'/configuracion/leermoneda', function(data){
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

			sumaMonto();

			if (esTransferenciaIngresoEgreso()) {
				if (!validarBalanceCajaTransferencia()) {
					flError = true;
				}
			}
	
			if (flError) {
				return;
			}

			// Transferencia: regenera asiento on-the-fly y luego valida/graba
			if (esTransferenciaIngresoEgreso()) {
				flModificaAsiento = true;
				generaAsientoContable(function (ok) {
					if (!ok) {
						return;
					}
					sumaMontoAsiento();
					if (!validarBalanceAsientoTransferencia()) {
						muestraVentanaAsiento();
						return;
					}
					continuarGrabacionTrasValidaciones();
				});
				return;
			}

			// Valida montos asiento
			sumaMontoAsiento();

			totalDebeAsiento = parseTotalAsientoCampo($("#totaldebeasiento").val());
			totalHaberAsiento = parseTotalAsientoCampo($("#totalhaberasiento").val());

			if (Math.abs(totalDebeAsiento - totalHaberAsiento) > 0.02 || totalDebeAsiento < 0.02)
			{
				alert('Problemas en el asiento, no coincide el debe con el haber');
				flError = true;
				muestraVentanaAsiento();
			}
	
			if (!flError)
			{
				continuarGrabacionTrasValidaciones();
			}
		});

		function continuarGrabacionTrasValidaciones()
		{
			if (typeof window.obtenerComprobantesIvaIngresoEgreso === 'function') {
				$('#comprobantes_ivacompra_json').val(JSON.stringify(window.obtenerComprobantesIvaIngresoEgreso()));
			}

			var comprobantesIva = typeof window.obtenerComprobantesIvaIngresoEgreso === 'function'
				? window.obtenerComprobantesIvaIngresoEgreso()
				: [];
			if (comprobantesIva.length > 0 && typeof window.validarComprobantesIvaContraCaja === 'function') {
				window.validarComprobantesIvaContraCaja(function (valido) {
					if (!valido) {
						return;
					}
					continuarGrabacionIngresoEgreso(totalDebe, totalHaber, parseTotalAsientoCampo($("#totaldebeasiento").val()));
				});
				return;
			}

			continuarGrabacionIngresoEgreso(totalDebe, totalHaber, parseTotalAsientoCampo($("#totaldebeasiento").val()));
		}

		function continuarGrabacionIngresoEgreso(totalDebe, totalHaber, totalDebeAsiento)
		{
			var flError = false;
			totalDebeAsiento = parseTotalAsientoCampo(totalDebeAsiento);

			// Controla total de la operacion contra el total del asiento
			if (totalDebe != 0 || totalHaber != 0)
			{
				let totalOperacion;

				if (totalDebe > totalHaber)
					totalOperacion = totalDebe;
				else
					totalOperacion = totalHaber;

				if (Math.abs(totalOperacion - totalDebeAsiento) > 0.02)
				{
					alert('Problemas en el asiento, no coincide el monto total de la operación con el asiento contable');
					flError = true;
					muestraVentanaAsiento();						
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
		}
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
			flModificaAsiento = true;
			sumaMonto();
		});
		
		$('.codigo').on('change', function (event) {
			event.preventDefault();
			var codigo = $(this);
			var codigo_ant = $(this).parents("tr").find(".codigo_previo").val();
			var codigo_nuevo = codigo.val();
			let empresa_id = $('#empresa_id').val();

			let url_cta = carpetaBase+'/caja/cuentacaja/leercuentacajaporcodigo/'+codigo_nuevo;

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
			if (typeof cuentacajaxcodigoEmitido !== 'undefined' && cuentacajaxcodigoEmitido && cuentacajaxcodigoEmitido.length) {
				var seleccionE = $(this).parents("tr").children().html();
				var nombreE = $(this).parents("tr").find(".nombre").html();
				var codigoE = $(this).parents("tr").find(".codigo").html();
				var monedaE = $(this).parents("tr").find(".moneda_id").html();
				cuentacajaxcodigoEmitido.find('.cuentacaja_emitido_id').val(seleccionE);
				cuentacajaxcodigoEmitido.find('.codigo_emitido').val(codigoE);
				cuentacajaxcodigoEmitido.find('.nombre_emitido').val(nombreE);
				cuentacajaxcodigoEmitido.find('.moneda_emitido_id').val(monedaE);
				cuentacajaxcodigoEmitido = null;
				$('#consultacuentacajaModal').modal('hide');
				flModificaAsiento = true;
				return;
			}
			if (typeof cuentacajaxcodigoReemplazo !== 'undefined' && cuentacajaxcodigoReemplazo && cuentacajaxcodigoReemplazo.length) {
				var seleccionR = $(this).parents("tr").children().html();
				var codigoR = $(this).parents("tr").find(".codigo").html();
				cuentacajaxcodigoReemplazo.find('.cuentacaja_reemplazo_id').val(seleccionR);
				cuentacajaxcodigoReemplazo.find('.codigo_reemplazo').val(codigoR);
				cuentacajaxcodigoReemplazo = null;
				$('#consultacuentacajaModal').modal('hide');
				flModificaAsiento = true;
				return;
			}
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
		$('#tabs-ingresoegreso .nav-link').removeClass('active');
		$('#botonform5').addClass('active');
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
    	var renglon = $('#ie-template-renglon-archivo').html();
    	if (!renglon) {
    		return;
    	}
    	$("#ie-tbody-tabla-archivo").append(renglon);
    }

    function borraRenglonArchivo() {
    	event.preventDefault();
    	var $tbody = $('#ie-tbody-tabla-archivo');
    	var $fila = $(this).closest('tr.item-archivo-ie');
    	if ($tbody.find('tr.item-archivo-ie').length <= 1) {
    		$fila.find('input[type=file]').val('');
    		return;
    	}
    	$fila.remove();
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
			let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
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

		if (typeof sumaMontosChequesIngresoEgreso === 'function') {
			var extraCheques = sumaMontosChequesIngresoEgreso();
			if (extraCheques.extraDebe) {
				totalDebe += extraCheques.extraDebe;
				$("#totaldebe").val(totalDebe.toFixed(2));
			}
			if (extraCheques.extraHaber) {
				totalHaber += extraCheques.extraHaber;
				$("#totalhaber").val(totalHaber.toFixed(2));
			}
		}

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

	function generaAsientoContable(onDone)
	{
		let token = $("meta[name='csrf-token']").attr("content");
		let datosCuentasCaja=[];
		let datosCuentasContables=[];
		var wrapper = $(".container-asiento");
		let tipotransaccion_caja_id = $("#tipotransaccion_caja_id").val();
		let conceptogasto_id = $("#conceptogasto_id").val();
		let empresa_id = $('#empresa_id').val();
		var callback = typeof onDone === 'function' ? onDone : null;

		if (!empresa_id)
		{
			alert("Debe asignar empresa");
			if (callback) {
				callback(false);
			}
			return;
		}

		if (!tipotransaccion_caja_id)
		{
			alert("Debe asignar tipo de transaccion de caja");
			if (callback) {
				callback(false);
			}
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

		var datosChequesEmitidos = typeof serializarChequesEmitidos === 'function' ? serializarChequesEmitidos() : '[]';
		var datosChequesRecibidos = typeof serializarChequesRecibidos === 'function' ? serializarChequesRecibidos() : '[]';
		var datosChequesReemplazo = typeof serializarChequesReemplazo === 'function' ? serializarChequesReemplazo() : '[]';
		var comprobantesIvaJson = typeof window.obtenerComprobantesIvaIngresoEgreso === 'function'
			? JSON.stringify(window.obtenerComprobantesIvaIngresoEgreso())
			: ($('#comprobantes_ivacompra_json').val() || '[]');
		
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		});
		
		let url = carpetaBase+"/caja/generaasientocontable_ingresoegreso";

		$.ajax({
			type: "POST",
			url: url,
			data: {
				tipotransaccion_caja_id: tipotransaccion_caja_id,
				conceptogasto_id: conceptogasto_id,
				empresa_id: empresa_id,
				fecha: $('#fecha').val(),
				datoscaja: datosCuentasCaja,
				datoscontables: datosCuentasContables,
				datoscheques_emitidos: datosChequesEmitidos,
				datoscheques_recibidos: datosChequesRecibidos,
				datoscheques_reemplazo: datosChequesReemplazo,
				comprobantes_ivacompra_json: comprobantesIvaJson
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
								'<input type="hidden" name="cuenta[]" class="form-control iicuentacontable" readonly value="1" />'+
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
                            	'<input type="text" style="WIDTH: 250px;HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="'+nombreCuentaContable+'" readonly>'+
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

					totalDebeAsiento = parseTotalAsientoCampo($("#totaldebeasiento").val());
					totalHaberAsiento = parseTotalAsientoCampo($("#totalhaberasiento").val());
					flModificaAsiento = false;
					if (callback) {
						callback(true);
					}
				}
				else {
					alert("Error en generación del asiento contable");
					if (callback) {
						callback(false);
					}
				}
			},
			error: function (r) {
				var msg = "Error grave en generación del asiento contable";
				if (r && r.responseJSON && r.responseJSON.message) {
					msg = r.responseJSON.message;
				} else if (r && r.responseText) {
					try {
						var j = JSON.parse(r.responseText);
						if (j.message) {
							msg = j.message;
						}
					} catch (e) {}
				}
				alert(msg);
				if (callback) {
					callback(false);
				}
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
		//$.get('/configuracion/leermoneda', function(data){
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
			url = carpetaBase+"/caja/actualizaringresoegreso/"+id;
		else
			url = carpetaBase+"/caja/ingresoegreso";

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
					var listarUri = carpetaBase+"/caja/movimientocaja";
				else
					var listarUri = carpetaBase+"/caja/ingresoegreso";

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
		let $opt = $('#tipotransaccion_caja_id option:selected');
		ingresoEgresoAbrevTipo = String($opt.data('abreviatura') || '').toUpperCase();
		actualizarAvisoTransferenciaIe();

		let tipotransaccion_caja_id = $('#tipotransaccion_caja_id').val();
		if (!tipotransaccion_caja_id) {
			return;
		}
		let url = carpetaBase+'/caja/leertipotransaccion_caja/'+tipotransaccion_caja_id;

		$.get(url, function(data){
			if (data.id > 0)
			{
				ingresoEgresoAbrevTipo = String(data.abreviatura || '').toUpperCase();
				actualizarAvisoTransferenciaIe();

				if (data.signo == 'E' && !esTransferenciaIngresoEgreso())
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
					if (esTransferenciaIngresoEgreso()) {
						$('#ordenservicio_id').val('');
						$('#conceptogasto_id').val('');
						$('#proveedor_id').val('');
					}
				}
			}
			else
			{
				alert("No existe el tipo de transaccion de caja");
				return;
			}
		});
	}

	function esTransferenciaIngresoEgreso()
	{
		if (ingresoEgresoAbrevTipo === ABREV_TRANSFERENCIA_IE) {
			return true;
		}
		var op = $('#tipotransaccion_caja_id option:selected').data('operacion');
		return String(op || '').toUpperCase() === 'T';
	}

	function actualizarAvisoTransferenciaIe()
	{
		if (esTransferenciaIngresoEgreso()) {
			$('#aviso-transferencia-ie').show();
		} else {
			$('#aviso-transferencia-ie').hide();
		}
		asegurarRenglonesCuentaPorTipo();
	}

	function cantidadRenglonesCuenta()
	{
		return $("#tbody-cuenta-table .item-cuenta").length;
	}

	function renglonCuentaVacio($tr)
	{
		var cta = parseInt($tr.find('.cuentacaja_id').val() || '0', 10);
		var codigo = String($tr.find('.codigo').val() || '').trim();
		var monto = parseFloat($tr.find('.monto').val() || '0');
		return cta <= 0 && codigo === '' && (isNaN(monto) || Math.abs(monto) < 0.000001);
	}

	/**
	 * TRA: al menos 2 renglones. Resto: 1 por defecto (quita solo vacíos sobrantes).
	 */
	function asegurarRenglonesCuentaPorTipo()
	{
		if (esTransferenciaIngresoEgreso()) {
			while (cantidadRenglonesCuenta() < 2) {
				agregaUnRenglon();
			}
			return;
		}

		while (cantidadRenglonesCuenta() > 1) {
			var $last = $("#tbody-cuenta-table .item-cuenta").last();
			if (!$last.length || !renglonCuentaVacio($last)) {
				break;
			}
			$last.remove();
		}
		if (cantidadRenglonesCuenta() === 0 && flCrear) {
			agregaUnRenglon();
		} else {
			actualizaRenglonesCuenta();
		}
	}

	function parseTotalAsientoCampo(val)
	{
		if (window.AsientoMontosFormato && typeof AsientoMontosFormato.parseDecimal === 'function') {
			return AsientoMontosFormato.parseDecimal(val);
		}
		if (typeof parseMontoAsiento === 'function') {
			return parseMontoAsiento(val);
		}
		var n = parseFloat(String(val || '').replace(/\./g, '').replace(',', '.'));
		return isNaN(n) ? 0 : n;
	}

	function validarBalanceCajaTransferencia()
	{
		sumaMonto();
		var hayEntrada = totalDebe > 0.02;
		var haySalida = totalHaber > 0.02;
		var lineas = 0;
		$("#tbody-cuenta-table .item-cuenta").each(function () {
			var cta = parseInt($(this).find('.cuentacaja_id').val() || '0', 10);
			var monto = parseFloat($(this).find('.monto').val() || '0');
			if (cta > 0 && Math.abs(monto) > 0.000001) {
				lineas++;
			}
		});
		if (lineas < 2 || !hayEntrada || !haySalida) {
			alert('Transferencia: cargue al menos una cuenta con monto positivo (entrada) y otra con monto negativo (salida).');
			return false;
		}
		if (Math.abs(totalDebe - totalHaber) > 0.02) {
			alert('Transferencia: las cuentas de caja deben quedar balanceadas (entradas = salidas).');
			return false;
		}
		return true;
	}

	function validarBalanceAsientoTransferencia()
	{
		sumaMontoAsiento();
		var debe = parseTotalAsientoCampo($("#totaldebeasiento").val());
		var haber = parseTotalAsientoCampo($("#totalhaberasiento").val());
		if (Math.abs(debe - haber) > 0.02 || debe < 0.02) {
			alert('Transferencia: el asiento contable debe estar balanceado (Debe = Haber).');
			return false;
		}
		if (Math.abs(debe - totalDebe) > 0.02) {
			alert('Transferencia: el total del asiento debe coincidir con el total de la operación de caja.');
			return false;
		}
		return true;
	}

		


