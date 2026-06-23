var cuentacontablexcodigo;
var nombrexcodigo;
var codigoxcodigo;
   
    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);
		$('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

		activa_eventos(true);

		// Completa centros de costo al abrir asiento
		$("#tbody-cuenta-table .codigo").each(function(index) {
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
		sumaMonto();
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuenta').off('click');
			$('.codigo').off('change');
			$('.debe').off('change');
			$('.haber').off('change');
			$('.moneda').off('change');
		}
		
		$('.codigo').on('change', function (event) {
			event.preventDefault();
			var codigo = $(this);
			var codigo_ant = $(this).parents("tr").find(".codigo_previo").val();
			var codigo_nuevo = codigo.val();
			let empresa_id = $('#empresa_id').val();

			let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontableporcodigo/'+empresa_id+'/'+codigo_nuevo;

			$.get(url_cta, function(data){
				if (data.id > 0)
				{
					$(codigo).parents("tr").find('.cuentacontable_id').val(data.id);
					$(codigo).parents("tr").find(".cuentacontable_id_previa").val(data.id);
					$(codigo).parents("tr").find(".nombre").val(data.nombre);
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
				leeCentroCosto(this);
		});

		$('.consultacuenta').on('click', function (event) {
        	cuentacontablexcodigo = $(this).parents("tr").find(".cuentacontable_id");
			nombrexcodigo = $(this).parents("tr").find(".nombre");
			codigoxcodigo = $(this).parents("tr").find(".codigo");
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
			$(nombrexcodigo).val(nombre);
			$(codigoxcodigo).val(codigo);

			//* Asigna nueva cuentacontable
			$(cuentacontablexcodigo).parents("tr").find(".cuentacontable_id_previa").val($(cuentacontablexcodigo).val());
		
			$('#consultacuentaModal').modal('hide');

			leeCentroCosto(codigoxcodigo);
		});

		$('.debe').on('change', function (event) {
			event.preventDefault();
			sumaMonto();
		});

		$('.haber').on('change', function (event) {
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

	function completarCentroCosto(ptrcodigo, cuentacontable_id, centrocosto_id){
		let url_cta = carpetaBase+'/contable/cuentacontable/leercuentacontablecentrocosto/'+cuentacontable_id;

		$.get(url_cta, function(data){
			if (data === "No maneja centro de costo")
			{
				$(ptrcodigo).parents("tr").find('.centrocosto').empty();
				$(ptrcodigo).parents("tr").find('.centrocosto').append('<option value="0" selected>Sin CC</option>');
				$(ptrcodigo).parents("tr").find('.centrocosto').attr("readonly", true);
			}
			else
			{
				var cta = $.map(data, function(value, index){
					return [value];
				});
				$(ptrcodigo).parents("tr").find('.centrocosto').empty();
				$(ptrcodigo).parents("tr").find('.centrocosto').append('<option value="">-- Seleccione CC --</option>');
				$.each(cta, function(index,value){
					if (value.id == centrocosto_id)
						$(ptrcodigo).parents("tr").find('.centrocosto').append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
					else
						$(ptrcodigo).parents("tr").find('.centrocosto').append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
				});
			}
        });
        setTimeout(() => {
        }, 3000);
    }

	function leeCentroCosto(ptr) 
	{
		var codigo = $(ptr);
		var codigo_ant = $(ptr).parents("tr").find(".codigo_previo").val();
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
					$(codigo).parents("tr").find(".nombre").val(data.nombre);
					if (data.manejaccosto === 'S')
					{
						$(codigo).parents("tr").find('.centrocosto').attr("readonly", false);

						completarCentroCosto(codigo, data.id, 0);
					}
					else
					{
						$(codigo).parents("tr").find('.centrocosto').empty();
						$(codigo).parents("tr").find('.centrocosto').append('<option value="0" selected>Sin CC</option>');
						$(codigo).parents("tr").find('.centrocosto').attr("readonly", true);
					}
				});

				//* Asigna nuevo codigo de cuenta
				$(this).parents("tr").find(".codigo_previo").val(codigo_nuevo);
			}
		}
	}

	function leeCotizacion(ptr)
	{
		let fecha = $('#fecha').val();
		let moneda_id = $(ptr).parents("tr").find('.moneda').val();
		let url_cot = carpetaBase+'/configuracion/leercotizacion/'+fecha+'/'+moneda_id;
	
		$.get(url_cot, function(data){
			$(ptr).parents("tr").find('.cotizacion').val(data.cotizacionventa);
			sumaMonto();
		});
	}

	function sumaMonto()
	{
		let totDebe = 0;
		let totHaber = 0;
		let monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val();

		$("#tbody-cuenta-table .debe").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			let cotizacion = $(this).parents("tr").find('.cotizacion').val();

            if (valor >= 0)
			{
				let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

                totDebe += valor * coef;
			}
        });

        $("#tbody-cuenta-table .haber").each(function() {
            let valor = parseFloat($(this).val());
			let moneda = $(this).parents("tr").find('.moneda').val();
			let cotizacion = $(this).parents("tr").find('.cotizacion').val();

			if (valor >= 0)
			{
				let coef = calculaCoeficienteMoneda(monedaDefault, moneda, cotizacion);

				totHaber += valor * coef;
			}
    	});
		$("#totaldebe").val(totDebe.toFixed(2));
		$("#totalhaber").val(totHaber.toFixed(2));
	}

	$("#form-general").submit(function (e) {
		e.preventDefault();
		enviarFormularioAsiento(false);
	});

	function enviarFormularioAsiento(confirmarPendiente) {
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
	
	function BeforeSend()
	{
		$("#loading").show();
	}
	
	function CompleteFunc()
	{
		$("#loading").hide();
	}
	


		


