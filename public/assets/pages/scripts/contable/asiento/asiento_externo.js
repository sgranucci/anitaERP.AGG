var cuentacontablexcodigo;
var nombrecontablexcodigo;
var codigocontablexcodigo;
var totalDebeAsiento = 0;
var totalHaberAsiento = 0;

	/** Marca la línea (y el asiento) como edición manual para no regenerarlo al grabar. */
	function marcaAsientoLineaManual($tr) {
		if (!$tr || !$tr.length) {
			return;
		}
		$tr.find('.carga_cuentacontable_manual').val('S');
		window.flAsientoEditadoManual = true;
	}

	window.marcaAsientoLineaManual = marcaAsientoLineaManual;
	window.asientoTieneEdicionManual = function () {
		if (window.flAsientoEditadoManual === true) {
			return true;
		}
		var manual = false;
		$('#cuenta-asiento-table .carga_cuentacontable_manual, #tbody-cuenta-asiento-table .carga_cuentacontable_manual').each(function () {
			var v = String($(this).val() || '').toUpperCase().trim();
			if (v !== '' && v !== 'N' && v !== '0') {
				manual = true;
				return false;
			}
		});
		return manual;
	};

	function esTeclaF1Asiento(e) {
		return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
	}

	function esTeclaEnterAsiento(e) {
		return e && (e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13);
	}

	function modalConsultaCuentaAsientoAbierto() {
		var $modal = $('#consultacuentaModal');
		return window.__asientoAbriendoModalCuenta
			|| (typeof abriendoModalCuentaContable !== 'undefined' && abriendoModalCuentaContable)
			|| ($modal.length > 0 && ($modal.hasClass('show') || $modal.hasClass('in') || $modal.is(':visible')));
	}

	function apuntarPtrsConsultaCuentaAsiento($tr) {
		cuentacontablexcodigo = $tr.find('.cuentacontable_id');
		nombrecontablexcodigo = $tr.find('.nombrecuentacontable');
		codigocontablexcodigo = $tr.find('.codigoasiento');
		if (typeof ptrCuentacontableContext !== 'undefined') {
			ptrCuentacontableContext = $tr;
		}
		window.ptrIeCpFilaCuentaConcepto = null;
	}

	function abrirConsultaCuentaAsientoFila($tr) {
		var empresaId = $('#empresa_id').val();
		if (!empresaId) {
			alert('Debe ingresar empresa');
			return;
		}
		apuntarPtrsConsultaCuentaAsiento($tr);
		window.__asientoConsultaCuentaOrigen = true;
		window.__asientoAbriendoModalCuenta = true;
		if (typeof abriendoModalCuentaContable !== 'undefined') {
			abriendoModalCuentaContable = true;
		}
		$('#consultacuentacontable').val('');
		$('#datoscuentas').html('');
		$('#consultaempresa_id').val(empresaId);
		$('#consultacuentaModal').modal('show');
		if (typeof buscar_datos === 'function') {
			buscar_datos('');
		}
	}

	function limpiarCuentaAsientoEnFila($tr, conservarCodigo) {
		if (!$tr || !$tr.length) {
			return;
		}
		$tr.find('.cuentacontable_id, .cuentacontable_id_previa').val('');
		$tr.find('.nombrecuentacontable').val('');
		$tr.find('.codigo_previo_cuentacontable').val('');
		if (!conservarCodigo) {
			$tr.find('.codigoasiento').val('');
		}
	}

	function aplicarCuentaAsientoEnFila($tr, data) {
		if (!$tr || !$tr.length || !data || !(parseInt(data.id, 10) > 0)) {
			return;
		}
		$tr.find('.cuentacontable_id').val(data.id);
		$tr.find('.cuentacontable_id_previa').val(data.id);
		if (data.codigo != null && data.codigo !== '') {
			$tr.find('.codigoasiento').val(data.codigo);
			$tr.find('.codigo_previo_cuentacontable').val(data.codigo);
		} else {
			$tr.find('.codigo_previo_cuentacontable').val($tr.find('.codigoasiento').val());
		}
		$tr.find('.nombrecuentacontable').val(data.nombre || '');
		$tr.find('.codigoasiento').removeData('asiento-codigo-invalido');
		marcaAsientoLineaManual($tr);
	}

	function refrescarCcAsientoTrasCuenta($tr, data) {
		var $codigo = $tr.find('.codigoasiento');
		var cuentaId = parseInt((data && data.id) || $tr.find('.cuentacontable_id').val() || '0', 10) || 0;
		if (cuentaId <= 0) {
			return $.Deferred().resolve().promise();
		}
		var ccPrevio = parseInt($tr.find('.centrocostoasiento_id_previo').val() || '0', 10) || 0;
		if (data && data.manejaccosto !== undefined) {
			var manejaCc = data.manejaccosto === 'S' || data.manejaccosto === '1' || data.manejaccosto === 1;
			if (!manejaCc) {
				$tr.find('.centrocostoasiento').empty().append('<option value="0" selected>Sin CC</option>').attr('readonly', true);
				return $.Deferred().resolve().promise();
			}
			$tr.find('.centrocostoasiento').attr('readonly', false);
		}
		return completarCentroCostoAsiento($codigo, cuentaId, ccPrevio);
	}

	function enfocarCampoAsiento(el) {
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

	function centrocostoAsientoRequiereEleccion($tr) {
		var $cc = $tr.find('.centrocostoasiento');
		if (!$cc.length || $cc.prop('disabled') || $cc.prop('readonly')) {
			return false;
		}
		return $cc.find('option').filter(function () {
			var v = String(this.value || '').trim();
			return v !== '' && v !== '0';
		}).length > 0;
	}

	function enfocarSiguienteTrasCuentaAsiento($tr) {
		if (centrocostoAsientoRequiereEleccion($tr)) {
			$tr.find('.centrocostoasiento').trigger('focus');
			return;
		}
		var debe = $tr.find('.debeasiento')[0];
		if (debe && !debe.disabled && !debe.readOnly) {
			enfocarCampoAsiento(debe);
			return;
		}
		var haber = $tr.find('.haberasiento')[0];
		if (haber && !haber.disabled && !haber.readOnly) {
			enfocarCampoAsiento(haber);
		}
	}

	function avisarCuentaAsientoInvalida($input) {
		var $modal = $('#consultacuentaModal');
		if ($modal.length && ($modal.hasClass('show') || $modal.is(':visible'))) {
			$modal.modal('hide');
		}
		setTimeout(function () {
			alert('No existe la cuenta');
			enfocarCampoAsiento($input[0]);
		}, 0);
	}

	function filaAsientoDesdePtrCuenta() {
		if (!codigocontablexcodigo || !codigocontablexcodigo.length) {
			return $();
		}
		var nodo = codigocontablexcodigo.get(0);
		if (!nodo || !document.contains(nodo)) {
			return $();
		}
		return $(nodo).closest('tr.item-cuenta-asiento');
	}

	function resolverCodigoAsiento($input, opciones) {
		opciones = opciones || {};
		var alertar = opciones.alertar === true;
		var avanzar = opciones.avanzar === true;
		var onDone = typeof opciones.onDone === 'function' ? opciones.onDone : function () {};
		var $tr = $input.closest('tr.item-cuenta-asiento');
		var codigoNuevo = String($input.val() || '').trim();
		var empresaId = $('#empresa_id').val();
		var codigoAnt = String($tr.find('.codigo_previo_cuentacontable').val() || '').trim();
		var idActual = parseInt($tr.find('.cuentacontable_id').val() || '0', 10) || 0;

		if (modalConsultaCuentaAsientoAbierto()) {
			onDone(false);
			return;
		}

		if (!codigoNuevo) {
			limpiarCuentaAsientoEnFila($tr, true);
			onDone(false);
			return;
		}

		if (!empresaId) {
			if (alertar) {
				alert('Debe ingresar empresa');
			}
			onDone(false);
			return;
		}

		if (codigoNuevo === codigoAnt && idActual > 0) {
			if (avanzar) {
				enfocarSiguienteTrasCuentaAsiento($tr);
			}
			onDone(true);
			return;
		}

		var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/'
			+ empresaId + '/' + encodeURIComponent(codigoNuevo);

		$.get(urlCta, function (data) {
			if (data && parseInt(data.id, 10) > 0) {
				aplicarCuentaAsientoEnFila($tr, data);
				$.when(refrescarCcAsientoTrasCuenta($tr, data)).always(function () {
					if (avanzar) {
						enfocarSiguienteTrasCuentaAsiento($tr);
					}
					onDone(true);
				});
				return;
			}
			$input.data('asiento-codigo-invalido', 1);
			limpiarCuentaAsientoEnFila($tr, true);
			if (alertar) {
				avisarCuentaAsientoInvalida($input);
			}
			onDone(false);
		}).fail(function () {
			$input.data('asiento-codigo-invalido', 1);
			limpiarCuentaAsientoEnFila($tr, true);
			if (alertar) {
				avisarCuentaAsientoInvalida($input);
			}
			onDone(false);
		});
	}

	function aplicarEleccionModalCuentaAsiento(data) {
		var $tr = filaAsientoDesdePtrCuenta();
		if (!$tr.length) {
			return false;
		}
		aplicarCuentaAsientoEnFila($tr, data);
		$.when(refrescarCcAsientoTrasCuenta($tr, data)).always(function () {
			enfocarSiguienteTrasCuentaAsiento($tr);
		});
		return true;
	}

	function activarTecladoGrillaAsiento() {
		if (window.__asientoTecladoActivo) {
			return;
		}
		window.__asientoTecladoActivo = true;

		document.addEventListener('keydown', function (e) {
			var target = e.target;
			if (!target || !target.closest) {
				return;
			}
			var tabla = target.closest('#cuenta-asiento-table');
			if (!tabla) {
				return;
			}
			var $tr = $(target).closest('tr.item-cuenta-asiento');
			if (!$tr.length) {
				return;
			}

			if (esTeclaF1Asiento(e)) {
				if (!$(target).is('.codigoasiento, .nombrecuentacontable, .consultacuenta')) {
					return;
				}
				if (target.readOnly && !$(target).hasClass('nombrecuentacontable')) {
					return;
				}
				if (modalConsultaCuentaAsientoAbierto()) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				if (typeof e.stopImmediatePropagation === 'function') {
					e.stopImmediatePropagation();
				}
				abrirConsultaCuentaAsientoFila($tr);
				return;
			}

			if (!esTeclaEnterAsiento(e)) {
				return;
			}
			if (!$(target).hasClass('codigoasiento')) {
				return;
			}
			if (target.readOnly || target.disabled) {
				return;
			}
			if (modalConsultaCuentaAsientoAbierto()) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
			if (typeof e.stopImmediatePropagation === 'function') {
				e.stopImmediatePropagation();
			}

			var $input = $(target);
			$input.data('asiento-enter-procesado', 1);
			var codigo = String($input.val() || '').trim();
			if (codigo === '') {
				abrirConsultaCuentaAsientoFila($tr);
				return;
			}
			resolverCodigoAsiento($input, { alertar: true, avanzar: true });
		}, true);
	}

    $(function () {
        $('#agrega_renglon_asiento').on('click', agregaRenglonCuentaAsiento);
        $(document).on('click', '.eliminar_cuenta_asiento', borraRenglonCuentaAsiento);

		activa_eventosAsiento(true);

		// Completa centros de costo al abrir asiento
		$("#tbody-cuenta-asiento-table .codigoasiento").each(function(index) {
			var codigo = $(this);
			var cuentacontable_id = $(this).parents("tr").find(".cuentacontable_id").val();
			var centrocosto_id = $(this).parents("tr").find(".centrocostoasiento_id_previo").val();

			completarCentroCostoAsiento(codigo, cuentacontable_id, centrocosto_id);
		});

		// Muestra sumatoria de montos del asiento
		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.initEnContenedor('#tbody-cuenta-asiento-table');
		}
		sumaMontoAsiento();

		$(document).on('asiento:monto-actualizado', function () {
			if ($('#tbody-cuenta-asiento-table').length) {
				sumaMontoAsiento();
			}
		});
    });

	function activa_eventosAsiento(flInicio)
	{
		activarTecladoGrillaAsiento();

		if (window.__asientoExtEventosActivos) {
			return;
		}
		window.__asientoExtEventosActivos = true;

		$(document)
			.off('change.asientoExt blur.asientoExt', '#cuenta-asiento-table .codigoasiento')
			.on('change.asientoExt blur.asientoExt', '#cuenta-asiento-table .codigoasiento', function (event) {
				var $input = $(this);
				if ($input.data('asiento-enter-procesado')) {
					$input.removeData('asiento-enter-procesado');
					return;
				}
				if (modalConsultaCuentaAsientoAbierto()) {
					return;
				}
				var codigoActual = String($input.val() || '').trim();
				var codigoPrevio = String($input.closest('tr').find('.codigo_previo_cuentacontable').val() || '').trim();
				if (codigoActual === codigoPrevio) {
					return;
				}
				event.preventDefault();
				resolverCodigoAsiento($input, { alertar: false, avanzar: false });
			});

		$(document)
			.off('input.asientoExt', '#cuenta-asiento-table .codigoasiento')
			.on('input.asientoExt', '#cuenta-asiento-table .codigoasiento', function () {
				$(this).removeData('asiento-codigo-invalido');
			});

		$(document)
			.off('click.asientoExt', '#cuenta-asiento-table .consultacuenta')
			.on('click.asientoExt', '#cuenta-asiento-table .consultacuenta', function (event) {
				event.preventDefault();
				var $tr = $(this).closest('tr.item-cuenta-asiento');
				if (!$tr.length) {
					return;
				}
				abrirConsultaCuentaAsientoFila($tr);
			});

		$('#consultacuentaModal')
			.off('shown.bs.modal.asientoExt')
			.on('shown.bs.modal.asientoExt', function () {
				window.__asientoAbriendoModalCuenta = false;
				$(this).find('[autofocus]').focus();
			})
			.off('hidden.bs.modal.asientoExt')
			.on('hidden.bs.modal.asientoExt', function () {
				window.__asientoAbriendoModalCuenta = false;
				setTimeout(function () {
					window.__asientoConsultaCuentaOrigen = false;
				}, 0);
			});

		$(document)
			.off('click.asientoExtElige', '.eligeconsultacuentacontable')
			.on('click.asientoExtElige', '.eligeconsultacuentacontable', function () {
				if (!window.__asientoConsultaCuentaOrigen) {
					return;
				}
				var $trModal = $(this).closest('tr');
				var data = {
					id: $.trim($trModal.find('.cuentacontable_id').first().text()),
					codigo: $.trim($trModal.find('.codigocuentacontable').first().text()),
					nombre: $.trim($trModal.find('.nombrecuentacontable').first().text()),
				};
				if (aplicarEleccionModalCuentaAsiento(data)) {
					$('#consultacuentaModal').modal('hide');
				}
			});

		$(document)
			.off('change.asientoExt input.asientoExt', '#cuenta-asiento-table .debeasiento')
			.on('change.asientoExt input.asientoExt', '#cuenta-asiento-table .debeasiento', function (event) {
				event.preventDefault();
				if (event.type === 'change') {
					marcaAsientoLineaManual($(this).closest('tr'));
				}
				sumaMontoAsiento();
			});

		$(document)
			.off('change.asientoExt input.asientoExt', '#cuenta-asiento-table .haberasiento')
			.on('change.asientoExt input.asientoExt', '#cuenta-asiento-table .haberasiento', function (event) {
				event.preventDefault();
				if (event.type === 'change') {
					marcaAsientoLineaManual($(this).closest('tr'));
				}
				sumaMontoAsiento();
			});

		$(document)
			.off('change.asientoExt input.asientoExt', '#cuenta-asiento-table .cotizacionasiento')
			.on('change.asientoExt input.asientoExt', '#cuenta-asiento-table .cotizacionasiento', function (event) {
				event.preventDefault();
				sumaMontoAsiento();
			});

		$(document)
			.off('change.asientoExt', '#cuenta-asiento-table .monedaasiento')
			.on('change.asientoExt', '#cuenta-asiento-table .monedaasiento', function (event) {
				event.preventDefault();
				leeCotizacionAsiento(this);
			});
	}

    function agregaRenglonCuentaAsiento(){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuenta-asiento').html();
		let monedaDefault = $("#tbody-cuenta-asiento-table").children(':first').find('.monedaasiento').val();

    	$("#tbody-cuenta-asiento-table").append(renglon);
    	actualizaRenglonesCuentaAsiento();

		// Default de moneda = 1.er renglón (sin bloquear mezcla: pantallas de proceso / otros sistemas)
		let $nuevo = $("#tbody-cuenta-asiento-table").children().last();
		if (monedaDefault) {
			$nuevo.find('.monedaasiento').val(monedaDefault);
			leeCotizacionAsiento($nuevo.find('.monedaasiento'));
		}
		marcaAsientoLineaManual($nuevo);

		activa_eventosAsiento(false);

		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.initEnContenedor($("#tbody-cuenta-asiento-table tr.item-cuenta-asiento").last());
		}
    }

    function borraRenglonCuentaAsiento(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
		window.flAsientoEditadoManual = true;
    	actualizaRenglonesCuentaAsiento();
		sumaMontoAsiento();
    }

    function actualizaRenglonesCuentaAsiento() {
    	var item = 1;

    	$("#tbody-cuenta-asiento-table .iicuentacontable").each(function() {
    		$(this).val(item++);
    	});
    }

	function urlCentrocostoCuentaContable(cuentacontable_id, centrocosto_id) {
		var url = carpetaBase+'/contable/cuentacontable/leercuentacontablecentrocosto/'+cuentacontable_id;
		var cc = parseInt(centrocosto_id || '0', 10) || 0;
		if (cc > 0) {
			url += (url.indexOf('?') === -1 ? '?' : '&') + 'incluir=' + cc;
		}
		return url;
	}

	function llenarSelectCentroCostoAsiento($sel, data, centrocosto_id) {
		if (data === "No maneja centro de costo" || data === "Cuenta inexistente") {
			$sel.empty();
			$sel.append('<option value="0" selected>Sin CC</option>');
			$sel.attr("readonly", true);
			return;
		}

		var cta = $.map(data, function(value, index){
			return [value];
		});
		$sel.empty();
		$sel.append('<option value="">-- Seleccione CC --</option>');
		var seleccionado = false;
		$.each(cta, function(index,value){
			if (value.id == centrocosto_id) {
				$sel.append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
				seleccionado = true;
			} else {
				$sel.append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
			}
		});
		if (!seleccionado && parseInt(centrocosto_id || '0', 10) > 0) {
			$sel.append('<option value="'+centrocosto_id+'" selected>'+centrocosto_id+'</option>');
		}
		$sel.attr("readonly", false);
	}

	// Devuelve la promesa del $.get para que el llamador pueda esperar el alta de los CC.
	function completarCentroCostoAsiento(ptrcodigo, cuentacontable_id, centrocosto_id){
		return $.get(urlCentrocostoCuentaContable(cuentacontable_id, centrocosto_id), function(data){
			llenarSelectCentroCostoAsiento($(ptrcodigo).parents("tr").find('.centrocostoasiento'), data, centrocosto_id);
        });
    }

	function leeCentroCostoAsiento(ptr) 
	{
		var codigo = $(ptr);
		var codigo_ant = $(ptr).parents("tr").find(".codigo_previo_cuentacontable").val();
		var codigo_nuevo = codigo.val();

		if (codigo_nuevo != codigo_ant)
		{
			let empresa_id = $("#empresa_id").val();

			if (!empresa_id)
				alert("Debe ingresar empresa");
			else
			{
				let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontableporcodigo/'+empresa_id+'/'+codigo_nuevo;

				return $.get(url_cta, function(data){
					var $trCc = $(codigo).parents("tr");
					$trCc.find('.cuentacontable_id').val(data.id);
					$trCc.find(".cuentacontable_id_previa").val(data.id);
					$trCc.find(".nombrecuentacontable").val(data.nombre);
					$trCc.find(".codigo_previo_cuentacontable").val(codigo_nuevo);
					if (data.manejaccosto === 'S')
					{
						$trCc.find('.centrocostoasiento').attr("readonly", false);

						var ccPrevio = parseInt($trCc.find('.centrocostoasiento_id_previo').val() || '0', 10) || 0;
						completarCentroCosto(codigo, data.id, ccPrevio);
					}
					else
					{
						$trCc.find('.centrocostoasiento').empty();
						$trCc.find('.centrocostoasiento').append('<option value="0" selected>Sin CC</option>');
						$trCc.find('.centrocostoasiento').attr("readonly", true);
					}
				});
			}
		}
	}

	function completarCentroCosto(ptrcodigo, cuentacontable_id, centrocosto_id){
		return $.get(urlCentrocostoCuentaContable(cuentacontable_id, centrocosto_id), function(data){
			llenarSelectCentroCostoAsiento($(ptrcodigo).parents("tr").find('.centrocostoasiento'), data, centrocosto_id);
        });
    }

	function controlaCentroCosto()
	{
		var flError = false;

		$("#tbody-cuenta-asiento-table .centrocostoasiento").each(function () {
			var $sel = $(this);
			if ($sel.prop('disabled') || $sel.is('[readonly]')) {
				return;
			}
			var val = String($sel.val() || '').trim();
			if (val !== '' && val !== '0') {
				return;
			}
			var requiereCc = $sel.find('option').filter(function () {
				var v = String(this.value || '').trim();
				return v !== '' && v !== '0';
			}).length > 0;
			if (requiereCc) {
				flError = true;
				return false;
			}
		});

		return flError;
	}

	function leeCotizacionAsiento(ptr)
	{
		let fecha = $('#fecha').val();
		let moneda_id = $(ptr).parents("tr").find('.monedaasiento').val();
		let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
	
		$.get(url_cot, function(data){
			var $cot = $(ptr).parents("tr").find('.cotizacionasiento');
			$cot.val(data.cotizacionventa);
			if (window.AsientoMontosFormato) {
				AsientoMontosFormato.formatearInput($cot[0]);
			}
			sumaMontoAsiento();
		});
	}

	function parseMontoArAsiento(valor) {
		if (valor == null || valor === '') {
			return 0;
		}
		var t = String(valor).trim().replace(/\s/g, '');
		if (t.indexOf(',') >= 0) {
			t = t.replace(/\./g, '').replace(',', '.');
		} else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
			t = t.replace(/\./g, '');
		}
		var n = parseFloat(t);
		return isNaN(n) ? 0 : Math.round(n * 100) / 100;
	}

	function parseMontoAsiento(valor) {
		if (window.AsientoMontosFormato && window.AsientoMontosFormato.parseDecimal) {
			return AsientoMontosFormato.parseDecimal(valor);
		}
		return parseMontoArAsiento(valor);
	}

	function formateaMontoTotalAsiento(n) {
		if (window.AsientoMontosFormato && window.AsientoMontosFormato.fmt) {
			return AsientoMontosFormato.fmt(n);
		}
		return Number(n || 0).toLocaleString('es-AR', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function sumaMontoAsiento()
	{
		let totalDebeAsiento = 0;
		let totalHaberAsiento = 0;

		$("#tbody-cuenta-asiento-table .debeasiento").each(function() {
            let valor = parseMontoAsiento($(this).val());

            if (valor > 0.000001) {
                totalDebeAsiento += valor;
			}
        });

        $("#tbody-cuenta-asiento-table .haberasiento").each(function() {
            let valor = parseMontoAsiento($(this).val());

			if (valor > 0.000001) {
				totalHaberAsiento += valor;
			}
    	});

		totalDebeAsiento = Math.round(totalDebeAsiento * 100) / 100;
		totalHaberAsiento = Math.round(totalHaberAsiento * 100) / 100;

		$("#totaldebeasiento").val(formateaMontoTotalAsiento(totalDebeAsiento));
		$("#totalhaberasiento").val(formateaMontoTotalAsiento(totalHaberAsiento));
	}
