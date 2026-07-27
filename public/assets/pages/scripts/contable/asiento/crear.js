    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		activa_eventos(true);

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
            $(".form1").show();
            $(".form2").hide();
        });
		$("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Cuentas");
        });

		// copia asiento
		$("#botonform3").click(function(){
			$('#copiarasientoModal').modal('show');
        });

		$('#aceptacopiarasientoModal').on('click', function () {

			$('#copiarasientoModal').modal('hide');

			let url = carpetaBase+'/contable/copiar_asiento';

			$.post(url, {_token: $('input[name=_token]').val(), 
						id: $('#id').val(),
						fecha: $('#fechacopia').val()}, function(data)
						{ 
							alert("ASIENTO COPIADO CORRECTAMENTE GENERO EL ASIENTO CON ID:"+data.asiento_id+" NUMERO: "+data.numeroasiento); 
						});
    	});

		$('#cierracopiarasientoModal').on('click', function () {
			$('#copiarasientoModal').modal('hide');
		});

		// revierte asiento
		$("#botonform4").click(function(){
			$('#revertirasientoModal').modal('show');
        });

		$('#aceptarevertirasientoModal').on('click', function () {

			$('#revertirasientoModal').modal('hide');

			let url = carpetaBase+'/contable/copiar_asiento';

			$.post(url, {_token: $('input[name=_token]').val(), 
						id: $('#id').val(),
						fecha: $('#fechacopia').val(),
						revierte: 1}, function(data)
						{ 
							alert("ASIENTO REVERTIDO CORRECTAMENTE GENERO EL ASIENTO CON ID:"+data.asiento_id+" NUMERO: "+data.numeroasiento); 
						});
    	});

		$('#cierrarevertirasientoModal').on('click', function () {
			$('#revertirasientoModal').modal('hide');
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
    });

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
			leeCotizacion(this);
		});
	}

    function agregaRenglonCuenta(){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuenta').html();
		let monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val();

    	$("#tbody-cuenta-table").append(renglon);
    	actualizaRenglonesCuenta();

		// Asigna default de moneda
		$("#tbody-cuenta-table").last().find('.moneda').val(monedaDefault);

		let ptrUltimoRenglon = $("#tbody-cuenta-table").last().find('.moneda');

		// Lee cotizacion de la moneda
		leeCotizacion(ptrUltimoRenglon);

		activa_eventos(false);

		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.initEnContenedor($("#tbody-cuenta-table tr.item-cuenta").last());
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
		if (window.AsientoMontosFormato) {
			return AsientoMontosFormato.fmt(n);
		}
		return Number(n || 0).toFixed(2);
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

	function enviarFormularioAsiento(confirmarPendiente) {
		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.normalizarAntesDeEnviar('#form-general');
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
					alert("Asiento guardado en estado PENDIENTE (Nº "+data.numeroasiento+"). Contaduría fue notificada para su aprobación.");
					window.location.href = carpetaBase+'/contable/asiento';
					return;
				}
				if (data.mensaje == 'ok')
					alert("Se grabó el asiento con éxito");
				else if (data.errores)
					alert("Error: "+data.errores);
				else
					alert("Error de grabacion");
				if (data.mensaje == 'ok')
					window.location.href = carpetaBase+'/contable/asiento';
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

