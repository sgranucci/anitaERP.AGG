
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

        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();            

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Datos facturac&oacute;n");
        });

        $("#botonform3").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").show();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();

		 	// Hace foco en el campo de la leyenda
			$("#leyenda").focus();            
        });

        $("#botonform4").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").show();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();            
        });

        $("#botonform5").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").show();
            $(".form6").hide();
            $(".form7").hide();            
        });
	                     
        $("#botonform6").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").show();
            $(".form7").hide();            
        });
	                     
        $("#botonform7").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").show();    
            
            // lee historia
			leeHistoria();
        });
	             
        $('#descripcion').on('change', function () {                             
            let descripcion = $(this).val();

            $(".descripcion").val(descripcion);
        });  
        
        $('#sku').on('change', function () {                             
            let sku = $(this).val();

            $(".sku").val(sku);
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
        $('#agrega_renglon_cuentacontable').on('click', agregaRenglonCuentaContable);
        $(document).on('click', '.eliminar_cuentacontable', borraRenglonCuentaContable);    
        
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

    	$("#tbody-tabla-archivo").append(renglon);
        activa_eventos(false);
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function actualizaArchivo(elem) {
	  	var fn = $(elem).val();
		var filename = fn.match(/[^\\/]*$/)[0]; // remove C:\fakename

		$(elem).parents("tr").find(".nombresanteriores").val(filename);
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

		let listarUri = "/anitaERP/public/stock/actualizaestadoarticulo/"+estadoArticulo+"/"+articulo_id;

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

		let url = '/anitaERP/public/stock/leer_historia_articulo/'+articulo_id;

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

