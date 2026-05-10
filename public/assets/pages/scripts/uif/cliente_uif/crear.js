
    var ptrriesgo;

    var PERIODO_ANIO_MIN_UIF = 2010;

    function anioMaxPeriodoPickerUif(anioValor) {
        var base = new Date().getFullYear() + 5;
        var v = parseInt(anioValor, 10);
        if (!isNaN(v)) {
            return Math.max(base, v);
        }
        return base;
    }

    function poblarSelectAnioUif($select, anioSeleccionado) {
        if (!$select || !$select.length) {
            return;
        }
        var sel = anioSeleccionado ? String(anioSeleccionado) : '';
        var maxY = anioMaxPeriodoPickerUif(sel);
        var minY = PERIODO_ANIO_MIN_UIF;
        if (maxY < minY) {
            maxY = minY;
        }
        $select.empty();
        $select.append($('<option>', { value: '', text: 'Año' }));
        for (var y = minY; y <= maxY; y++) {
            var ys = String(y);
            $select.append($('<option>', { value: ys, text: ys }));
        }
        if (sel) {
            $select.val(sel);
        }
    }

    function sincronizarPeriodoOcultoDesdeSelectsUif($row) {
        var y = $row.find('.periodo-anio').val();
        var m = $row.find('.periodo-mes').val();
        var $h = $row.find('.periodo');
        if (y && m) {
            $h.val(y + '-' + m);
            return;
        }
        $h.val('');
    }

    function normalizaPeriodoValorServidorAUyyyMm(val) {
        if (val === undefined || val === null) {
            return '';
        }
        val = String(val).trim();
        if (!val) {
            return '';
        }
        var ym = val.match(/^(\d{4})-(0[1-9]|1[0-2])$/);
        if (ym) {
            return val;
        }
        var slash = val.match(/^(\d{1,2})\/(\d{4})$/);
        if (slash) {
            var mes = ('0' + slash[1]).slice(-2);
            return slash[2] + '-' + mes;
        }
        var compact = val.match(/^(\d{2})(\d{4})$/);
        if (compact) {
            return compact[2] + '-' + compact[1];
        }
        return '';
    }

    function setPeriodoEnFilaUif($row, valorRaw) {
        var $anio = $row.find('.periodo-anio');
        var $mes = $row.find('.periodo-mes');
        var $h = $row.find('.periodo');
        var yyyymm = normalizaPeriodoValorServidorAUyyyMm(valorRaw);
        if (!yyyymm || !/^\d{4}-(0[1-9]|1[0-2])$/.test(String(yyyymm))) {
            var anioDefault = String(new Date().getFullYear());
            poblarSelectAnioUif($anio, anioDefault);
            $anio.val(anioDefault);
            $mes.val('');
            $h.val('');
            return;
        }
        var parts = String(yyyymm).split('-');
        var y = parts[0];
        var m = parts[1];
        poblarSelectAnioUif($anio, y);
        $anio.val(y);
        $mes.val(m);
        $h.val(y + '-' + m);
    }

    function inicializarNuevaFilaPeriodoUif($row) {
        var anioActual = String(new Date().getFullYear());
        poblarSelectAnioUif($row.find('.periodo-anio'), anioActual);
        $row.find('.periodo-anio').val(anioActual);
        $row.find('.periodo-mes').val('');
        $row.find('.periodo').val('');
    }

    function inicializarTablaRiesgoPeriodoFilas() {
        $('#tbody-tabla-riesgo .item-riesgo').each(function () {
            var $r = $(this);
            setPeriodoEnFilaUif($r, $r.find('.periodo').val());
        });
    }

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
        $(document).on('click', '.eliminar-archivo-cliente-uif', borraTarjetaArchivoClienteUif);

        inicializarTablaRiesgoPeriodoFilas();

        $('#tbody-tabla-riesgo').on('change', '.periodo-anio, .periodo-mes', function (event) {
            event.preventDefault();
            var $row = $(this).closest('tr.item-riesgo');
            sincronizarPeriodoOcultoDesdeSelectsUif($row);
            ptrriesgo = $row.find('.riesgo');
            if ($row.find('.periodo-mes').val()) {
                $row.find('.inusualidad_uif').focus();
            }
            calculaRiesgo($row.find('.periodo').val(), $row.find('.inusualidad_uif').val());
        });
        $('#tbody-tabla-riesgo').on('change', '.inusualidad_uif', function (event) {
            event.preventDefault();
            var $row = $(this).closest('tr.item-riesgo');
            ptrriesgo = $row.find('.riesgo');
            calculaRiesgo($row.find('.periodo').val(), $(this).val());
        });

        activa_eventos_consultaactividad_uif();
        chequeaSujetoObligado();

        // Pone en timeout para darle tiempo a refrescar las localidades
        setTimeout(() => {
            verificaAlertaUif();
        }, 3000);

        var inputArchivo = document.getElementById('fotodocumento');
        if (inputArchivo) {
            inputArchivo.addEventListener('change', function () {
                var archivoSeleccionado = document.getElementById('archivoseleccionado');
                var previewWrap = document.getElementById('fotodocumento-preview-nuevo');
                var previewImg = document.getElementById('fotodocumento-preview-img');
                if (this.files && this.files[0]) {
                    var f = this.files[0];
                    if (archivoSeleccionado) {
                        archivoSeleccionado.innerHTML = f.name;
                    }
                    if (f.type && f.type.indexOf('image/') === 0 && previewImg && previewWrap) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            previewImg.src = e.target.result;
                            previewWrap.style.display = 'block';
                        };
                        reader.readAsDataURL(f);
                    } else if (previewWrap) {
                        previewWrap.style.display = 'none';
                    }
                } else {
                    if (archivoSeleccionado) {
                        archivoSeleccionado.innerHTML = '';
                    }
                    if (previewWrap) {
                        previewWrap.style.display = 'none';
                    }
                }
            });
        }
    });

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
    
        let url = carpetaBase+"/uif/elimina_premio_uif";

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

        inicializarNuevaFilaPeriodoUif($("#tbody-tabla-riesgo tr.item-riesgo:last"));
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

    function agregaRenglonArchivo(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-archivo').html();

    	$("#tbody-tabla-archivo").append(renglon);
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    }

    function borraTarjetaArchivoClienteUif(event) {
        event.preventDefault();
        var $wrap = $(this).closest('.cliente-uif-archivo-item');
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

    /**
     * Borra la foto del DNI vía POST+DELETE en un form aparte (evita form anidado y _method duplicado en edición).
     */
    window.eliminarFotoDocumentoClienteUif = function (url) {
        if (!url || !confirm('¿Eliminar la foto del documento del cliente?')) {
            return;
        }
        var token = $('meta[name="csrf-token"]').attr('content');
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = url;
        var iToken = document.createElement('input');
        iToken.type = 'hidden';
        iToken.name = '_token';
        iToken.value = token;
        var iMethod = document.createElement('input');
        iMethod.type = 'hidden';
        iMethod.name = '_method';
        iMethod.value = 'DELETE';
        f.appendChild(iToken);
        f.appendChild(iMethod);
        document.body.appendChild(f);
        f.submit();
    };

    function calculaRiesgo(periodo, inusualidad_uif_id)
    {
        var yyyymm = normalizaPeriodoValorServidorAUyyyMm(periodo);
        if (!yyyymm || !inusualidad_uif_id) {
            return;
        }
        var cliente_uif_id = $('#cliente_uif_id').val();
        if (!cliente_uif_id || cliente_uif_id === '0') {
            return;
        }
        var parts = yyyymm.split('-');
        var periodoSinBarras = parts[1] + parts[0];
        var numeroPeriodo = parseInt(periodoSinBarras, 10);

        var url_cta = carpetaBase + '/uif/calculariesgo_uif/' + cliente_uif_id + '/' + numeroPeriodo + '/' + inusualidad_uif_id;

        $.get(url_cta, function (data) {
            ptrriesgo.val(data.riesgo);
        });
    }
