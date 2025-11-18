
    function completarLetra(condicioniva_id){
		var condiva = $("#condicioniva_query").val();
		const replace = '"';
		var data = condiva.replace(/&quot;/g, replace);
		var dataP = JSON.parse(data);

		$.each(dataP, (index, value) => {
			if (value['id'] == condicioniva_id)
				$("#letra").val(value['letra']);
  		});
	}

    $(function () {
        $("#condicioniva_id").change(function(){
            var  condicioniva_id = $(this).val();
            completarLetra(condicioniva_id);
        });

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
            $(".form8").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();
            $(".form8").hide();

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
            $(".form8").hide();            

			activaEventoEntrega();

	        $("#tbody-tabla .localidades").each(function(index) {
            	var provincia = $(this).parents("tr").find(".provincias");
            	var localidad = $(this).parents("tr").find(".localidades");
            	completarLocalidadesEntrega(provincia);
	
            	var localidad_id_previa = $(this).parents("tr").find(".localidad_id_previas").val();
            	if (localidad_id_previa != "") {
                	setTimeout(() => {
                        $(localidad).val(localidad_id_previa);
                        $("this option[value="+localidad_id_previa+"]").attr("selected",true);
                	}, 1000);
				}
            });
        });

        $("#botonform4").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").show();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();
            $(".form8").hide();

		 	// Hace foco en el campo de la leyenda
			$("#leyenda").focus();
        });

        $("#botonform5").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").show();
            $(".form6").hide();
            $(".form7").hide();
            $(".form8").hide();            
        });

        $("#botonform6").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").show();
            $(".form7").hide();
            $(".form8").hide();     
            
		 	// Hace foco en el campo de la leyenda
			$("#leyenda").focus();            
        });
	       
        $("#botonform7").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").show();
            $(".form8").hide();      
            
            $('#articulo-suspendido-table').find('tr').last().find('.codigoarticulo').focus();
        });
	             
        $("#botonform8").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            $(".form6").hide();
            $(".form7").hide();
            $(".form8").show();   
            
            $('#cm05-table').find('tr').last().find('.codigoprovincia').focus();
        });
	                     
        muestraEmiteNotaDeCredito();

        $("#botonemitenc").click(function(){
            let cliente_id = $('#cliente_id').val();
            let url = '/anitaERP/public/ventas/cliente/emitenc/'+cliente_id;

            $.get(url, function(data, textStatus){
				if (textStatus == 'success')
				{
                    if ($('#botonemitenc').hasClass('btn-danger'))
                    {
                        $('#botonemitenc').removeClass('btn-danger').addClass('btn-success'); 
                        $('#iconoemitenc').removeClass('fa-times').addClass('fa-check'); 
                    }
                    else
                    {
                        $('#botonemitenc').removeClass('btn-success').addClass('btn-danger'); 
                        $('#iconoemitenc').removeClass('fa-check').addClass('fa-times');
                    }                    
				}
				else	
					alert('Ha ocurrido un error modificando el cliente')
			});
        });
	                     
        activa_eventos(true);        

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

        // Acepta modal de suspension de cliente
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

		var condicioniva_id = $("#condicioniva_id").val();
        completarLetra(condicioniva_id);

        // Muestra tipo de suspension
        muestraTipoSuspension();
        
        $('#agrega_renglon').on('click', agregaRenglon);
        $(document).on('click', '.eliminar', borraRenglon);
        $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
        $('#agrega_renglon_seguimiento').on('click', agregaRenglonSeguimiento);
        $(document).on('click', '.eliminar_seguimiento', borraRenglonSeguimiento);
        $('#agrega_renglon_articulo_suspendido').on('click', agregaRenglonArticuloSuspendido);
        $(document).on('click', '.eliminar_articulo_suspendido', borraRenglonArticuloSuspendido);        
        $('#agrega_renglon_cm05').on('click', agregaRenglonCm05);
        $(document).on('click', '.eliminar_cm05', borraRenglonCm05);  
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}

		// Activa eventos de consulta
		activa_eventos_consultaarticulo();
        activa_eventos_consultalocalidad();
        activa_eventos_consultaprovincia();
    }

    function muestraEmiteNotaDeCredito()
    {
        let emiteNotaDeCredito = $("#emitenotadecredito").val();

        $('#botonemitenc').removeClass('btn-danger'); 
        $('#iconoemitenc').removeClass('fa-times'); 
        $('#botonemitenc').removeClass('btn-success'); 
        $('#iconoemitenc').removeClass('fa-check');

        if (emiteNotaDeCredito == 'Emite Nota de Credito')
        {
            $('#botonemitenc').addClass('btn-success'); 
            $('#iconoemitenc').addClass('fa-check'); 
        }
        else
        {
            $('#botonemitenc').addClass('btn-danger'); 
            $('#iconoemitenc').addClass('fa-times');
        }
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

   function agregaRenglonSeguimiento(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-seguimiento').html();

    	$("#tbody-tabla-seguimiento").append(renglon);
        activa_eventos(false);
    }

    function borraRenglonSeguimiento(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function actualizaSeguimiento(elem) {
    	var item = 1;

    	$("#tbody-tabla-seguimiento .iiseguimiento").each(function() {
    		$(this).val(item++);
    	});
	}

   function agregaRenglonArticuloSuspendido(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-articulo-suspendido').html();

    	$("#tbody-tabla-articulo-suspendido").append(renglon);
        activa_eventos(false);

        $('#articulo-suspendido-table').find('tr').last().find('.codigoarticulo').focus();
    }

    function borraRenglonArticuloSuspendido(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function actualizaArticuloSuspendido(elem) {
    	var item = 1;

    	$("#tbody-tabla-articulo-suspendido .iiarticulo-suspendido").each(function() {
    		$(this).val(item++);
    	});
	}    

    function agregaRenglonCm05(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-cm05').html();

    	$("#tbody-tabla-cm05").append(renglon);
        activa_eventos(false);

        $('#cm05-table').find('tr').last().find('.codigoprovincia').focus();
    }

    function borraRenglonCm05(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function actualizaCm05(elem) {
    	var item = 1;

    	$("#tbody-tabla-cm05 .iicm05").each(function() {
    		$(this).val(item++);
    	});
	}    
