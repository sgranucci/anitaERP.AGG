    var RETENCIONES_LETRA_C = {
        retieneiva: 'N',
        retieneganancia: 'N',
        condicionganancia: 'N',
    };

    var CAMPOS_REGLAS_RETENCION = [
        'retieneiva',
        'condicionganancia',
        'retieneganancia',
        'retencionganancia_id',
        'retencioniva_id',
        'retienesuss',
        'retencionsuss_id',
    ];

    function $selectRetencionProveedor(nombre) {
        var $porId = $('#' + nombre);
        if ($porId.length && $porId.is('select')) {
            return $porId;
        }
        return $('select[name="' + nombre + '"]');
    }

    function idsRetencionSinCodigo() {
        var raw = $('#tab2').attr('data-retencion-sin-codigo');
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function esLetraFacturaC() {
        return (($('#letra').val() || '').toString().trim().toUpperCase() === 'C');
    }

    function esCondicionMonotributo() {
        var cfg = document.getElementById('proveedor-arca-validacion-config');
        var monoId = cfg ? (cfg.getAttribute('data-condicioniva-monotributo-id') || '') : '';
        var actual = ($('#condicioniva_id').val() || '').toString();

        return monoId !== '' && actual === monoId;
    }

    function estadoCampoRetencionImpuestos(campo) {
        var esLetraC = esLetraFacturaC();
        var condicionGanancia = ($selectRetencionProveedor('condicionganancia').val() || '').toString();
        var retieneGanancia = ($selectRetencionProveedor('retieneganancia').val() || '').toString();
        var retieneIva = ($selectRetencionProveedor('retieneiva').val() || '').toString();
        var retieneSuss = ($selectRetencionProveedor('retienesuss').val() || '').toString();
        var sinCodigo = idsRetencionSinCodigo();
        var noRetieneGanancia = condicionGanancia === 'N' || retieneGanancia === 'N';

        switch (campo) {
            case 'retieneiva':
                return esLetraC ? { bloqueado: true, valor: 'N' } : { bloqueado: false };
            case 'condicionganancia':
                return esCondicionMonotributo() ? { bloqueado: true, valor: 'N' } : { bloqueado: false };
            case 'retieneganancia':
                if (esCondicionMonotributo() || condicionGanancia === 'N') {
                    return { bloqueado: true, valor: 'N' };
                }
                return { bloqueado: false };
            case 'retencionganancia_id':
                if (esCondicionMonotributo() || noRetieneGanancia) {
                    return { bloqueado: true, valor: sinCodigo.retencionganancia_id || '' };
                }
                return { bloqueado: false };
            case 'retencioniva_id':
                if (retieneIva === 'N') {
                    return { bloqueado: true, valor: sinCodigo.retencioniva_id || '' };
                }
                return { bloqueado: false };
            case 'retienesuss':
                return { bloqueado: false };
            case 'retencionsuss_id':
                if (retieneSuss === 'N') {
                    return { bloqueado: true, valor: sinCodigo.retencionsuss_id || '' };
                }
                return { bloqueado: false };
            default:
                return { bloqueado: false };
        }
    }

    function bloquearSelectRetencionImpuestos($select, valor) {
        if (valor !== undefined && valor !== null) {
            $select.val(String(valor));
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change.select2');
            }
        }
        var nombre = $select.attr('name') || $select.attr('id');
        var $hidden = $select.next('input[data-proveedor-retencion-bloqueado="' + nombre + '"]');
        if (!$hidden.length) {
            $hidden = $('<input type="hidden">')
                .attr('data-proveedor-retencion-bloqueado', nombre)
                .attr('name', nombre);
            $select.after($hidden);
        }
        $hidden.val($select.val());
        $select.prop('disabled', true);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        }
    }

    function desbloquearSelectRetencionImpuestos($select) {
        var nombre = $select.attr('name') || $select.attr('id');
        $select.prop('disabled', false);
        $select.next('input[data-proveedor-retencion-bloqueado="' + nombre + '"]').remove();
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        }
    }

    function aplicarReglasRetencionesImpuestos() {
        CAMPOS_REGLAS_RETENCION.forEach(function (campo) {
            var $select = $selectRetencionProveedor(campo);
            if (!$select.length) {
                return;
            }
            var estado = estadoCampoRetencionImpuestos(campo);
            if (estado.bloqueado) {
                bloquearSelectRetencionImpuestos($select, estado.valor);
            } else {
                desbloquearSelectRetencionImpuestos($select);
            }
        });
    }

    function aplicarRetencionesPorLetra() {
        aplicarReglasRetencionesImpuestos();
    }

    function completarLetra(condicioniva_id){
		var condiva = $("#condicioniva_query").val();
		const replace = '"';
		var data = condiva.replace(/&quot;/g, replace);
		var dataP = JSON.parse(data);

		$.each(dataP, (index, value) => {
			if (value['id'] == condicioniva_id)
				$("#letra").val(value['letra']);
  		});
        aplicarRetencionesPorLetra();
	}

    function valorFpCampo($el) {
        return (($el.val() || '') + '').trim();
    }

    function renglonFormapagoTieneDatos($tr) {
        var selectores = [
            '.fp-nombre',
            '.fp-formapago',
            '.fp-tipocuentacaja',
            '.fp-moneda',
            '.fp-cbu',
            '.fp-numerocuenta',
            '.fp-nroinscripcion',
            '.fp-banco',
            '.fp-mediopago',
            '.fp-email',
        ];
        for (var i = 0; i < selectores.length; i++) {
            if (valorFpCampo($tr.find(selectores[i]).first()) !== '') {
                return true;
            }
        }
        return false;
    }

    function limpiarRenglonesFormapagoVacios() {
        $('#tbody-formapago-table tr.item-formapago').each(function () {
            var $tr = $(this);
            if (!renglonFormapagoTieneDatos($tr)) {
                $tr.remove();
            }
        });
        actualizaRenglonesFormapago();
    }

    function sincronizarRequiredFormapago() {
        $('#tbody-formapago-table tr.item-formapago').each(function () {
            var $tr = $(this);
            var activo = renglonFormapagoTieneDatos($tr);

            // TC nunca es obligatorio (tipocuentacaja_id nullable); solo Nombre / Forma / Moneda.
            var $tc = $tr.find('.fp-tipocuentacaja').first();
            if ($tc.length) {
                $tc.removeClass('fp-requerido').removeClass('required').removeAttr('required');
                if (typeof marcarCampoObligatorio === 'function') {
                    marcarCampoObligatorio($tc[0], false);
                }
            }

            $tr.find('.fp-requerido').each(function () {
                var $campo = $(this);
                if (activo) {
                    $campo.addClass('required').attr('required', 'required');
                } else {
                    $campo.removeClass('required').removeAttr('required');
                    if (typeof marcarCampoObligatorio === 'function') {
                        marcarCampoObligatorio(this, false);
                    }
                }
            });
        });
    }

    function validarFormasPagoProveedor() {
        limpiarRenglonesFormapagoVacios();
        sincronizarRequiredFormapago();

        var primerInvalido = null;
        var cantidadInvalidos = 0;
        var etiqueta = '';

        $('#tbody-formapago-table tr.item-formapago').each(function () {
            var $tr = $(this);
            var nro = valorFpCampo($tr.find('.iiformapago')) || '?';
            $tr.find('.fp-requerido').each(function () {
                var vacio = valorFpCampo($(this)) === '';
                if (typeof marcarCampoObligatorio === 'function') {
                    marcarCampoObligatorio(this, vacio);
                } else {
                    this.style.borderColor = vacio ? '#dc3545' : '';
                }
                if (vacio) {
                    cantidadInvalidos++;
                    if (!primerInvalido) {
                        primerInvalido = this;
                        etiqueta = ($(this).attr('data-fp-label') || this.name || 'campo') +
                            ' (renglón ' + nro + ')';
                    }
                }
            });
        });

        return {
            valido: cantidadInvalidos === 0,
            primerInvalido: primerInvalido,
            cantidadInvalidos: cantidadInvalidos,
            etiqueta: etiqueta,
        };
    }

    $(function () {
        var formGeneral = document.getElementById('form-general');
        if (formGeneral) {
            // Antes del validador global de funciones.js: limpia vacíos y marca required.
            formGeneral.addEventListener('submit', function () {
                aplicarReglasRetencionesImpuestos();
                limpiarRenglonesFormapagoVacios();
                sincronizarRequiredFormapago();
                if (typeof sincronizarEstadoProveedorPost === 'function') {
                    sincronizarEstadoProveedorPost();
                }
            }, true);
        }

        $('#form-general').on('submit', function (event) {
            aplicarReglasRetencionesImpuestos();
            var resultadoFp = validarFormasPagoProveedor();
            if (resultadoFp.valido) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (typeof mostrarSolapaDelPrimerCampoInvalido === 'function') {
                mostrarSolapaDelPrimerCampoInvalido(resultadoFp.primerInvalido);
            } else {
                $('#botonform3').trigger('click');
            }

            var mensaje = 'Complete los campos obligatorios de Formas de pago';
            if (resultadoFp.cantidadInvalidos > 1) {
                mensaje += ' (' + resultadoFp.cantidadInvalidos + ' pendientes)';
            }
            if (resultadoFp.etiqueta) {
                mensaje += '. Falta: ' + resultadoFp.etiqueta;
            }
            mensaje += '.';

            if (typeof Biblioteca !== 'undefined' && typeof Biblioteca.notificaciones === 'function') {
                Biblioteca.notificaciones(mensaje, 'Formulario incompleto', 'warning');
            } else {
                alert(mensaje);
            }

            if (typeof enfocarCampoInvalido === 'function') {
                enfocarCampoInvalido(resultadoFp.primerInvalido);
            } else if (resultadoFp.primerInvalido) {
                resultadoFp.primerInvalido.focus();
            }
        });

        $(document).on('input change', '#tbody-formapago-table .fp-requerido, #tbody-formapago-table .fp-formapago, #tbody-formapago-table .fp-tipocuentacaja, #tbody-formapago-table .fp-cbu, #tbody-formapago-table .fp-numerocuenta, #tbody-formapago-table .fp-nroinscripcion, #tbody-formapago-table .fp-banco, #tbody-formapago-table .fp-mediopago, #tbody-formapago-table .fp-email', function () {
            sincronizarRequiredFormapago();
            if (this.classList.contains('fp-requerido') && valorFpCampo($(this)) !== '' && typeof marcarCampoObligatorio === 'function') {
                marcarCampoObligatorio(this, false);
            }
        });

        $("#condicioniva_id").change(function(){
            var  condicioniva_id = $(this).val();
            completarLetra(condicioniva_id);
        });

        $('#condicionganancia, #retieneganancia, #retieneiva, #retienesuss').on('change', aplicarReglasRetencionesImpuestos);

        // En el alta, hasta elegir tipo de empresa el estado no se edita.
        // Un select disabled no viaja en el POST: se copia a un hidden.
        function sincronizarEstadoProveedorPost() {
            var $estado = $('#estado');
            if (!$estado.length) {
                return;
            }
            var $hidden = $('#estado_sincronizado');
            if ($estado.prop('disabled')) {
                if (!$hidden.length) {
                    $hidden = $('<input type="hidden" id="estado_sincronizado" name="estado">');
                    $estado.after($hidden);
                }
                $estado.removeAttr('name');
                $hidden.val($estado.val() || 'Activo');
            } else {
                $estado.attr('name', 'estado');
                $hidden.remove();
            }
        }

        let tipoempresa_id = $("#tipoempresa_id").val();
        if (tipoempresa_id == '') {
            $("#estado").prop('disabled', true);
        }
        sincronizarEstadoProveedorPost();

        $('#tipoempresa_id').on('change', function () {
            if ($(this).val()) {
                $('#estado').prop('disabled', false);
            }
            sincronizarEstadoProveedorPost();
        });

        colorSemaforo();

        $("#rojo").click(function(){
            if (confirm("Esta seguro de cambiar el color del semáforo a rojo?"))
            {
                $("#semaforo").val('Rojo');

                colorSemaforo();
            }
        });

        $("#amarillo").click(function(){
            if (confirm("Esta seguro de cambiar el color del semáforo a amarillo?"))
            {            
                $("#semaforo").val('Amarillo');

                colorSemaforo();
            }
        });

        $("#verde").click(function(){
            if (confirm("Esta seguro de cambiar el color del semáforo a verde?"))
            {
                $("#semaforo").val('Verde');

                colorSemaforo();
            }
        });

        function condicionivaBajaImpuestosIdProveedor() {
            var $cfg = $('#proveedor-arca-validacion-config').first();
            if (!$cfg.length) {
                return 7;
            }
            return parseInt($cfg.data('condicioniva-baja-id') || $cfg.attr('data-condicioniva-baja-id') || '7', 10);
        }

        function proveedorTieneCondicionivaBajaImpuestos() {
            var bajaId = condicionivaBajaImpuestosIdProveedor();
            var condId = parseInt($('#condicioniva_id').val() || '0', 10);
            return bajaId > 0 && condId === bajaId;
        }

        function $botonesRegularizarProveedor() {
            return $('.js-btn-regularizar-proveedor, #btn-regularizar-proveedor, #btn-regularizar-suspension-proveedor');
        }

        function puedeRegularizarProveedorSegunReglas() {
            if (proveedorTieneCondicionivaBajaImpuestos()) {
                return false;
            }
            var estado = String($('#estado').val() || '');
            if (estado === 'Regularizado') {
                return false;
            }
            var padronConProblemas = window.proveedorPadronArcaConProblemas === true;
            return padronConProblemas || estado === 'Suspendido' || estado !== 'Regularizado';
        }

        function aplicarEstadoProveedorEnFormulario(estado) {
            estado = String(estado || 'Activo');
            $('#estado').val(estado);
            var $btn = $('#botonestado');
            if ($btn.length) {
                $btn.html("<i class='fa fa-bell'></i>&nbsp;Estado " + estado);
            }
            if (estado === 'Regularizado' || estado === 'Activo') {
                $('#tiposuspension_id').val('');
            }
            if (typeof muestraTipoSuspension === 'function') {
                muestraTipoSuspension();
            }
            sincronizarEstadoProveedorPost();
            actualizarUiRegularizarProveedor();
        }

        function regularizarProveedorEnFormulario() {
            if (String($('#estado').val() || '') === 'Regularizado') {
                return;
            }
            if (proveedorTieneCondicionivaBajaImpuestos()) {
                alert('No se puede regularizar un proveedor con condición IVA Baja de impuestos.');
                return;
            }
            if (!confirm('¿Regularizar el proveedor? Podrá operarse pese a observaciones ARCA. Debe grabar para persistir.')) {
                return;
            }
            aplicarEstadoProveedorEnFormulario('Regularizado');
        }

        function actualizarUiRegularizarProveedor() {
            var puedeRegularizar = puedeRegularizarProveedorSegunReglas();
            $botonesRegularizarProveedor().each(function () {
                var $el = $(this);
                if (puedeRegularizar) {
                    $el.removeClass('d-none').css('display', 'inline-block');
                } else {
                    $el.hide();
                }
            });
            var $btnModal = $('#btn-regularizar-arca-modal');
            if ($btnModal.length && $('#proveedor-arca-validacion-config').length) {
                if (puedeRegularizar) {
                    $btnModal.removeClass('d-none').show()
                        .addClass('js-btn-regularizar-proveedor')
                        .html('<i class="fa fa-check-circle"></i> Regularizar proveedor');
                } else if (!$('#btn-regularizar-cliente').length) {
                    $btnModal.addClass('d-none');
                }
            }
        }

        window.marcarPadronArcaProveedorConProblemas = function (conProblemas) {
            window.proveedorPadronArcaConProblemas = !!conProblemas;
            actualizarUiRegularizarProveedor();
        };
        window.aplicarEstadoProveedorEnFormulario = aplicarEstadoProveedorEnFormulario;
        window.regularizarProveedorEnFormulario = regularizarProveedorEnFormulario;
        window.actualizarUiRegularizarProveedor = actualizarUiRegularizarProveedor;

        $('#condicioniva_id, #estado').on('change.regularizarProveedor', function () {
            actualizarUiRegularizarProveedor();
        });

        $(document).on('click', '.js-btn-regularizar-proveedor, #btn-regularizar-proveedor, #btn-regularizar-suspension-proveedor', function (e) {
            e.preventDefault();
            regularizarProveedorEnFormulario();
            $('#suspensionModal').modal('hide');
            $('#arca-impuestos-validacion-modal').modal('hide');
        });

        actualizarUiRegularizarProveedor();

        $("#botonestado").click(function(){

            var estado = $("#estado").val();
			var descripcion = $("#botonestado").text();

			if (estado == 'Activo' || estado == 'Regularizado')
			{
				estado = 'Suspendido';
				descripcion = 'Suspendido';

                // Muestra modal si tiene orden de trabajo generada
                $("#suspensionModal").modal('show');
            }
            else
			{
				estado = 'Activo';
				descripcion = 'Activo';
                
                // Pasa tipo de suspension al form
                $('#tiposuspension_id').val('');

                // Muestra tipo de suspension
                muestraTipoSuspension();
			}

            $("#estado").val(estado);
            $("#botonestado").html("<i class='fa fa-bell'></i>&nbsp;Estado "+descripcion);
            sincronizarEstadoProveedorPost();
            actualizarUiRegularizarProveedor();
        });

        function mostrarSolapaProveedor(n) {
            for (var i = 1; i <= 9; i++) {
                $(".form" + i).toggle(i === n);
            }
            $(".prov-tab-solapa").removeClass("btn-primary font-weight-bold").addClass("btn-info");
            $("#botonform" + n).removeClass("btn-info").addClass("btn-primary font-weight-bold");
        }

        $("#botonform1").click(function(){
            mostrarSolapaProveedor(1);
        });

        $("#botonform2").click(function(){
            mostrarSolapaProveedor(2);
			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Datos impuestos");
        });

        $("#botonform3").click(function(){
            mostrarSolapaProveedor(3);

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
            mostrarSolapaProveedor(4);
			$("#leyenda").focus();
        });

        $("#botonform5").click(function(){
            mostrarSolapaProveedor(5);
        });

        $("#botonform6").click(function(){
            mostrarSolapaProveedor(6);
        });

        $("#botonform7").click(function(){
            mostrarSolapaProveedor(7);
        });

        $("#botonform8").click(function(){
            mostrarSolapaProveedor(8);
        });

        $("#botonform9").click(function(){
            mostrarSolapaProveedor(9);
        });

        $(document).on('click', '.eliminar-documento-fiscal-proveedor', function () {
            $(this).closest('.col-md-6, .col-lg-4').remove();
        });

        $(document).on('click', '.eliminar-renglon-documento-fiscal', function () {
            var $tbody = $('#tbody-tabla-documento-fiscal');
            if ($tbody.find('tr').length <= 1) {
                $tbody.find('input[type=file]').val('');
                $tbody.find('input[type=date], input[type=number]').val('');
                return;
            }
            $(this).closest('tr').remove();
        });

        $('#agrega_renglon_documento_fiscal').click(function () {
            var tpl = document.getElementById('proveedor-template-renglon-documento-fiscal');
            if (!tpl) {
                return;
            }
            $('#tbody-tabla-documento-fiscal').append(tpl.content.cloneNode(true));
        });
	
        $( "#botonform0" ).click(function() {

            // Dispara submit para que lo atienda el control de campos required en funciones.js
            $( "#form-general" ).trigger('submit'); 
            
        });

        // Controla apertura modal de anulacion
        $('#suspensionModal').on('show.bs.modal', function (event) {
            var modal = $(this);
            var nombre = $("#nombre").val();
            var tiposuspension_id = $('#modaltiposuspension_id').val();

            var tituloModal = "Suspension del proveedor "+nombre;
            modal.find('.modal-title').text(tituloModal);
            $('#modaltiposuspension_id').val(tiposuspension_id);
        });

        $('#cierrasuspensionModal').on('click', function () {
            
        });

        // Acepta modal de suspension de proveedor
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
        aplicarReglasRetencionesImpuestos();

        // Muestra tipo de suspension
        muestraTipoSuspension();

        // Formatea los C.U.I.T. ya cargados en Formas de pago (XX-XXXXXXXX-X).
        if (typeof formatarCUIT === 'function') {
            $('#tbody-formapago-table .fp-nroinscripcion').each(function () {
                if (valorFpCampo($(this)) !== '') {
                    formatarCUIT(this);
                }
            });
        }

        // Marca el TC como requerido en las filas de transferencia ya existentes.
        sincronizarRequiredFormapago();

        $('#agrega_renglon_exclusion').on('click', agregaRenglonExclusion);
        $(document).on('click', '.eliminar_exclusion', borraRenglonExclusion);
        $('#agrega_renglon_formapago').on('click', agregaRenglonFormapago);
        $(document).on('click', '.eliminar_formapago', borraRenglonFormapago);
        $('#agrega_renglon_servicio').on('click', agregaRenglonServicio);
        $(document).on('click', '.eliminar_servicio', borraRenglonServicio);
        $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
        $(document).on('click', '.eliminar-archivo-proveedor', borraTarjetaArchivoProveedor);

        $('#agrega_renglon_proveedor_cm05').on('click', agregaRenglonProveedorCm05);
        $(document).on('click', '.eliminar_proveedor_cm05', borraRenglonProveedorCm05);
        if (typeof activa_eventos_consultaprovincia === 'function') {
            activa_eventos_consultaprovincia();
        }

    });

    function colorSemaforo()
    {
        let semaforo = $("#semaforo").val();

        $('.luz').removeClass('active');

        switch(semaforo)
        {
            case 'Verde':
                $('#verde').addClass('active');
                break;
            case 'Amarillo':
                $('#amarillo').addClass('active');
                break;
            case 'Rojo':
                $('#rojo').addClass('active');
                break;
        }
    }

    function muestraTipoSuspension()
    {
        var tiposuspensionproveedor_query = $("#tiposuspensionproveedor_query").val();
        var tiposuspension_id = $("#tiposuspension_id").val();

        if (tiposuspension_id > 0)
        {
            var tbl_tiposuspension = JSON.parse(tiposuspensionproveedor_query);

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

    function agregaRenglonProveedorCm05(event) {
        if (event) {
            event.preventDefault();
        }
        var tpl = document.getElementById('proveedor-template-renglon-cm05');
        if (!tpl) {
            return;
        }
        $('#tbody-proveedor-cm05').append(tpl.content.cloneNode(true));
        $('#tbody-proveedor-cm05').find('tr').last().find('.codigoprovincia').trigger('focus');
    }

    function borraRenglonProveedorCm05(event) {
        if (event) {
            event.preventDefault();
        }
        $(this).closest('tr').remove();
    }

    function agregaRenglonExclusion(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-exclusion').html();

    	$("#tbody-exclusion-table").append(renglon);
    	actualizaRenglonesExclusion();
    }

    function borraRenglonExclusion() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesExclusion();
    }

    function actualizaRenglonesExclusion() {
    	var item = 1;

    	$("#tbody-exclusion-table .iiexclusion").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonFormapago(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-formapago').html();

        $("#tbody-formapago-table").append(renglon);
    	actualizaRenglonesFormapago();
    }

    function borraRenglonFormapago() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesFormapago();
    }

    function actualizaRenglonesFormapago() {
    	var item = 1;

    	$("#tbody-formapago-table .iiformapago").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonServicio(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-servicio').html();
        $("#tbody-servicio-table").append(renglon);
        var empresaId = $('#empresa_id').val() || '';
        $("#tbody-servicio-table tr.item-servicio:last .servicio-empresa-id").val(empresaId);
    	actualizaRenglonesServicio();
    }

    function borraRenglonServicio(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesServicio();
    }

    function actualizaRenglonesServicio() {
    	var item = 1;
    	$("#tbody-servicio-table .iiservicio").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonArchivo(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-archivo').html();

    	$("#tbody-tabla-archivo").append(renglon);
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function borraTarjetaArchivoProveedor(event) {
        event.preventDefault();
        var $wrap = $(this).closest('.proveedor-archivo-item');
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

