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

        // --- Alerta de descripciones similares (duplicados) solo en alta ---
        var articuloSimilaresEstado = {
            ultimaBusqueda: '',
            aceptada: '',
            pendientesSubmit: false,
            minLen: 5,
        };

        function articuloSimilaresUrl() {
            var el = document.getElementById('articulo-buscar-similares-descripcion-url');
            if (el && el.value) {
                return el.value;
            }
            if (typeof carpetaBase !== 'undefined' && carpetaBase) {
                return String(carpetaBase).replace(/\/$/, '') + '/stock/articulo/buscar-similares-descripcion';
            }
            return '/stock/articulo/buscar-similares-descripcion';
        }

        function articuloEsAlta() {
            return $('#form-general').length > 0
                && $('#articulo-buscar-similares-descripcion-url').length > 0
                && (!$('#articulo_id').length || !$('#articulo_id').val());
        }

        function articuloRenderSimilares(articulos) {
            var $tbody = $('#tbody-articulo-similares-descripcion');
            $tbody.empty();
            (articulos || []).forEach(function (row) {
                var consultar = '';
                if (row.url_consultar) {
                    consultar = '<a class="btn btn-info btn-sm" href="' + row.url_consultar +
                        '" target="_blank" rel="noopener">Consultar</a>';
                }
                var estado = row.estado || '';
                var badge;
                if (estado === 'ACTIVO') {
                    badge = '<span class="badge badge-success">ACTIVO</span>';
                } else if (estado === 'PENDIENTE') {
                    badge = '<span class="badge badge-warning">PENDIENTE</span>';
                } else if (estado === 'RECHAZADO') {
                    badge = '<span class="badge badge-danger">RECHAZADO</span>';
                } else {
                    badge = '<span class="badge badge-secondary">' + $('<div>').text(estado).html() + '</span>';
                }
                $tbody.append(
                    '<tr>' +
                    '<td>' + (row.id || '') + '</td>' +
                    '<td>' + $('<div>').text(row.sku || '').html() + '</td>' +
                    '<td>' + $('<div>').text(row.descripcion || '').html() + '</td>' +
                    '<td>' + badge + '</td>' +
                    '<td class="text-center text-nowrap">' + consultar + '</td>' +
                    '</tr>'
                );
            });
        }

        function articuloMostrarModalSimilares(descripcion, articulos) {
            $('#articulo-similares-descripcion-buscada').text(descripcion);
            articuloRenderSimilares(articulos);
            $('#articuloSimilaresDescripcionModal').modal('show');
        }

        function articuloBuscarSimilaresDescripcion(descripcion, opciones) {
            opciones = opciones || {};
            descripcion = String(descripcion || '').trim();

            if (!articuloEsAlta() || descripcion.length < articuloSimilaresEstado.minLen) {
                if (typeof opciones.onVacio === 'function') {
                    opciones.onVacio();
                }
                return;
            }

            if (descripcion === articuloSimilaresEstado.aceptada) {
                if (typeof opciones.onAceptada === 'function') {
                    opciones.onAceptada();
                }
                return;
            }

            if (descripcion === articuloSimilaresEstado.ultimaBusqueda
                && !opciones.forzar
                && typeof opciones.onMismaBusqueda === 'function') {
                opciones.onMismaBusqueda();
                return;
            }

            articuloSimilaresEstado.ultimaBusqueda = descripcion;

            $.ajax({
                url: articuloSimilaresUrl(),
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        || $('input[name="_token"]').val(),
                },
                data: { descripcion: descripcion },
            })
                .done(function (resp) {
                    if (resp && resp.min_len) {
                        articuloSimilaresEstado.minLen = parseInt(resp.min_len, 10) || 5;
                    }
                    var lista = (resp && resp.articulos) ? resp.articulos : [];
                    if (lista.length === 0) {
                        articuloSimilaresEstado.aceptada = descripcion;
                        if (typeof opciones.onVacio === 'function') {
                            opciones.onVacio();
                        }
                        return;
                    }
                    articuloMostrarModalSimilares(descripcion, lista);
                    if (typeof opciones.onSimilares === 'function') {
                        opciones.onSimilares(lista);
                    }
                })
                .fail(function () {
                    if (typeof opciones.onError === 'function') {
                        opciones.onError();
                    }
                });
        }

        $('#descripcion').on('change', function () {
            let descripcion = $(this).val();
            $(".descripcion").val(descripcion);
        });

        $('#descripcion').on('blur', function () {
            if (!articuloEsAlta()) {
                return;
            }
            var descripcion = String($(this).val() || '').trim();
            articuloBuscarSimilaresDescripcion(descripcion);
        });

        $('#btn-continuar-apesar-similares').on('click', function () {
            var desc = String($('#descripcion').val() || '').trim();
            articuloSimilaresEstado.aceptada = desc;
            $('#articuloSimilaresDescripcionModal').modal('hide');
            if (articuloSimilaresEstado.pendientesSubmit) {
                articuloSimilaresEstado.pendientesSubmit = false;
                var form = document.getElementById('form-general');
                if (form) {
                    form.submit();
                }
            }
        });

        $('#articuloSimilaresDescripcionModal').on('hidden.bs.modal', function () {
            if (articuloSimilaresEstado.pendientesSubmit
                && articuloSimilaresEstado.aceptada !== String($('#descripcion').val() || '').trim()) {
                articuloSimilaresEstado.pendientesSubmit = false;
            }
        });

        $('#form-general').on('submit', function (e) {
            if (!articuloEsAlta()) {
                return;
            }
            var desc = String($('#descripcion').val() || '').trim();
            if (desc.length < articuloSimilaresEstado.minLen) {
                return;
            }
            if (desc === articuloSimilaresEstado.aceptada) {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();
            articuloSimilaresEstado.pendientesSubmit = true;
            articuloBuscarSimilaresDescripcion(desc, {
                forzar: true,
                onVacio: function () {
                    articuloSimilaresEstado.pendientesSubmit = false;
                    articuloSimilaresEstado.aceptada = desc;
                    $('#form-general')[0].submit();
                },
                onAceptada: function () {
                    articuloSimilaresEstado.pendientesSubmit = false;
                    $('#form-general')[0].submit();
                },
                onError: function () {
                    articuloSimilaresEstado.pendientesSubmit = false;
                    $('#form-general')[0].submit();
                },
            });
            return false;
        });

        $('#sku').on('change', function () {
            let sku = String($(this).val() || '').trim();

            $(".sku").val(sku);

            if (sku === '') {
                return;
            }

            // Evita códigos SKU repetidos
            let url = carpetaBase + '/stock/leerunarticuloporsku/' + encodeURIComponent(sku);

            $.get(url, function (articulo) {
                if (articulo && articulo.id > 0) {
                    alert(
                        'Ya existe un artículo con el SKU "' + sku + '" (ID ' + articulo.id +
                        '). Descripción: ' + (articulo.descripcion || '') +
                        '\n\nNo se pueden crear códigos repetidos. Elija otro SKU.'
                    );
                    $("#sku").val('');
                    $(".sku").val('');
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

    function tipoArticuloIdPorNombre(nombre) {
        var buscado = $.trim(nombre || '').toUpperCase();
        var id = '';
        $('#tipoarticulo_id option').each(function () {
            if ($.trim($(this).text()).toUpperCase() === buscado) {
                id = $(this).val();
                return false;
            }
        });
        return id;
    }

    function aplicarTipoIndumentariaDesdeCategoria() {
        var nombreCat = $.trim($('#categoria_id option:selected').text() || '');
        if (!/INDUMENTARIA/i.test(nombreCat)) {
            return;
        }
        var tipoServicioId = tipoArticuloIdPorNombre('SERVICIO');
        var tipoActual = $('#tipoarticulo_id').val();
        if (tipoServicioId && tipoActual === tipoServicioId) {
            return;
        }
        var tipoIndumentariaId = tipoArticuloIdPorNombre('INDUMENTARIA');
        if (tipoIndumentariaId) {
            $('#tipoarticulo_id').val(tipoIndumentariaId);
        }
    }

    $(document).on('change', '#categoria_id', aplicarTipoIndumentariaDesdeCategoria);

