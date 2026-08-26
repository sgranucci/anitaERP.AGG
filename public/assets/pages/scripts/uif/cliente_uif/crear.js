
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
            if (esEdicionClienteUif()) {
                verificaAlertaUif();
            }
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
        
        var tabParam = new URLSearchParams(window.location.search).get('uif_tab');
        if (tabParam === '1') {
            $('#botonform1').trigger('click');
        } else if (tabParam === '2') {
            $('#botonform2').trigger('click');
        } else if (tabParam === '3') {
            if ($('#botonform3').length) {
                $('#botonform3').trigger('click');
            } else {
                $('.form1').hide();
                $('.form2').hide();
                $('.form3').show();
                $('.form4').hide();
                $('.form5').hide();
            }
        } else if (tabParam === '4') {
            $('#botonform4').trigger('click');
        } else if (tabParam === '5') {
            $('#botonform5').trigger('click');
        }

        $('#agrega_renglon_riesgo').on('click', agregaRenglonRiesgo);
        $(document).on('click', '.eliminar_riesgo', borraRenglonRiesgo);
        $(document).on('click', '.eliminar_premio', borraRenglonPremio);
        $(document).on('click', '#agrega_renglon_premio', function (event) {
            var $btn = $(this);
            if ($btn.is('a') && $btn.attr('href')) {
                if (mostrarModalCumplimientoPremioUif($btn.attr('href'))) {
                    event.preventDefault();
                }
                return;
            }
            event.preventDefault();
            if (mostrarModalCumplimientoPremioUif('#', true)) {
                return;
            }
            if (!window.confirm('Se guardará el cliente y luego podrá cargar el premio. ¿Continuar?')) {
                return;
            }
            $('#ir_a_agregar_premio').val('1');
            $('#form-general').trigger('submit');
        });
        $('#uif-modal-cumplimiento-continuar').on('click', function (event) {
            try {
                sessionStorage.setItem('uif-cumplimiento-premio-aviso', '1');
            } catch (e) {}
            var href = ($(this).attr('href') || '').trim();
            if (href && href !== '#') {
                return;
            }
            event.preventDefault();
            $('#uif-modal-cumplimiento').modal('hide');
            if ($('#ir_a_agregar_premio').length) {
                $('#ir_a_agregar_premio').val('1');
                $('#form-general').trigger('submit');
            }
        });
        $('#uif-modal-cumplimiento-ficha').on('click', function (event) {
            var tab = $(this).attr('data-uif-ir-tab') || '2';
            if ($('#botonform' + tab).length) {
                event.preventDefault();
                $('#uif-modal-cumplimiento').modal('hide');
                irAPendienteUif(tab, $(this).attr('data-uif-selector') || '');
            }
        });
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
        $(document).on('change', '#actividad_uif_id, #nombreactividad_uif', function () {
            marcarCampoObligatorioSiExiste(document.getElementById('actividad_uif_id'), !$('#actividad_uif_id').val().trim());
        });

        inicializarDocumentoYCuitUif();

        $(document).on('change', [
            '#fechaconfirmapep', '#fechafirmapep', '#fechavencimientodni',
            '#fechavencimientoactividad', '#firmodeclaracionjurada', '#riesgopep',
            '#fechainformenosis', '#fechainformepep', '#fotodocumento',
            '#so_uif_id', '#pep_uif_id'
        ].join(', '), verificaAlertaUif);
        $(document).on('change', '#tbody-tabla-archivo input[type=file]', verificaAlertaUif);
        $(document).on('click', '.uif-banner-item-link, [data-uif-ir-tab]', function (event) {
            if ($(this).is('#uif-modal-cumplimiento-ficha, #uif-modal-cumplimiento-continuar')) {
                return;
            }
            var tab = $(this).attr('data-uif-ir-tab');
            var selector = $(this).attr('data-uif-selector');
            var href = ($(this).attr('href') || '').trim();
            if (!tab && href && href !== '#') {
                return;
            }
            event.preventDefault();
            $('#uif-modal-cumplimiento').modal('hide');
            irAPendienteUif(tab, selector);
        });

        setTimeout(verificaAlertaUif, 200);

        var inputArchivo = document.getElementById('fotodocumento');
        if (inputArchivo) {
            var previewPdfObjectUrl = null;
            inputArchivo.addEventListener('change', function () {
                var archivoSeleccionado = document.getElementById('archivoseleccionado');
                var previewWrap = document.getElementById('fotodocumento-preview-nuevo');
                var previewImg = document.getElementById('fotodocumento-preview-img');
                var previewPdf = document.getElementById('fotodocumento-preview-pdf');
                if (previewPdfObjectUrl) {
                    URL.revokeObjectURL(previewPdfObjectUrl);
                    previewPdfObjectUrl = null;
                }
                if (this.files && this.files[0]) {
                    var f = this.files[0];
                    var esPdf = (f.type === 'application/pdf') || /\.pdf$/i.test(f.name || '');
                    if (archivoSeleccionado) {
                        archivoSeleccionado.innerHTML = f.name;
                    }
                    if (f.type && f.type.indexOf('image/') === 0 && previewImg && previewWrap) {
                        if (previewPdf) {
                            previewPdf.classList.add('d-none');
                            previewPdf.removeAttribute('src');
                        }
                        previewImg.classList.remove('d-none');
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            previewImg.src = e.target.result;
                            previewWrap.style.display = 'block';
                        };
                        reader.readAsDataURL(f);
                    } else if (esPdf && previewWrap) {
                        if (previewImg) {
                            previewImg.classList.add('d-none');
                            previewImg.removeAttribute('src');
                        }
                        if (previewPdf) {
                            previewPdfObjectUrl = URL.createObjectURL(f);
                            previewPdf.src = previewPdfObjectUrl;
                            previewPdf.classList.remove('d-none');
                        }
                        previewWrap.style.display = 'block';
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
                    if (previewImg) {
                        previewImg.removeAttribute('src');
                    }
                    if (previewPdf) {
                        previewPdf.classList.add('d-none');
                        previewPdf.removeAttribute('src');
                    }
                }
                verificaAlertaUif();
            });
        }

        aplicarRestriccionPerfilClienteUif();
        verificaAlertaUif();

        // Alta/edición cajero: no dejar que campos ocultos de form2 bloqueen el Guardar (HTML5).
        $(document).on('click', '#botonform0', function (event) {
            var perfil = $('#uif_perfil_cliente').val();
            if (perfil === 'supervisor') {
                return;
            }
            quitarRequiredCumplimientoUifCajero();
            var so = ($('#so_uif_id').val() || '').trim();
            var pep = ($('#pep_uif_id').val() || '').trim();
            if (!so || !pep) {
                event.preventDefault();
                event.stopImmediatePropagation();
                $('#botonform2').trigger('click');
                window.alert('En Datos UIF indique Sujeto Obligado y Expuesto Políticamente (PEP). El resto lo verifica Enc-UIF.');
                return false;
            }
        });

        $('#form-general').on('submit', function (event) {
            var perfil = $('#uif_perfil_cliente').val();
            if (perfil === 'supervisor') {
                return;
            }
            quitarRequiredCumplimientoUifCajero();
            $('.form2').find('input:disabled, select:disabled, textarea:disabled')
                .prop('required', false)
                .removeAttr('required');

            var so = ($('#so_uif_id').val() || '').trim();
            var pep = ($('#pep_uif_id').val() || '').trim();
            if (!so || !pep) {
                event.preventDefault();
                event.stopImmediatePropagation();
                $('#botonform2').trigger('click');
                window.alert('En Datos UIF indique Sujeto Obligado y Expuesto Políticamente (PEP). El resto lo verifica Enc-UIF.');
                return false;
            }
        });
    });

    /**
     * Supervisor: sin bloqueos.
     * Cajero: solapa Datos UIF (form2) solo SO, PEP y fecha última firma PEP; solapa Riesgo (form4) solo lectura.
     *        Solapas 1, 3 y 5 sin restricción en esta función.
     *        Fechas de validación / DNI / DJ / informes las completa Enc-UIF después (no bloquean el alta).
     * Operador (solo visualización en edición): todo bloqueado salvo botones de solapa.
     *        En alta, operador con crear se trata como cajero (puede grabar con defaults).
     */
    /**
     * Campos de form2 deshabilitados no se envían en el POST; el servidor completa con defectos.
     * Estos hidden evitan validación HTML5 en selects required ocultos al enviar como cajero.
     */
    function asegurarHiddenCamposUifCajero() {
        var defaults = {
            nivelsocioeconomico_uif_id: '8',
            riesgopep: 'BAJO',
            firmodeclaracionjurada: 'N',
            resideparaisofiscal: 'N',
            resideexterior: 'N',
            cumplenormativaso: 'N'
        };
        Object.keys(defaults).forEach(function (name) {
            var $field = $('[name="' + name + '"]');
            if (!$field.length) {
                return;
            }
            var id = 'uif-cajero-hidden-' + name;
            $('#' + id).remove();
            if ($field.is(':disabled')) {
                $('<input>', { type: 'hidden', id: id, name: name, value: defaults[name] }).appendTo('#form-general');
            }
        });
    }

    /** Campos de cumplimiento que completa Enc-UIF; nunca deben bloquear HTML5 al cajero. */
    function quitarRequiredCumplimientoUifCajero() {
        var ids = [
            '#nivelsocioeconomico_uif_id', '#riesgopep', '#resideparaisofiscal', '#resideexterior',
            '#cumplenormativaso', '#actividadso', '#fechainformepep', '#fechaconfirmapep',
            '#fechainformenosis', '#fechavencimientodni', '#fechavencimientoactividad',
            '#firmodeclaracionjurada', '#fechafirmapep'
        ];
        ids.forEach(function (sel) {
            $(sel).prop('required', false).removeAttr('required').removeClass('required');
        });
    }

    function aplicarRestriccionPerfilClienteUif() {
        var perfil = $('#uif_perfil_cliente').val();
        var cid = ($('#cliente_uif_id').val() || '').trim();
        var esEdicion = cid !== '' && cid !== '0';
        var $tabs = $('#botonform1, #botonform2, #botonform3, #botonform4, #botonform5');

        if (perfil === 'supervisor') {
            return;
        }

        // Alta: operador con permiso de crear actúa como cajero (defaults + SO/PEP).
        var perfilRestringido = (perfil === 'cajero') || (!esEdicion && perfil === 'operador');

        if (perfilRestringido) {
            var $f2 = $('.form2').find('input:not([type=hidden]), select, textarea, button');
            $f2.prop('disabled', true).addClass('bg-light');
            $('#so_uif_id, #pep_uif_id, #fechafirmapep').prop('disabled', false).removeClass('bg-light');
            quitarRequiredCumplimientoUifCajero();

            $('.form4').find('input:not([type=hidden]), select, textarea, button')
                .prop('disabled', true)
                .addClass('bg-light');

            asegurarHiddenCamposUifCajero();
        }

        if (!esEdicion) {
            return;
        }

        if (perfil === 'operador') {
            $('.form1, .form2, .form3, .form4, .form5').find('input:not([type=hidden]), select, textarea, button')
                .prop('disabled', true)
                .addClass('bg-light');
            $tabs.prop('disabled', false).removeClass('bg-light');
            $('#botonestado').prop('disabled', true).addClass('bg-light');
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

    function borraRenglonPremio(event) {
    	event.preventDefault();
        var $tr = $(this).parents('tr');
        var id = ($tr.find('.premio_id').val() || '').trim();

        if (!id) {
            if (!window.confirm('¿Quitar esta línea de la grilla?')) {
                return;
            }
            $tr.remove();
            actualizaRenglonesPremio();
            return;
        }

        if (!window.confirm('¿Eliminar este premio de forma permanente?')) {
            return;
        }

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    
        var url = carpetaBase+"/uif/elimina_premio_uif";

        $.ajax({
            type: "POST",
            url: url,
            data: {
                id: id
            },
            success: function (data) {
                if (!data || data.mensaje !== 'ok') {
                    alert("No se pudo borrar el premio");
                    return;
                }
                alert("Premio borrado con éxito");
                try {
                    var u = new URL(window.location.href);
                    u.searchParams.set('uif_tab', '3');
                    window.location.href = u.toString();
                } catch (e) {
                    location.reload();
                }
            },
            error: function (r) {
                alert("No se pudo borrar el premio");
            }
        });
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
        verificaAlertaUif();
    }

    function borraRenglonArchivo(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
        verificaAlertaUif();
    }

    function borraTarjetaArchivoClienteUif(event) {
        event.preventDefault();
        var $wrap = $(this).closest('.cliente-uif-archivo-item');
        if ($wrap.length) {
            $wrap.remove();
            verificaAlertaUif();
            return;
        }
        $(this).closest('.col-md-6').remove();
        verificaAlertaUif();
    }

    function actualizaArchivo(elem) {
	  	var fn = $(elem).val();
		var filename = fn.match(/[^\\/]*$/)[0]; // remove C:\fakename

		$(elem).parents("tr").find(".nombresanteriores").val(filename);
	}

    /** Valor de input fecha → timestamp y YYYY-MM-DD para comparar y para formateaFecha. */
    function parseFechaCampoUif(val) {
        val = String(val || '').trim();
        if (!val) {
            return null;
        }
        var iso = null;
        var m = val.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            iso = m[1] + '-' + m[2] + '-' + m[3];
        } else {
            m = val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (m) {
                iso = m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
            }
        }
        if (!iso) {
            var t = Date.parse(val);
            if (!isFinite(t)) {
                return null;
            }
            return { ts: t, isoYmd: val };
        }
        var ts = Date.parse(iso);
        if (!isFinite(ts)) {
            return null;
        }
        return { ts: ts, isoYmd: iso };
    }

    function actualizaRequeridosSujetoObligado() {
        // Cajero: no reponer required en campos deshabilitados (bloquea el alta).
        if ($('#essupervisor').val() !== 'S') {
            $('#actividadso, #cumplenormativaso').prop('required', false).removeAttr('required');
            marcarCampoObligatorioSiExiste(document.getElementById('actividadso'), false);
            marcarCampoObligatorioSiExiste(document.getElementById('cumplenormativaso'), false);
            return;
        }
        var esSo = String($('#so_uif_id').val()) === '2';
        $('#actividadso, #cumplenormativaso').prop('required', esSo);
        if (!esSo) {
            marcarCampoObligatorioSiExiste(document.getElementById('actividadso'), false);
            marcarCampoObligatorioSiExiste(document.getElementById('cumplenormativaso'), false);
        }
    }

    function marcarCampoObligatorioSiExiste(campo, invalido) {
        if (!campo) {
            return;
        }
        if (typeof marcarCampoObligatorio === 'function') {
            marcarCampoObligatorio(campo, invalido);
        } else {
            campo.style.borderColor = invalido ? '#dc3545' : '';
        }
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
        actualizaRequeridosSujetoObligado();
    }

    function esEdicionClienteUif() {
        var cid = parseInt(($('#cliente_uif_id').val() || '').trim(), 10);
        return !isNaN(cid) && cid > 0;
    }

    function digitoVerificadorCuitUif(base10) {
        var mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        var s = String(base10).replace(/\D/g, '').padStart(10, '0').slice(-10);
        var sum = 0;
        for (var i = 0; i < 10; i++) {
            sum += parseInt(s.charAt(i), 10) * mult[i];
        }
        var resto = 11 - (sum % 11);
        if (resto === 11) return 0;
        if (resto === 10) return 9;
        return resto;
    }

    function precargarCuitDesdeDniYSexo() {
        if ($('#cuit').val().trim()) {
            return;
        }
        var dni = $('#numerodocumento').val().replace(/\D/g, '');
        if (dni.length === 11) {
            dni = dni.substring(2, 10);
        }
        if (dni.length < 7 || dni.length > 8) {
            return;
        }
        dni = dni.padStart(8, '0');
        var sexo = $('#sexo').val();
        if (!sexo) {
            return;
        }
        var tipo = (sexo === 'FEMENINO') ? '27' : '20';
        var dv = digitoVerificadorCuitUif(tipo + dni);
        var cuit11 = tipo + dni + String(dv);
        $('#cuit').val(cuit11.substring(0, 2) + '-' + cuit11.substring(2, 10) + '-' + cuit11.substring(10));
    }

    function esTipoDocumentoCuitUif() {
        var texto = ($('#tipodocumento_id option:selected').text() || '').replace(/\./g, '').trim().toUpperCase();
        return texto === 'CUIT' || texto.indexOf('CUIT') !== -1;
    }

    function cuitCuilParaInferirSexoUif() {
        var cuit = ($('#cuit').val() || '').replace(/\D/g, '');
        if (cuit.length === 11) {
            return $('#cuit').val();
        }
        if (esTipoDocumentoCuitUif()) {
            var nro = ($('#numerodocumento').val() || '').replace(/\D/g, '');
            if (nro.length === 11) {
                return $('#numerodocumento').val();
            }
        }
        return '';
    }

    function predefinirSexoDesdeNombreUif() {
        if ($('#sexo').val()) {
            return;
        }
        if (typeof window.inferirSexoUifDesdeNombre !== 'function') {
            return;
        }
        var sexo = window.inferirSexoUifDesdeNombre($('#nombre').val(), {
            cuit: cuitCuilParaInferirSexoUif(),
        });
        if (sexo) {
            $('#sexo').val(sexo).trigger('change');
        }
    }

    function inicializarDocumentoYCuitUif() {
        $('#tipodocumento_id').on('change', function () {
            var $nro = $('#numerodocumento');
            if (esTipoDocumentoCuitUif()) {
                $nro.attr('placeholder', 'XX-XXXXXXXX-X');
                if ($nro.val() && typeof formatarCUIT === 'function') {
                    formatarCUIT($nro[0]);
                }
                $nro.off('input.cuitFormat').on('input.cuitFormat', function () {
                    formatarCUIT(this);
                });
            } else {
                $nro.removeAttr('placeholder');
                $nro.off('input.cuitFormat');
            }
        });

        $('#numerodocumento').on('blur change', function () {
            if (esTipoDocumentoCuitUif()) {
                if (typeof formatarCUIT === 'function') {
                    formatarCUIT(this);
                }
                var dig = $(this).val().replace(/\D/g, '');
                if (dig.length === 11) {
                    $('#cuit').val(
                        dig.substring(0, 2) + '-' + dig.substring(2, 10) + '-' + dig.substring(10)
                    );
                }
            } else {
                precargarCuitDesdeDniYSexo();
            }
        });

        $('#sexo').on('change', precargarCuitDesdeDniYSexo);

        $('#cuit').on('blur', function () {
            if (typeof formatarCUIT === 'function') {
                formatarCUIT(this);
            }
            var dig = $(this).val().replace(/\D/g, '');
            if (dig.length === 11 && !esTipoDocumentoCuitUif()) {
                $('#numerodocumento').val(dig.substring(2, 10));
            }
            predefinirSexoDesdeNombreUif();
        });

        $('#nombre').on('blur', predefinirSexoDesdeNombreUif);

        $('#tipodocumento_id').trigger('change');
        if ($('#cuit').val()) {
            var cEl = document.getElementById('cuit');
            if (cEl && typeof formatarCUIT === 'function') {
                formatarCUIT(cEl);
            }
        }
    }

    var SELECTORES_CAMPO_ALERTA_UIF = [
        '#div-fotodocumento', '#div-archivos-uif',
        '#div-fechafirmapep', '#div-fechaconfirmapep', '#div-fechavencimientodni',
        '#div-fechavencimientoactividad', '#div-firmodeclaracionjurada', '#div-riesgopep',
        '#div-fechainformenosis', '#div-fechainformepep'
    ];

    function tieneFotoDocumentoUif() {
        var input = document.getElementById('fotodocumento');
        if (input && input.files && input.files.length) {
            return true;
        }
        return $('.cliente-uif-fotodocumento-vista').length > 0;
    }

    function tieneArchivosAdjuntosUif() {
        if ($('.cliente-uif-archivo-item').length) {
            return true;
        }
        var tieneNuevo = false;
        $('#tbody-tabla-archivo input[type=file]').each(function () {
            if (this.files && this.files.length) {
                tieneNuevo = true;
                return false;
            }
        });
        return tieneNuevo;
    }

    function marcarDivAlertaUif(selector, activo) {
        var $el = $(selector);
        $el.toggleClass('uif-campo-pendiente', !!activo);
        if (!activo) {
            $el.css('color', '');
        }
    }

    function limpiarMarcasAlertaUif() {
        SELECTORES_CAMPO_ALERTA_UIF.forEach(function (sel) {
            marcarDivAlertaUif(sel, false);
        });
    }

    function asegurarBadgeTabUif(btnSelector, badgeId) {
        var $btn = $(btnSelector);
        if (!$btn.length) {
            return $();
        }
        var $badge = $btn.find('#' + badgeId);
        if (!$badge.length) {
            $badge = $('<span/>', {
                id: badgeId,
                class: 'badge badge-danger uif-tab-badge d-none',
                text: '0'
            });
            $btn.append(' ').append($badge);
        }
        return $badge;
    }

    function actualizarBadgesTabUif(items) {
        var porTab = { 1: 0, 2: 0, 5: 0 };
        (items || []).forEach(function (item) {
            var tab = String(item.tab || '');
            if (porTab[tab] !== undefined) {
                porTab[tab] += 1;
            }
        });
        [
            { btn: '#botonform1', id: 'uif-badge-form1', n: porTab[1] },
            { btn: '#botonform2', id: 'uif-badge-form2', n: porTab[2] },
            { btn: '#botonform5', id: 'uif-badge-form5', n: porTab[5] }
        ].forEach(function (cfg) {
            var $badge = asegurarBadgeTabUif(cfg.btn, cfg.id);
            if (!$badge.length) {
                return;
            }
            if (cfg.n > 0) {
                $badge.text(cfg.n).removeClass('d-none');
            } else {
                $badge.addClass('d-none').text('0');
            }
        });
    }

    function irAPendienteUif(tab, selector) {
        var n = parseInt(tab, 10);
        var $btnTab = n >= 1 && n <= 5 ? $('#botonform' + n) : $();
        if ($btnTab.length) {
            $btnTab.trigger('click');
        } else {
            var cid = ($('#cliente_uif_id').val() || '').trim();
            if (cid && cid !== '0' && n >= 1 && n <= 5) {
                window.location.href = carpetaBase + '/uif/cliente_uif/' + cid + '/editar?uif_tab=' + n;
                return;
            }
        }
        if (!selector) {
            return;
        }
        setTimeout(function () {
            var el = document.querySelector(selector);
            if (el && typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 80);
    }

    function mostrarModalCumplimientoPremioUif(hrefContinuar, paraGuardarCliente) {
        verificaAlertaUif();
        var $box = $('#uif-alertas-cumplimiento');
        var $lista = $('#uif-alertas-cumplimiento-lista');
        var $modal = $('#uif-modal-cumplimiento');
        if (!$modal.length || !$box.length || $box.hasClass('d-none') || !$lista.children().length) {
            return false;
        }
        $('#uif-modal-cumplimiento-titulo').text($('#uif-alertas-cumplimiento-titulo').text() || 'Faltan documentos o firmas UIF');
        var subtitulo = ($('#uif-alertas-cumplimiento-subtitulo').text() || '').trim();
        $('#uif-modal-cumplimiento-subtitulo').text(subtitulo).toggleClass('d-none', !subtitulo);
        $('#uif-modal-cumplimiento-contador').text($lista.children().length);
        $('#uif-modal-cumplimiento-lista').html($lista.html());
        var $header = $('#uif-modal-cumplimiento-header');
        $header.removeClass('bg-warning bg-danger text-dark text-white');
        if ($box.hasClass('is-warning')) {
            $header.addClass('bg-warning text-dark');
            $header.find('.close').removeClass('text-white').addClass('text-dark');
        } else {
            $header.addClass('bg-danger text-white');
            $header.find('.close').removeClass('text-dark').addClass('text-white');
        }
        var $continuar = $('#uif-modal-cumplimiento-continuar');
        hrefContinuar = (hrefContinuar || '#').trim() || '#';
        $continuar.attr('href', hrefContinuar);
        if (hrefContinuar !== '#' || paraGuardarCliente) {
            $continuar.removeClass('d-none');
            $continuar.text(paraGuardarCliente ? 'Guardar cliente y continuar' : 'Continuar con el premio');
        } else {
            $continuar.addClass('d-none');
        }
        var $ficha = $('#uif-modal-cumplimiento-ficha');
        var primerTab = $lista.find('[data-uif-ir-tab]').first().attr('data-uif-ir-tab') || '2';
        $ficha.attr('data-uif-ir-tab', primerTab);
        if ($('#botonform' + primerTab).length || (($ficha.attr('href') || '#') !== '#')) {
            $ficha.removeClass('d-none');
        }
        $modal.modal('show');
        return true;
    }

    function renderAlertasCumplimientoUif(items, opciones) {
        var $box = $('#uif-alertas-cumplimiento');
        var $lista = $('#uif-alertas-cumplimiento-lista');
        var $titulo = $('#uif-alertas-cumplimiento-titulo');
        var $subtitulo = $('#uif-alertas-cumplimiento-subtitulo');
        var $contador = $('#uif-alertas-cumplimiento-contador');
        var $acciones = $('#uif-alertas-cumplimiento-acciones');
        if (!$box.length || !$lista.length) {
            return;
        }
        opciones = opciones || {};
        items = items || [];
        $lista.empty();
        if ($acciones.length) {
            $acciones.empty();
        }
        actualizarBadgesTabUif(items);
        if (!items.length) {
            $box.addClass('d-none').removeClass('is-warning is-danger');
            return;
        }
        if ($titulo.length) {
            $titulo.text(opciones.titulo || 'Faltan documentos o firmas UIF');
        }
        if ($subtitulo.length) {
            $subtitulo.text(opciones.subtitulo || '');
            $subtitulo.toggleClass('d-none', !opciones.subtitulo);
        }
        if ($contador.length) {
            $contador.text(items.length);
        }
        $box
            .removeClass('is-warning is-danger alert-warning alert-info alert-danger')
            .addClass(opciones.claseBanner || 'is-danger');
        items.forEach(function (item) {
            var texto = typeof item === 'string' ? item : (item.texto || '');
            var $li = $('<li/>');
            if (item && (item.tab || item.selector)) {
                var $a = $('<a/>', {
                    href: '#',
                    class: 'uif-banner-item-link',
                    text: texto
                });
                $a.attr('data-uif-ir-tab', item.tab || '');
                $a.attr('data-uif-selector', item.selector || '');
                $li.append($a);
            } else {
                $li.text(texto);
            }
            $lista.append($li);
        });
        if ($acciones.length) {
            var tabsUsados = {};
            var botones = [
                { tab: '1', label: 'Datos principales' },
                { tab: '2', label: 'Datos UIF' },
                { tab: '5', label: 'Archivos asociados' }
            ];
            items.forEach(function (item) {
                if (item && item.tab) {
                    tabsUsados[String(item.tab)] = true;
                }
            });
            botones.forEach(function (btn) {
                if (!tabsUsados[btn.tab]) {
                    return;
                }
                $acciones.append(
                    $('<button/>', {
                        type: 'button',
                        class: 'btn btn-sm btn-outline-dark',
                        text: 'Ir a ' + btn.label
                    }).attr('data-uif-ir-tab', btn.tab)
                );
            });
        }
        $box.removeClass('d-none');
    }

    function avisoUif(texto, tab, selector) {
        return { texto: texto, tab: tab, selector: selector };
    }

    function verificaAlertaUif() {
        if (!$('#uif-alertas-cumplimiento').length) {
            return;
        }

        limpiarMarcasAlertaUif();

        var esSupervisor = $('#essupervisor').val() === 'S';
        var avisos = [];

        if (!tieneFotoDocumentoUif()) {
            avisos.push(avisoUif(
                'Pedí y adjuntá la foto o PDF del DNI.',
                '1',
                '#div-fotodocumento'
            ));
            marcarDivAlertaUif('#div-fotodocumento', true);
        }

        if (!tieneArchivosAdjuntosUif()) {
            avisos.push(avisoUif(
                'Adjuntá documentación de respaldo (declaración jurada, informes, constancias) en Archivos asociados.',
                '5',
                '#div-archivos-uif'
            ));
            marcarDivAlertaUif('#div-archivos-uif', true);
        }

        if (!esSupervisor) {
            if (!parseFechaCampoUif($('#fechafirmapep').val())) {
                avisos.push(avisoUif(
                    'Pedí la firma PEP y cargá la fecha de última firma.',
                    '2',
                    '#div-fechafirmapep'
                ));
                marcarDivAlertaUif('#div-fechafirmapep', true);
            }
            if (!parseFechaCampoUif($('#fechaconfirmapep').val())) {
                avisos.push(avisoUif(
                    'Falta validación de firma PEP (la completa Enc-UIF).',
                    '2',
                    '#div-fechaconfirmapep'
                ));
                marcarDivAlertaUif('#div-fechaconfirmapep', true);
            }
            if (!parseFechaCampoUif($('#fechavencimientodni').val())) {
                avisos.push(avisoUif(
                    'Pedí el DNI vigente; el vencimiento lo carga Enc-UIF.',
                    '2',
                    '#div-fechavencimientodni'
                ));
                marcarDivAlertaUif('#div-fechavencimientodni', true);
            }
            if (!parseFechaCampoUif($('#fechavencimientoactividad').val())) {
                avisos.push(avisoUif(
                    'Pedí constancia de actividad económica (vencimiento lo carga Enc-UIF).',
                    '2',
                    '#div-fechavencimientoactividad'
                ));
                marcarDivAlertaUif('#div-fechavencimientoactividad', true);
            }
            if ($('#firmodeclaracionjurada').val() !== 'S') {
                avisos.push(avisoUif(
                    'Pedí la declaración jurada firmada de origen de ingresos/fondos.',
                    '2',
                    '#div-firmodeclaracionjurada'
                ));
                marcarDivAlertaUif('#div-firmodeclaracionjurada', true);
            }
            renderAlertasCumplimientoUif(avisos, {
                titulo: 'Pedí al cliente estos documentos y firmas',
                subtitulo: 'Adjuntá lo que puedas ahora. Enc-UIF completa fechas de validación, vencimientos e informes.',
                claseBanner: 'is-warning'
            });
            return;
        }

        var fechaBase = new Date();
        var fecha6Meses = new Date(fechaBase.getTime());
        fecha6Meses.setMonth(fecha6Meses.getMonth() - 6);
        var umbral6MesesMs = fecha6Meses.getTime();

        var fechaConfirmaPep = $('#fechaconfirmapep').val();
        var parsedConfPep = parseFechaCampoUif(fechaConfirmaPep);
        if (!parsedConfPep) {
            avisos.push(avisoUif(
                'PEP: falta fecha de validación de última firma' + (String(fechaConfirmaPep || '').trim() ? ' (fecha no válida).' : '.'),
                '2',
                '#div-fechaconfirmapep'
            ));
            marcarDivAlertaUif('#div-fechafirmapep', true);
            marcarDivAlertaUif('#div-fechaconfirmapep', true);
        } else if (parsedConfPep.ts < umbral6MesesMs) {
            avisos.push(avisoUif(
                'PEP: debe renovar firma (última validación: ' + formateaFecha(parsedConfPep.isoYmd) + ').',
                '2',
                '#div-fechaconfirmapep'
            ));
            marcarDivAlertaUif('#div-fechafirmapep', true);
            marcarDivAlertaUif('#div-fechaconfirmapep', true);
        }

        var parsedDni = parseFechaCampoUif($('#fechavencimientodni').val());
        if (!parsedDni) {
            avisos.push(avisoUif(
                'DNI: falta o es inválida la fecha de vencimiento.',
                '2',
                '#div-fechavencimientodni'
            ));
            marcarDivAlertaUif('#div-fechavencimientodni', true);
        } else if (parsedDni.ts < Date.now()) {
            avisos.push(avisoUif(
                'DNI: vencido el ' + formateaFecha(parsedDni.isoYmd) + '.',
                '2',
                '#div-fechavencimientodni'
            ));
            marcarDivAlertaUif('#div-fechavencimientodni', true);
        }

        var parsedVtoAct = parseFechaCampoUif($('#fechavencimientoactividad').val());
        if (!parsedVtoAct) {
            avisos.push(avisoUif(
                'Actividad económica: falta o es inválida la fecha de vencimiento.',
                '2',
                '#div-fechavencimientoactividad'
            ));
            marcarDivAlertaUif('#div-fechavencimientoactividad', true);
        } else if (parsedVtoAct.ts < umbral6MesesMs) {
            avisos.push(avisoUif(
                'Actividad económica: vencimiento próximo o vencido (' + formateaFecha(parsedVtoAct.isoYmd) + ').',
                '2',
                '#div-fechavencimientoactividad'
            ));
            marcarDivAlertaUif('#div-fechavencimientoactividad', true);
        }

        if ($('#firmodeclaracionjurada').val() !== 'S') {
            avisos.push(avisoUif(
                'Falta declaración jurada firmada de origen de ingresos y/o fondos.',
                '2',
                '#div-firmodeclaracionjurada'
            ));
            marcarDivAlertaUif('#div-firmodeclaracionjurada', true);
        }

        if ($('#riesgopep').val() === 'ALTO') {
            avisos.push(avisoUif(
                'Nivel de riesgo PEP: ALTO.',
                '2',
                '#div-riesgopep'
            ));
            marcarDivAlertaUif('#div-riesgopep', true);
        }

        var parsedNosis = parseFechaCampoUif($('#fechainformenosis').val());
        if (!parsedNosis) {
            avisos.push(avisoUif(
                'Informe NOSIS: sin fecha o fecha inválida.',
                '2',
                '#div-fechainformenosis'
            ));
            marcarDivAlertaUif('#div-fechainformenosis', true);
        } else if (parsedNosis.ts < umbral6MesesMs) {
            avisos.push(avisoUif(
                'Informe NOSIS: debe renovar (último: ' + formateaFecha(parsedNosis.isoYmd) + ').',
                '2',
                '#div-fechainformenosis'
            ));
            marcarDivAlertaUif('#div-fechainformenosis', true);
        }

        var parsedInfPep = parseFechaCampoUif($('#fechainformepep').val());
        if (!parsedInfPep) {
            avisos.push(avisoUif(
                'Informe PEP: sin fecha o fecha inválida.',
                '2',
                '#div-fechainformepep'
            ));
            marcarDivAlertaUif('#div-fechainformepep', true);
        } else if (parsedInfPep.ts < umbral6MesesMs) {
            avisos.push(avisoUif(
                'Informe PEP: debe renovar (último: ' + formateaFecha(parsedInfPep.isoYmd) + ').',
                '2',
                '#div-fechainformepep'
            ));
            marcarDivAlertaUif('#div-fechainformepep', true);
        }

        renderAlertasCumplimientoUif(avisos, {
            titulo: 'Faltan documentos o firmas de cumplimiento UIF',
            subtitulo: 'Completá o renová estos requisitos. Tocá un ítem para ir al campo.',
            claseBanner: 'is-danger'
        });
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
