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

		// Confirmar solo aplica a PRE CARGA
		if ($('#estado').val() !== 'PRE CARGA') {
			$('#botonconfirmar, #div-botonconfirmar').hide();
		}

		buscaTipoTransaccionCaja();
		activa_eventos(true);

		$(document).on('click', '#cob-btn-aplicar-secuencial', function (e) {
			e.preventDefault();
			aplicarSecuencialPorMontoCobranza($('#cob-monto-aplicar-secuencial').val());
		});
		$(document).on('keydown', '#cob-monto-aplicar-secuencial', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				aplicarSecuencialPorMontoCobranza($(this).val());
			}
		});
		$(document).on('click', '#cob-btn-limpiar-aplicaciones', function (e) {
			e.preventDefault();
			limpiarAplicacionesComprobantes();
		});

		const estado = $('#estado').val();

		if (estado == 'PRE CARGA')
			flModificaAsiento = true;

		function marcarSolapaCobActiva($boton) {
			$('#tabs-cobranza .nav-link').removeClass('active');
			$boton.addClass('active');
		}

		function ocultarSolapasCob() {
			$(".form1").hide();
			$(".form2").hide();
			$(".form3").hide();
			$(".form4").hide();
			$(".form5").hide();
			$(".formasientoexterno").hide();
			$(".form7").hide();
		}

		$("#botonform1").click(function(e){
			e.preventDefault();
            ocultarSolapasCob();
            $(".form1").show();
			marcarSolapaCobActiva($(this));
        });
		$("#botonform2").click(function(e){
			e.preventDefault();
			ocultarSolapasCob();
            $(".form2").show();
			marcarSolapaCobActiva($(this));
			sugerirMontoCajaDesdeAplicado();
        });
		$("#botonform3").click(function(e){
			e.preventDefault();
			ocultarSolapasCob();
			$(".form3").show();
			marcarSolapaCobActiva($(this));
			sugerirMontoChequeDesdeAplicado();
        });
		$("#botonform4").click(function(e){
			e.preventDefault();
			ocultarSolapasCob();
			$(".form4").show();
			marcarSolapaCobActiva($(this));
        });
		$("#botonform5").click(function(e){
			e.preventDefault();
			ocultarSolapasCob();
			$(".form5").show();
			marcarSolapaCobActiva($(this));
			leeHistoria();
        });
		$("#botonform6").click(function(e){
			e.preventDefault();
			if (flCrear || flModificaAsiento)
				generaAsientoContable();

			ocultarSolapasCob();
			$(".formasientoexterno").show();
			marcarSolapaCobActiva($(this));
        });
		$("#botonform7").click(function(e){
			e.preventDefault();
			ocultarSolapasCob();
			$(".form7").show();
			marcarSolapaCobActiva($(this));
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
				$("#botonform0").trigger('click');
			}
        });

		$('#aceptarevertircobranzaModal').on('click', function () {

			$('#revertircobranzaModal').modal('hide');

			let url = carpetaBase+'/caja/copiar_cobranza';

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
		$.get(carpetaBase+'/configuracion/leermoneda', function(data){
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

		var cliente_id = $('#cliente_id').val();
		if (cliente_id > 0)
			$(".editarcliente").show();
		else
			$(".editarcliente").hide();

		let valorOriginal = $('#nombrecliente').val();
		let clienteIdOriginal = String($('#cliente_id').val() || '');

		setInterval(function() {
			let valorActual = $('#nombrecliente').val();
			let clienteIdActual = String($('#cliente_id').val() || '');
			if (valorActual !== valorOriginal || clienteIdActual !== clienteIdOriginal) {

				if ((valorOriginal == '' && clienteIdOriginal === '') && !(venta_id > 0))
					leeCuentaCorriente();
				else
				{
					if (valorOriginal != '' || clienteIdOriginal !== '')
						leeCuentaCorriente();
				}

				valorOriginal = valorActual;
				clienteIdOriginal = clienteIdActual;

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
		}, 500);

		$('#empresa_id').focus();

		$( "#botonform0" ).click(function() {
			let flError = false;
	
			$("#tbody-cuenta-table tr").each(function() {
				var $tr = $(this);
				if (!filaCuentaCobranzaConDatos($tr)) {
					return;
				}
				if ($tr.find('.moneda').val() === '')
				{
					alert("Debe ingresar moneda");
					flError = true;
					return false;
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
	
			if (!flError) {
				marcarFilasVaciasCobranza();
				$( "#form-general" ).submit();
			}
		});

		aplicarUltimaTipoTransaccionCobranzaSiCorresponde();
		activarTecladoCobranza();
    });

	function fmtCob(n) {
		var v = Number(n);
		if (!Number.isFinite(v)) {
			v = 0;
		}
		return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function numeroSeguroCob(v) {
		var n = parseFloat(String(v == null ? '' : v).replace(',', '.'));
		return Number.isFinite(n) ? n : 0;
	}

	/** Fila de cuenta de caja con datos cargados (ignora renglones dados de alta vacíos). */
	function filaCuentaCobranzaConDatos($tr) {
		if (!$tr || !$tr.length) {
			return false;
		}
		var codigo = String($tr.find('.codigo').val() || '').trim();
		var id = String($tr.find('.cuentacaja_id').val() || '').trim();
		var monto = numeroSeguroCob($tr.find('.monto').val());
		return id !== '' || codigo !== '' || Math.abs(monto) > 0.000001;
	}

	function filaChequeCobranzaConDatos($tr) {
		if (!$tr || !$tr.length) {
			return false;
		}
		var banco = String($tr.find('.codigobanco').val() || '').trim();
		var nro = String($tr.find('.numerocheque').val() || '').trim();
		var monto = numeroSeguroCob($tr.find('.montocheque').val());
		return banco !== '' || nro !== '' || Math.abs(monto) > 0.000001;
	}

	function filaRetencionCobranzaConDatos($tr) {
		if (!$tr || !$tr.length) {
			return false;
		}
		var ret = String($tr.find('.retencion_cobranza_id').val() || '').trim();
		var comprobante = String($tr.find('.comprobanteretencion').val() || '').trim();
		var monto = numeroSeguroCob($tr.find('.montoretencion').val());
		return ret !== '' || comprobante !== '' || Math.abs(monto) > 0.000001;
	}

	function marcarFilasVaciasCobranza() {
		function marcar($trs, conDatos) {
			$trs.each(function () {
				var $tr = $(this);
				$tr.toggleClass('erp-fila-vacia', !conDatos($tr));
			});
		}
		marcar($('#tbody-cuenta-table tr'), filaCuentaCobranzaConDatos);
		marcar($('#tbody-cobranza-cheque-table tr'), filaChequeCobranzaConDatos);
		marcar($('#tbody-cobranza-retencion-table tr'), filaRetencionCobranzaConDatos);
		marcar($('#tbody-cuenta-asiento-table tr.item-cuenta-asiento'), function ($tr) {
			if (!$tr || !$tr.length) {
				return false;
			}
			var codigo = String($tr.find('.codigoasiento').val() || '').trim();
			var id = String($tr.find('.cuentacontable_id').val() || '').trim();
			var debe = numeroSeguroCob($tr.find('.debeasiento').val());
			var haber = numeroSeguroCob($tr.find('.haberasiento').val());
			return id !== '' || codigo !== '' || Math.abs(debe) > 0.000001 || Math.abs(haber) > 0.000001;
		});
	}

	function claveUltimaTipoCobranza() {
		var usuarioId = String($('#form-general').attr('data-usuario-id') || '').trim();
		return 'anitaERP_cobranza_ultima_tipotransaccion' + (usuarioId ? '_u' + usuarioId : '');
	}

	function guardarUltimaTipoTransaccionCobranza(tipoId) {
		var key = claveUltimaTipoCobranza();
		var id = String(tipoId || '').trim();
		if (!key || !id) {
			return;
		}
		try {
			localStorage.setItem(key, id);
		} catch (eIgn) {}
	}

	function aplicarUltimaTipoTransaccionCobranzaSiCorresponde() {
		var $sel = $('#tipotransaccion_caja_id');
		if (!$sel.length) {
			return;
		}
		if (String($('#id').val() || '').trim() !== '') {
			guardarUltimaTipoTransaccionCobranza($sel.val());
			return;
		}
		if (String($sel.val() || '').trim() !== '') {
			guardarUltimaTipoTransaccionCobranza($sel.val());
			return;
		}
		var guardada = '';
		try {
			guardada = String(localStorage.getItem(claveUltimaTipoCobranza()) || '').trim();
		} catch (eIgn) {
			guardada = '';
		}
		if (!guardada || !$sel.find('option[value="' + guardada + '"]').length) {
			return;
		}
		$sel.val(guardada).trigger('change');
	}

	function monedaBaseCobranza() {
		var m = null;
		$("#tbody-comprobante-table tr.item-comprobante").each(function () {
			if (numeroSeguroCob($(this).find('.montoaplicadocomprobante').val()) > 0.000001) {
				m = $(this).find('.monedacomprobante').val();
				return false;
			}
		});
		if (m) {
			return m;
		}
		m = $("#tbody-comprobante-table tr.item-comprobante").first().find('.monedacomprobante').val();
		if (m) {
			return m;
		}
		$("#tbody-cuenta-table tr").each(function () {
			if (filaCuentaCobranzaConDatos($(this))) {
				m = $(this).find('.moneda').val();
				return false;
			}
		});
		if (m) {
			return m;
		}
		$("#tbody-cobranza-cheque-table tr").each(function () {
			if (filaChequeCobranzaConDatos($(this))) {
				m = $(this).find('.monedacheque_id').val();
				return false;
			}
		});
		if (m) {
			return m;
		}
		$("#tbody-cobranza-retencion-table tr").each(function () {
			if (filaRetencionCobranzaConDatos($(this))) {
				m = $(this).find('.monedaretencion_id').val();
				return false;
			}
		});
		if (m) {
			return m;
		}
		return (idMoneda && idMoneda.length) ? idMoneda[0] : 1;
	}

	function coefSeguroCob(aMoneda, deMoneda, cotizacion) {
		if (!aMoneda || !deMoneda || String(aMoneda) === String(deMoneda)) {
			return 1;
		}
		var cot = numeroSeguroCob(cotizacion);
		if (cot <= 0) {
			cot = 1;
		}
		var coef = (typeof calculaCoeficienteMoneda === 'function')
			? calculaCoeficienteMoneda(aMoneda, deMoneda, cot)
			: 1;
		coef = Number(coef);
		return Number.isFinite(coef) ? coef : 1;
	}

	function esCreditoComprobante($tr) {
		if (!$tr || !$tr.length) {
			return false;
		}
		if (String($tr.attr('data-lado') || '') === 'credito') {
			return true;
		}
		return numeroSeguroCob($tr.find('.montocomprobante').val()) < 0;
	}

	function signoAplicacionComprobante($tr) {
		return esCreditoComprobante($tr) ? -1 : 1;
	}

	function disponibleComprobante($tr) {
		var disponible = parseFloat($tr.find('.saldocomprobante').attr('data-saldo-disponible'));
		if (!Number.isFinite(disponible)) {
			disponible = numeroSeguroCob($tr.find('.saldocomprobante').val());
		}
		if (!Number.isFinite(disponible) || Math.abs(disponible) <= 0.000001) {
			disponible = numeroSeguroCob($tr.find('.montocomprobante').val());
		}
		return Math.abs(disponible);
	}

	function limpiarAplicacionesComprobantes() {
		$('#tbody-comprobante-table tr.item-comprobante').each(function () {
			var $tr = $(this);
			$tr.find('.checkaplicacion').prop('checked', false);
			$tr.find('.montoaplicadocomprobante').val('');
			var ptr = $tr.find('.saldocomprobante')[0] || $tr.find('.checkaplicacion')[0];
			if (ptr) {
				actualizaSaldoComprobante(ptr);
			}
		});
		sumaMontoComprobante();
	}

	function aplicarSecuencialPorMontoCobranza(montoTotal) {
		var restante = Math.round(numeroSeguroCob(montoTotal) * 100) / 100;
		limpiarAplicacionesComprobantes();
		if (restante <= 0.009) {
			return;
		}
		$('#tbody-comprobante-table tr.item-comprobante').each(function () {
			if (restante <= 0.009) {
				return false;
			}
			var $tr = $(this);
			if (esCreditoComprobante($tr)) {
				return;
			}
			var disponible = disponibleComprobante($tr);
			var aplicar = Math.min(disponible, restante);
			if (aplicar <= 0.009) {
				return;
			}
			$tr.find('.montoaplicadocomprobante').val(aplicar.toFixed(2));
			$tr.find('.checkaplicacion').prop('checked', true);
			actualizaSaldoComprobante($tr.find('.montoaplicadocomprobante')[0]);
			restante = Math.round((restante - aplicar) * 100) / 100;
		});
		sumaMontoComprobante();
	}

	function pintarResumenLiquidacion() {
		var aplicado = 0;
		var saldoDeuda = 0;
		var montoDeuda = 0;
		$('#tbody-comprobante-table .montoaplicadocomprobante').each(function () {
			var $tr = $(this).closest('tr');
			aplicado += signoAplicacionComprobante($tr) * (parseFloat(String($(this).val()).replace(',', '.')) || 0);
			saldoDeuda += parseFloat(String($tr.find('.saldocomprobante').val()).replace(',', '.')) || 0;
			montoDeuda += parseFloat(String($tr.find('.montocomprobante').val()).replace(',', '.')) || 0;
		});

		var descuentos = 0;
		if (typeof totalDescuentosPorMoneda === 'function') {
			var dm = totalDescuentosPorMoneda();
			Object.keys(dm || {}).forEach(function (k) {
				descuentos += Number(dm[k]) || 0;
			});
		}

		var retenciones = 0;
		$('#tbody-cobranza-retencion-table tr').each(function () {
			if (!filaRetencionCobranzaConDatos($(this))) {
				return;
			}
			retenciones += numeroSeguroCob($(this).find('.montoretencion').val());
		});

		var medios = 0;
		$('#tbody-cuenta-table tr').each(function () {
			if (!filaCuentaCobranzaConDatos($(this))) {
				return;
			}
			medios += numeroSeguroCob($(this).find('.monto').val());
		});
		$('#tbody-cobranza-cheque-table tr').each(function () {
			if (!filaChequeCobranzaConDatos($(this))) {
				return;
			}
			medios += numeroSeguroCob($(this).find('.montocheque').val());
		});

		var acobrar = Math.round((aplicado - descuentos - retenciones) * 100) / 100;
		var dif = Math.round((medios - acobrar) * 100) / 100;
		var neto = Math.round((aplicado - descuentos) * 100) / 100;

		$('#cob-bar-aplicado, #cob-card-aplicado, #cob-tfoot-aplicado, #cob-ref-aplicado-txt').text(fmtCob(aplicado));
		$('#cob-bar-descuentos, #cob-card-descuentos').text(fmtCob(descuentos));
		$('#cob-bar-retenciones, #cob-ref-retenciones-txt').text(fmtCob(retenciones));
		$('#cob-bar-acobrar, #cob-card-neto, #cob-ref-acobrar-txt').text(fmtCob(acobrar));
		$('#cob-bar-medios, #cob-ref-medios-txt').text(fmtCob(medios));
		$('#cob-bar-dif, #cob-ref-dif-txt').text(fmtCob(dif));
		$('#cob-card-saldo, #cob-tfoot-saldo').text(fmtCob(saldoDeuda));
		$('#cob-tfoot-monto').text(fmtCob(montoDeuda));

		var $dif = $('#cob-bar-dif, #cob-ref-dif-txt');
		$dif.removeClass('text-success text-danger text-warning');
		if (Math.abs(dif) < 0.015) {
			$dif.addClass('text-success');
			$('#cob-bar-dif-wrap').css('background', '#d5f5e3');
		} else {
			$dif.addClass(dif < 0 ? 'text-danger' : 'text-warning');
			$('#cob-bar-dif-wrap').css('background', '#fdebd0');
		}

		if ($('#tbody-comprobante-table tr.item-comprobante').length) {
			$('.cob-deuda-tfoot').show();
		} else {
			$('.cob-deuda-tfoot').hide();
		}
	}

	window.pintarResumenLiquidacion = pintarResumenLiquidacion;

	function importeFilaCuentaCobEnMonedaBase($tr) {
		var valor = numeroSeguroCob($tr.find('.monto').val());
		var moneda = $tr.find('.moneda').val();
		var cotizacion = $tr.find('.cotizacion').val();
		return valor * coefSeguroCob(monedaBaseCobranza(), moneda, cotizacion);
	}

	function importeFilaChequeCobEnMonedaBase($tr) {
		var valor = numeroSeguroCob($tr.find('.montocheque').val());
		var moneda = $tr.find('.monedacheque_id').val();
		var cotizacion = $tr.find('.cotizacioncheque').val();
		return valor * coefSeguroCob(monedaBaseCobranza(), moneda, cotizacion);
	}

	function montoRestanteCobranza($trExcluir) {
		var monedaDefault = monedaBaseCobranza();
		var aplicado = 0;
		$('#tbody-comprobante-table .montoaplicadocomprobante').each(function () {
			var valor = numeroSeguroCob($(this).val());
			if (Math.abs(valor) < 0.000001) {
				return;
			}
			var $tr = $(this).closest('tr');
			valor *= signoAplicacionComprobante($tr);
			var moneda = $tr.find('.monedacomprobante').val();
			var cotizacion = $tr.find('.cotizacioncomprobante').val();
			aplicado += valor * coefSeguroCob(monedaDefault, moneda, cotizacion);
		});

		var descuentos = 0;
		if (typeof totalDescuentosPorMoneda === 'function') {
			var dm = totalDescuentosPorMoneda();
			idMoneda.forEach(function (moneda) {
				var desc = (dm && dm[moneda]) || 0;
				if (desc <= 0) {
					return;
				}
				var cotizacion = $('#tbody-comprobante-table .monedacomprobante').filter(function () {
					return $(this).val() == moneda;
				}).first().parents('tr').find('.cotizacioncomprobante').val();
				descuentos += desc * coefSeguroCob(monedaDefault, moneda, cotizacion);
			});
		}

		var retenciones = 0;
		$('#tbody-cobranza-retencion-table tr').each(function () {
			var $tr = $(this);
			if (!filaRetencionCobranzaConDatos($tr)) {
				return;
			}
			var valor = numeroSeguroCob($tr.find('.montoretencion').val());
			var moneda = $tr.find('.monedaretencion_id').val();
			var cotizacion = $tr.find('.cotizacionretencion').val();
			retenciones += valor * coefSeguroCob(monedaDefault, moneda, cotizacion);
		});

		var medios = 0;
		$('#tbody-cuenta-table tr').each(function () {
			if ($trExcluir && this === $trExcluir[0]) {
				return;
			}
			if (!filaCuentaCobranzaConDatos($(this))) {
				return;
			}
			medios += importeFilaCuentaCobEnMonedaBase($(this));
		});
		$('#tbody-cobranza-cheque-table tr').each(function () {
			if ($trExcluir && this === $trExcluir[0]) {
				return;
			}
			if (!filaChequeCobranzaConDatos($(this))) {
				return;
			}
			medios += importeFilaChequeCobEnMonedaBase($(this));
		});

		var resto = Math.round((aplicado - descuentos - retenciones - medios) * 100) / 100;
		return resto > 0 ? resto : 0;
	}

	function montoRestanteEnMonedaFilaCob($tr, selectorMoneda, selectorCotizacion) {
		var restoBase = montoRestanteCobranza($tr);
		if (restoBase <= 0) {
			return 0;
		}
		var monedaDefault = monedaBaseCobranza();
		var moneda = $tr.find(selectorMoneda).val();
		var cotizacion = $tr.find(selectorCotizacion).val();
		var coef = coefSeguroCob(monedaDefault, moneda, cotizacion);
		if (!coef) {
			coef = 1;
		}
		return Math.round((restoBase / coef) * 100) / 100;
	}

	function precargarMontoRestanteEnCuenta($tr, forzar) {
		if (!$tr || !$tr.length) {
			return;
		}
		var $monto = $tr.find('.monto');
		if (!$monto.length) {
			return;
		}
		var actual = numeroSeguroCob($monto.val());
		if (!forzar && actual > 0) {
			return;
		}
		var resto = montoRestanteEnMonedaFilaCob($tr, '.moneda', '.cotizacion');
		if (resto > 0) {
			$monto.val(resto.toFixed(2));
			sumaMonto();
		}
	}

	function precargarMontoRestanteEnCheque($tr, forzar) {
		if (!$tr || !$tr.length) {
			return;
		}
		var $monto = $tr.find('.montocheque');
		if (!$monto.length) {
			return;
		}
		var actual = numeroSeguroCob($monto.val());
		if (!forzar && actual > 0) {
			return;
		}
		var resto = montoRestanteEnMonedaFilaCob($tr, '.monedacheque_id', '.cotizacioncheque');
		if (resto > 0) {
			$monto.val(resto.toFixed(2));
			sumaMontoCheque();
		}
	}

	function sugerirMontoCajaDesdeAplicado() {
		var $filas = $('#tbody-cuenta-table tr');
		if (!$filas.length) {
			agregaUnRenglonCuenta();
			$filas = $('#tbody-cuenta-table tr');
		}
		var $vacia = $filas.filter(function () {
			return !(numeroSeguroCob($(this).find('.monto').val()) > 0);
		}).first();
		if ($vacia.length) {
			precargarMontoRestanteEnCuenta($vacia, true);
		}
	}

	function sugerirMontoChequeDesdeAplicado() {
		var $filas = $('#tbody-cobranza-cheque-table tr');
		var $vacia = $filas.filter(function () {
			return !(numeroSeguroCob($(this).find('.montocheque').val()) > 0);
		}).first();
		if ($vacia.length) {
			precargarMontoRestanteEnCheque($vacia, true);
		}
	}

	function enfocarCampoCob(el) {
		if (!el) {
			return;
		}
		setTimeout(function () {
			el.focus();
			if (typeof el.select === 'function' && el.tagName === 'INPUT') {
				el.select();
			}
		}, 0);
	}

	function buscarCuentaCajaCob($input, onOk) {
		var codigo = String($input.val() || '').trim();
		var $tr = $input.closest('tr');
		if (!codigo) {
			if (typeof onOk === 'function') {
				onOk(false);
			}
			return;
		}
		if ($input.data('cob-buscando')) {
			return;
		}
		$input.data('cob-buscando', 1);
		var url_cta = carpetaBase + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo);
		$.get(url_cta, function (data) {
			if (data && data.id > 0) {
				$tr.find('.cuentacaja_id').val(data.id);
				$tr.find('.cuentacaja_id_previa').val(data.id);
				$tr.find('.nombre').val(data.nombre);
				$tr.find('.moneda').val(data.moneda_id);
				$tr.find('.codigo_previo').val(codigo);
				flModificaAsiento = true;
				precargarMontoRestanteEnCuenta($tr, false);
				sumaMonto();
				if (typeof onOk === 'function') {
					onOk(true);
				}
			} else {
				alert('No existe la cuenta de caja');
				$tr.find('.cuentacaja_id').val('');
				$tr.find('.nombre').val('');
				if (typeof onOk === 'function') {
					onOk(false);
				}
			}
		}).fail(function () {
			alert('Error al consultar la cuenta de caja');
			if (typeof onOk === 'function') {
				onOk(false);
			}
		}).always(function () {
			$input.data('cob-buscando', 0);
		});
	}

	function activarTecladoCobranza() {
		if (window.__cobTecladoActivo) {
			return;
		}
		window.__cobTecladoActivo = true;

		document.addEventListener('keydown', function (e) {
			var target = e.target;
			if (!target || !target.closest) {
				return;
			}

			var enForm = target.closest('#form-general');
			if (!enForm) {
				return;
			}

			var esF1 = e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
			var esEnter = e.key === 'Enter' || e.which === 13;

			if (esF1) {
				var $tF1 = $(target);
				if ($tF1.is('.codigo, .nombre') && target.closest('#cuenta-table')) {
					var $modalCc = $('#consultacuentacajaModal');
					if ($modalCc.length && ($modalCc.hasClass('show') || $modalCc.is(':visible'))) {
						return;
					}
					e.preventDefault();
					e.stopPropagation();
					if (typeof e.stopImmediatePropagation === 'function') {
						e.stopImmediatePropagation();
					}
					$tF1.closest('tr').find('.consultacuentacaja').trigger('click');
					return;
				}
				return;
			}

			if (!esEnter) {
				return;
			}

			// No bloquear Enter en textareas ni en el buscador del modal
			if (target.tagName === 'TEXTAREA' || target.closest('.modal')) {
				return;
			}

			var $t = $(target);

			// Cuentas de caja
			if (target.closest('#cuenta-table') && $t.is('.codigo, .monto, .cotizacion, .observacion')) {
				e.preventDefault();
				e.stopPropagation();
				if (typeof e.stopImmediatePropagation === 'function') {
					e.stopImmediatePropagation();
				}
				if ($t.hasClass('codigo')) {
					if (!String($t.val() || '').trim()) {
						return;
					}
					buscarCuentaCajaCob($t, function (ok) {
						if (ok) {
							enfocarCampoCob($t.closest('tr').find('.monto')[0]);
						} else {
							enfocarCampoCob(target);
						}
					});
					return;
				}
				if ($t.hasClass('monto')) {
					var monto = parseFloat(String(target.value || '').replace(',', '.'));
					if (isNaN(monto) || Math.abs(monto) < 0.000001) {
						alert('Ingrese un monto');
						enfocarCampoCob(target);
						return;
					}
					sumaMonto();
					flModificaAsiento = true;
					enfocarCampoCob($t.closest('tr').find('.cotizacion')[0]);
					return;
				}
				if ($t.hasClass('cotizacion')) {
					sumaMonto();
					enfocarCampoCob($t.closest('tr').find('.observacion')[0]);
					return;
				}
				if ($t.hasClass('observacion')) {
					var $trCta = $t.closest('tr');
					if ($trCta.next('tr').length) {
						enfocarCampoCob($trCta.next('tr').find('.codigo')[0]);
					} else {
						$('#agrega_renglon_cuenta').trigger('click');
					}
					return;
				}
			}

			// Cheques recibidos
			if (target.closest('#cobranza-cheque-table') && $t.is('.codigobanco, .fechapago, .numerocheque, .sucursalpago, .cuentalibradora, .montocheque, .cotizacioncheque, .monedacheque_id')) {
				e.preventDefault();
				e.stopPropagation();
				if (typeof e.stopImmediatePropagation === 'function') {
					e.stopImmediatePropagation();
				}
				var $trCh = $t.closest('tr');
				if ($t.hasClass('fechapago')) {
					enfocarCampoCob($trCh.find('.codigobanco')[0]);
					return;
				}
				if ($t.hasClass('codigobanco')) {
					// banco/consulta.js resuelve en change; forzar blur/change y avanzar
					$t.trigger('change');
					enfocarCampoCob($trCh.find('.numerocheque')[0]);
					return;
				}
				if ($t.hasClass('numerocheque')) {
					enfocarCampoCob($trCh.find('.sucursalpago')[0]);
					return;
				}
				if ($t.hasClass('sucursalpago')) {
					enfocarCampoCob($trCh.find('.cuentalibradora')[0]);
					return;
				}
				if ($t.hasClass('cuentalibradora')) {
					enfocarCampoCob($trCh.find('.montocheque')[0]);
					return;
				}
				if ($t.hasClass('montocheque')) {
					var mCh = parseFloat(String(target.value || '').replace(',', '.'));
					if (isNaN(mCh) || Math.abs(mCh) < 0.000001) {
						alert('Ingrese un monto');
						enfocarCampoCob(target);
						return;
					}
					sumaMontoCheque();
					flModificaAsiento = true;
					enfocarCampoCob($trCh.find('.cotizacioncheque')[0]);
					return;
				}
				if ($t.hasClass('cotizacioncheque')) {
					sumaMontoCheque();
					if ($trCh.next('tr').length) {
						enfocarCampoCob($trCh.next('tr').find('.fechapago')[0]);
					} else {
						$('#agrega_renglon_cheque').trigger('click');
					}
					return;
				}
			}

			// Retenciones
			if (target.closest('#cobranza-retencion-table') && $t.is('.retencion_cobranza_id, .comprobanteretencion, .tasaretencion, .montoretencion, .cotizacionretencion, .monedaretencion_id')) {
				e.preventDefault();
				e.stopPropagation();
				if (typeof e.stopImmediatePropagation === 'function') {
					e.stopImmediatePropagation();
				}
				var $trRet = $t.closest('tr');
				if ($t.hasClass('retencion_cobranza_id')) {
					enfocarCampoCob($trRet.find('.comprobanteretencion')[0]);
					return;
				}
				if ($t.hasClass('comprobanteretencion')) {
					enfocarCampoCob($trRet.find('.tasaretencion')[0]);
					return;
				}
				if ($t.hasClass('tasaretencion')) {
					enfocarCampoCob($trRet.find('.montoretencion')[0]);
					return;
				}
				if ($t.hasClass('montoretencion')) {
					var mRet = parseFloat(String(target.value || '').replace(',', '.'));
					if (isNaN(mRet) || Math.abs(mRet) < 0.000001) {
						alert('Ingrese un monto');
						enfocarCampoCob(target);
						return;
					}
					sumaMontoRetencion();
					flModificaAsiento = true;
					enfocarCampoCob($trRet.find('.cotizacionretencion')[0]);
					return;
				}
				if ($t.hasClass('cotizacionretencion')) {
					sumaMontoRetencion();
					if ($trRet.next('tr').length) {
						enfocarCampoCob($trRet.next('tr').find('.retencion_cobranza_id')[0]);
					} else {
						$('#agrega_renglon_retencion').trigger('click');
					}
					return;
				}
			}

			// Cliente / cabecera / aplicado en grilla
			if ($t.is('#codigocliente, .codigocliente')) {
				e.preventDefault();
				e.stopPropagation();
				var codigoCli = String($t.val() || '').trim();
				if (codigoCli && typeof leeUnCliente === 'function') {
					leeUnCliente(0, codigoCli, true);
				}
				return;
			}

			if ($t.is('#detalle, #fecha, #tipotransaccion_caja_id, #empresa_id')) {
				e.preventDefault();
				var ordenCab = ['#empresa_id', '#fecha', '#tipotransaccion_caja_id', '#codigocliente', '#detalle'];
				var idx = ordenCab.indexOf('#' + ($t.attr('id') || ''));
				if (idx >= 0 && idx < ordenCab.length - 1) {
					enfocarCampoCob($(ordenCab[idx + 1])[0]);
				} else if ($t.is('#detalle')) {
					enfocarCampoCob($('#tbody-comprobante-table tr:first .montoaplicadocomprobante')[0]
						|| $('#agrega_renglon_cuenta')[0]);
				}
				return;
			}

			if ($t.hasClass('montoaplicadocomprobante')) {
				e.preventDefault();
				e.stopPropagation();
				sumaMontoComprobante();
				var $trComp = $t.closest('tr');
				var $next = $trComp.nextAll('tr.item-comprobante').first().find('.montoaplicadocomprobante');
				if ($next.length) {
					enfocarCampoCob($next[0]);
				} else {
					$('#botonform2').trigger('click');
					setTimeout(function () {
						var $cod = $('#tbody-cuenta-table tr:last .codigo');
						if (!$cod.length || $cod.val()) {
							$('#agrega_renglon_cuenta').trigger('click');
							$cod = $('#tbody-cuenta-table tr:last .codigo');
						}
						enfocarCampoCob($cod[0]);
					}, 50);
				}
				return;
			}

			// Evita submit accidental en el resto de inputs del form
			if ($t.is('input, select') && !$t.is('[type=submit], [type=button]')) {
				e.preventDefault();
			}
		}, true);
	}

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
			guardarUltimaTipoTransaccionCobranza($(this).val());
			buscaTipoTransaccionCaja();
		});
		
		$('#empresa_id').on('change', function (event) {
			leeCuentaCorriente();
		});

		$('#cuenta-table').off('change.cobCuentacaja', '.codigo');
		$('#cuenta-table').on('change.cobCuentacaja', '.codigo', function () {
			var $input = $(this);
			var codigoNuevo = String($input.val() || '').trim();
			if (!codigoNuevo || codigoNuevo === 'undefined') {
				return;
			}
			buscarCuentaCajaCob($input);
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

			let ptrUltimoRenglon = $(cuentacajaxcodigo).parents('tr');
			if (!ptrUltimoRenglon.length) {
				ptrUltimoRenglon = $("#tbody-cuenta-table tr:last");
			}
			precargarMontoRestanteEnCuenta(ptrUltimoRenglon, false);
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
			let $tr = $(this).parents("tr");

			if ($(this).prop("checked"))
			{
				let disponible = disponibleComprobante($tr);
				$tr.find('.montoaplicadocomprobante').val(disponible.toFixed(2));
				$tr.find('.montoaplicadocomprobante').focus();
			}
			else
			{
				$tr.find('.montoaplicadocomprobante').val('');
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

			// Marca / destilda según haya monto
			var monto = numeroSeguroCob($(this).val());
			$(this).parents("tr").find('.checkaplicacion').prop('checked', monto > 0.009);
		})
	}

	function actualizaSaldoComprobante(ptr)
	{
		let $tr = $(ptr).parents("tr");
		let disponibleSigned = parseFloat($tr.find('.saldocomprobante').attr('data-saldo-disponible'));
		if (!Number.isFinite(disponibleSigned)) {
			disponibleSigned = numeroSeguroCob($tr.find('.montocomprobante').val());
		}
		let aplicadoRaw = $tr.find('.montoaplicadocomprobante').val();
		let aplicado = 0;
		if (aplicadoRaw !== '' && aplicadoRaw !== null && Number.isFinite(parseFloat(String(aplicadoRaw).replace(',', '.')))) {
			aplicado = Math.abs(numeroSeguroCob(aplicadoRaw));
		}
		let saldo = esCreditoComprobante($tr)
			? (disponibleSigned + aplicado)
			: (disponibleSigned - aplicado);

		if (!Number.isFinite(saldo)) {
			saldo = disponibleSigned;
		}

		$tr.find('.saldocomprobante').val(saldo.toFixed(2));
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
		$('#tabs-cobranza .nav-link').removeClass('active');
		$('#botonform6').addClass('active');
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
		precargarMontoRestanteEnCuenta(ptrUltimoRenglon, true);

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

    	$("#tbody-cobranza-cheque-table").append(renglon);

		// Hace focus sobre el primer elemento de la tabla
		let ptrUltimoRenglon = $("#tbody-cobranza-cheque-table tr:last");
		var monedaDefault = monedaBaseCobranza();
		if (monedaDefault) {
			ptrUltimoRenglon.find('.monedacheque_id').val(monedaDefault);
		}
		$(ptrUltimoRenglon).find('.fechapago').focus();

		activa_eventos(false);
		precargarMontoRestanteEnCheque(ptrUltimoRenglon, true);

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
			let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
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
			let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
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
			let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
		
			$.get(url_cot, function(data){
				$(ptr).parents("tr").find('.cotizacionretencion').val(data.cotizacionventa);
				sumaMontoRetencion();
			});
		}
	}

	function normalizaFechaInputDate(valor)
	{
		if (valor === null || valor === undefined || valor === '') {
			return '';
		}
		var texto = String(valor).trim();
		var m = texto.match(/^(\d{4}-\d{2}-\d{2})/);
		if (m) {
			return m[1];
		}
		return '';
	}

	function leeCuentaCorriente()
	{
		let cliente_id = $("#cliente_id").val();
		let empresa_id = $("#empresa_id").val();
		let venta_id = $('#venta_id').val();

		if (!venta_id && !(parseInt(cliente_id, 10) > 0)) {
			$('#tbody-comprobante-table').empty();
			$('#tbody-nc-pendiente-table').empty();
			sumaMontoComprobante();
			return;
		}

		if (venta_id > 0)
			var url = carpetaBase+'/ventas/cliente/consultadeuda/0/0/'+venta_id;
		else
			var url = carpetaBase+'/ventas/cliente/consultadeuda/'+cliente_id+'/'+empresa_id;

		// Vaciar solo tras respuesta OK: si falla el GET no se pierde lo ya cargado/aplicado.
		$.get(url, function(data){
			$('#tbody-comprobante-table').empty();
			$('#tbody-nc-pendiente-table').empty();

			$.each(data, function(index, item) {
				agregaRenglonComprobante();

				var $fila = $('#tbody-comprobante-table tr.item-comprobante').last();
				$fila.find('.idventa').val(item.idventa);
				$fila.find('.idcuentacorriente').val(item.idcuentacorriente);
				$fila.find('.codigocomprobante').val(item.codigo);
				$fila.find('.fechacomprobante').val(normalizaFechaInputDate(item.fecha));
				$fila.find('.fechavencimientocomprobante').val(normalizaFechaInputDate(item.fechavencimiento));
				$fila.find('.monedacomprobante').val(item.moneda_id);
				$fila.find('.cotizacioncomprobante').val(Number(item.cotizacion || 0).toFixed(4));

				var totalComp = parseFloat(item.total) || 0;
				var aplicadoPrevio = (item.aplicado === null || item.aplicado === undefined)
					? 0
					: (parseFloat(item.aplicado) || 0);
				var saldoDisponible = (item.saldo !== undefined && item.saldo !== null)
					? (parseFloat(item.saldo) || 0)
					: (totalComp + aplicadoPrevio);
				var lado = item.lado || ((totalComp < 0 || saldoDisponible < 0) ? 'credito' : 'deuda');
				$fila.attr('data-lado', lado);
				$fila.toggleClass('item-comprobante-credito', lado === 'credito');

				$fila.find('.montocomprobante').val(totalComp.toFixed(2));
				$fila.find('.montoaplicadocomprobante').val('');
				$fila.find('.saldocomprobante')
					.val(saldoDisponible.toFixed(2))
					.attr('data-saldo-disponible', saldoDisponible.toFixed(2));
				$fila.find('.checkaplicacion').prop('checked', false);

				if (item.idventa) {
					let urlEditarFactura = route('editar_factura', ':id');
					let urlGenerarNotaDeCredito = route('generar_notadecredito', ':id');
					let urlListarFactura = route('lista_una_factura', ':id');
					$fila.find('.editarfactura').attr('href', urlEditarFactura.replace(':id', item.idventa)).show();
					$fila.find('.generarnotadecredito').attr('href', urlGenerarNotaDeCredito.replace(':id', item.idventa)).show();
					$fila.find('.listarfactura').attr('href', urlListarFactura.replace(':id', item.idventa)).show();
				} else {
					$fila.find('.editarfactura, .generarnotadecredito').hide();
					if (item.cobranza_id && typeof route === 'function') {
						try {
							$fila.find('.listarfactura').attr('href', route('listar_una_cobranza', item.cobranza_id)).show();
						} catch (e) {
							$fila.find('.listarfactura').hide();
						}
					} else {
						$fila.find('.listarfactura').hide();
					}
				}

				if (venta_id > 0)
				{
					$('#empresa_id').val(item.empresa_id);
					$('#cliente_id').val(item.cliente_id);
					$('#nombrecliente').val(item.nombrecliente);
					if (item.codigocliente) {
						$('#codigocliente').val(item.codigocliente);
					}
					if (item.cliente_id && window.clientePoliticaComercial) {
						$.get(carpetaBase+'/ventas/leeruncliente/'+item.cliente_id).done(function (cli) {
							if (cli && cli.politica_comercial) {
								window.clientePoliticaComercial.setActual(cli.politica_comercial);
							}
						});
					}
				}
			});
		}).done(function(data, textStatus, jqXHR) {
			activa_eventos(false);
			sumaMontoComprobante();
		}).fail(function () {
			alert('No se pudo cargar la deuda del cliente');
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
			let $tr = $(this).parents("tr");
			let moneda = $tr.find('.monedacomprobante').val();
			let cotizacion = $tr.find('.cotizacioncomprobante').val();
			let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

			if (!Number.isNaN(valor))
				totalMoneda[moneda] += valor * signoAplicacionComprobante($tr);
        });

		// Muestra totales por moneda
		$(wrapper).empty();

		const descuentosMoneda = (typeof totalDescuentosPorMoneda === 'function') ? totalDescuentosPorMoneda() : {};

		idMoneda.forEach(function(moneda, indice, array) {
			let detalleLabel = 'Total a cobrar '+descripcionMoneda[moneda];
			const desc = descuentosMoneda[moneda] || 0;
			const neto = (totalMoneda[moneda] || 0) - desc;

			if (totalMoneda[moneda] !== undefined && totalMoneda[moneda] != 0) 
			{
				$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
				$(wrapper).append('<input type="hidden" name="monedacomprobantes[]" class="form-control col-lg-1" readonly value="'+moneda+'" />');
				$(wrapper).append('<input type="text" name="totalcomprobantes[]" class="form-control col-lg-1" readonly value="'+neto.toFixed(2)+'" />');
			}
		});

		if (typeof sumaTotalDescuentosPantalla === 'function') {
			sumaTotalDescuentosPantalla();
		}

		sumaCobranza();
	}

	function sumaMonto()
	{
		let monedaDefault = monedaBaseCobranza();
		var wrapper = $(".totales-por-moneda");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cuenta-table tr").each(function() {
			var $tr = $(this);
			if (!filaCuentaCobranzaConDatos($tr)) {
				return;
			}
            let valor = numeroSeguroCob($tr.find('.monto').val());
			let moneda = $tr.find('.moneda').val();
			if (!moneda || !Number.isFinite(totalMoneda[moneda])) {
				return;
			}
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
		let monedaDefault = monedaBaseCobranza();
		var wrapper = $(".totales-por-moneda-cheque");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cobranza-cheque-table tr").each(function() {
			var $tr = $(this);
			if (!filaChequeCobranzaConDatos($tr)) {
				return;
			}
            let valor = numeroSeguroCob($tr.find('.montocheque').val());
			let moneda = $tr.find('.monedacheque_id').val();
			if (!moneda || !Number.isFinite(totalMoneda[moneda])) {
				return;
			}
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
		let monedaDefault = monedaBaseCobranza();
		var wrapper = $(".totales-por-moneda-retencion");

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
		});

		$("#tbody-cobranza-retencion-table tr").each(function() {
			var $tr = $(this);
			if (!filaRetencionCobranzaConDatos($tr)) {
				return;
			}
            let valor = numeroSeguroCob($tr.find('.montoretencion').val());
			let moneda = $tr.find('.monedaretencion_id').val();
			if (!moneda || !Number.isFinite(totalMoneda[moneda])) {
				return;
			}
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
		let monedaDefault = monedaBaseCobranza();
		let flMovimientoMoneda = [];

		saldoFinalCobranza = 0;
		totalFinalCobranza = 0;

		// Inicializa totales por moneda
		idMoneda.forEach(function(moneda, indice, array) {
			totalMoneda[moneda] = 0;
			flMovimientoMoneda[moneda] = false;
		});

		$("#tbody-comprobante-table .montoaplicadocomprobante").each(function() {
            let valor = numeroSeguroCob($(this).val());
			if (Math.abs(valor) < 0.000001) {
				return;
			}
			let $tr = $(this).parents("tr");
			valor *= signoAplicacionComprobante($tr);
			let moneda = $tr.find('.monedacomprobante').val();
			let cotizacion = $tr.find('.cotizacioncomprobante').val();
			let coef = coefSeguroCob(monedaDefault, moneda, cotizacion);

			if (moneda && Number.isFinite(totalMoneda[moneda])) {
				totalMoneda[moneda] += valor;
				flMovimientoMoneda[moneda] = true;
			}
			saldoFinalCobranza += (valor * coef);
        });

		if (typeof totalDescuentosPorMoneda === 'function') {
			const descuentosMoneda = totalDescuentosPorMoneda();
			idMoneda.forEach(function(moneda) {
				const desc = descuentosMoneda[moneda] || 0;
				if (desc <= 0) {
					return;
				}
				let cotizacion = $("#tbody-comprobante-table .monedacomprobante").filter(function(){ return $(this).val() == moneda; }).first().parents("tr").find('.cotizacioncomprobante').val();
				let coef = coefSeguroCob(monedaDefault, moneda, cotizacion);
				if (Number.isFinite(totalMoneda[moneda])) {
					totalMoneda[moneda] -= desc;
					flMovimientoMoneda[moneda] = true;
				}
				saldoFinalCobranza -= (desc * coef);
			});
		}

		$("#tbody-cuenta-table tr").each(function() {
			var $tr = $(this);
			if (!filaCuentaCobranzaConDatos($tr)) {
				return;
			}
            let valor = numeroSeguroCob($tr.find('.monto').val());
			let moneda = $tr.find('.moneda').val();
			let cotizacion = $tr.find('.cotizacion').val();
			let coef = coefSeguroCob(monedaDefault, moneda, cotizacion);

			if (moneda && Number.isFinite(totalMoneda[moneda])) {
				totalMoneda[moneda] -= valor;
				flMovimientoMoneda[moneda] = true;
			}
			saldoFinalCobranza -= (valor * coef);
			totalFinalCobranza += (valor * coef);
        });

		$("#tbody-cobranza-cheque-table tr").each(function() {
			var $tr = $(this);
			if (!filaChequeCobranzaConDatos($tr)) {
				return;
			}
            let valor = numeroSeguroCob($tr.find('.montocheque').val());
			let moneda = $tr.find('.monedacheque_id').val();
			let cotizacion = $tr.find('.cotizacioncheque').val();
			let coef = coefSeguroCob(monedaDefault, moneda, cotizacion);

			if (moneda && Number.isFinite(totalMoneda[moneda])) {
				totalMoneda[moneda] -= valor;
				flMovimientoMoneda[moneda] = true;
			}
			saldoFinalCobranza -= (valor * coef);
			totalFinalCobranza += (valor * coef);
        });

		$("#tbody-cobranza-retencion-table tr").each(function() {
			var $tr = $(this);
			if (!filaRetencionCobranzaConDatos($tr)) {
				return;
			}
            let valor = numeroSeguroCob($tr.find('.montoretencion').val());
			let moneda = $tr.find('.monedaretencion_id').val();
			let cotizacion = $tr.find('.cotizacionretencion').val();
			let coef = coefSeguroCob(monedaDefault, moneda, cotizacion);

			if (moneda && Number.isFinite(totalMoneda[moneda])) {
				totalMoneda[moneda] -= valor;
				flMovimientoMoneda[moneda] = true;
			}
			saldoFinalCobranza -= (valor * coef);
			totalFinalCobranza += (valor * coef);
        });

		if (!Number.isFinite(saldoFinalCobranza)) {
			saldoFinalCobranza = 0;
		}
		if (!Number.isFinite(totalFinalCobranza)) {
			totalFinalCobranza = 0;
		}

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
					$(wrapper).append('<input type="text" name="totalcobranzas[]" class="form-control col-lg-1 totalcobranza" readonly value="'+Number(totalMoneda[moneda] || 0).toFixed(2)+'" />');
			}
		});

		// Agrega saldo final en moneda de la cobranza
		if (monedaDefault != null && monedaDefault !== '')
		{
			var etiquetaMoneda = descripcionMoneda[monedaDefault] || '';
			detalleLabel = 'Saldo final cobranza '+etiquetaMoneda;
			$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
			$(wrapper).append('<input type="text" name="saldofinalcobranzas[]" class="form-control col-lg-1 totalfinalcobranza" readonly value="'+saldoFinalCobranza.toFixed(2)+'" />');
		
			// Agrega total final en moneda de la cobranza
			detalleLabel = 'Total final cobranza '+etiquetaMoneda;
			$(wrapper).append('<label class="col-lg-2 col-form-label">'+detalleLabel+'</label>');
			$(wrapper).append('<input type="hidden" name="monedafinalcobranza_id" class="form-control col-lg-1" readonly value="'+monedaDefault+'" />');
			$(wrapper).append('<input type="text" name="totalfinalcobranza" class="form-control col-lg-1 totalfinalcobranza" readonly value="'+totalFinalCobranza.toFixed(2)+'" />');		
		}

		pintarResumenLiquidacion();
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
			var $tr = $(this).parents("tr");
			moneda_ids = $tr.find(".monedacomprobante").val();

			montos = numeroSeguroCob($tr.find(".montoaplicadocomprobante").val());
			cotizaciones = $tr.find(".cotizacioncomprobante").val();

			if (Math.abs(montos) > 0.000001) {
				if (esCreditoComprobante($tr)) {
					montos = -Math.abs(montos);
				}
				datosComprobantes.push({
					moneda_ids,
					montos,
					cotizaciones
				});
			}
		});
		datosComprobantes = JSON.stringify(datosComprobantes);

		// Genera datos de las cuentas de caja cargadas
		$("#cuenta-table .item-cuenta").each(function() {
			var $tr = $(this);
			if (!filaCuentaCobranzaConDatos($tr)) {
				return;
			}
			cuentacaja_ids = $tr.find(".cuentacaja_id").val();
			moneda_ids = $tr.find(".moneda").val();

			montos = $tr.find(".monto").val();

			debes = haberes = ' ';
			debes = $tr.find(".monto").val();

			cotizaciones = $tr.find(".cotizacion").val();
			observaciones = $tr.find(".observacion").val();

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
		$("#cobranza-cheque-table tr").each(function() {
			var $tr = $(this);
			if (!filaChequeCobranzaConDatos($tr)) {
				return;
			}
			moneda_ids = $tr.find(".monedacheque_id").val();

			montos = $tr.find(".montocheque").val();
			cotizaciones = $tr.find(".cotizacioncheque").val();

			datosCheques.push({
				moneda_ids,
				montos,
				cotizaciones
			});			
		});
		datosCheques = JSON.stringify(datosCheques);

		// Agrega retenciones
		$("#cobranza-retencion-table tr").each(function() {
			var $tr = $(this);
			if (!filaRetencionCobranzaConDatos($tr)) {
				return;
			}
			cuenta_retencion_ids = $tr.find(".retencion_cobranza_id").val();
			moneda_ids = $tr.find(".monedaretencion_id").val();

			montos = $tr.find(".montoretencion").val();
			cotizaciones = $tr.find(".cotizacionretencion").val();

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
		
		let url = carpetaBase+"/caja/generaasientocontable_cobranza";

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
								((centroCosto && parseInt(centroCosto, 10) > 0)
									? '<option value="'+centroCosto+'" selected>'+centroCosto+'</option>'
									: '<option value="0" selected>Sin CC</option>')+
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

		let url = carpetaBase+'/caja/leer_historia_cobranza/'+cobranza_id;

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

	function iniciarBannerGrabacionCob()
	{
		if (window.cobGrabacionEnCurso) {
			return false;
		}
		window.cobGrabacionEnCurso = true;
		$('#botonform0').prop('disabled', true).addClass('disabled');
		var form = document.getElementById('form-general');
		if (window.AnitaGrabacion) {
			if (form && typeof AnitaGrabacion.marcar === 'function') {
				AnitaGrabacion.marcar(form);
			} else if (typeof AnitaGrabacion.mostrar === 'function') {
				var titulo = (form && form.getAttribute('data-mensaje-grabacion')) || 'Grabando cobranza…';
				AnitaGrabacion.mostrar(titulo);
			}
		}
		return true;
	}

	function liberarBannerGrabacionCob(opciones)
	{
		opciones = opciones || {};
		var mantenerBloqueo = !!opciones.mantenerBloqueoEnvio;
		if (!mantenerBloqueo) {
			window.cobGrabacionEnCurso = false;
			$('#botonform0').prop('disabled', false).removeClass('disabled');
			$('#form-general').data('cob-ajax-enviado', false);
		}
		if (window.AnitaGrabacion && typeof AnitaGrabacion.liberar === 'function') {
			AnitaGrabacion.liberar();
		}
	}

	$("#form-general").submit(function (e) {
		e.preventDefault();
		if ($(this).data('cob-ajax-enviado')) {
			iniciarBannerGrabacionCob();
			return;
		}
		$(this).data('cob-ajax-enviado', true);
		iniciarBannerGrabacionCob();
		if (window.AsientoMontosFormato && typeof AsientoMontosFormato.normalizarAntesDeEnviar === 'function') {
			AsientoMontosFormato.normalizarAntesDeEnviar(this);
		}
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
			url = carpetaBase+"/caja/actualizarcobranza/"+id;
		else
			url = carpetaBase+"/caja/cobranza";

		//realizamos la petición ajax con la función de jquery
		$.ajax({
			type: "POST",
			url: url,
			data: parametros,
			contentType: false, //importante enviar este parametro en false
			processData: false, //importante enviar este parametro en false
			success: function (data) {
				if (data.mensaje == 'ok') {
					liberarBannerGrabacionCob({mantenerBloqueoEnvio: true});
					alert("Se grabó transacción de caja con éxito" + (data.aviso_politica ? "\n\n" + data.aviso_politica : ""));

					if (data.url_comprobante_pdf) {
						window.open(data.url_comprobante_pdf, '_blank', 'noopener');
					}

					let listarUri = data.redirect_url;
					if (!listarUri) {
						let origen = $('#origen').val();
						listarUri = carpetaBase+"/caja/cobranza";

						switch(origen)
						{
							case 'movimientocaja':
								listarUri = carpetaBase+"/caja/movimientocaja";
								break;
							case 'cobranza':
								listarUri = carpetaBase+"/caja/cobranza";
								break;
							case 'ordenventa':
								listarUri = $('#referer').val() || listarUri;
								break;
						}
					}

					window.location.href = listarUri;
					return;
				}

				liberarBannerGrabacionCob();
				if (data.errores)
					alert("Error de grabación: " + data.errores);
				else
					alert("Error de grabacion");

				$( "#botonform0" ).show();
			},
			error :function( data ) {
				var detalle = '';
				if (data && data.responseJSON && data.responseJSON.errores) {
					detalle = ': ' + data.responseJSON.errores;
				} else if (data && data.responseJSON && data.responseJSON.message) {
					detalle = ': ' + data.responseJSON.message;
				}

				liberarBannerGrabacionCob();
				if( data.status === 422 ) {
					alert("Error de grabación, verifique los datos" + detalle);
				}
				else
				{
					alert("Error de grabación " + data.status + detalle);
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
		var tipotransaccion_caja_id = String($('#tipotransaccion_caja_id').val() || '').trim();
		if (!tipotransaccion_caja_id || tipotransaccion_caja_id === 'undefined') {
			return;
		}
		var url = carpetaBase+'/caja/leertipotransaccion_caja/'+encodeURIComponent(tipotransaccion_caja_id);

		$.get(url, function(data){
			if (!data || !(data.id > 0))
			{
				alert("No existe el tipo de transaccion de caja");
			}
		});
	}

		


