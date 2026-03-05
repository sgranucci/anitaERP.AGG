// Scripts para carga de pedidos

	var talles_txt;
	var medidas_txt;
	var precios_txt;
	var tallesid_txt;
	var cantidadmodal_txt;
	var nombre_modulo;
	var moduloElegido_id;
	var articuloxsku;
	var pedido_combinacion;
	var descripcion_articulo;
	var nombre_combinacion;
	var tbl_medidas;
	var medidas=[];
	var cantidades=[];
	var cantidades_por_modulo=[];
	var precios=[];
	var dpr=[];
	var dlp=[];
	var dii=[];
	var dmo=[];
	var totPares;
	var cantidad;
	var precio;
	var flAnulacionItem = false;
	var itemAnulacion;
	var itemAnulacionId;
	var botonAnulacion;
	var fl_tiene_entrega = false;
	var modulo_actual = 1;
	var codigoAnulacionOt;
	var idAnulacionOt;
	var motivoAnulacionOt
	var nombreClienteAnulacionOt;
	var itemAnulacionOt;
	var flFactura;
	var pedido_combinacion_ids=[];
	var cliente_id;
	var modulo_actual = 1;
	var ordentrabajo_ids=[];
	var nombrecliente;
	var tallesfactura_txt=[];
	var medidasfactura_txt=[]; 
	var preciosfactura_txt=[]; 
	var tallesidfactura_txt=[];
	var titulofactura_txt=[];
	var offFactura;
	var modalActivo;
	var descuentoCliente;
	
	function completarCliente_Entrega(cliente_id){
        var loc_id;
		var lugarentrega = $("#lugarentrega").val();
        $.get('/anitaERP/public/ventas/leercliente_entrega/'+cliente_id, function(data){
            var entr = $.map(data, function(value, index){
                return [value];
            });
            $("#cliente_entrega_id").empty();
            $("#cliente_entrega_id").append('<option value=""></option>');
            fl_tiene_entrega = false;
            $.each(entr, function(index,value){
				if (value.nombre != lugarentrega)
				{
                	$("#cliente_entrega_id").append('<option value="'+value.id+'">'+value.nombre+'</option>');
				}
				else
				{
                	$("#cliente_entrega_id").append('<option value="'+value.id+'" selected>'+value.nombre+'</option>');
				}
                fl_tiene_entrega = true;
            });
            if (fl_tiene_entrega)
            {
              $("#divcodigoentrega").show();
              $("#divlugar").hide();
            }
            else
            {
              $("#divcodigoentrega").hide();
              $("#divlugar").show();
            }
        });
        setTimeout(() => {
        }, 3000);
    }

    function completarCombinaciones(articulo, combinacion_id, flsinfiltro){
        var comb_id;
		var articulo_id = $(articulo).val();
		var fl_todas_las_combinaciones = $(articulo).parents("tr").find('input:checkbox[class=checkCombinacion]:checked').val();
		var fl_todos_los_articulos = $(articulo).parents("tr").find('input:checkbox[class=checkSinFiltro]:checked').val();

		// Si marca boton de todas las combinaciones trae sin filtrar las activas o esta leyendo todos los articulos sin filtrar
		if (fl_todas_las_combinaciones == 'on' || fl_todos_los_articulos == 'on' || flsinfiltro)
			var url_comb = '/anitaERP/public/stock/leercombinaciones/';
		else
			var url_comb = '/anitaERP/public/stock/leercombinacionesactivas/';

        $.get(url_comb+articulo_id, function(data){
            var comb = $.map(data, function(value, index){
                return [value];
            });
            $(articulo).parents("tr").find('.combinacion').empty();
            $(articulo).parents("tr").find('.combinacion').append('<option value=""></option>');
            $.each(comb, function(index,value){
				if (value.id == combinacion_id)
                	$(articulo).parents("tr").find('.combinacion').append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
				else
                	$(articulo).parents("tr").find('.combinacion').append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
            });
        });
        setTimeout(() => {
                var comb_id = $("#combinacion_id").val();
                if (comb_id != undefined) {
                    completarModulos(comb_id, 0);
                }
        }, 3000);
    }

    function completarModulos(articulo, modulo_id){
        var comb_id;
		var eligioModulo = false;
		var articulo_id = $(articulo).val();
		var flTieneModuloAbierto = false;
        $.get('/anitaERP/public/stock/leermodulos/'+articulo_id+'/'+modulo_id, function(data){
            var mod = $.map(data, function(value, index){
                return [value];
            });
            $(articulo).parents("tr").find('.modulo').empty();
            $(articulo).parents("tr").find('.modulo').append('<option value=""></option>');
			flTieneModuloAbierto = false;
            $.each(mod, function(index,value){
			  	if (value.id == 30)
				  	flTieneModuloAbierto = true;

				if (value.id == modulo_id)
				{
                	$(articulo).parents("tr").find('.modulo').append('<option value="'+value.id+'" selected>'+value.nombre+'</option>');
					eligioModulo = true;
				}
				else
                	$(articulo).parents("tr").find('.modulo').append('<option value="'+value.id+'">'+value.nombre+'</option>');
            });

			// Agrega modulo abierto
			if (!flTieneModuloAbierto)
            	$(articulo).parents("tr").find('.modulo').append('<option value="'+'30'+'">'+'Abierto'+'</option>');
        });
    }

	function completarTalles(modulo_id, ptrcheck, medidas, cantidades, precios)
	{
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
		nombre_modulo = "";

		// Lee talles del modulo
        $.get('/anitaERP/public/stock/leertalles/'+modulo_id, function(data){
			var flEncontro, flHayMedidas;

           	var tall = $.map(data, function(value, index){
               	return [value];
           	});
			talles_txt = "<table class='table-bordered table-striped'><tr>";
			medidas_txt = "<tr>";
			precios_txt = "<tr>";
			tallesid_txt = "<tr>";

			// Arma variables modal
			cantidadmodal_txt = " autofocus ";
           	$.each(tall, function(index,value){
				nombre_modulo = value.nombre;
				for (var t in value.talles) {
					flEncontro = false;
					flHayMedidas = false;
					
					for (let s in medidas) 
					{
						flHayMedidas = true;
						
						if (parseFloat(value.talles[t].id) === parseFloat(medidas[s]))
						{
							var cant = parseFloat(cantidades[s]);
							var prec = parseFloat(precios[s]);
							
							// Calcula modulo actual
							if (value.talles[t].pivot.cantidad != 0) 
							{
								cantidades_por_modulo[s] = value.talles[t].pivot.cantidad;
							
								modulo_actual = cant / value.talles[t].pivot.cantidad;
							}

							agregaMedida(value.talles[t].nombre, cant, prec, value.talles[t].id);
							flEncontro = true;
							break;
						}
					}
					if (!flEncontro)
					{
						if (flHayMedidas)
							agregaMedida(value.talles[t].nombre, '', 0, value.talles[t].id);
						else
							agregaMedida(value.talles[t].nombre, (value.talles[t].pivot.cantidad == 0 ? '' : value.talles[t].pivot.cantidad), 0, value.talles[t].id);
					}
				}
			});
			talles_txt = talles_txt + "</tr>";
			medidas_txt = medidas_txt + "</tr>";
			precios_txt = precios_txt + "</tr>";
			tallesid_txt = tallesid_txt + "</tr>";

			if (flFactura)
			{
				tallesfactura_txt[offFactura] = talles_txt;
				medidasfactura_txt[offFactura] = medidas_txt;
				preciosfactura_txt[offFactura] = precios_txt;
				tallesidfactura_txt[offFactura] = tallesid_txt;

				let descripcion_art = $(ptrcheck).parents("tr").find(".articulo option:selected").text();
				let nombre_comb = $(ptrcheck).parents("tr").find(".desc_combinacion").val();
				titulofactura_txt[offFactura] = descripcion_art+" "+nombre_comb;

				offFactura = offFactura + 1;
			}
		});
	}

	function agregaMedida(Ptalle, Pcant, Pprec, Ptalle_id)
	{
		let nombre = "";

    	talles_txt = talles_txt + "<th><input name='medidasportalles[]' class='medidasportalles' style='width:30px; text-align:center; background-color   : #D2D8DC;' type='text' readonly value='"+Ptalle+"'></input></th>";

		if (!flAnulacionItem)
			nombre = "cantidadesportalles";
		else
			nombre = "cantidadesportallesa";
		
		medidas_txt = medidas_txt + "<th><input name='"+nombre+"[]' "+cantidadmodal_txt+" class='"+nombre+"' style='width:30px;' type='text' value='"+Pcant+"'></input></th>";

    	precios_txt = precios_txt + "<th><input name='preciosportalles[]' class='preciosportalles' type='hidden' value='"+Pprec+"'></input></th>";
    	tallesid_txt = tallesid_txt + "<th><input name='tallesid[]' class='tallesid' type='hidden' value='"+Ptalle_id+"'></input></th>";
		cantidadmodal_txt = "";
	}

	function asignaPrecio(Particulo_id, Ptalle_id)
	{
		// Lee talles del modulo
        $.get('/anitaERP/public/stock/asignaprecio/'+Particulo_id+'/'+Ptalle_id, function(data){
           	var prec = $.map(data, function(value, index){
               	return [value];
           	});
			dpr=[];
			dlp=[];
			dii=[];
			dmo=[];
           	$.each(prec, function(index,value){
				dpr.push(value.precio);
				dlp.push(value.listaprecio_id);
				dii.push(value.incluyeimpuesto);
				dmo.push(value.moneda_id);
			});
		});
        setTimeout(() => {
			return(precio);
        }, 300);
	}

    $(function () {
		var articulo_id;
		var combinacion_id;
		var modulo_id;

		// Completa combinaciones y modulos al abrir pedido
		$("#tbody-tabla .articulo").each(function(index) {
			var articulo = $(this);
			var combinacion = $(this).parents("tr").find(".combinacion").val();
			var combinacion_id = $(this).parents("tr").find(".combinacion_id_previa").val();
			var modulo_id = $(this).parents("tr").find(".modulo_id_previa").val();

        	completarCombinaciones(articulo, combinacion_id, true);
        	completarModulos(articulo, modulo_id);
		});

		// Marca items como facturados, completa combinaciones y modulos al abrir pedido
		marcaItemFacturado();
		activa_eventos(true);
		TotalParesPedido();
	});

	function marcaItemFacturado()
	{
		// Completa combinaciones y modulos al abrir pedido
		$("#tbody-tabla .otcodigo").each(function(index) {
			let ordentrabajo = $(this).val();
			let ot = this;
			let tilde = $(this).parents("tr").find(".checkImpresion");
			let pedido_combinacion_id = $(this).parents("tr").find(".ids").val();
			let articulo = $(this).parents("tr").find(".articulo");
			let combinacion = $(this).parents("tr").find(".combinacion");

			if (ordentrabajo > 0)
			{
				articulo.prop('disabled', 'disabled');
				combinacion.prop('disabled', 'disabled');
			}
			else
			{
				articulo.prop('disabled', false);
				combinacion.prop('disabled', false);
			}
			
			// Busca si tiene factura asociada
			var listarUri = "/anitaERP/public/ventas/estadoot/"+ordentrabajo+"/"+pedido_combinacion_id;
		
			$.get(listarUri, function(data){
				
				if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
				{
					$(ot).css("background-color","red");
						$(ot).css("font-weight","900");

					$(tilde).prop("checked",false);
				}
			});
		});
	}

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.articulo').off('click');
			$('.articulo').off('change');
        	$(".modulo").off('change');
			$(".checkImpresion").off('change');
        	$(".cantidad").off('click keydown');
    		$('.consultaSKU').off('click');
			$('#aceptaarticuloxskuModal').off('click');
			$('#medidasModal').off('show.bs.modal');
			$('#cierraModal').off('click');
			$('#aceptaModal').off('click');
			$('#medidasModal').off('hidden.bs.modal');
        	$('#aceptaOrdenTrabajoModal').off('click');
			$('#lote_id').off('change');
			$(document).off('change', '.desc_combinacion');
			$(document).off('change', '.desc_modulo');
			$(document).off('change', '.cantidadesportalles');
		}

		$('.articulo').on('click', function (event) {

			armaSelectArticulo(this, this, 1);

		});

		$('.articulo').on('change', function (event) {
			event.preventDefault();
			var articulo = $(this);
			var articulo_ant = $(this).parents("tr").find(".articulo_id_previo").val();
			var articulo_nuevo = articulo.val();

			if (articulo_nuevo != articulo_ant)
			{
            	completarCombinaciones(articulo, 0, false);
            	completarModulos(articulo, 0);

				//* Asigna nuevo articulo
				$(this).parents("tr").find(".articulo_id_previo").val(articulo_nuevo);
			}
        });

		$('.checkImpresion').on('change', function (event) {
			event.preventDefault();
			
			if (flFactura && $(this).prop("checked"))
			{
				let ordentrabajo = $(this).parents("tr").find(".otcodigo").val();
				let tilde = this;
				let cliente_id = $("#cliente_id").val();
				let estadocliente = $("#estadocliente").va/czl();
				let tiposuspensioncliente_id = $("#tiposuspensioncliente_id").val();
				let nombretiposuspensioncliente = $("#nombretiposuspensioncliente").val();
				let pedido_combinacion_id = $(this).parents("tr").find(".ids").val();
			
				// No deja factura cliente stock
				if (cliente_id == CLIENTE_STOCK_ID)
				{
					alert("No puede facturar cliente STOCK");
					$(tilde).prop("checked",false);
					return;
				}

				// Debe chequear estado del cliente
				if (estadocliente > '0' && 
					(tiposuspensioncliente_id == PROFORMA ||
					tiposuspensioncliente_id == MOROSO ||
					tiposuspensioncliente_id == NO_FACTURAR
					))
				{
					alert("No puede facturar cliente en estado "+nombretiposuspensioncliente);
					$(tilde).prop("checked",false);
					return;
				}
			
				// chequea si puede facturar
				if (ordentrabajo <= 0)
				{
					alert('No puede facturar porque no tiene OT generada');
					$(tilde).prop("checked",false);
					return;
				}

				// Busca si tiene factura asociada
				var listarUri = "/anitaERP/public/ventas/estadoot/"+ordentrabajo+"/"+pedido_combinacion_id;
            
				$.get(listarUri, function(data){
					
					if (data.numerofactura == -3)
					{
						alert("OT no está terminada");

						$(tilde).prop("checked",false);
						return;						
					}
					if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
					{
						alert("OT ya facturada "+data.numerofactura);
						$(tilde).prop("checked",false);
						return;
					}
				});
			}
        });

    	$('.consultaSKU').on('click', function (event) {
        	articuloxsku = $(this).parents("tr").find(".articulo");

        	// Abre modal de consulta
        	$("#articuloxskuModal").modal('show');
	
			var selectxsku = $("#articuloxsku_id");
        	armaSelectArticulo(selectxsku, articuloxsku, 2);
    	});

    	$('#aceptaarticuloxskuModal').on('click', function () {
        	var articulo_id = $('#articuloxsku_id').val();

        	$(articuloxsku).val(articulo_id);

        	armaSelectArticulo(articuloxsku, articuloxsku, 2);

            completarCombinaciones(articuloxsku, 0, false);
            completarModulos(articuloxsku, 0);

			//* Asigna nuevo articulo
			$(this).parents("tr").find(".articulo_id_previo").val($(articuloxsku).val());
	
        	$('#articuloxskuModal').modal('hide');
    	});

        $(".modulo").on('change', function() {
			modulo_id = $(this).parents("tr").find(".modulo").val();
		  	moduloElegido_id = modulo_id;

			// Blanquea medidas
			$(this).parents("tr").find(".medidas").val("");
		});

		// Con click sobre cantidad abre modal de medidas
        $(".cantidad").on('click keydown', function() {
			cantidad = $(this);

			articulo_id = $(this).parents("tr").find(".articulo").val();
			descripcion_articulo = $(this).parents("tr").find(".articulo option:selected").text();
			modulo_id = $(this).parents("tr").find(".modulo").val();
			combinacion_id = $(this).parents("tr").find(".combinacion").val();
			nombre_combinacion = $(this).parents("tr").find(".combinacion option:selected").text();

			// Lee tabla de medidas
			var val_medida = $(this).parents("tr").find(".medidas").val();

			medidas=[];
			cantidades=[];
			precios=[];

			if (val_medida != '')
			{
				var tbl_medidas = JSON.parse(val_medida);

           		$.each(tbl_medidas, function(index,value){
					medidas.push(value.talle_id);
					cantidades.push(value.cantidad);
					precios.push(value.precio);
				});
			}

			completarTalles(modulo_id, 0, medidas, cantidades, precios);

        	setTimeout(() => {
				$("#medidasModal").modal('show');
			}, 300);
        });

		// Controla apertura modal de medidas
		$('#medidasModal').on('show.bs.modal', function (event) {
  			var modal = $(this);
			modalActivo = "medidasModal";

  			modal.find('.modal-title').text('Medidas item '+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
  			modal.find('#medidasModal').empty();
  			modal.find('#medidasModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
			sumaPares(modalActivo, 'cantidadesportalles');
			muestraTotalPares();
		});

		// Autofocus en modal de medidas
		$(document).on('shown.bs.modal', '.modal', function() {
		  	// Si es modulo manual hace foco en cantidades 
		  	if (moduloElegido_id == 30)
  				$(this).find('[autofocus]').focus();
			else
  				$("#cantmodulo").focus();

			var _cant = 1;

			if (modulo_actual != 1)
        		$("#cantmodulo").val(modulo_actual);
			else
				$("#cantmodulo").val(_cant);

        	$("#cantmodulo").off('change');

			$("#cantmodulo").on('change', function () {

				// Multiplica por la cantidad de modulos a cada cantidad por talle
				$("#medidasModal .cantidadesportalles").each(function(index) {
					var cantidad = $(this).val();
					var cantmodulo = $("#cantmodulo").val();
					
					if (cantidad != '')
					{
						if (modulo_actual > 0 && modulo_actual != cantmodulo)
						{
							var cantidad_base = parseFloat(cantidad) / modulo_actual;
							var nueva_cantidad = cantidad_base * parseFloat(cantmodulo);
						}
						else	
							var nueva_cantidad = arseFloat(cantidad)*parseFloat(cantmodulo);

				  		$(this).val(nueva_cantidad);
						sumaPares(modalActivo, 'cantidadesportalles');
						muestraTotalPares();
					}
				});

			});

		});

	  	// Cierra modal medidas 
		$('#cierraModal').on('click', function () {
		});

		// Acepta modal de medidas
		$('#aceptaModal').on('click', function () {
		  	let jsonObject = new Array();

			med = [];
			$(".medidasportalles").each(function() {
            	med.push($(this).val());
			});
			talleid = [];
			$(".tallesid").each(function() {
            	talleid.push($(this).val());
			});
			cant = [];
			$(".cantidadesportalles").each(function() {
            	cant.push($(this).val());
			});
        	prec = []
        	$(".preciosportalles").each(function(){
            	prec.push($(this).val());
        	});

			let jsonTallesId = JSON.stringify(talleid); 

			asignaPrecio(articulo_id, jsonTallesId);

			off = 0;
		    var flError = false;
        	setTimeout(() => {
			for (let i in med) 
			{
				if (cant[i] == '')
					cant[i] = 0;
			  	jsonObject.push({
					medida: med[i],
				  	cantidad: cant[i],
				  	precio: dpr[i],
				  	listaprecio: dlp[i],
				  	incluyeimpuesto: dii[i],
				  	moneda: dmo[i],
				  	talle_id: talleid[i]
				});
			  	// Valida cantidades que tengan precio
			    if (cant[i] > 0 && dpr[i] == 0)
			  	{
					flError = true;	  	
					// Pedido por gaby 27/6 porque todos los articulos de la expo no tienen precio
				    //alert('Medida '+med[i]+' Cantidad '+cant[i]+' No tiene precio asignado');
			  	}
				if (dpr[i] > 0)
					off = i;		
			}

			let jsonString = JSON.stringify(jsonObject); 

			// Asigna medidas, cantidades y precios
			$(cantidad).parents('tr').find('.medidas').val(jsonString);

			// Asigna variables de precio
			var pre = fNumero(dpr[off], 2);
			var lis = fNumero(dlp[off], 0);
			var inc = fNumero(dii[off], 0);
			var mon = fNumero(dmo[off], 0);
			if (pre === 'NaN' || pre < 0 || pre > 9999999999)
			  	pre = 0;
	
			$(cantidad).parents('tr').find('.precio').val(pre);
			$(cantidad).parents('tr').find('.listaprecio_id').val(lis);
			$(cantidad).parents('tr').find('.incluyeimpuesto').val(inc);
			$(cantidad).parents('tr').find('.moneda_id').val(mon);
	
        	}, 300);

			$('#medidasModal').modal('hide');

			// Asigna total de pares a la cantidad del item en el formulario
			sumaPares(modalActivo, 'cantidadesportalles');
			muestraTotalPares();
			$(cantidad).val(totPares);
			TotalParesPedido();
		});

		$('#medidasModal').on('hidden.bs.modal', function () {

			// Inicializa variables modal
			talles_txt = "";
			medidas_txt = "";
			precios_txt = "";
			tallesid_txt = "";
		});

		// Llena variable desc_combinacion
		$(document).on('change', '.desc_combinacion', function(event) {
     		$(this).val($(".combinacion option:selected").text());
		});
		// Llena variable desc_modulo
		$(document).on('change', '.desc_modulo', function(event) {
     		$(this).val($(".modulo option:selected").text());
		});
		$(document).on('change', '.cantidadesportalles', function(event) {
			sumaPares(modalActivo, 'cantidadesportalles');
			muestraTotalPares();
		});

        // Acepta modal OT
        $('#aceptaOrdenTrabajoModal').on('click', function () {
            var leyenda = $("#leyendaot").val();
            var checkotstock = $("input:checkbox[class=checkboxotstock]:checked").val();
			var ordentrabajo_stock_codigo = $("#ordentrabajo_stock_codigo").val();
			var articulo_id = $(pedido_combinacion).parents('tr').find('.articulo').val();
			var combinacion_id = $(pedido_combinacion).parents('tr').find('.combinacion').val();
			var cantidad = $(pedido_combinacion).parents('tr').find('.cantidad').val();
			
			if (ordentrabajo_stock_codigo == '')
				ordentrabajo_stock_codigo = 0;

			// Elimina caracteres especiales de la leyenda
			var pattern = /[\^*@!"#$%&/,()=?¡!¿'\\]/gi;
			leyenda = leyenda.replace(pattern, ' ');

			if (ordentrabajo_stock_codigo > 0)
			{
				var listarUri = "/anitaERP/public/ventas/controlaordentrabajostock/"+ordentrabajo_stock_codigo+"/"+articulo_id+"/"+combinacion_id;

				$.get(listarUri, function(data){
					if (data.estado != -1)
					{
						alert("Saldo lote "+ordentrabajo_stock_codigo+" Saldo "+data.saldo+" Cantidad "+cantidad+" Deposito "+data.deposito_id);

						if (data.saldo < cantidad)
						{
							alert('No puede hacer la orden de trabajo porque no tiene saldo suficiente');
							return;
						}
						if (data.deposito_id < 1)
						{
							alert('No puede hacer la orden de trabajo porque no tiene deposito asignado el lote');
							return;							
						}
						$('#crearOrdenTrabajoModal').modal('hide');

						if (checkotstock == 'on')
							var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/pedido/"+
											$(pedido_combinacion).val()+"/on/"+ordentrabajo_stock_codigo+'/'+data.deposito_id+'/'+leyenda;
						else
							var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/pedido/"+
											$(pedido_combinacion).val()+"/off/"+ordentrabajo_stock_codigo+'/'+data.deposito_id+'/'+leyenda;
						$.get(listarUri, function(data){
							// Asigna ot id y nro. de orden 
							if (data.id > 0)
							{
								$(pedido_combinacion).parents('tr').find('.ot').val(data.id);
								$(pedido_combinacion).parents('tr').find('.otcodigo').val(data.nro_orden);
			
								alert("OT "+data.nro_orden+" creada con exito");
			
								$("#ordentrabajo_stock_codigo").val('');
							}
						});
					}
					else	
					{
						alert("Lote inexistente");
						return;
					}
				});
			}
			else
			{
				$('#crearOrdenTrabajoModal').modal('hide');

				if (checkotstock == 'on')
					var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/pedido/"
									+$(pedido_combinacion).val()+"/on/"+ordentrabajo_stock_codigo+'/1/'+leyenda;
				else
					var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/pedido/"
									+$(pedido_combinacion).val()+"/off/"+ordentrabajo_stock_codigo+'/1/'+leyenda;
	
				$.get(listarUri, function(data){
					// Asigna ot id y nro. de orden 
					if (data.id > 0)
					{
						$(pedido_combinacion).parents('tr').find('.ot').val(data.id);
						$(pedido_combinacion).parents('tr').find('.otcodigo').val(data.nro_orden);
	
						alert("OT "+data.nro_orden+" creada con exito");
	
						$("#ordentrabajo_stock_codigo").val('');
					}
				});
			}
		});

		// Asigna los lotes a cada item
		$('#lote_id').on('change', function () {
			var lote_id = $(this).val();
			
			$(".loteids").each(function() {
				$(this).val(lote_id);
			});
		});
	}

	function armaSelectArticulo(ptrselect, ptrarticulo, opdata)
	{
		var select = $(ptrselect);
      	var options = select.children();
		var articulo_id = $(ptrarticulo).val();
		var mventa_id = $('#mventa_id').val();
		var mventa_nombre = $("#mventa_id option:selected").text();
		var fl_todos_los_articulos = $(ptrarticulo).parents("tr").find('input:checkbox[class=checkSinFiltro]:checked').val();

		// elige articulos x descripcion o por sku
		if (fl_todos_los_articulos == 'on')
			var sel_articulos = JSON.parse(document.querySelector('#marca').dataset.articuloall);
		else
			var sel_articulos = JSON.parse(document.querySelector('#marca').dataset.articulo);
		if (opdata == 2)
		{
			sel_articulos.sort(function(a, b) {
    				var textA = a.sku;
    				var textB = b.sku;
    				return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
				});
		}

		select.empty();

		if (mventa_nombre === "-- Seleccionar marca --")
			select.append('<option value="">-- Articulos sin filrar --</option>');
		else
			select.append('<option value="">-- Articulos ' + mventa_nombre + ' --</option>');

		$.each(sel_articulos, function(obj, item) {
			if (articulo_id == item.id)
				op = 'selected="selected"';
			else
				op = '';
			if (mventa_id == undefined || mventa_id == '')
				select.append('<option value="' + item.id + '"'+op+'>' + (opdata == 2 ? item.sku + '-' + item.descripcion : item_descripcion + '-' + item.sku) + '</option>');
			else
			{
				if (item.mventa_id == mventa_id)
					select.append('<option value="' + item.id + '"'+op+'>' + (opdata == 2 ? item.sku + '-' + item.descripcion : item.descripcion + '-' + item.sku) + '</option>');
			}
		});

		if (articulo_id > 0)
		{
			select.value = articulo_id;

			select.children().filter(function(){
   				return this.text == articulo_id;
			}).prop('selected', true);
		}
	}

	function sumaPares(modalactivo, clasetalle)
	{
		totPares = 0;

		$("#"+modalactivo+" ."+clasetalle).each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPares += parseFloat($(this).val());
		});
	}

	function muestraTotalPares()
	{
		$("#totPares").val(totPares.toFixed(0));

		if (flFactura)
			$("#facturartotpares").val(totPares.toFixed(0));
	}

	function TotalParesPedido()
	{
		totPares = 0;

		$(".cantidad").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPares += parseFloat($(this).val());
		});
		$("#totalparespedido").val(totPares.toFixed(0));
	}

	function sumaanulacionPares()
	{
		totPares = 0;

		$(".cantidadesportallesa").each(function() {
			if (parseFloat($(this).val()) >= 1 && parseFloat($(this).val()) <= 999999)
				totPares += parseFloat($(this).val());
		});
	}

	function muestraanulacionTotalPares()
	{
		$("#totanulacionPares").val(totPares.toFixed(0));
	}

	function muestrahistoriaTotalPares()
	{
		$("#tothistoriaPares").val(totPares.toFixed(0));
	}

	// Arma medidas y cantidades para modal
	function armaMedidas(item)
	{
		articulo_id = $(item).parents("tr").find(".articulo").val();
		descripcion_articulo = $(item).parents("tr").find(".articulo option:selected").text();
		modulo_id = $(item).parents("tr").find(".modulo").val();
		combinacion_id = $(item).parents("tr").find(".combinacion").val();
		nombre_combinacion = $(item).parents("tr").find(".combinacion option:selected").text();

		// Lee tabla de medidas
		var val_medida = $(item).parents("tr").find(".medidas").val();

		medidas=[];
		cantidades=[];
		precios=[];

		if (val_medida != '')
		{
			var tbl_medidas = JSON.parse(val_medida);

       		$.each(tbl_medidas, function(index,value){
				medidas.push(value.talle_id);
				cantidades.push(value.cantidad);
				precios.push(value.precio);
			});
		}
		completarTalles(modulo_id, 0, medidas, cantidades, precios);
	}

	// Manejo de grilla 

    $(function () {
        $('#agrega_renglon').on('click', agregaRenglon);
        $(document).on('click', '.eliminar', borraRenglon);
        $(document).on('click', '.generaot', generaOt);
        $(document).on('click', '.imprimeot', imprimeOt);
        $(document).on('click', '.anulaitem', anulaItem);
		$(document).on('click', '.historiaitem', historiaItem);

		// Si no tiene items agrega el primero
		if(!$('.item-pedido').length)
			agregaRenglon();

		let cliente_id = $("#cliente_id").val();
		if (cliente_id == CLIENTE_STOCK_ID)
			$("#divlote").show();
		else
			$("#divlote").hide();
    });

    function agregaRenglon(){
		if (event != undefined)
        	event.preventDefault();
        var renglon = $('#template-renglon').html();

        $("#tbody-tabla").append(renglon);
        actualizaRenglones();

		activa_eventos(false);
    }

    function imprimeOt() {
		var ot = $(this).parents("tr").find(".ot").val();
        var listarUri = "/anitaERP/public/ventas/crearemisionot";
		
		if (ot == 0 || ot == -1)
			alert("No puede listar OT");
		else
			$.post(listarUri, {_token: $('input[name=_token]').val(), ordenestrabajo: ot, tipoemision: "COMPLETA"}, function(data)
			{ 
				alert("OT EMITIDA CORRECTAMENTE"); 
			});
	}

    function generaOt() {
		var ot = $(this).parents("tr").find(".ot").val();
		var tiposuspension_id = $('#tiposuspension_id').val();
		var tipoalta = $('#tipoalta').val();

        pedido_combinacion = $(this).parents("tr").find(".ids");
		if (ot > 0)
			alert("No puede volver a generar OT");
		else
		{
			if (tiposuspension_id == 3)
				alert('No puede generar ot a cliente moroso');
			else
			{
				if (tipoalta == 'P')
					alert('No puede generar ot a cliente provisorio');
				else
				{
					if (pedido_combinacion.val() == 0)
					{
						alert('No puede generar ot sin grabar el nuevo item');
					}
					else
					{
						var leyenda = $(this).parents("tr").find(".observacion").val();
						$("#leyendaot").val(leyenda);
		
						$("#crearOrdenTrabajoModal").modal('show');
					}
				}
			}
		}
	}

	// Anula item 
    function anulaItem() {
       	codigoAnulacionOt = $(this).parents('tr').find('.otcodigo').val();
		idAnulacionOt = $(this).parents('tr').find('.ot').val();
		motivoAnulacionOt = $(this).parents('tr').find('.motivosanulacion').val();
		nombreClienteAnulacionOt = $(this).parents('tr').find('.clientesanulacion').val();
	  	itemAnulacionOt = $(this);
	  	let pedido_combinacion_id = $(this).parents("tr").find(".ids").val();

	  	itemAnulacion = $(this).parents('tr').find('.item');
	  	itemAnulacionId = $(this).parents('tr').find('.ids').val();
	  	botonAnulacion = $(this).parents('tr').find('.ianulaItem');

	  	flAnulacionItem = true;

		// Busca si tiene factura asociada
		var listarUri = "/anitaERP/public/ventas/estadoot/"+codigoAnulacionOt+"/"+pedido_combinacion_id;

		$.get(listarUri, function(data){
			
			if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
			{
				alert("OT ya facturada "+data.numerofactura);
			}
			else
			{
				armaMedidas(itemAnulacionOt);

				setTimeout(() => {
					$("#anulacionModal").modal('show');
				}, 300);	
			}
		});
	}

	// Controla apertura modal de anulacion
	$('#anulacionModal').on('show.bs.modal', function (event) {
  		var modal = $(this);
		modalActivo = "anulacionModal";

		if (botonAnulacion.hasClass('text-danger'))
	  	{
			var tituloModal = "Anulación item ";
  			modal.find('#aceptaanulacionModal').text("Anula item");
			$("#clientereasignado").hide();
			$("#motivocierrepedido").hide();
	  	}
		else
	  	{
			var tituloModal = "Recupera item ";
  			modal.find('#aceptaanulacionModal').text("Recupera item");
			$("#clientereasignado").show();
			$("#motivocierrepedido").show();
			$("#nombreclientereasignado").empty();
			$("#nombreclientereasignado").append(nombreClienteAnulacionOt);
			$("#nombremotivoanulacion").empty();
			$("#nombremotivoanulacion").append(motivoAnulacionOt);
		}

		$("#ordentrabajoanulacion").val(codigoAnulacionOt);
  		modal.find('.modal-title').text(tituloModal+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
  		modal.find('#anulacionModal').empty();
  		modal.find('#anulacionModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
		sumaanulacionPares();
		muestraanulacionTotalPares();
	});

	$('#cierraanulacionModal').on('click', function () {
	  	flAnulacionItem = false;
	});

	// Acepta modal de anulacion de item
	$('#aceptaanulacionModal').on('click', function () {
	  	let nuevoClienteId = $('#nuevocliente_id').val();
	  	let motivoAnulacionId = $('#motivoanulacion_id').val();

		if (motivoAnulacionId == '')
		{
			alert("Debe ingresar motivo");
			return;
		}
	  	flAnulacionItem = false;

		$('#anulacionModal').modal('hide');

	  	// Anula el item 
        $.get('/anitaERP/public/ventas/anularitempedido/'+itemAnulacionId+'/'+codigoAnulacionOt+'/'+motivoAnulacionId+'/'+nuevoClienteId, function(data){
            var ret = $.map(data, function(value, index){
                return [value];
            });
            $.each(ret, function(index,value){
			  	if (value == 'anulado')
			  	{
				  	$(itemAnulacion).css("background-color","red");
				  	$(itemAnulacion).css("font-weight","900");
				  	alert("Item anulado con exito");
					$(itemAnulacionOt).parents('tr').find('.motivosanulacion').val($("select[id=motivoanulacion_id] option:selected").text());
					$(itemAnulacionOt).parents('tr').find('.clientesanulacion').val($("select[id=nuevocliente_id] option:selected").text());
			  	}
			  	else
			  	{
				  	$(itemAnulacion).css("background-color","");
				  	$(itemAnulacion).css("font-weight","normal");
				  	alert("Item recuperado con exito");
			  	}
				// Cambia atributo del boton
				botonAnulacion.attr('class', botonAnulacion.hasClass('fa fa-window-close text-success ianulaItem') ? 
			  							'fa fa-window-close text-danger ianulaItem' : 
			  							'fa fa-window-close text-success ianulaItem' );
			});
        });
        setTimeout(() => {
        }, 3000);
	});

	$('#anulacionModal').on('hidden.bs.modal', function () {
		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
	});

	// Muestra historia del item
    function historiaItem() {
		itemAnulacionOt = $(this);
		codigoAnulacionOt = $(this).parents('tr').find('.otcodigo').val();
		flAnulacionItem = true;

		armaMedidas(itemAnulacionOt);

		setTimeout(() => {
			$("#historiaModal").modal('show');
		}, 300);
	}

	// Controla apertura modal de historia
	$('#historiaModal').on('show.bs.modal', function (event) {
		var modal = $(this);
		let tituloModal = "Historia Anulación Item ";

		$("#ordentrabajohistoria").val(codigoAnulacionOt);
		modal.find('.modal-title').text(tituloModal+descripcion_articulo+' Combinacion '+nombre_combinacion+' Modulo '+nombre_modulo);
		modal.find('#historiaModal').empty();
		modal.find('#historiaModal').append(talles_txt+medidas_txt+precios_txt+tallesid_txt);
		modal.find('#tbody-historia').empty();
		
		let historia = $(itemAnulacionOt).parents("tr").find('.historiaanulacion').val();
		historia = JSON.parse(historia);

		historia_txt = "";
		for (var i=0; i < historia.length; i++)
		{
			var motivo = historia[i];

			historia_txt += "<tr>";
			
			let fechaCierre = Date.parse(motivo.created_at);

			historia_txt += "<td>"+new Date(fechaCierre).toLocaleString("es-AR")+"</td>";
			historia_txt += "<td>"+motivo.motivoscierrepedido.nombre+"</td>";
			if (motivo.clientes != null)
				historia_txt += "<td>"+motivo.clientes.nombre+"</td>";
			else
				historia_txt += "<td></td>";
			historia_txt += "<td>"+motivo.observacion+"</td>";
			historia_txt += "<td>"+motivo.estado+"</td>";
			historia_txt += "</tr>"
		}

		modal.find('#tbody-historia').append(historia_txt);

		sumaanulacionPares();
		muestrahistoriaTotalPares();
	});

	$('#aceptahistoriaModal').on('click', function () {
		$('#historiaModal').modal('hide');
		flAnulacionItem = false;
	});

	$('#historiaModal').on('hidden.bs.modal', function () {
		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
	});

    function borraRenglon() {
        event.preventDefault();
		ordentrabajo = $(this).parents('tr').find('.otcodigo').val();
		let pedido_combinacion_id = $(this).parents("tr").find(".ids").val();
		
		// Busca si tiene factura asociada
		var listarUri = "/anitaERP/public/ventas/estadoot/"+ordentrabajo+"/"+pedido_combinacion_id;
		var flError = false;

		$.get(listarUri, function(data){
							
			if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
			{
				alert("OT ya facturada "+data.numerofactura);
				flError = true;
			}
		});

		setTimeout(() => {
			if (!flError)
			{
				if (confirm("¿Desea borrar renglon?"))
				{
					$(this).parents('tr').remove();
					actualizaRenglones();
				}
				TotalParesPedido();
			}
		}, 300);
	}

    function actualizaRenglones() {
        var item = 1;

        $("#tbody-tabla .item").each(function() {
            $(this).val(item++);
        });
    }

	function preparaPreFactura()
	{
        $("#tbody-tabla .checkImpresion").each(function() {
			$(this).show();
		});
		flFactura = false;
		$("#imprimePreFactura").show();
	}

	function preparaFactura()
	{
        $("#tbody-tabla .checkImpresion").each(function() {
			$(this).show();
		});
		flFactura = true;
		$("#generaFactura").show();
	}

	function imprimePreFactura()
	{
		let checksId=[];
		let itemId;
	  	let pedidoId = $("#pedidoid").val();
		let descuentoLinea;

		$("input[type=checkbox]:checked").each(function(){
			
	  		itemId = $(this).parents('tr').find('.ids').val();
    		checksId.push(itemId);

		});
		descuentoLinea = prompt("Ingrese descuento de linea: ");

		let listarUri = "/anitaERP/public/ventas/listarprefactura"+"/"+pedidoId+'/'+checksId+"/"+descuentoLinea;
		document.location.href= listarUri;
	}

	function generaFactura()
	{
		let itemId, otId;
		
		tallesfactura_txt = [];
		medidasfactura_txt = [];
		preciosfactura_txt = [];
		tallesidfactura_txt = [];
		titulofactura_txt = [];
		offFactura = 0;
		pedido_combinacion_ids = [];
		ordentrabajo_ids = [];

		cliente_id = $("#cliente_id").val();
		
		$("input[type=checkbox]:checked").each(function(){

			ordentrabajo = $(this).parents('tr').find('.otcodigo').val();
			itemId = $(this).parents('tr').find('.ids').val();
			
			if (!otFacturada(ordentrabajo, itemId))
			{
				pedido_combinacion_ids.push(itemId);

				otId = $(this).parents('tr').find('.ot').val();
				ordentrabajo_ids.push(otId);
			
				descripcion_articulo = $(this).parents("tr").find(".articulo option:selected").text();
				nombre_combinacion = $(this).parents("tr").find(".desc_combinacion").val();
				cantidad = $(this).parents("tr").find(".cantidad").val();

				articulo_id = $(this).parents("tr").find(".articulo").val();
				modulo_id = $(this).parents("tr").find(".modulo").val();
				combinacion_id = $(this).parents("tr").find(".combinacion").val();
				nombrecliente = $("#cliente_id option:selected").text();
				descuentoCliente = $('#descuento').val();

				// Lee tabla de medidas
				var val_medida = $(this).parents("tr").find(".medidas").val();
				let check = this;
			
				medidas=[];
				cantidades=[];
				precios=[];
			
				if (val_medida != '')
				{
					var tbl_medidas = JSON.parse(val_medida);
				
					$.each(tbl_medidas, function(index,value){
						medidas.push(value.talle_id);
						cantidades.push(value.cantidad);
						precios.push(value.precio);
					});
				}
				
				completarTalles(modulo_id, check, medidas, cantidades, precios);
			}
		});
		
		setTimeout(() => {
			$("#facturarOrdenTrabajoModal").modal('show');
		}, 300);
	}

	// Carga modal de facturacion
	$(document).on('shown.bs.modal', '#facturarOrdenTrabajoModal', function() {
		var modal = $(this);
		modalActivo = "facturarOrdenTrabajoModal";

		var numeroPedido = $('#codigopedido').val();
		let sel_puntoventa = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let sel_puntoventaremito = JSON.parse(document.querySelector('#datosfactura').dataset.puntoventa);
		let selectPuntoVenta = $('#puntoventa_id');
		let selectPuntoVentaRemito = $('#puntoventaremito_id');
		let puntoVentaDefault = $('#puntoventadefault_id').val();
		let puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
		let sel_tipotransaccion = JSON.parse(document.querySelector('#datosfactura').dataset.tipotransaccion);
		let selectTipoTransaccion = $('#tipotransaccion_id');
		let tipoTransaccionDefault = $('#tipotransacciondefault_id').val();

		if (document.querySelector('#datosfactura').dataset.incoterm !== '')
		{
			var sel_incoterm = JSON.parse(document.querySelector('#datosfactura').dataset.incoterm);
			var selectIncoterm = $('#incoterm_id');
		}
			
		if (document.querySelector('#datosfactura').dataset.formapago !== '')
		{
			var sel_formapago = JSON.parse(document.querySelector('#datosfactura').dataset.formapago);
			var selectFormapago = $('#formapago_id');
		}
	
		const tiempoTranscurrido = Date.now();
		const hoy = new Date(tiempoTranscurrido);

		modal.find('#fechafactura').val(hoy.toISOString().substring(0,10));
		modal.find('#nombrecliente').val(nombrecliente);
		modal.find('.modal-title').text('Factura PEDIDO '+numeroPedido);
		modal.find('#facturarMedidasModal').empty();
		modal.find('#descuentopie').val(descuentoCliente);

    	// Lee punto de venta si es de exportacion
    	leePuntoVenta(puntoVentaDefault);

		alert('Va a facturar '+offFactura+' items');
		
		for (i = 0; i < offFactura; i++)
		{
			modal.find('#facturarMedidasModal').append(titulofactura_txt[i]+tallesfactura_txt[i]+medidasfactura_txt[i]+preciosfactura_txt[i]+tallesidfactura_txt[i]);
		}

		// Arma select de tipos de transacciones
		selectTipoTransaccion.empty();
		selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
		$.each(sel_tipotransaccion, function(obj, item) {
			if (tipoTransaccionDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
			selectTipoTransaccion.append('<option value="' + item.id + '"'+op+'>' + item.abreviatura + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta
		selectPuntoVenta.empty();
		selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventa, function(obj, item) {
			if (puntoVentaDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
			selectPuntoVenta.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de puntos de venta del remito
		selectPuntoVentaRemito.empty();
		selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
		$.each(sel_puntoventaremito, function(obj, item) {
			if (puntoVentaRemitoDefault == item.id)
				op = 'selected="selected"';
			else
				op = '';
			selectPuntoVentaRemito.append('<option value="' + item.id + '"'+op+'>' + item.codigo + '-' + item.nombre + '</option>');
		});

		// Arma select de incoterms
		if (document.querySelector('#datosfactura').dataset.incoterm !== '')
		{
			selectIncoterm.empty();
			selectIncoterm.append('<option value="">-- Seleccionar incoterm --</option>');
			$.each(sel_incoterm, function(obj, item) {
				selectIncoterm.append('<option value="' + item.id + '">' + item.nombre + '</option>');
			});
		}

		// Arma select de formas de pago
		if (document.querySelector('#datosfactura').dataset.formapago !== '')
		{
			selectFormapago.empty();
			selectFormapago.append('<option value="">-- Seleccionar forma de pago --</option>');
			$.each(sel_formapago, function(obj, item) {
				selectFormapago.append('<option value="' + item.id + '">' + item.nombre + '</option>');
			});
		}

		let _cant = 1;

		if (modulo_actual != 1)
			$("#facturarcantmodulo").val(modulo_actual);
		else
			$("#facturarcantmodulo").val(_cant);

		// Multiplica por la cantidad de modulos a cada cantidad por talle
		$("#facturarMedidasModal .cantidadesportalles").each(function(index) {
			var cantidad = $(this).val();
				
			$(this).val(cantidad);
			
		});
		
		sumaPares(modalActivo, 'cantidadesportalles');
		muestraTotalPares();
	});

	// Cierra modal medidas
	$('#cierraFacturarOrdenTrabajoModal').on('click', function () {
		tallesfactura_txt = [];
		medidasfactura_txt = [];
		preciosfactura_txt = [];
		tallesidfactura_txt = [];
		titulofactura_txt = [];
		offFactura = 0;
		$('#facturarOrdenTrabajoModal').modal('hide');
	});

	// Acepta modal
	$('#aceptaFacturarOrdenTrabajoModal').on('click', function () {
		// Factura el item
		var token = $('#csrf_token').val();
		var puntoventa_id = $('#puntoventa_id').val();
		var tipotransaccion_id = $('#tipotransaccion_id').val();
		var descuentopie = $('#descuentopie').val();
		var descuentoimportepie = $('#descuentoimportepie').val();
		var descuentolinea = $('#descuentolinea').val();
		var fechafactura = $('#fechafactura').val();
		var leyendafactura = $('#leyendafactura').val();
		var cantidadbulto = $('#cantidadbulto').val();
		var puntoventaremito_id = $('#puntoventaremito_id').val();
		var formapago_id = $('#formapago_id').val();
		var incoterm_id = $('#incoterm_id').val();
		var mercaderia = $('#mercaderia').val();
		var leyendaexportacion = $('#leyendaexportacion').val();

		if (cantidadbulto < 1 || cantidadbulto > 999999)
		{
			alert("No permite facturar sin cargar bultos");
			return false;
		}
		
		$('#facturarOrdenTrabajoModal').modal('hide');

		$.post("/anitaERP/public/ventas/facturarItemOt",
				{
					pedido_combinacion_id: pedido_combinacion_ids,
					ordentrabajo_id: ordentrabajo_ids,
					tipotransaccion_id: tipotransaccion_id,
					puntoventa_id: puntoventa_id,
					fechafactura: fechafactura,
					descuentopie: descuentopie,
					descuentoimportepie: descuentoimportepie,
					descuentolinea: descuentolinea,
					leyendafactura: leyendafactura,
					cantidadbulto: cantidadbulto,
					puntoventaremito_id: puntoventaremito_id,
					formapago_id: formapago_id,
					incoterm_id: incoterm_id,
					mercaderia: mercaderia,
					leyendaexportacion: leyendaexportacion,
					_token: token
				},
				function(data, status){
					if (data.error != '')
                 	   alert(data.error);
                	else
                	{
						alert("Factura Número: " + data.factura + "\nEstado: " + status);

						$("#facturarOrdenTrabajoModal").modal('hide');

						// Marca como facturados los items
						marcaItemFacturado();
					}
				});
	});

	$('#facturarOrdenTrabajoModal').on('hidden.bs.modal', function () {

		// Inicializa variables modal
		talles_txt = "";
		medidas_txt = "";
		precios_txt = "";
		tallesid_txt = "";
	});

	$('#puntoventa_id').on('change', function () {
		let puntoventa_id = $('#puntoventa_id').val();

		// Lee punto de venta si es de exportacion
		leePuntoVenta(puntoventa_id);
	});

	function leePuntoVenta(puntoventa_id)
	{
		var listarUri = "/anitaERP/public/ventas/chequeapuntoventa/"+puntoventa_id;

		$.get(listarUri, function(data){
			
			if (data.modofacturacion == 'E')
			{
				$('#div_formapago').show();
				$('#div_mercaderia').show();
				$('#div_incoterm').show();
				$('#div_leyendaexportacion').show();
			}
			else
			{
				$('#div_formapago').hide();
				$('#div_mercaderia').hide();
				$('#div_incoterm').hide();
				$('#div_leyendaexportacion').hide();
			}
		});
	}

   	function asignaDatosCliente(cliente_id, flCambioCliente){
        $.get('/anitaERP/public/ventas/leercliente/'+cliente_id, function(data){
            var datoscli = $.map(data, function(value, index){
                return [value];
            });
            const vendedor_id = datoscli[1];
            const transporte_id = datoscli[2];
            const condicionventa_id = datoscli[3];
            const descuento = datoscli[4];
			const tiposuspension_id = datoscli[5];

			if (flCambioCliente)
			{
				$('#vendedor_id').val(vendedor_id);
				$('#transporte_id').val(transporte_id);
				$('#condicionventa_id').val(condicionventa_id);
				$('#descuento').val(descuento);
			}
			$('#tiposuspension_id').val(tiposuspension_id);
		});
		
        setTimeout(() => {
			
        }, 3000);
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

	function otFacturada(ordentrabajo, pedido_combinacion_id)
	{
		// Busca si tiene factura asociada
		var listarUri = "/anitaERP/public/ventas/estadoot/"+ordentrabajo+"/"+pedido_combinacion_id;
            
		$.get(listarUri, function(data){
							
			if (data.numerofactura != -1 && data.numerofactura != -2 && data.numerofactura != -3)
				return true;
			else 
			{
				if ($data.numerofactura == -2)
				{
					alert('OT no esta terminada');
				}
				return false;
			}

		});

	}
