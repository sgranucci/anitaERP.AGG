var cuentacontablexcodigo;
var nombrexcodigo;
var codigoxcodigo;
   
    $(function () {
        $('#agrega_renglon_cuenta').on('click', agregaRenglonCuenta);
        $(document).on('click', '.eliminar_cuenta', borraRenglonCuenta);

		activa_eventos(true);

    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuenta').off('click');
			$('.codigo').off('change');
		}
		
		$('.codigo').on('change', function (event) {
			event.preventDefault();
			var codigo = $(this);
			var codigo_ant = $(this).parents("tr").find(".codigo_previo").val();
			var codigo_nuevo = codigo.val();
			let empresa_id = $(this).parents("tr").find(".empresa").val();

			let url_cta = '/anitaERP/public/contable/cuentacontable/leercuentacontableporcodigo/'+empresa_id+'/'+codigo_nuevo;

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
			let empresa_id = $(this).parents("tr").find(".empresa").val();

			// Abre modal de consulta
			if (empresa_id > 0)
			{
				$("#consultacuentaModal").modal('show');
				$("#consultaempresa_id").val(empresa_id);
			}
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
		});

	}

    function agregaRenglonCuenta(event){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuenta').html();
		let empresaDefault = $("#tbody-cuenta-table").children(':last').find('.empresa').val();

		$("#tbody-cuenta-table").append(renglon);
    	actualizaRenglonesCuenta();

		// Asigna default de empresa
		$("#tbody-cuenta-table").last().find('.empresa').val(empresaDefault);

		activa_eventos(false);
    }

    function borraRenglonCuenta(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuenta();
    }

    function actualizaRenglonesCuenta() {
    	var item = 1;

    	$("#tbody-cuenta-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }

	$("#form-general").submit(function (e) {
		e.preventDefault();
		let token = $("meta[name='csrf-token']").attr("content");
		let usuario_id = $("#usuario_id").val();
	
		let datosCuentas=[];
		let datosHeader=[];
		var empresa_ids;
		var cuentacontable_ids;
		var url;

		datosHeader.push({
			usuario_id
		});
		datosHeader = JSON.stringify(datosHeader);

		$("#cuenta-table .item-cuenta").each(function() {
			cuentacontable_ids = $(this).find(".cuentacontable_id").val();
			empresa_ids = $(this).find(".empresa").val();

			datosCuentas.push({
				cuentacontable_ids,
				empresa_ids
			});
		});
		datosCuentas = JSON.stringify(datosCuentas);
		
		$.ajaxSetup({
			beforeSend: BeforeSend,
			complete: CompleteFunc,
		});

		var parametros=new FormData($(this)[0])

		parametros.append('_token', token);


		url = "/anitaERP/public/contable/actualizar_usuario_cuentacontable";

		//realizamos la petición ajax con la función de jquery
		$.ajax({
			type: "POST",
			url: url,
			data: parametros,
			contentType: false, //importante enviar este parametro en false
			processData: false, //importante enviar este parametro en false
			success: function (data) {
				if (data.mensaje == 'ok')
					alert("Se grabó el usuario con éxito");
				else
					alert("Error de grabacion");
				window.location.href = '/anitaERP/public/contable/usuario_cuentacontable';
			},
			error: function (r) {
				alert("Error del servidor");
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
	


		


