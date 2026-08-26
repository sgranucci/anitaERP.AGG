
    var ptrriesgo;
    var premioUifEnviando = false;

    $(function () {
        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
        });

        $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
        $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
        $(document).on('click', '.eliminar-archivo-premio-uif', borraTarjetaArchivoPremioUif);
        $('#botonform0').on('click', enviarFormularioPremioUif);
        $('#form-general').on('submit', function (e) {
            if (premioUifEnviando) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
            if (!formularioPremioUifEsValido(this)) {
                return true;
            }
            marcarPremioUifEnviando();
            return true;
        });
        window.addEventListener('pageshow', finalizarBannerGrabacionPremioUif);
        window.addEventListener('pagehide', ocultarOverlayGuardandoPremioUif);

        activa_eventos(true);

        var $foto = $('#foto');
        var previewUrl = ($foto.data('initial-preview') || '').toString().trim();
        var tienePreviewReal = previewUrl !== ''
            && previewUrl.indexOf('user2-160x160') === -1;

        $foto.fileinput({
            language: 'es',
            allowedFileExtensions: ['jpg', 'jpeg', 'png'],
            maxFileSize: 2000,
            showUpload: false,
            showClose: false,
            initialPreviewAsData: true,
            initialPreview: tienePreviewReal ? [previewUrl] : [],
            previewSettings: { image: { 'max-height': '180px', 'max-width': '100%' } },
            dropZoneEnabled: false,
            theme: 'fa',
            browseClass: 'btn btn-outline-primary btn-sm',
            removeClass: 'btn btn-outline-danger btn-sm',
        });

        mostrarModalCumplimientoPremioAltaUif();
    });
	
    function mostrarModalCumplimientoPremioAltaUif() {
        var $modal = $('#uif-modal-cumplimiento');
        if (!$modal.length || !$modal.find('#uif-modal-cumplimiento-lista li').length) {
            return;
        }
        var yaMostrado = false;
        try {
            yaMostrado = sessionStorage.getItem('uif-cumplimiento-premio-aviso') === '1';
            sessionStorage.removeItem('uif-cumplimiento-premio-aviso');
        } catch (e) {}
        var esAlta = ($('#form-general').find('input[name="_method"]').val() || '').toUpperCase() !== 'PUT';
        if (yaMostrado || !esAlta) {
            return;
        }
        $modal.modal('show');
    }

    function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
		}
    }

    function enviarFormularioPremioUif(event) {
        if (event) {
            event.preventDefault();
        }
        if (premioUifEnviando) {
            return;
        }
        var form = document.getElementById('form-general');
        if (!form) {
            return;
        }
        if (!formularioPremioUifEsValido(form, true)) {
            return;
        }
        marcarPremioUifEnviando();
        form.submit();
    }

    function formularioPremioUifEsValido(form, reportar) {
        if (!form) {
            return false;
        }
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            if (reportar && typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
            return false;
        }
        return true;
    }

    function overlayGuardandoPremioUif() {
        return document.getElementById('premio-uif-guardando-overlay');
    }

    function mostrarOverlayGuardandoPremioUif() {
        var overlay = overlayGuardandoPremioUif();
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlayGuardandoPremioUif() {
        var overlay = overlayGuardandoPremioUif();
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function marcarPremioUifEnviando() {
        premioUifEnviando = true;
        var $btn = $('#botonform0');
        if ($btn.length) {
            $btn.prop('disabled', true);
            if (!$btn.data('html-original')) {
                $btn.data('html-original', $btn.html());
            }
            $btn.html('<i class="fa fa-spinner fa-spin"></i> Guardando…');
        }
        mostrarOverlayGuardandoPremioUif();
    }

    function restaurarBotonPremioUif() {
        premioUifEnviando = false;
        var $btn = $('#botonform0');
        if (!$btn.length) {
            return;
        }
        $btn.prop('disabled', false);
        var htmlOrig = $btn.data('html-original');
        if (htmlOrig) {
            $btn.html(htmlOrig);
        }
    }

    function finalizarBannerGrabacionPremioUif() {
        ocultarOverlayGuardandoPremioUif();
        restaurarBotonPremioUif();
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

    function borraTarjetaArchivoPremioUif(event) {
        event.preventDefault();
        var $wrap = $(this).closest('.cliente-premio-uif-archivo-item');
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