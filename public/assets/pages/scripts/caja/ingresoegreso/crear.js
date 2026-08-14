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
var montoPendienteSp = 0;
   
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
		montoPendienteSp = parseFloat($('#solicitudpago_monto_pendiente').val() || '0') || 0;
		if (parseInt($('#solicitudpago_id').val() || '0', 10) > 0) {
			// Forzar regeneración del asiento desde cuentas de la SP
			flModificaAsiento = true;
		}

		buscaTipoTransaccionCaja();
		activa_eventos(true);
		if (typeof activaEventosChequesIngresoEgreso === 'function') {
			activaEventosChequesIngresoEgreso();
		}
		initModoUsoIe();

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

			var ui = window.IngresoEgresoAnularRevertirUi;
			if (ui && ui.estaEnCurso && ui.estaEnCurso()) {
				return;
			}
			if (ui && ui.setEnCurso) {
				ui.setEnCurso(true);
			}
			if (ui && ui.mostrarProcesando) {
				ui.mostrarProcesando('Revirtiendo OP…');
			}

			let url = carpetaBase+'/caja/revertir_ingresoegreso';

			$.post(url, {_token: $('input[name=_token]').val(), 
						id: $('#id').val(),
						fecha: $('#fechacopia').val()}, function(data)
						{ 
							if (data && data.mensaje === 'ok') {
								var nro = (data.resultado && data.resultado.numerotransaccion) ? data.resultado.numerotransaccion : '';
								var texto = 'Transacción revertida.' + (nro ? (' Anulación N°: ' + nro + '.') : '');
								if (ui && ui.mostrarProcesando) {
									ui.mostrarProcesando('Listo', texto + ' Actualizando…');
								}
								window.setTimeout(function () {
									window.location.reload();
								}, 400);
								return;
							}
							if (ui && ui.setEnCurso) {
								ui.setEnCurso(false);
							}
							if (ui && ui.ocultarProcesando) {
								ui.ocultarProcesando();
							}
							alert((data && data.mensaje) ? data.mensaje : 'No se pudo revertir');
						}).fail(function(xhr){
							if (ui && ui.setEnCurso) {
								ui.setEnCurso(false);
							}
							if (ui && ui.ocultarProcesando) {
								ui.ocultarProcesando();
							}
							var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo revertir';
							alert(msg);
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

			if (!flError && !validarMontoSolicitudPagoIe()) {
				flError = true;
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

			if (!asientoIeBalanceado())
			{
				// Pago SP: el asiento pudo generarse antes de completar la cuenta de caja.
				// Se regenera una vez con los datos actuales y se vuelve a validar.
				if (esPagoSolicitudPagoIe()) {
					flModificaAsiento = true;
					generaAsientoContable(function (ok) {
						if (!ok) {
							return;
						}
						sumaMontoAsiento();
						if (!validarBalanceAsientoIe()) {
							muestraVentanaAsiento();
							return;
						}
						continuarGrabacionTrasValidaciones();
					});
					return;
				}

				validarBalanceAsientoIe();
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

	function esTeclaF1CuentaIe(e) {
		return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
	}

	function esTeclaEnterCuentaIe(e) {
		return e && (e.key === 'Enter' || e.keyCode === 13 || e.which === 13);
	}

	function modalConsultaCuentacajaAbiertoIe() {
		var $m = $('#consultacuentacajaModal');
		return $m.length && ($m.hasClass('show') || $m.is(':visible'));
	}

	function apuntarPtrsConsultaCuentaDesdeFila($tr) {
		cuentacajaxcodigo = $tr.find('.cuentacaja_id');
		nombrexcodigo = $tr.find('.nombre');
		codigoxcodigo = $tr.find('.codigo');
	}

	function abrirConsultaCuentaCajaFila($tr) {
		var empresa_id = $('#empresa_id').val();
		if (!empresa_id) {
			alert('Debe ingresar empresa');
			return;
		}
		apuntarPtrsConsultaCuentaDesdeFila($tr);
		$('#consultacuentacaja').val('');
		$('#datoscuentacaja').html('');
		$('#consultacuentacajaModal').modal('show');
	}

	function limpiarCuentaEnFilaIe($tr) {
		$tr.find('.cuentacaja_id, .cuentacaja_id_previa').val('');
		$tr.find('.codigo, .codigo_previo, .nombre').val('');
		$tr.find('.moneda').val('');
	}

	function aplicarCuentaCajaEnFilaIe($tr, data) {
		if (!$tr || !$tr.length || !data || !(parseInt(data.id, 10) > 0)) {
			return;
		}
		$tr.find('.cuentacaja_id').val(data.id);
		$tr.find('.cuentacaja_id_previa').val(data.id);
		$tr.find('.codigo').val(data.codigo != null ? data.codigo : $tr.find('.codigo').val());
		$tr.find('.codigo_previo').val(data.codigo != null ? data.codigo : $tr.find('.codigo').val());
		$tr.find('.nombre').val(data.nombre || '');
		if (data.moneda_id) {
			$tr.find('.moneda').val(data.moneda_id);
		}
		aplicarMontoDefaultSpSiCorresponde($tr);
		flModificaAsiento = true;
	}

	function enfocarCampoCuentaIe(el) {
		if (!el) {
			return;
		}
		setTimeout(function () {
			el.focus();
			if (typeof el.select === 'function' && el.tagName === 'INPUT' && el.type !== 'hidden') {
				el.select();
			}
		}, 0);
	}

	function listarCamposNavCuentaIe() {
		var out = [];
		$('#tbody-cuenta-table .item-cuenta').each(function () {
			$(this).find('.codigo, .monto, .cotizacion, .observacion').each(function () {
				if (this.disabled || this.readOnly) {
					return;
				}
				if (this.offsetParent === null) {
					return;
				}
				out.push(this);
			});
		});
		return out;
	}

	function siguienteCampoNavCuentaIe(actual) {
		var campos = listarCamposNavCuentaIe();
		var idx = campos.indexOf(actual);
		if (idx >= 0 && idx < campos.length - 1) {
			return campos[idx + 1];
		}
		return null;
	}

	function validarCodigoCuentaCajaIe($input, callback) {
		var $tr = $input.closest('tr.item-cuenta');
		var codigoNuevo = String($input.val() || '').trim();
		var empresaId = $('#empresa_id').val();

		if (!callback) {
			callback = function () {};
		}

		if (codigoNuevo === '') {
			limpiarCuentaEnFilaIe($tr);
			callback(false);
			return;
		}

		if (!empresaId) {
			alert('Debe ingresar empresa');
			callback(false);
			return;
		}

		var urlCta = carpetaBase + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigoNuevo);

		$.get(urlCta, { empresa_id: empresaId })
			.done(function (data) {
				if (data && parseInt(data.id, 10) > 0) {
					aplicarCuentaCajaEnFilaIe($tr, data);
					callback(true);
					return;
				}
				alert('No existe la cuenta de caja');
				limpiarCuentaEnFilaIe($tr);
				callback(false);
			})
			.fail(function (xhr) {
				var msg = 'No existe la cuenta de caja';
				if (xhr.responseJSON && xhr.responseJSON.error) {
					msg = xhr.responseJSON.error;
				}
				alert(msg);
				limpiarCuentaEnFilaIe($tr);
				callback(false);
			});
	}

	function validarMontoCuentaIe(input) {
		var raw = String(input.value || '').trim();
		if (raw === '') {
			alert('Ingrese un monto');
			return false;
		}
		var valor = parseFloat(raw);
		if (isNaN(valor)) {
			alert('Monto inválido');
			return false;
		}
		return true;
	}

	function validarCotizacionCuentaIe(input) {
		var raw = String(input.value || '').trim();
		if (raw === '') {
			alert('Ingrese la cotización');
			return false;
		}
		var valor = parseFloat(raw);
		if (isNaN(valor) || valor <= 0) {
			alert('Cotización inválida');
			return false;
		}
		return true;
	}

	function avanzarTrasCampoCuentaIe(input) {
		var next = siguienteCampoNavCuentaIe(input);
		if (next) {
			enfocarCampoCuentaIe(next);
			return;
		}
		agregaUnRenglon();
	}

	function manejarEnterCampoCuentaIe(input) {
		var $input = $(input);

		if ($input.hasClass('codigo')) {
			var codigo = String($input.val() || '').trim();
			if (codigo === '') {
				abrirConsultaCuentaCajaFila($input.closest('tr.item-cuenta'));
				return;
			}
			validarCodigoCuentaCajaIe($input, function (ok) {
				if (ok) {
					leeCotizacion(input);
					avanzarTrasCampoCuentaIe(input);
				} else {
					enfocarCampoCuentaIe(input);
				}
			});
			return;
		}

		if ($input.hasClass('monto')) {
			if (!validarMontoCuentaIe(input)) {
				enfocarCampoCuentaIe(input);
				return;
			}
			leeCotizacion(input);
			sumaMonto();
			flModificaAsiento = true;
			avanzarTrasCampoCuentaIe(input);
			return;
		}

		if ($input.hasClass('cotizacion')) {
			if (!validarCotizacionCuentaIe(input)) {
				enfocarCampoCuentaIe(input);
				return;
			}
			sumaMonto();
			flModificaAsiento = true;
			avanzarTrasCampoCuentaIe(input);
			return;
		}

		if ($input.hasClass('observacion')) {
			avanzarTrasCampoCuentaIe(input);
		}
	}

	function activarTecladoGrillaCuentaIe() {
		if (window.__ieCuentaTecladoActivo) {
			return;
		}
		window.__ieCuentaTecladoActivo = true;

		document.addEventListener('keydown', function (e) {
			var target = e.target;
			if (!target || !target.closest) {
				return;
			}
			var tabla = target.closest('#cuenta-table');
			if (!tabla) {
				return;
			}
			var $tr = $(target).closest('tr.item-cuenta');
			if (!$tr.length) {
				return;
			}

			if (esTeclaF1CuentaIe(e)) {
				if (!$(target).hasClass('codigo') && !$(target).hasClass('nombre')) {
					return;
				}
				if (target.readOnly && !$(target).hasClass('nombre')) {
					return;
				}
				if (modalConsultaCuentacajaAbiertoIe()) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				if (typeof e.stopImmediatePropagation === 'function') {
					e.stopImmediatePropagation();
				}
				abrirConsultaCuentaCajaFila($tr);
				return;
			}

			if (!esTeclaEnterCuentaIe(e)) {
				return;
			}
			if (modalConsultaCuentacajaAbiertoIe() || document.querySelector('.modal.show')) {
				return;
			}
			if (target.tagName === 'TEXTAREA' || target.tagName === 'BUTTON' || target.tagName === 'SELECT') {
				return;
			}
			if (!$(target).is('.codigo, .monto, .cotizacion, .observacion')) {
				return;
			}
			if (target.readOnly || target.disabled) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
			if (typeof e.stopImmediatePropagation === 'function') {
				e.stopImmediatePropagation();
			}
			manejarEnterCampoCuentaIe(target);
		}, true);
	}

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuenta').off('click');
			$('#cuenta-table .consultacuentacaja').off('click');
			$('#cuenta-table .codigo').off('change');
			$('#cuenta-table .monto').off('change');
			$('#cuenta-table .moneda').off('change');
			$('#tipotransaccion_caja_id').off('change');
			$('#proveedor_id').off('change');
			$('#servicioterrestre_id').off('change');
			$('.tipocomision').off('change');
			$('#cuenta-table .cotizacion').off('change');
		}

		// Activa eventos de consulta
		activa_eventos_consultaproveedor();
		activa_eventos_consultaconceptogasto();
		activarTecladoGrillaCuentaIe();

		$('#tipotransaccion_caja_id').on('change', function (event) {
			event.preventDefault();

			buscaTipoTransaccionCaja();
			flModificaAsiento = true;
			sumaMonto();
		});
		
		$('#cuenta-table .codigo').on('change', function (event) {
			event.preventDefault();
			var $input = $(this);
			validarCodigoCuentaCajaIe($input, function (ok) {
				if (ok) {
					leeCotizacion($input[0]);
					enfocarCampoCuentaIe($input.closest('tr').find('.monto')[0]);
				} else {
					enfocarCampoCuentaIe($input[0]);
				}
			});
		});

		$('#cuenta-table .consultacuentacaja').on('click', function (event) {
			event.preventDefault();
			abrirConsultaCuentaCajaFila($(this).closest('tr.item-cuenta'));
    	});

		$('#consultacuentacajaModal').off('shown.bs.modal.ieCuenta').on('shown.bs.modal.ieCuenta', function () {
			$(this).find('#consultacuentacaja, [autofocus]').first().focus();
		});

    	$('#aceptaconsultacuentacajaModal').off('click.ieCuenta').on('click.ieCuenta', function () {
        	$('#consultacuentacajaModal').modal('hide');
    	});

    	$(document).off('click.ieCuentaElige', '.eligeconsultacuentacaja').on('click.ieCuentaElige', '.eligeconsultacuentacaja', function () {
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
			if (!cuentacajaxcodigo || !cuentacajaxcodigo.length) {
				return;
			}
			var seleccion = $(this).parents("tr").children().html();
			var nombre = $(this).parents("tr").find(".nombre").html();
			var codigo = $(this).parents("tr").find(".codigo").html();
			var moneda_id = $(this).parents("tr").find(".moneda_id").html();
			var $tr = $(cuentacajaxcodigo).closest('tr.item-cuenta');

			aplicarCuentaCajaEnFilaIe($tr, {
				id: seleccion,
				codigo: codigo,
				nombre: nombre,
				moneda_id: moneda_id
			});
		
			$('#consultacuentacajaModal').modal('hide');
			flModificaAsiento = true;
			enfocarCampoCuentaIe($tr.find('.monto')[0]);
		});

		$('#cuenta-table .monto').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			sumaMonto();
			flModificaAsiento = true;
		});

		$('#cuenta-table .moneda').on('change', function (event) {
			event.preventDefault();
			leeCotizacion(this);
			flModificaAsiento = true;
		});

		$('#cuenta-table .cotizacion').on('change', function (event) {
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
		aplicarMontoDefaultSpSiCorresponde(ptrUltimoRenglon);
		$(ptrUltimoRenglon).find('.codigo').focus();

		activa_eventos(false);

		flModificaAsiento = true;
		sumaMonto();
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

		actualizarAvisoMontoSpIe();
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
				solicitudpago_id: $('#solicitudpago_id').val() || '',
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
								'<input type="hidden" class="codigo_previo_cuentacontable" name="codigo_previo_cuentacontables[]" value="'+cuentaContableCodigo+'" >'+
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

					// Rellena select de moneda y centros de costo (respeta CC de la SP)
					$("#cuenta-asiento-table .item-cuenta-asiento").each(function() {
						armaSelectMoneda(this);

						var $tr = $(this);
						var codigoPtr = $tr.find(".codigoasiento");
						var cuentaId = parseInt($tr.find(".cuentacontable_id").val() || '0', 10) || 0;
						var ccPrevio = parseInt($tr.find(".centrocostoasiento_id_previo").val() || '0', 10) || 0;
						if (cuentaId > 0 && typeof completarCentroCostoAsiento === 'function') {
							completarCentroCostoAsiento(codigoPtr, cuentaId, ccPrevio);
						} else if (cuentaId > 0 && typeof completarCentroCosto === 'function') {
							completarCentroCosto(codigoPtr, cuentaId, ccPrevio);
						} else {
							leeCentroCostoAsiento(codigoPtr);
						}
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
				if (data.mensaje == 'ok') {
					alert("Se grabó transacción de caja con éxito");
					if (data.url_comprobante_pdf) {
						window.open(data.url_comprobante_pdf, '_blank');
					}
					var listarUri = data.redirect_url;
					if (!listarUri) {
						let origen = $('#origen').val();
						if (origen == 'solicitudpago') {
							listarUri = carpetaBase + "/solicitudpago/solicitudpago";
						} else if (origen == 'movimientocaja') {
							listarUri = carpetaBase + "/caja/movimientocaja";
						} else {
							listarUri = carpetaBase + "/caja/ingresoegreso";
						}
					}
					window.location.href = listarUri;
					return;
				}

				var detalle = data.errores || data.error || data.message || '';
				alert(detalle ? ("Error de grabación: " + detalle) : "Error de grabación");
			},
			error :function( data ) {
				if( data.status === 422 ) {
					var msg = "Error de grabación, verifique los datos";
					if (data.responseJSON && data.responseJSON.errors) {
						var errs = data.responseJSON.errors;
						var parts = [];
						Object.keys(errs).forEach(function (k) {
							parts = parts.concat(errs[k]);
						});
						if (parts.length) {
							msg = parts.join("\n");
						}
					} else if (data.responseJSON && data.responseJSON.message) {
						msg = data.responseJSON.message;
					}
					alert(msg);
				}
				else
				{
					alert("Error de grabación "+data.status);
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
						if (typeof limpiarProveedorEnPantalla === 'function') {
							limpiarProveedorEnPantalla();
						} else {
							$('#proveedor_id').val('');
							$('#codigoproveedor').val('');
							$('#nombreproveedor').val('');
						}
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

	function esPagoSolicitudPagoIe()
	{
		return parseInt($('#solicitudpago_id').val() || '0', 10) > 0;
	}

	function asientoIeBalanceado()
	{
		totalDebeAsiento = parseTotalAsientoCampo($("#totaldebeasiento").val());
		totalHaberAsiento = parseTotalAsientoCampo($("#totalhaberasiento").val());
		return !(Math.abs(totalDebeAsiento - totalHaberAsiento) > 0.02 || totalDebeAsiento < 0.02);
	}

	function validarBalanceAsientoIe()
	{
		if (!asientoIeBalanceado()) {
			alert('Problemas en el asiento, no coincide el debe con el haber');
			return false;
		}
		return true;
	}

	function totalOperacionCajaActualIe()
	{
		return totalDebe > totalHaber ? totalDebe : totalHaber;
	}

	function restoMontoPendienteSpIe($trExcluir)
	{
		if (!(montoPendienteSp > 0)) {
			return 0;
		}
		var usado = 0;
		$('#tbody-cuenta-table .item-cuenta').each(function () {
			if ($trExcluir && $trExcluir.length && this === $trExcluir[0]) {
				return;
			}
			usado += Math.abs(parseFloat($(this).find('.monto').val()) || 0);
		});
		return Math.round((montoPendienteSp - usado) * 100) / 100;
	}

	function aplicarMontoDefaultSpSiCorresponde($tr)
	{
		if (!(montoPendienteSp > 0) || !$tr || !$tr.length) {
			return;
		}
		var $monto = $tr.find('.monto');
		var actual = String($monto.val() || '').trim();
		if (actual !== '' && Math.abs(parseFloat(actual) || 0) > 0.000001) {
			return;
		}
		var resto = restoMontoPendienteSpIe($tr);
		if (resto > 0.009) {
			$monto.val(resto.toFixed(2));
		}
	}

	function validarMontoSolicitudPagoIe()
	{
		if (!(montoPendienteSp > 0.009)) {
			return true;
		}
		var actual = totalOperacionCajaActualIe();
		if (Math.abs(actual - montoPendienteSp) > 0.02) {
			alert(
				'El total del pago (' + actual.toFixed(2) + ') debe ser exactamente el monto pendiente de la solicitud ('
				+ montoPendienteSp.toFixed(2) + '). No puede ser ni mayor ni menor.'
			);
			return false;
		}
		return true;
	}

	function actualizarAvisoMontoSpIe()
	{
		var $aviso = $('#aviso-monto-sp-ie');
		if (!(montoPendienteSp > 0.009)) {
			$aviso.addClass('d-none').text('');
			return;
		}
		if (!$aviso.length) {
			$('.totales-por-moneda').before(
				'<div id="aviso-monto-sp-ie" class="alert py-2 mb-2"></div>'
			);
			$aviso = $('#aviso-monto-sp-ie');
		}
		var actual = totalOperacionCajaActualIe();
		var diff = Math.round((actual - montoPendienteSp) * 100) / 100;
		var txt = 'Monto fijo SP: ' + montoPendienteSp.toFixed(2)
			+ ' — cargado: ' + actual.toFixed(2);
		if (Math.abs(diff) <= 0.02) {
			$aviso.removeClass('d-none alert-warning alert-danger').addClass('alert-success')
				.text(txt + ' (ok)');
		} else if (diff < 0) {
			$aviso.removeClass('d-none alert-success alert-danger').addClass('alert-warning')
				.text(txt + ' — faltan ' + Math.abs(diff).toFixed(2));
		} else {
			$aviso.removeClass('d-none alert-success alert-warning').addClass('alert-danger')
				.text(txt + ' — sobran ' + diff.toFixed(2));
		}
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

	/**
	 * Cards de modo de uso (general / TRA / canje cheques): filtra tipos y orienta solapas.
	 */
	function initModoUsoIe()
	{
		var $root = $('#ie-modo-uso');
		if (!$root.length) {
			return;
		}
		var $hidden = $('#ie_modo_uso');
		var $etiqueta = $('#ie-modo-etiqueta');
		var $select = $('#tipotransaccion_caja_id');
		if (!$select.data('ie-options-cache')) {
			$select.data('ie-options-cache', $select.find('option').clone(true));
		}

		function etiquetas(modo) {
			if (modo === 'transferencia') return 'Escenario: Transferencia entre cuentas';
			if (modo === 'canje_cheques') return 'Escenario: Canje / reemplazo de cheques';
			return 'Escenario: Operación general';
		}

		function filtrarTipos(modo) {
			var $cache = $select.data('ie-options-cache');
			var valorActual = $select.val();
			$select.empty();
			$cache.each(function () {
				var $opt = $(this).clone(true);
				var op = String($opt.attr('data-operacion') || '').toUpperCase();
				var abr = String($opt.attr('data-abreviatura') || '').toUpperCase();
				var val = $opt.attr('value');
				if (!val) {
					$select.append($opt);
					return;
				}
				var ok = true;
				if (modo === 'transferencia') {
					ok = abr === 'TRA' || op === 'T';
				} else if (modo === 'canje_cheques') {
					ok = op === 'E' || op === 'P' || abr === 'EGR' || abr === 'OPP';
				} else {
					ok = op !== 'T' && abr !== 'TRA';
				}
				if (ok) {
					$select.append($opt);
				}
			});
			if ($select.find('option[value="' + valorActual + '"]').length) {
				$select.val(valorActual);
			} else {
				var $first = $select.find('option[value!=""]').first();
				if ($first.length) {
					$select.val($first.attr('value'));
				}
			}
			$select.trigger('change');
		}

		function aplicar(modo) {
			$root.find('.ie-modo-card').each(function () {
				var on = $(this).attr('data-modo') === modo;
				$(this).toggleClass('is-selected', on);
				$(this).attr('aria-pressed', on ? 'true' : 'false');
			});
			if ($hidden.length) {
				$hidden.val(modo);
			}
			if ($etiqueta.length) {
				$etiqueta.text(etiquetas(modo));
			}
			filtrarTipos(modo);
			if (modo === 'canje_cheques') {
				setTimeout(function () {
					$('#botonform2').trigger('click');
					var $tabReemp = $('#tabs-cheques-ingresoegreso a[href="#panel-cheques-reemplazo"]');
					if ($tabReemp.length) {
						$tabReemp.trigger('click');
					}
				}, 50);
			}
		}

		$root.find('.ie-modo-card').on('click', function () {
			aplicar($(this).attr('data-modo'));
		});

		var inicial = $root.attr('data-flujo-inicial') || ($hidden.val() || 'general');
		aplicar(inicial);
	}

