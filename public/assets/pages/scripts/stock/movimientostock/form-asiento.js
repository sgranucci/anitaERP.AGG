$(function () {
    if (!$('#formgeneral').length || !$('#ms-solapa-asiento-contable').length) {
        return;
    }

    var $form = $('#formgeneral');
    var previewUrl = String($form.data('preview-url') || '');
    var tieneAsientoGrabado = String($form.data('tiene-asiento-grabado') || '0') === '1';
    var previewTimer = null;
    var previewXhr = null;

    function tipoManejaContabilidad() {
        return typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta().manejaContabilidad
            : false;
    }

    function mostrarSolapa(sel) {
        $('.ms-solapa').hide();
        $(sel).show();
    }

    function marcarTabActivo(btnDomId) {
        $('.ms-tab-solapa').removeClass('font-weight-bold');
        var $b = $('#' + btnDomId);
        if ($b.length) {
            $b.addClass('font-weight-bold');
        }
    }

    function actualizarBadgeAsiento(tieneProblema) {
        var $tab = $('#ms-boton-asiento-contable');
        if (!$tab.length) {
            return;
        }
        $tab.find('.ms-badge-asiento-error').remove();
        if (tieneProblema) {
            $tab.append('<span class="badge badge-warning ml-1 ms-badge-asiento-error" title="Revise el cuadre antes de guardar">!</span>');
        }
    }

    function toggleContabilidadUi() {
        var activo = tipoManejaContabilidad();
        $('#ms_panel_centrocosto').toggle(activo);
        $('#ms-boton-asiento-contable').toggle(activo);
        if (!activo) {
            mostrarSolapa('#ms-solapa-principal');
            marcarTabActivo('ms-boton-principal');
        }
    }

    function recargarPreviewAsiento() {
        if (!previewUrl || !tipoManejaContabilidad() || tieneAsientoGrabado) {
            return;
        }

        if (previewXhr && previewXhr.readyState !== 4) {
            previewXhr.abort();
        }

        previewXhr = $.ajax({
            url: previewUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            data: $form.serialize()
        })
            .done(function (res) {
                if (res && res.html) {
                    $('#ms-asiento-preview-body').html(res.html);
                }
                actualizarBadgeAsiento(!!(res && res.error));
            })
            .fail(function () {
                /* silencioso */
            });
    }

    function programarPreviewAsiento() {
        if (tieneAsientoGrabado || !tipoManejaContabilidad()) {
            return;
        }
        clearTimeout(previewTimer);
        previewTimer = setTimeout(recargarPreviewAsiento, 350);
    }

    $('#ms-boton-principal').on('click', function () {
        mostrarSolapa('#ms-solapa-principal');
        marcarTabActivo('ms-boton-principal');
    });

    $('#ms-boton-asiento-contable').on('click', function () {
        mostrarSolapa('#ms-solapa-asiento-contable');
        marcarTabActivo('ms-boton-asiento-contable');
        recargarPreviewAsiento();
    });

    $('#tipotransaccion_stock_id').on('change', function () {
        toggleContabilidadUi();
        programarPreviewAsiento();
    });

    $form.on('input change', '#fecha, #deposito_id, #deposito_salida_id, #deposito_entrada_id, #centrocosto_destino_id', function () {
        programarPreviewAsiento();
    });

        $(document).on('change input', '#tbody-tabla .codigoarticulo, #tbody-tabla .articulo_id, #tbody-tabla .cantidad', function () {
            programarPreviewAsiento();
        });

    $(document).on('click', '#agrega_renglon, .eliminar', function () {
        setTimeout(programarPreviewAsiento, 100);
    });

    toggleContabilidadUi();
    if (tipoManejaContabilidad() && !tieneAsientoGrabado) {
        programarPreviewAsiento();
    }

    window.movStockProgramarPreviewAsiento = programarPreviewAsiento;
});
