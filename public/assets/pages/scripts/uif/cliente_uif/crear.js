
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

        function sincronizarBarraAltaPremioUifCliente() {
            var $bar = $('#barra-alta-premio-uif-cliente');
            if (!$bar.length) {
                return;
            }
            $bar.toggle($('.form3').is(':visible'));
        }

        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();
            sincronizarBarraAltaPremioUifCliente();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").hide();

			$("#titulo").html("");
			$("#titulo").html("<span class='fa fa-cash-register'></span> Datos facturac&oacute;n");
            sincronizarBarraAltaPremioUifCliente();
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
            sincronizarBarraAltaPremioUifCliente();
        });

        $("#botonform4").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").show();
            $(".form5").hide();
            sincronizarBarraAltaPremioUifCliente();
        });

        $("#botonform5").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").hide();
            $(".form4").hide();
            $(".form5").show();
            sincronizarBarraAltaPremioUifCliente();
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
                sincronizarBarraAltaPremioUifCliente();
            }
        } else if (tabParam === '4') {
            $('#botonform4').trigger('click');
        } else if (tabParam === '5') {
            $('#botonform5').trigger('click');
        }
        sincronizarBarraAltaPremioUifCliente();

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
        $(document).on('change', '#actividad_uif_id, #nombreactividad_uif', function () {
            marcarCampoObligatorioSiExiste(document.getElementById('actividad_uif_id'), !$('#actividad_uif_id').val().trim());
        });

        inicializarDocumentoYCuitUif();

        if (esEdicionClienteUif()) {
            setTimeout(function () {
                verificaAlertaUif();
            }, 1500);
        }

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

        aplicarRestriccionPerfilClienteUif();
    });

    /**
     * Supervisor: sin bloqueos.
     * Cajero: solapa Datos UIF (form2) solo SO, PEP y fecha última firma PEP; solapa Riesgo (form4) solo lectura.
     *        Solapas 1, 3 y 5 sin restricción en esta función.
     * Operador (solo visualización en edición): todo bloqueado salvo botones de solapa.
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

    function aplicarRestriccionPerfilClienteUif() {
        var perfil = $('#uif_perfil_cliente').val();
        var cid = ($('#cliente_uif_id').val() || '').trim();
        var esEdicion = cid !== '' && cid !== '0';
        var $tabs = $('#botonform1, #botonform2, #botonform3, #botonform4, #botonform5');

        if (perfil === 'supervisor') {
            return;
        }

        if (perfil === 'cajero') {
            var $f2 = $('.form2').find('input:not([type=hidden]), select, textarea, button');
            $f2.prop('disabled', true).addClass('bg-light');
            $('#so_uif_id, #pep_uif_id, #fechafirmapep').prop('disabled', false).removeClass('bg-light');

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

    function agregaRenglonPremio(event){
    	event.preventDefault();
    	var renglon = $('#template-renglon-premio').html();

    	$("#tbody-tabla-premio").append(renglon);
    	actualizaRenglonesPremio();
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

    function marcarDivAlertaUif(selector, activo) {
        $(selector).css('color', activo ? '#dc3545' : '');
    }

    function renderAlertasCumplimientoUif(items) {
        var $box = $('#uif-alertas-cumplimiento');
        var $lista = $('#uif-alertas-cumplimiento-lista');
        if (!$box.length || !$lista.length) {
            return;
        }
        $lista.empty();
        if (!items.length) {
            $box.addClass('d-none');
            return;
        }
        items.forEach(function (txt) {
            $lista.append($('<li/>').text(txt));
        });
        $box.removeClass('d-none');
    }

    function verificaAlertaUif() {
        if (!esEdicionClienteUif()) {
            renderAlertasCumplimientoUif([]);
            return;
        }

        var avisos = [];
        var fechaBase = new Date();
        var fecha6Meses = new Date(fechaBase.getTime());
        fecha6Meses.setMonth(fecha6Meses.getMonth() - 6);
        var umbral6MesesMs = fecha6Meses.getTime();

        var idsResaltar = [
            '#div-fechafirmapep', '#div-fechaconfirmapep', '#div-fechavencimientodni',
            '#div-fechavencimientoactividad', '#div-firmodeclaracionjurada', '#div-riesgopep',
            '#div-fechainformenosis', '#div-fechainformepep'
        ];
        idsResaltar.forEach(function (sel) { marcarDivAlertaUif(sel, false); });

        var fechaConfirmaPep = $('#fechaconfirmapep').val();
        var parsedConfPep = parseFechaCampoUif(fechaConfirmaPep);
        if (!parsedConfPep) {
            avisos.push('PEP: falta fecha de validación de última firma' + (String(fechaConfirmaPep || '').trim() ? ' (fecha no válida).' : '.'));
            marcarDivAlertaUif('#div-fechafirmapep', true);
            marcarDivAlertaUif('#div-fechaconfirmapep', true);
        } else if (parsedConfPep.ts < umbral6MesesMs) {
            avisos.push('PEP: debe renovar firma (última validación: ' + formateaFecha(parsedConfPep.isoYmd) + ').');
            marcarDivAlertaUif('#div-fechafirmapep', true);
            marcarDivAlertaUif('#div-fechaconfirmapep', true);
        }

        var parsedDni = parseFechaCampoUif($('#fechavencimientodni').val());
        if (!parsedDni) {
            avisos.push('DNI: falta o es inválida la fecha de vencimiento.');
            marcarDivAlertaUif('#div-fechavencimientodni', true);
        } else if (parsedDni.ts < Date.now()) {
            avisos.push('DNI: vencido el ' + formateaFecha(parsedDni.isoYmd) + '.');
            marcarDivAlertaUif('#div-fechavencimientodni', true);
        }

        var parsedVtoAct = parseFechaCampoUif($('#fechavencimientoactividad').val());
        if (!parsedVtoAct) {
            avisos.push('Actividad económica: falta o es inválida la fecha de vencimiento.');
            marcarDivAlertaUif('#div-fechavencimientoactividad', true);
        } else if (parsedVtoAct.ts < umbral6MesesMs) {
            avisos.push('Actividad económica: vencimiento próximo o vencido (' + formateaFecha(parsedVtoAct.isoYmd) + ').');
            marcarDivAlertaUif('#div-fechavencimientoactividad', true);
        }

        if ($('#firmodeclaracionjurada').val() !== 'S') {
            avisos.push('Falta declaración jurada de origen de ingresos y/o fondos.');
            marcarDivAlertaUif('#div-firmodeclaracionjurada', true);
        }

        if ($('#riesgopep').val() === 'ALTO') {
            avisos.push('Nivel de riesgo PEP: ALTO.');
            marcarDivAlertaUif('#div-riesgopep', true);
        }

        if ($('#essupervisor').val() === 'S') {
            var parsedNosis = parseFechaCampoUif($('#fechainformenosis').val());
            if (!parsedNosis) {
                avisos.push('Informe NOSIS: sin fecha o fecha inválida.');
                marcarDivAlertaUif('#div-fechainformenosis', true);
            } else if (parsedNosis.ts < umbral6MesesMs) {
                avisos.push('Informe NOSIS: debe renovar (último: ' + formateaFecha(parsedNosis.isoYmd) + ').');
                marcarDivAlertaUif('#div-fechainformenosis', true);
            }

            var parsedInfPep = parseFechaCampoUif($('#fechainformepep').val());
            if (!parsedInfPep) {
                avisos.push('Informe PEP: sin fecha o fecha inválida.');
                marcarDivAlertaUif('#div-fechainformepep', true);
            } else if (parsedInfPep.ts < umbral6MesesMs) {
                avisos.push('Informe PEP: debe renovar (último: ' + formateaFecha(parsedInfPep.isoYmd) + ').');
                marcarDivAlertaUif('#div-fechainformepep', true);
            }
        }

        renderAlertasCumplimientoUif(avisos);
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
