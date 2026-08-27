
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

    function restaurarSolapasTrasErrorServidor() {
        if (!$('.alert-danger').length || typeof mostrarSolapaDelPrimerCampoInvalido !== 'function') {
            return;
        }

        var invalido = document.querySelector('#form-general [required]:invalid')
            || document.querySelector('#form-general .is-invalid');

        if (!invalido && typeof camposObligatoriosEnFormulario === 'function' && typeof valorCampoObligatorio === 'function') {
            var form = document.getElementById('form-general');
            if (form) {
                camposObligatoriosEnFormulario(form).some(function (campo) {
                    if (typeof campoObligatorioDebeValidarse === 'function' && !campoObligatorioDebeValidarse(campo)) {
                        return false;
                    }
                    if (!valorCampoObligatorio(campo)) {
                        invalido = campo;
                        return true;
                    }
                    return false;
                });
            }
        }

        if (invalido) {
            mostrarSolapaDelPrimerCampoInvalido(invalido);
            if (typeof enfocarCampoInvalido === 'function') {
                enfocarCampoInvalido(invalido);
            }
        }
    }

    $(function () {
        restaurarSolapasTrasErrorServidor();

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
                    if (typeof window.verificarClienteDocumentoDuplicado === 'function') {
                        var digitos = (this.value || '').replace(/\D+/g, '');
                        if (digitos.length === 11) {
                            window.verificarClienteDocumentoDuplicado({ debounce: false });
                        }
                    }
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
            var estado = String($("#estado").val() || '0');

            if (estado === '0') {
                $("#suspensionModal").modal('show');
                return;
            }

            if (estado === 'R') {
                if (confirm('¿Reactivar el cliente como Activo?')) {
                    aplicarEstadoClienteEnFormulario('0');
                }
                return;
            }

            if (estado === '1') {
                if (confirm('¿Reactivar el cliente como Activo?')) {
                    aplicarEstadoClienteEnFormulario('0');
                }
                return;
            }

            aplicarEstadoClienteEnFormulario('0');
        });

        function condicionivaBajaImpuestosId() {
            var $cfg = $('#cliente-arca-config, #cliente-arca-validacion-config, .js-cliente-arca-validacion-config').first();
            if (!$cfg.length) {
                return 7;
            }
            return parseInt($cfg.data('condicioniva-baja-id') || $cfg.attr('data-condicioniva-baja-id') || '7', 10);
        }

        function clienteTieneCondicionivaBajaImpuestos() {
            var bajaId = condicionivaBajaImpuestosId();
            var condId = parseInt($('#condicioniva_id').val() || '0', 10);
            return bajaId > 0 && condId === bajaId;
        }

        function $cfgClienteArcaValidacion() {
            return $('.js-cliente-arca-validacion-config, #cliente-arca-validacion-config').first();
        }

        function $botonesRegularizarCliente() {
            return $('.js-btn-regularizar-cliente, #btn-regularizar-cliente, #btn-regularizar-arca, #btn-regularizar-suspension');
        }

        function puedeRegularizarClienteSegunReglas() {
            if (clienteTieneCondicionivaBajaImpuestos()) {
                return false;
            }
            var estado = String($('#estado').val() || '0');
            if (estado === 'R') {
                return false;
            }
            var padronConProblemas = window.clientePadronArcaConProblemas === true;
            var alertaPadronVisible = $('#arca-impuestos-alerta').is(':visible');
            // Activo con problemas de padrón ARCA, o suspendido, o ya no Regularizado.
            return padronConProblemas || alertaPadronVisible || estado === '1' || estado !== 'R';
        }

        function descripcionEstadoCliente(estado) {
            if (estado === '1') {
                return 'Suspendido';
            }
            if (estado === 'R') {
                return 'Regularizado';
            }
            return 'Activo';
        }

        function aplicarEstadoClienteEnFormulario(estado) {
            estado = String(estado || '0');
            $("#estado").val(estado);

            var $btn = $("#botonestado");
            if ($btn.length) {
                $btn.removeClass('btn-info btn-danger btn-warning btn-success');
                if (estado === 'R') {
                    $btn.addClass('btn-warning');
                } else if (estado === '1') {
                    $btn.addClass('btn-danger');
                } else {
                    $btn.addClass('btn-info');
                }
                $btn.html("<i class='fa fa-bell'></i>&nbsp;Estado " + descripcionEstadoCliente(estado));
            }

            if (estado === 'R' || estado === '0') {
                $('#tiposuspension_id').val('');
            }

            if (typeof muestraTipoSuspension === 'function') {
                muestraTipoSuspension();
            }

            actualizarUiRegularizarCliente();
        }

        function regularizarClienteEnFormulario() {
            if (String($("#estado").val() || '') === 'R') {
                return;
            }
            if (clienteTieneCondicionivaBajaImpuestos()) {
                alert('No se puede regularizar un cliente con condición IVA Baja de impuestos.');
                return;
            }
            if (!confirm('¿Regularizar el cliente (estado R)? Podrá facturarse pese a observaciones ARCA. Debe grabar para persistir.')) {
                return;
            }
            aplicarEstadoClienteEnFormulario('R');
        }

        function actualizarUiRegularizarCliente() {
            var puedeRegularizar = puedeRegularizarClienteSegunReglas();

            $botonesRegularizarCliente().each(function () {
                var $el = $(this);
                if (puedeRegularizar) {
                    $el.removeClass('d-none').css('display', 'inline-block');
                } else {
                    $el.hide();
                }
            });

            var $btnModal = $('#btn-regularizar-arca-modal');
            if ($btnModal.length) {
                if ($cfgClienteArcaValidacion().length && puedeRegularizar) {
                    $btnModal.removeClass('d-none').show();
                } else {
                    $btnModal.addClass('d-none');
                }
            }
        }

        window.marcarPadronArcaClienteConProblemas = function (conProblemas) {
            window.clientePadronArcaConProblemas = !!conProblemas;
            actualizarUiRegularizarCliente();
        };
        window.marcarPadronArcaClienteConProblemas = window.marcarPadronArcaClienteConProblemas;

        window.aplicarEstadoClienteEnFormulario = aplicarEstadoClienteEnFormulario;
        window.regularizarClienteEnFormulario = regularizarClienteEnFormulario;
        window.actualizarUiRegularizarCliente = actualizarUiRegularizarCliente;

        $('#condicioniva_id').on('change.regularizarCliente', function () {
            actualizarUiRegularizarCliente();
        });

        $('#btn-regularizar-cliente, #btn-regularizar-arca, #btn-regularizar-arca-modal, #btn-regularizar-suspension').on('click', function (e) {
            e.preventDefault();
            regularizarClienteEnFormulario();
            $('#suspensionModal').modal('hide');
            $('#arca-impuestos-validacion-modal').modal('hide');
        });

        aplicarEstadoClienteEnFormulario(String($("#estado").val() || '0'));

        $('#tabs-cliente a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr('href');

            if (target === '#tab-lugares-entrega') {
                activaEventoEntrega();

                $("#tbody-tabla .localidades").each(function() {
                    var $tr = $(this).closest("tr");
                    completarLocalidadesEntrega(
                        $tr.find(".provincias"),
                        $tr.find(".localidad_id_previas").val()
                    );
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

            if (target === '#tab-exclusion-percepcion') {
                $('#exclusion-percepcion-table').find('tr').last().find('.tipoexclusion').focus();
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

            aplicarEstadoClienteEnFormulario('1');
            muestraTipoSuspension();

            $('#suspensionModal').modal('hide');
        });

        $('#suspensionModal').on('hidden.bs.modal', function () {
        
        });

		var condicioniva_id = $("#condicioniva_id").val();
        completarLetra(condicioniva_id);

        if ($('#tipodocumento_id option:selected').text().trim() === 'CUIT') {
            var $nroDoc = $('#numerodocumento');
            if ($nroDoc.val() && typeof formatarCUIT === 'function') {
                formatarCUIT($nroDoc[0]);
            }
        }

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
        $('#agrega_renglon_exclusion_percepcion').on('click', agregaRenglonExclusionPercepcion);
        $(document).on('click', '.eliminar_exclusion_percepcion', borraRenglonExclusionPercepcion);
        $(document).on('change', '#exclusion-percepcion-table .tipoexclusion', function () {
            aplicarTipoExclusionFila($(this).closest('tr'));
        });
        $(document).on('blur', '#exclusion-percepcion-table .porcentajeexclusion', formatearPorcentajeExclusionInput);
        $('#exclusion-percepcion-table tbody tr').each(function () {
            aplicarTipoExclusionFila($(this));
        });
        aplicarFormatoPorcentajesExclusion();
        $('#form-general').on('submit', aplicarFormatoPorcentajesExclusion);
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
        activa_eventos_consultavendedor();
        activa_eventos_consultacobrador();
        activa_eventos_consultadistribuidor();
        activa_eventos_consultalistaprecio();
        activa_eventos_consulta_cuentacontable();
        activa_eventos_consultatransporte();
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
        var estadoCli = ($('#estado').val() || '').toString().toUpperCase();
        if (estadoCli === 'R') {
            $('#tiposuspension_id').val('');
            $('#nombretiposuspension').text('');
            return;
        }

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

    function formatearPorcentajeExclusion(valor) {
        if (valor === undefined || valor === null) {
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

    function formatearPorcentajeExclusionInput() {
        var formateado = formatearPorcentajeExclusion($(this).val());
        if (formateado !== '') {
            $(this).val(formateado);
        }
    }

    function aplicarFormatoPorcentajesExclusion() {
        $('#exclusion-percepcion-table .porcentajeexclusion').each(function () {
            var formateado = formatearPorcentajeExclusion($(this).val());
            if (formateado !== '') {
                $(this).val(formateado);
            }
        });
    }

    function aplicarTipoExclusionFila($fila) {
        if (!$fila || !$fila.length) {
            return;
        }
        var tipo = ($fila.find('.tipoexclusion').val() || '').toUpperCase();
        var esIva = tipo === 'IVA';
        var $codigo = $fila.find('.codigoprovincia');
        var $nombre = $fila.find('.nombreprovincia');
        var $id = $fila.find('.provincia_id');
        var $idPrevia = $fila.find('.provincia_id_previa');
        var $codigoPrevio = $fila.find('.codigo_previo_provincia');
        var $lupa = $fila.find('.consultaprovincia');
        $codigo.prop('readonly', esIva);
        $lupa.prop('disabled', esIva);
        if (esIva) {
            $id.val('');
            $idPrevia.val('');
            $codigo.val('');
            $codigoPrevio.val('');
            $nombre.val('');
        }
    }

    function agregaRenglonExclusionPercepcion() {
        event.preventDefault();
        var renglon = $('#template-renglon-exclusion-percepcion').html();
        $("#tbody-tabla-exclusion-percepcion").append(renglon);
        activa_eventos(false);
        var $fila = $('#exclusion-percepcion-table').find('tr').last();
        aplicarTipoExclusionFila($fila);
        $fila.find('.tipoexclusion').focus();
    }

    function borraRenglonExclusionPercepcion(event) {
        event.preventDefault();
        $(this).parents('tr').remove();
    }    
