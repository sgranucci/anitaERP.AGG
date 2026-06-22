
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

        $("#tipodocumento_id").change(function(){
            var tipodocumento_id = $(this).val();
            var texto = $("#tipodocumento_id option:selected").text();

            if (texto == "CUIT")
            {
                var $nro = $('#numerodocumento');
                $nro.attr('placeholder', 'XX-XXXXXXXX-X');

                if ($nro.val() && typeof formatarCUIT === 'function') {
                    formatarCUIT($nro[0]);
                }

                $nro.off('input.cuitFormat').on('input.cuitFormat', function() {
                    formatarCUIT(this);
                });
            }
            else
            {
                $('#numerodocumento').removeAttr('placeholder');

                $('#numerodocumento').off('input.cuitFormat');
            }

            $('#numerodocumento').focus();
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

        $('#tabs-cliente a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');

            if (target === '#tab-lugares-entrega') {
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
            }

            if (target === '#tab-leyendas' || target === '#tab-seguimiento') {
                $("#leyenda").focus();
            }

            if (target === '#tab-articulos-suspendidos') {
                $('#articulo-suspendido-table').find('tr').last().find('.codigoarticulo').focus();
            }

            if (target === '#tab-cm05') {
                $('#cm05-table').find('tr').last().find('.codigoprovincia').focus();
            }
        });
	                     
        muestraEmiteNotaDeCredito();

        $("#botonemitenc").click(function(){
            let cliente_id = $('#cliente_id').val();
            let url = carpetaBase+'/ventas/cliente/emitenc/'+cliente_id;

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

        $('#nombre').on('input', function() {
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
        $(document).on('click', '.eliminar-archivo-cliente', borraTarjetaArchivoCliente);
        $('#agrega_renglon_seguimiento').on('click', agregaRenglonSeguimiento);
        $(document).on('click', '.eliminar_seguimiento', borraRenglonSeguimiento);
        $('#agrega_renglon_articulo_suspendido').on('click', agregaRenglonArticuloSuspendido);
        $(document).on('click', '.eliminar_articulo_suspendido', borraRenglonArticuloSuspendido);        
        $('#agrega_renglon_cm05').on('click', agregaRenglonCm05);
        $(document).on('click', '.eliminar_cm05', borraRenglonCm05);
        $(document).on('blur', '#cm05-table .coeficiente', formatearCoeficienteCm05Input);
        aplicarFormatoCoeficientesCm05();
        $('#form-general').on('submit', aplicarFormatoCoeficientesCm05);
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
        activa_eventos_consultazonavta();
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

    function agregaRenglonArchivo(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-archivo').html();

    	$("#tbody-tabla-archivo").append(renglon);
        activa_eventos(false);
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function borraTarjetaArchivoCliente(event) {
        event.preventDefault();
        var $wrap = $(this).closest('.cliente-archivo-item');
        if ($wrap.length) {
            $wrap.remove();
            return;
        }
        $(this).closest('.col-md-6').remove();
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

    function formatearCoeficienteCm05(valor) {
        if (valor === '' || valor === null || typeof valor === 'undefined') {
            return '';
        }

        var texto = String(valor).trim().replace(',', '.');
        if (texto === '') {
            return '';
        }

        var num = parseFloat(texto);
        if (isNaN(num)) {
            return valor;
        }

        if (num < 0) {
            num = 0;
        } else if (num > 100) {
            num = 100;
        }

        return num.toFixed(4);
    }

    function formatearCoeficienteCm05Input() {
        var formateado = formatearCoeficienteCm05($(this).val());
        if (formateado !== '') {
            $(this).val(formateado);
        }
    }

    function aplicarFormatoCoeficientesCm05() {
        $('#cm05-table .coeficiente').each(function() {
            var formateado = formatearCoeficienteCm05($(this).val());
            if (formateado !== '') {
                $(this).val(formateado);
            }
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
