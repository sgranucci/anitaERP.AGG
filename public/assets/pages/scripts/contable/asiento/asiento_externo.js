var cuentacontablexcodigo;
var nombrecontablexcodigo;
var codigocontablexcodigo;
var totalDebeAsiento = 0;
var totalHaberAsiento = 0;

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
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuenta').off('click');
			$('.codigoasiento').off('change');
			$('.debeasiento').off('change input');
			$('.haberasiento').off('change input');
			$('.cotizacionasiento').off('change input');
			$('.monedaasiento').off('change');
		}
		
		$('.codigoasiento').on('change', function (event) {
			event.preventDefault();
			var codigo = $(this);
			var codigo_ant = $(this).parents("tr").find(".codigo_previo_cuentacontable").val();
			var codigo_nuevo = codigo.val();
			let empresa_id = $('#empresa_id').val();

			let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontableporcodigo/'+empresa_id+'/'+codigo_nuevo;

			$.get(url_cta, function(data){
				if (data.id > 0)
				{
					$(codigo).parents("tr").find('.cuentacontable_id').val(data.id);
					$(codigo).parents("tr").find(".cuentacontable_id_previa").val(data.id);
					$(codigo).parents("tr").find(".nombrecuentacontable").val(data.nombre);
				}
				else
				{
					alert("No existe la cuenta");

					// Borra el renglon
					$(codigo).parents('tr').remove();
					return;
				}
			});

			if (codigo_nuevo != codigo_ant && empresa_id)
				leeCentroCostoAsiento(this);
		});

		$('.consultacuenta').on('click', function (event) {
        	cuentacontablexcodigo = $(this).parents("tr").find(".cuentacontable_id");
			nombrecontablexcodigo = $(this).parents("tr").find(".nombrecuentacontable");
			codigocontablexcodigo = $(this).parents("tr").find(".codigoasiento");
			let empresa_id = $('#empresa_id').val();

        	// Abre modal de consulta
			if (empresa_id)
				$("#consultacuentaModal").modal('show');
			else	
				alert('Debe ingresar empresa');
    	});

		$('#consultacuentaModal').on('shown.bs.modal', function () {
			$(this).find('[autofocus]').focus();
		})

    	$('#aceptaconsultacuentaModal').on('click', function () {
        	$('#consultacuentaModal').modal('hide');
    	});

		$(document).on('click', '.eligeconsultacuentacontable', function () {
			var seleccion = $(this).parents("tr").children().html();
			var nombre = $(this).parents("tr").find(".nombrecuentacontable").html();
			var codigo = $(this).parents("tr").find(".codigocuentacontable").html();

			// Asigna a grilla los valores devueltos por consulta
			$(cuentacontablexcodigo).val(seleccion);
			$(nombrecontablexcodigo).val(nombre);
			$(codigocontablexcodigo).val(codigo);

			//* Asigna nueva cuentacontable
			$(cuentacontablexcodigo).parents("tr").find(".cuentacontable_id_previa").val($(cuentacontablexcodigo).val());
		
			$('#consultacuentaModal').modal('hide');

			leeCentroCostoAsiento(codigocontablexcodigo);
		});

		$('.debeasiento').on('change input', function (event) {
			event.preventDefault();
			sumaMontoAsiento();
		});

		$('.haberasiento').on('change input', function (event) {
			event.preventDefault();
			sumaMontoAsiento();
		});

		$('.cotizacionasiento').on('change input', function (event) {
			event.preventDefault();
			sumaMontoAsiento();
		});

		$('.monedaasiento').on('change', function (event) {
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

		activa_eventosAsiento(false);

		if (window.AsientoMontosFormato) {
			AsientoMontosFormato.initEnContenedor($("#tbody-cuenta-asiento-table tr.item-cuenta-asiento").last());
		}
    }

    function borraRenglonCuentaAsiento(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuentaAsiento();
		sumaMontoAsiento();
    }

    function actualizaRenglonesCuentaAsiento() {
    	var item = 1;

    	$("#tbody-cuenta-asiento-table .iicuentacontable").each(function() {
    		$(this).val(item++);
    	});
    }

	function completarCentroCostoAsiento(ptrcodigo, cuentacontable_id, centrocosto_id){
		let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontablecentrocosto/'+cuentacontable_id;

		$.get(url_cta, function(data){
			if (data === "No maneja centro de costo")
			{
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').empty();
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="0" selected>Sin CC</option>');
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').attr("readonly", true);
			}
			else
			{
				var cta = $.map(data, function(value, index){
					return [value];
				});
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').empty();
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="">-- Seleccione CC --</option>');
				$.each(cta, function(index,value){
					if (value.id == centrocosto_id)
						$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
					else
						$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
				});
			}
        });
        setTimeout(() => {
        }, 3000);
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

				$.get(url_cta, function(data){
					$(codigo).parents("tr").find('.cuentacontable_id').val(data.id);
					$(codigo).parents("tr").find(".cuentacontable_id_previa").val(data.id);
					$(codigo).parents("tr").find(".nombrecuentacontable").val(data.nombre);
					if (data.manejaccosto === 'S')
					{
						$(codigo).parents("tr").find('.centrocostoasiento').attr("readonly", false);

						var ccPrevio = parseInt($(codigo).parents("tr").find('.centrocostoasiento_id_previo').val() || '0', 10) || 0;
						completarCentroCosto(codigo, data.id, ccPrevio);
					}
					else
					{
						$(codigo).parents("tr").find('.centrocostoasiento').empty();
						$(codigo).parents("tr").find('.centrocostoasiento').append('<option value="0" selected>Sin CC</option>');
						$(codigo).parents("tr").find('.centrocostoasiento').attr("readonly", true);
					}
				});

				//* Asigna nuevo codigo de cuenta
				$(this).parents("tr").find(".codigo_previo_cuentacontable").val(codigo_nuevo);
			}
		}
	}

	function completarCentroCosto(ptrcodigo, cuentacontable_id, centrocosto_id){
		let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontablecentrocosto/'+cuentacontable_id;

		$.get(url_cta, function(data){
			if (data === "No maneja centro de costo")
			{
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').empty();
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="0" selected>Sin CC</option>');
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').attr("readonly", true);
			}
			else
			{
				var cta = $.map(data, function(value, index){
					return [value];
				});
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').empty();
				$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="">-- Seleccione CC --</option>');
				$.each(cta, function(index,value){
					if (value.id == centrocosto_id)
						$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
					else
						$(ptrcodigo).parents("tr").find('.centrocostoasiento').append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
				});
			}
        });
        setTimeout(() => {
        }, 3000);
    }

	function controlaCentroCosto()
	{
		let flError = false;

		$("#tbody-cuenta-asiento-table .centrocostoasiento").each(function() {
			var centrocosto_id = $(this);
			var codigo = $(this).parents("tr").find(".codigocuentacontable").val();

			let url_cta = carpetaBase+'/caja/cuentacaja/leercuentacajaporcodigo/'+codigo;

			$.get(url_cta, function(data){
				if (data.manejaccosto != 'N' && !$.isNumeric(centrocosto_id))
					flError = true;	
			});
		});

		return(flError);
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
		if (window.AsientoMontosFormato) {
			return AsientoMontosFormato.fmt(n);
		}
		return Number(n || 0).toFixed(2);
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
