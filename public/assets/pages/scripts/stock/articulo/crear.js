function mostrarSolapaArticulo(numero) {
    var secciones = (typeof SECCIONES_SOLAPA_FORM !== 'undefined')
        ? SECCIONES_SOLAPA_FORM
        : '.form1,.form2,.form3,.form4,.form5,.form6,.form7,.form8,.form9';
    $(secciones).hide();
    $('.form' + numero).show();
    var $tabs = $('#tabs-articulo');
    if ($tabs.length) {
        $tabs.find('[id^="botonform"]').removeClass('active');
        $('#botonform' + numero).addClass('active');
    } else {
        $('[id^="botonform"]').removeClass('btn-primary').addClass('btn-info');
        $('#botonform' + numero).removeClass('btn-info').addClass('btn-primary');
    }
}

    $(function () {
        $("#botonestado").click(function(){

            var estado = $("#estado").val();
			var descripcion = $("#botonestado").text();

			if (estado == '0')
			{
				estado = '1';
				descripcion = 'Suspendido';

                // Muestra modal si tiene orden de trabajo generada
                $("#suspensionModal").modal('show');
            }
            else
			{
				estado = '0';
				descripcion = 'Activo';
                
                // Pasa tipo de suspension al form
                $('#tiposuspension_id').val('');

                // Muestra tipo de suspension
                muestraTipoSuspension();
			}

            $("#estado").val(estado);
            $("#botonestado").html("<i class='fa fa-bell'></i>&nbsp;Estado "+descripcion);
        });

        $(document).on('click', '#botonform1', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(1);
        });

        $(document).on('click', '#botonform2', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(2);
			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Datos facturac&oacute;n");
        });

        $(document).on('click', '#botonform3', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(3);
        });

        $(document).on('click', '#botonform4', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(4);
        });

        $(document).on('click', '#botonform5', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(5);
		 	$("#leyenda").focus();
        });
	                     
        $(document).on('click', '#botonform6', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(6);
        });
	                     
        $(document).on('click', '#botonform7', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(7);
			leeHistoria();
        });

        $(document).on('click', '#botonform8', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(8);
        });

        $(document).on('click', '#botonform9', function (e) {
            e.preventDefault();
            mostrarSolapaArticulo(9);
        });

        if ($('#botonform1').length) {
            if ($('#tabs-articulo').length) {
                $('#tabs-articulo').find('[id^="botonform"]').removeClass('active');
                $('#botonform1').addClass('active');
            } else {
                $('#botonform1').removeClass('btn-info').addClass('btn-primary');
            }
        }
	             
        $('#descripcion').on('change', function () {                             
            let descripcion = $(this).val();

            $(".descripcion").val(descripcion);
        });  
        
        $('#sku').on('change', function () {                             
            let sku = $(this).val();

            $(".sku").val(sku);

            // busca el sku si existe
            let url = carpetaBase+'/stock/leerunarticuloporsku/'+sku;

            $.get(url, function(articulo){

                if (articulo.id > 0)
                {
                    alert('Articulo ya existente '+articulo.id+' Descripcion '+articulo.descripcion);

                    $("#sku").val('');
                    $("#sku").focus();
                }
            });  
        });
                
        $('#unidadmedida_id').on('change', function () {                             
            let unidadmedida_id = $(this).val();

            $(".unidadmedida").val(unidadmedida_id);
        });  

        $('#unidadmedida2_id').on('change', function () {                             
            let unidadmedida_id = $(this).val();

            $(".unidadmedida").val(unidadmedida_id);
        });  

        $('#unidadmedida3_id').on('change', function () {                             
            let unidadmedida_id = $(this).val();

            $(".unidadmedida").val(unidadmedida_id);
        });  
	                      
        $('#unidadmedidaalternativa_id').on('change', function () {                             
            let unidadmedidaalternativa_id = $(this).val();

            $(".unidadmedidaalternativa").val(unidadmedidaalternativa_id);
        });  

        $('#unidadmedidaalternativa2_id').on('change', function () {                             
            let unidadmedidaalternativa_id = $(this).val();

            $(".unidadmedidaalternativa").val(unidadmedidaalternativa_id);
        });  

        $('#unidadmedidaalternativa3_id').on('change', function () {                             
            let unidadmedidaalternativa_id = $(this).val();

            $(".unidadmedidaalternativa").val(unidadmedidaalternativa_id);
        });  

        activa_eventos(true);        

		// Muestra boton de anulacion
		let estadoArticulo = $('#estado').val();

		muestraBotonAnulacion(estadoArticulo);        

        // lee historia
        leeHistoria();
                
        $('#nombre').on('input', function() {
            filtraCaracteresEspeciales(this);
        });

        $('#domicilio').on('input', function() {
            filtraCaracteresEspeciales(this);
        });

        // Controla apertura modal de anulacion
        $('#suspensionModal').on('show.bs.modal', function (event) {
            var modal = $(this);
            var nombre = $("#nombre").val();
            var tiposuspension_id = $('#modaltiposuspension_id').val();

            var tituloModal = "Suspension del cliente "+nombre;
            modal.find('.modal-title').text(tituloModal);
            $('#modaltiposuspension_id').val(tiposuspension_id);
        });

        $('#cierrasuspensionModal').on('click', function () {
            
        });

        // Acepta modal de suspension de articulo
        $('#aceptasuspensionModal').on('click', function () {
            var tiposuspension_id = $('#modaltiposuspension_id').val();

            // Pasa tipo de suspension al form
            $('#tiposuspension_id').val(tiposuspension_id);

            $('#suspensionModal').modal('hide');
 
            // Muestra tipo de suspension
            muestraTipoSuspension();
        });

        $('#suspensionModal').on('hidden.bs.modal', function () {
        
        });

        $( ".botonsubmit" ).click(function() {
            $("#form-general").submit();
        });        

        // Muestra tipo de suspension
        muestraTipoSuspension();
        
        $('#agrega_renglon').on('click', agregaRenglon);
        $(document).on('click', '.eliminar', borraRenglon);
        $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
        $(document).on('click', '.eliminar-archivo-articulo', function () {
            $(this).closest('.articulo-archivo-item').remove();
        });
        $('#agrega_renglon_cuentacontable').on('click', agregaRenglonCuentaContable);
        $(document).on('click', '.eliminar_cuentacontable', borraRenglonCuentaContable);    
        $(document).on('click', '.replicar_cuentacontable', replicaCuentaContable); 
        
        $('#sku').focus();
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}

		// Activa eventos de consulta
		activa_eventos_consultaarticulo();
        activa_eventos_consulta_cuentacontable();
    }

    function muestraTipoSuspension()
    {
        var tiposuspensioncliente_query = $("#tiposuspensioncliente_query").val();
        var tiposuspension_id = $("#tiposuspension_id").val();

        if (tiposuspension_id > 0)
        {
            var tbl_tiposuspension = JSON.parse(tiposuspensioncliente_query);

            var nombre = "";
            $.each(tbl_tiposuspension, function(index,value){
                if (value.id == tiposuspension_id)
                    nombre = value.nombre;
            });

            $('#nombretiposuspension').text("SUSPENDIDO: "+nombre);
        }
        else
        {
            $('#nombretiposuspension').text('');
        }
    }

    function agregaRenglon(){
    	event.preventDefault();
    	var renglon = $('#template-renglon').html();

    	$("#tbody-tabla").append(renglon);
    	actualizaRenglones();
		activaEventoEntrega();

        activa_eventos(false);
    }

    function borraRenglon() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglones();
		activaEventoEntrega();
    }

    function actualizaRenglones() {
    	var item = 1;

    	$("#tbody-tabla .iicuota").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonArchivo(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-archivo').html();
    	var $tbody = $("#tbody-tabla-archivo");
    	$tbody.append(renglon);
        activa_eventos(false);
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	var $tbody = $("#tbody-tabla-archivo");
    	var $fila = $(this).parents('tr');
    	if ($tbody.find('tr').length <= 1) {
    		$fila.find('input[type="file"]').val('');
    		return;
    	}
    	$fila.remove();
    }

    function agregaRenglonCuentaContable(event){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuentacontable').html();

		$("#tbody-cuentacontable-table").append(renglon);
    	actualizaRenglonesCuentaContable();

        activa_eventos(false);
    }

    function borraRenglonCuentaContable(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuentaContable();
    }

    function replicaCuentaContable(event) {
        event.preventDefault();
        let empresa_id = $(this).parents('tr').find('.empresa').val();
        let tipoimputacion = $(this).parents('tr').find('.tipoimputacion').val();
        let cuentacontable_id = $(this).parents('tr').find('.cuentacontable_id').val();
        let flError = false;
		let url = carpetaBase+'/stock/replicar_cuentacontable_articulo/'+empresa_id+'/'+tipoimputacion+'/'+cuentacontable_id;

		$.get(url, function(cuentas){
			var cta = $.map(cuentas, function(value, index){
				return [value];
			});
			$.each(cta, function(index,value){
                if (value.empresa_id)
                {
                    // Busca si la cuenta que envia ya existe
                    $("#tbody-cuentacontable-table .empresa").each(function(index) {
                        let act_empresa_id = $(this).val();
                        let act_tipoimputacion = $(this).parents('tr').find('.tipoimputacion').val();

                        if (value.empresa_id == act_empresa_id && value.tipoimputacion == act_tipoimputacion)
                        {
                            alert("El registro ya fue replicado");
                            flError = true;
                        }
                    });        
                    
                    if (!flError)
                    {
                        agregaRenglonCuentaContable(event);

                        $('#cuentacontable-table').find('tr').last().find('.empresa').val(value.empresa_id);
                        $('#cuentacontable-table').find('tr').last().find('.tipoimputacion').val(value.tipoimputacion);
                        $('#cuentacontable-table').find('tr').last().find('.cuentacontable_id').val(value.cuentacontable_id);
                        $('#cuentacontable-table').find('tr').last().find('.codigocuentacontable').val(value.codigocuentacontable);
                        $('#cuentacontable-table').find('tr').last().find('.nombrecuentacontable').val(value.nombrecuentacontable);
                    }
                }
            });
        });
    }

    function actualizaRenglonesCuentaContable() {
    	var item = 1;

    	$("#tbody-cuentacontable-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }

	function anulaArticulo()
	{
		let estadoActualArticulo = $('#estado').val();

		if (estadoActualArticulo != 'INACTIVO' && estadoActualArticulo != 'ACTIVO')
		{
			alert("No se puede cambiar el estado del artículo")
			return;
		}
		switch(estadoActualArticulo)
		{
			case 'INACTIVO':
				$('#estado').val('ACTIVO');	
				break;
			case 'ACTIVO':
				$('#estado').val('INACTIVO');
				break;
		}

		// Actualiza estado de la orden de venta
		let estadoArticulo = $('#estado').val();
		let articulo_id = $('#articulo_id').val();

		let listarUri = carpetaBase+"/stock/actualizaestadoarticulo/"+estadoArticulo+"/"+articulo_id;

		$.get(listarUri)
			.done(function(data){
				alert('Artículo actualizado con éxito');

				muestraBotonAnulacion(estadoArticulo);

                leeHistoria();
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				alert("Error en la petición: "+textStatus+errorThrown);
				alert("Estado de la respuesta: "+jqXHR.status); // Ej: 404, 500
			});
	}
	
    function muestraBotonAnulacion(estadoArticulo)
	{
		switch(estadoArticulo)
		{
			case 'INACTIVO':
				$('#anulaarticulo').html('<i class="fas fa-check"></i>Activar el Artículo');
				$( "#anulaarticulo" ).css( "background-color", "green" ); 
				break;
			case 'ACTIVO':
				$('#anulaarticulo').html('<i class="fas fa-cross"></i>Inactivar el Artículo');
				$( "#anulaarticulo" ).css( "background-color", "yellow" ); 
				break;
		}
	}

    function leeHistoria()
	{
		var wrapper = $(".container-historia");
		let articulo_id = $("#articulo_id").val();

		let url = carpetaBase+'/stock/leer_historia_articulo/'+articulo_id;

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
                                '<input type="hidden" name="estadofechas[]" class="form-control estadofecha" value="'+value.fecha+'" readonly>'+
                                '<input type="datetime" name="estadocreated[]" class="form-control estadofecha" value="'+fechaObjeto+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="estados[]" class="form-control estado" value="'+value.estado+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="hidden" name="estadousuarios[]" class="form-control estadousuario" value="'+value.usuarios.id+'" readonly>'+
                                '<input type="text" name="estadonombreusuarios[]" class="form-control estadonombreusuarios" value="'+value.usuarios.nombre+'" readonly>'+
                            '</td>'+
                            '<td>'+
                                '<input type="text" name="estadoobservaciones[]" class="form-control estadoobservacion" value="'+value.observacion+'" readonly>'+
                            '</td>'+
                        '</tr>');
			});
		});
	}

