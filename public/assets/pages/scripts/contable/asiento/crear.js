    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		activa_eventos(true);
		asientoAplicarMonedaDesdePrimera(false);

		if (typeof activa_eventos_consulta_cuentacontable === 'function') {
			activa_eventos_consulta_cuentacontable();
		}

		$(document).on('change', '#tbody-cuenta-table .centrocosto', function () {
			sincronizarCentrocostoPrevio($(this).closest('tr'));
		});

		// Completa centros de costo al abrir asiento
		$("#tbody-cuenta-table .codigocuentacontable").each(function(index) {
			var codigo = $(this);
			var cuentacontable_id = $(this).parents("tr").find(".cuentacontable_id").val();
			var centrocosto_id = $(this).parents("tr").find(".centrocosto_id_previo").val();

			completarCentroCosto(codigo, cuentacontable_id, centrocosto_id);
		});

		$("#botonform1").click(function(){
            // Compat: solapas migradas a Bootstrap tabs (#tab-asiento-datos).
            if ($('#tab-asiento-datos-link').length) {
                $('#tab-asiento-datos-link').tab('show');
                return;
            }
            $(".form1").show();
            $(".form2").hide();
        });
		$("#botonform2").click(function(){
            if ($('#tab-asiento-archivos-link').length) {
                $('#tab-asiento-archivos-link').tab('show');
                return;
            }
            $(".form1").hide();
            $(".form2").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Cuentas");
        });

		$(document).on('click', '.eliminar-archivo-asiento', function (e) {
			e.preventDefault();
			$(this).closest('.asiento-archivo-item').remove();
		});

		// Muestra sumatoria de montos del asiento
		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.initEnContenedor('#tbody-cuenta-table');
		}
		sumaMonto();

		$(document).on('asiento:monto-actualizado', function () {
			if ($('#tbody-cuenta-table').length) {
				sumaMonto();
			}
		});

		$("#tbody-cuenta-table tr.item-cuenta").each(function () {
			asientoRefreshDetallePreview($(this));
		});

		$(document).on('click', '.asiento-abrir-detalle', function () {
			ptrAsientoDetalleLineaRow = $(this).closest('tr.item-cuenta');
			var v = ptrAsientoDetalleLineaRow.find('.asiento-ta-detalle').val() || '';
			$('#asiento_detalle_linea_editor').val(v);
			$('#modalAsientoDetalleLinea').modal('show');
		});

		$('#modalAsientoDetalleLinea').on('shown.bs.modal', function () {
			$('#asiento_detalle_linea_editor').trigger('focus');
		});

		$(document).on('click', '#asiento_detalle_linea_guardar', function () {
			if (!ptrAsientoDetalleLineaRow || !ptrAsientoDetalleLineaRow.length) {
				return;
			}
			var texto = $('#asiento_detalle_linea_editor').val() || '';
			var esPrimera = ptrAsientoDetalleLineaRow.is($('#tbody-cuenta-table tr.item-cuenta').first());
			ptrAsientoDetalleLineaRow.find('.asiento-ta-detalle').val(texto);
			asientoRefreshDetallePreview(ptrAsientoDetalleLineaRow);
			if (esPrimera) {
				asientoPropagarDetallePrimeraLinea(texto);
			}
			$('#modalAsientoDetalleLinea').modal('hide');
		});
    });

	var ptrAsientoDetalleLineaRow = null;

	function asientoRefreshDetallePreview($row) {
		var t = (($row.find('.asiento-ta-detalle').val() || '') + '').trim();
		var $prev = $row.find('.asiento-detalle-preview');
		var $btn = $row.find('.asiento-abrir-detalle');
		if (!$prev.length) {
			return;
		}
		if (!t.length) {
			$prev.text('—').addClass('is-empty').removeAttr('title');
			$btn.removeClass('has-detalle').attr('title', 'Agregar detalle de la línea');
			return;
		}
		$prev.text(t).removeClass('is-empty').attr('title', t);
		$btn.addClass('has-detalle').attr('title', 'Editar detalle de la línea');
	}

	function asientoPropagarDetallePrimeraLinea(texto) {
		var t = (texto || '').trim();
		if (!t.length) {
			return;
		}
		$('#tbody-cuenta-table tr.item-cuenta').each(function (idx) {
			if (idx === 0) {
				return;
			}
			var $row = $(this);
			var actual = (($row.find('.asiento-ta-detalle').val() || '') + '').trim();
			if (actual.length) {
				return;
			}
			$row.find('.asiento-ta-detalle').val(texto);
			asientoRefreshDetallePreview($row);
		});
	}

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.debe').off('change input');
			$('.haber').off('change input');
			$('.cotizacion').off('change input');
			$('.moneda').off('change');
		}

		$('.debe').on('change input', function (event) {
			event.preventDefault();
			sumaMonto();
		});

		$('.haber').on('change input', function (event) {
			event.preventDefault();
			sumaMonto();
		});

		$('.cotizacion').on('change input', function (event) {
			event.preventDefault();
			sumaMonto();
		});

		$('.moneda').on('change', function (event) {
			event.preventDefault();
			asientoOnMonedaChange(this);
		});
	}

	/**
	 * Regla: la moneda del asiento la fija el 1.er movimiento.
	 * El resto de renglones se alinean; no se permite mezclar PES/DOL/etc.
	 */
	function asientoMonedaPrimeraId() {
		return $("#tbody-cuenta-table tr.item-cuenta").first().find('.moneda').val();
	}

	function asientoAplicarMonedaDesdePrimera(releerCotizacion) {
		var monedaId = asientoMonedaPrimeraId();
		if (!monedaId) {
			return;
		}
		$("#tbody-cuenta-table tr.item-cuenta").each(function (idx) {
			if (idx === 0) {
				return;
			}
			var $sel = $(this).find('.moneda');
			if (String($sel.val()) === String(monedaId)) {
				return;
			}
			$sel.val(monedaId);
			if (releerCotizacion) {
				leeCotizacion($sel);
			}
		});
	}

	function asientoOnMonedaChange(ptr) {
		var $tr = $(ptr).closest('tr.item-cuenta');
		var $primera = $("#tbody-cuenta-table tr.item-cuenta").first();
		var esPrimera = $tr.length && $primera.length && $tr[0] === $primera[0];

		if (esPrimera) {
			leeCotizacion(ptr);
			asientoAplicarMonedaDesdePrimera(true);
			return;
		}

		var monedaFija = asientoMonedaPrimeraId();
		if (monedaFija && String($(ptr).val()) !== String(monedaFija)) {
			$(ptr).val(monedaFija);
			alert('La moneda la fija el primer movimiento del asiento; no se pueden mezclar monedas.');
		}
		leeCotizacion(ptr);
	}

	function asientoValidarMonedaUnica() {
		var monedaRef = null;
		var ok = true;
		$("#tbody-cuenta-table tr.item-cuenta").each(function () {
			var debe = parseMonto($(this).find('.debe').val());
			var haber = parseMonto($(this).find('.haber').val());
			if (debe <= 0 && haber <= 0) {
				return;
			}
			var mon = $(this).find('.moneda').val();
			if (!mon) {
				ok = false;
				return false;
			}
			if (monedaRef === null) {
				monedaRef = String(mon);
				return;
			}
			if (String(mon) !== monedaRef) {
				ok = false;
				return false;
			}
		});
		return ok;
	}

    function agregaRenglonCuenta(){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuenta').html();
		let $primera = $("#tbody-cuenta-table tr.item-cuenta").first();
		let monedaDefault = $primera.find('.moneda').val();
		let detalleDefault = $primera.find('.asiento-ta-detalle').val() || '';

    	$("#tbody-cuenta-table").append(renglon);
    	actualizaRenglonesCuenta();

		let $nuevo = $("#tbody-cuenta-table tr.item-cuenta").last();

		// Asigna default de moneda y detalle (leyenda 1ª línea)
		$nuevo.find('.moneda').val(monedaDefault);
		if ((detalleDefault || '').trim().length) {
			$nuevo.find('.asiento-ta-detalle').val(detalleDefault);
		}
		asientoRefreshDetallePreview($nuevo);

		// Lee cotizacion de la moneda
		leeCotizacion($nuevo.find('.moneda'));
		asientoAplicarMonedaDesdePrimera(false);

		activa_eventos(false);

		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.initEnContenedor($nuevo);
		}
    }

    function borraRenglonCuenta(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuenta();
		sumaMonto();
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
    	var $tbody = $("#tbody-tabla-archivo");
    	if ($tbody.find('tr.item-archivo').length <= 1) {
    		$(this).closest('tr').find('input[type=file]').val('');
    		return;
    	}
    	$(this).parents('tr').remove();
    }

    function actualizaArchivo(elem) {
	  	var fn = $(elem).val();
		var filename = fn.match(/[^\\/]*$/)[0]; // remove C:\fakename

		$(elem).parents("tr").find(".nombresanteriores").val(filename);
	}

	function armaSelectCuenta(ptrselect, ptrcuentacontable, opdata)
	{
		var select = $(ptrselect);
      	var options = select.children();
		var cuentacontable_id = $(ptrcuentacontable).val();
		var empresa_id = $('#empresa_id').val();
		var empresa_nombre = $("#empresa_id option:selected").text();

		// elige cuentas x nombre o por cuenta
		var sel_cuentas= JSON.parse(document.querySelector('#cuentas').dataset.cuenta);

		if (opdata == 2)
		{
			sel_cuentas.sort(function(a, b) {
    				var textA = a.codigo;
    				var textB = b.codigo;
    				return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
				});
		}

		select.empty();

		select.append('<option value="">-- Cuentas ' + empresa_nombre + ' --</option>');

		$.each(sel_cuentas, function(obj, item) {
			if (cuentacontable_id == item.id)
				op = 'selected="selected"';
			else
				op = '';
			if (empresa_id == undefined || empresa_id == '')
				select.append('<option value="' + item.id + '"'+op+'>' + (opdata == 2 ? item.codigo + '-' + item.nombre : item.nombre + '-' + item.codigo) + '</option>');
			else
			{
				if (item.empresa_id == empresa_id)
					select.append('<option value="' + item.id + '"'+op+'>' + (opdata == 2 ? item.codigo + '-' + item.nombre : item.nombre + '-' + item.codigo) + '</option>');
			}
		});

		if (empresa_id > 0)
		{
			select.value = empresa_id;

			select.children().filter(function(){
   				return this.text == empresa_id;
			}).prop('selected', true);
		}
	}

	function asegurarOpcionCentroCosto($sel, valor, texto) {
		var v = (valor === undefined || valor === null || valor === '') ? '0' : String(valor);
		var t = texto || (v === '0' ? 'Sin CC' : v);
		if (!$sel.find('option').length) {
			$sel.append($('<option/>').val(v).text(t).prop('selected', true));
		}
	}

	function sincronizarCentrocostoPrevio($tr) {
		var $sel = $tr.find('.centrocosto');
		var $prev = $tr.find('.centrocosto_id_previo');
		if ($sel.length && $prev.length) {
			$prev.val($sel.val() || '0');
		}
	}

	function completarCentroCosto(ptrcodigo, cuentacontable_id, centrocosto_id){
		var $tr = $(ptrcodigo).closest('tr');
		var $sel = $tr.find('.centrocosto');
		var valorPrev = centrocosto_id || $sel.val() || $tr.find('.centrocosto_id_previo').val() || '0';

		// Un select sin <option> no se envía en el POST → "Undefined array key N" al grabar.
		asegurarOpcionCentroCosto($sel, valorPrev);

		if (!cuentacontable_id) {
			return;
		}

		let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontablecentrocosto/'+cuentacontable_id;

		$.get(url_cta, function(data){
			if (data === "No maneja centro de costo" || data === "Cuenta inexistente")
			{
				$sel.empty();
				$sel.append('<option value="0" selected>Sin CC</option>');
				$sel.attr("readonly", true);
			}
			else
			{
				var cta = $.map(data, function(value, index){
					return [value];
				});
				$sel.empty();
				$sel.append('<option value="0">-- Seleccione CC --</option>');
				$.each(cta, function(index,value){
					if (value.id == centrocosto_id)
						$sel.append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
					else
						$sel.append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
				});
				if ($sel.val() === null || $sel.val() === undefined) {
					asegurarOpcionCentroCosto($sel, valorPrev);
				}
				$sel.attr("readonly", false);
			}
			sincronizarCentrocostoPrevio($tr);
        }).fail(function () {
			asegurarOpcionCentroCosto($sel, valorPrev);
			sincronizarCentrocostoPrevio($tr);
		});
    }

	function leeCentroCosto(ptr)
	{
		var $codigo = $(ptr);
		var $tr = $codigo.closest('tr');
		var codigo_ant = $.trim($tr.find('.codigo_previo').val() || '');
		var codigo_nuevo = $.trim($codigo.val() || '');

		if (codigo_nuevo === codigo_ant) {
			return;
		}

		var empresa_id = $('#empresa_id').val();

		if (!empresa_id) {
			alert('Debe ingresar empresa');
			return;
		}

		var url_cta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/' + empresa_id + '/' + encodeURIComponent(codigo_nuevo);

		$.get(url_cta, function (data) {
			if (!(data && data.id > 0)) {
				return;
			}

			$tr.find('.cuentacontable_id').val(data.id);
			$tr.find('.cuentacontable_id_previa').val(data.id);
			$tr.find('.nombrecuentacontable, .nombre').val(data.nombre);

			if (data.manejaccosto === 'S' || data.manejaccosto === '1' || data.manejaccosto === 1) {
				$tr.find('.centrocosto').attr('readonly', false);
				completarCentroCosto($codigo, data.id, 0);
			} else {
				$tr.find('.centrocosto').empty();
				$tr.find('.centrocosto').append('<option value="0" selected>Sin CC</option>');
				$tr.find('.centrocosto').attr('readonly', true);
				sincronizarCentrocostoPrevio($tr);
			}

			$tr.find('.codigo_previo').val(codigo_nuevo);
		});
	}

	function leeCotizacion(ptr)
	{
		let fecha = $('#fecha').val();
		let moneda_id = $(ptr).parents("tr").find('.moneda').val();
		let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
	
		$.get(url_cot, function(data){
			var $cot = $(ptr).parents("tr").find('.cotizacion');
			$cot.val(data.cotizacionventa);
			if (window.AsientoMontosFormato) {
				AsientoMontosFormato.formatearInput($cot[0]);
			}
			sumaMonto();
		});
	}

	function parseMontoAr(valor) {
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

	function parseMonto(valor) {
		if (window.AsientoMontosFormato && window.AsientoMontosFormato.parseDecimal) {
			return AsientoMontosFormato.parseDecimal(valor);
		}
		return parseMontoAr(valor);
	}

	function formateaMontoTotal(n) {
		if (window.AsientoMontosFormato && window.AsientoMontosFormato.fmt) {
			return AsientoMontosFormato.fmt(n);
		}
		return Number(n || 0).toLocaleString('es-AR', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function sumaMonto()
	{
		let totDebe = 0;
		let totHaber = 0;

		$("#tbody-cuenta-table .debe").each(function() {
            let valor = parseMonto($(this).val());

            if (valor > 0.000001) {
                totDebe += valor;
			}
        });

        $("#tbody-cuenta-table .haber").each(function() {
            let valor = parseMonto($(this).val());

			if (valor > 0.000001) {
				totHaber += valor;
			}
    	});

		totDebe = Math.round(totDebe * 100) / 100;
		totHaber = Math.round(totHaber * 100) / 100;

		$("#totaldebe").val(formateaMontoTotal(totDebe));
		$("#totalhaber").val(formateaMontoTotal(totHaber));
	}

	$("#form-general").submit(function (e) {
		e.preventDefault();
		enviarFormularioAsiento(false);
	});

	/**
	 * Antes de armar FormData: si el operador cambió el código y aún no resolvió
	 * (Enter/blur async), sincroniza cuentacontable_ids[] para que la grabación
	 * no persista el id anterior.
	 */
	function asegurarCuentasContablesResueltasAntesDeEnviar() {
		var empresaId = parseInt($('#empresa_id').val(), 10) || 0;
		if (!empresaId) {
			return { ok: false, mensaje: 'Debe ingresar empresa' };
		}

		var error = null;
		$('#tbody-cuenta-table tr.item-cuenta').each(function () {
			if (error) {
				return false;
			}
			var $tr = $(this);
			var codigo = $.trim($tr.find('.codigocuentacontable').first().val() || '');
			var codigoPrev = $.trim($tr.find('.codigo_previo').first().val() || '');
			var cuentaId = parseInt($tr.find('.cuentacontable_id').first().val(), 10) || 0;

			if (!codigo) {
				if (cuentaId > 0) {
					$tr.find('.cuentacontable_id').first().val('');
					$tr.find('.cuentacontable_id_previa').val('');
				}
				return;
			}

			if (cuentaId > 0 && codigo === codigoPrev) {
				return;
			}

			var urlCta = carpetaBase + '/contable/cuentacontable/leercuentacontableporcodigo/'
				+ empresaId + '/' + encodeURIComponent(codigo);
			$.ajax({
				url: urlCta,
				type: 'GET',
				async: false,
				success: function (data) {
					if (data && data.id > 0) {
						if (typeof aplicarCuentaContableEnContexto === 'function') {
							aplicarCuentaContableEnContexto($tr, data);
						} else {
							$tr.find('.cuentacontable_id').first().val(data.id);
							$tr.find('.cuentacontable_id_previa').val(data.id);
							$tr.find('.codigo_previo').val(data.codigo);
							$tr.find('.nombrecuentacontable').first().val(data.nombre);
							$tr.find('.codigocuentacontable').first().val(data.codigo);
						}
					} else {
						error = 'No existe la cuenta ' + codigo;
					}
				},
				error: function () {
					error = 'No se pudo validar la cuenta ' + codigo;
				}
			});
		});

		if (error) {
			return { ok: false, mensaje: error };
		}
		return { ok: true };
	}

	function enviarFormularioAsiento(confirmarPendiente) {
		var resolucionCuentas = asegurarCuentasContablesResueltasAntesDeEnviar();
		if (!resolucionCuentas.ok) {
			alert(resolucionCuentas.mensaje);
			return;
		}

		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.normalizarAntesDeEnviar('#form-general');
		}

		sumaMonto();
		var totDebe = parseMonto($('#totaldebe').val());
		var totHaber = parseMonto($('#totalhaber').val());
		var diff = Math.round((totDebe - totHaber) * 100) / 100;
		if (Math.abs(diff) > 0.01) {
			alert(
				'El asiento no balancea: Debe '
				+ formateaMontoTotal(totDebe)
				+ ' vs Haber '
				+ formateaMontoTotal(totHaber)
				+ ' (diferencia '
				+ formateaMontoTotal(Math.abs(diff))
				+ '). Corrija los importes antes de grabar.'
			);
			return;
		}
		if (totDebe < 0.01) {
			alert('El asiento no tiene importes. Ingrese al menos dos movimientos con Debe y Haber.');
			return;
		}
		asientoAplicarMonedaDesdePrimera(false);
		if (!asientoValidarMonedaUnica()) {
			alert('El asiento no puede mezclar monedas. La moneda la fija el primer movimiento.');
			return;
		}

		let token = $("meta[name='csrf-token']").attr("content");
		let id = $("#id").val();
		var url;

		var parametros=new FormData($("#form-general")[0]);
		parametros.append('_token', token);
		if (confirmarPendiente) {
			parametros.append('confirmar_pendiente_aprobacion', '1');
		}

		if (id != '')
			url = carpetaBase+"/contable/actualizarasiento/"+id;
		else
			url = carpetaBase+"/contable/asiento";

		$.ajaxSetup({
			beforeSend: BeforeSend,
			complete: CompleteFunc,
		});

		$.ajax({
			type: "POST",
			url: url,
			data: parametros,
			contentType: false,
			processData: false,
			dataType: 'json',
			success: function (data) {
				if (data.requiere_aprobacion) {
					mostrarModalAprobacionCuentas(data.cuentas_detalle || []);
					return;
				}
				if (data.mensaje === 'pendiente') {
					if (data.url_impresion_asiento) {
						window.open(data.url_impresion_asiento, '_blank', 'noopener');
					}
					alert("Asiento guardado en estado PENDIENTE (Nº "+data.numeroasiento+"). Contaduría fue notificada para su aprobación.");
					window.location.href = carpetaBase+'/contable/asiento';
					return;
				}
				if (data.mensaje == 'ok') {
					if (data.url_impresion_asiento) {
						window.open(data.url_impresion_asiento, '_blank', 'noopener');
					}
					alert("Se grabó el asiento con éxito");
					window.location.href = carpetaBase+'/contable/asiento';
					return;
				}
				if (data.errores)
					alert("Error: "+data.errores);
				else
					alert("Error de grabacion");
			},
			error: function (r) {
				alert("Error del servidor");
			}
		});
	}

	function mostrarModalAprobacionCuentas(cuentas) {
		var $lista = $('#lista-cuentas-no-autorizadas');
		$lista.empty();
		if (!cuentas.length) {
			$lista.append('<li>Cuentas fuera de su lista autorizada</li>');
		} else {
			cuentas.forEach(function (c) {
				$lista.append('<li><strong>'+c.codigo+'</strong> — '+c.nombre+'</li>');
			});
		}
		$('#aprobacionCuentasModal').modal('show');
	}

	$('#acepta-aprobacion-cuentas').on('click', function () {
		$('#aprobacionCuentasModal').modal('hide');
		enviarFormularioAsiento(true);
	});
	
	function mostrarOverlayGuardandoAsiento() {
		var overlay = document.getElementById('asiento-guardando-overlay');
		if (!overlay) {
			return;
		}
		overlay.classList.remove('d-none');
		overlay.style.display = 'flex';
		overlay.setAttribute('aria-hidden', 'false');
	}

	function ocultarOverlayGuardandoAsiento() {
		var overlay = document.getElementById('asiento-guardando-overlay');
		if (!overlay) {
			return;
		}
		overlay.classList.add('d-none');
		overlay.style.display = '';
		overlay.setAttribute('aria-hidden', 'true');
	}

	function BeforeSend()
	{
		mostrarOverlayGuardandoAsiento();
	}
	
	function CompleteFunc()
	{
		ocultarOverlayGuardandoAsiento();
	}

	window.addEventListener('pageshow', function () {
		ocultarOverlayGuardandoAsiento();
	});

