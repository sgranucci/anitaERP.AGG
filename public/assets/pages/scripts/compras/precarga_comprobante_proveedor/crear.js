
    $(function () {
        $('#agrega_renglon_concepto').on('click', agregaRenglonConcepto);
        $(document).on('click', '.eliminar_concepto', borraRenglonConcepto);

        $('#empresa_id').on('change', actualizarCodigoEmpresa);
        $('#tipotransaccion_compra_id').on('change', actualizarTipoComprobante);

        actualizarCodigoEmpresa();
        actualizarTipoComprobante();
    });


    $(function () {
        if (typeof activa_eventos_consultaproveedor === 'function') {
            activa_eventos_consultaproveedor();
        }
    });

    function actualizarCodigoEmpresa() {
        var codigo = $('#empresa_id').find('option:selected').data('codigo') || '';
        $('#codigoempresa').val(codigo);
    }

    function actualizarTipoComprobante() {
        var abreviatura = $('#tipotransaccion_compra_id').find('option:selected').data('abreviatura') || '';
        $('#tipo').val(abreviatura);
    }

    function agregaRenglonConcepto(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-concepto').html();

    	$("#tbody-concepto-table").append(renglon);
    	actualizaRenglonesConcepto();
    }

    function borraRenglonConcepto() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesConcepto();
    }

    function actualizaRenglonesConcepto() {
    	var item = 1;

    	$("#tbody-concepto-table .iiconcepto").each(function() {
    		$(this).val(item++);
    	});
    }

