
    var ptrriesgo;

    $(function () {
        $("#botonestado").click(function(){

            var estado = $("#estado").val();
			var descripcion = $("#botonestado").text();

			if (estado == '0')
			{
				estado = '1';
				descripcion = 'Suspendido';
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
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Datos facturac&oacute;n");
        });

        $("#botonform3").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").show();
            $(".form4").hide();
            $(".form5").hide();

	        $("#tbody-tabla .localidades").each(function(index) {
            	var provincia = $(this).parents("tr").find(".provincias");
            	var localidad = $(this).parents("tr").find(".localidades");
	
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
        });

        $("#botonform5").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").show();
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

        // Activa campos de sujeto obligado
        $('#so_uif_id').on('change', function (event) {
			event.preventDefault();

            chequeaSujetoObligado();
		});
        // Muestra tipo de suspension
        muestraTipoSuspension();
        
        $('#agrega_renglon_riesgo').on('click', agregaRenglonRiesgo);
        $(document).on('click', '.eliminar_riesgo', borraRenglonRiesgo);
        $('#agrega_renglon_premio').on('click', agregaRenglonPremio);
        $(document).on('click', '.eliminar_premio', borraRenglonPremio);
        $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);

        activa_eventos(true);
        activa_eventos_consultaactividad_uif();
        chequeaSujetoObligado();

        // Pone en timeout para darle tiempo a refrescar las localidades
        setTimeout(() => {
            verificaAlertaUif();
        }, 3000);

        var inputArchivo = document.getElementById('fotodocumento');

        inputArchivo.addEventListener("change", function() {
        let nombreArchivo = this.files[0].name;
        let archivoSeleccionado = document.getElementById('archivoseleccionado');
        if (this.value != "") {
            archivoSeleccionado.innerHTML = nombreArchivo
        } else {
            archivoSeleccionado.innerHTML = ''
        }
        });        
    });
	
    function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
            $('.periodo').off('change');
            $('.inusualidad_uif').off('change');
		}

        $('.periodo').datepicker( {
            changeMonth: true,
            changeYear: true,
            minViewMode: "months",
        });   

        $('.periodo').on('change', function (event) {
			event.preventDefault();
            $(this).parents('tr').find('.inusualidad_uif').focus();
            let periodo = $(this).parents('tr').find('.periodo').val();
            let inusualidad_uif = $(this).parents('tr').find('.inusualidad_uif').val();
            let fecha = new Date($(this).val());
            let mes = fecha.getMonth() + 1;
            let anio = fecha.getFullYear();

            ptrriesgo = $(this).parents('tr').find('.riesgo');

            if (mes >= 1)
                $(this).val(mes+"/"+anio);

            calculaRiesgo(periodo, inusualidad_uif);
		});

        $('.inusualidad_uif').on('change', function (event) {
			event.preventDefault();
            let periodo = $(this).parents('tr').find('.periodo').val();
            let inusualidad_uif = $(this).parents('tr').find('.inusualidad_uif').val();
            ptrriesgo = $(this).parents('tr').find('.riesgo');

            calculaRiesgo(periodo, inusualidad_uif);
		});        
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

    function agregaRenglonPremio(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-premio').html();

    	$("#tbody-tabla-premio").append(renglon);
    	actualizaRenglonesPremio();
    }

    function borraRenglonPremio(event) {
    	event.preventDefault();
        let id = $(this).parents('tr').find('.premio_id').val();

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    
        let url = "/anitaERP/public/uif/elimina_premio_uif";

        $.ajax({
            type: "POST",
            url: url,
            data: {
                id: id
            },
            success: function (data) {
                if (data.mensaje == 'ok')
                {
                    alert("Premio borrado con éxito");
                }
                location.reload();
            },
            error: function (r) {
                alert("No se pudo borrar el premio");
            }
        });

    	//$(this).parents('tr').remove();
    	//actualizaRenglonesPremio();
    }

    function actualizaRenglonesPremio() {
    	var item = 1;

    	$("#tbody-tabla-premio .iipremio").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonRiesgo(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-riesgo').html();

    	$("#tbody-tabla-riesgo").append(renglon);
    	actualizaRenglonesRiesgo();

        activa_eventos(false);
    }

    function borraRenglonRiesgo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesRiesgo();
    }

    function actualizaRenglonesRiesgo() {
    	var item = 1;

    	$("#tbody-tabla-riesgo .iiriesgo").each(function() {
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

    function chequeaSujetoObligado()
    {
        let so_uif_id = $("#so_uif_id").val();

        if (so_uif_id != 2)
        {
            $("#div-actividadso").hide();
            $("#div-cumplenormativaso").hide();
        }
        else
        {
            $("#div-actividadso").show();
            $("#div-cumplenormativaso").show();      
        }
    }

    function verificaAlertaUif()
    {
        let fechaActual = new Date();
        let fecha6Meses = new Date(fechaActual.setMonth(fechaActual.getMonth() - 6));
        let firmodeclaracionjurada = $('#firmodeclaracionjurada').val();
        let fechaConfirmaPep = $('#fechaconfirmapep').val();
        let fechaVencimientoDni = $('#fechavencimientodni').val();
        let fechaVencimientoActividad = $('#fechavencimientoactividad').val();
        let fechaInformeNosis = $('#fechainformenosis').val();
        let fechaInformePep = $('#fechainformepep').val();
        let riesgoPep = $('#riesgopep').val();
        let esSupervisor = $('#essupervisor').val();

        if (Date.parse(fechaConfirmaPep) < Date.parse(fecha6Meses))
        {
            alert('DEBE FIRMAR PEP\nULTIMA VALIDACION: '+ formateaFecha(fechaConfirmaPep));

            // Cambia estilo
            $('#div-fechafirmapep').css("color", "red");
            $('#div-fechaconfirmapep').css("color", "red");
        }

        if (Date.parse(fechaVencimientoDni) < new Date())
        {
            alert('DEBE RENOVAR DNI\nVENCIMIENTO: '+ formateaFecha(fechaVencimientoDni));

            // Cambia estilo
            $('#div-fechavencimientodni').css("color", "red");            
        }

        if (Date.parse(fechaVencimientoActividad) < Date.parse(fecha6Meses))
        {
            alert('DEBE RENOVAR ACTIVIDAD\nVENCIMIENTO: '+ formateaFecha(fechaVencimientoActividad));

            // Cambia estilo
            $('#div-fechavencimientoactividad').css("color", "red");            
        }

        if (firmodeclaracionjurada != 'S')
        {
            alert('DEBE FIRMAR DECLARACION JURADA DE ORIGEN DE INGRESOS Y/O FONDOS');

            // Cambia estilo
            $('#div-firmodeclaracionjurada').css("color", "red");
        }

        if (riesgoPep == 'ALTO')
        {
            alert('NIVEL DE RIESGO ALTO');

            // Cambia estilo
            $('#div-riesgopep').css("color", "red");            
        }

        // Alertas solo para supervisores
        if (esSupervisor == 'S')
        {
            if (Date.parse(fechaInformeNosis) < Date.parse(fecha6Meses))
            {
                alert('DEBE FIRMAR INFORME NOSIS\nVENCIMIENTO: '+ formateaFecha(fechaInformeNosis));

                // Cambia estilo
                $('#div-fechainformenosis').css("color", "red");            
            }

            if (Date.parse(fechaInformePep) < Date.parse(fecha6Meses))
            {
                alert('DEBE FIRMAR INFORME PEP\nVENCIMIENTO: '+ formateaFecha(fechaInformePep));

                // Cambia estilo
                $('#div-fechainformepep').css("color", "red");            
            }
        }
    }

    function calculaRiesgo(periodo, inusualidad_uif_id)
    {
        let tamano = periodo.length;
        let cliente_uif_id = $('#cliente_uif_id').val();
        let periodoSinBarras = periodo.replace(/\//g, '');
        var numeroPeriodo = parseInt(periodoSinBarras);

        if (tamano == 6 || tamano == 7)
        {
			let url_cta = '/anitaERP/public/uif/calculariesgo_uif/'+cliente_uif_id+'/'+numeroPeriodo+'/'+inusualidad_uif_id;

			$.get(url_cta, function(data){
                ptrriesgo.val(data.riesgo);
            });
        }
    }