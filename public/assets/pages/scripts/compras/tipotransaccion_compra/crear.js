
    $(function () {
        $('#agrega_renglon_centrocosto').on('click', agregaRenglonCentrocosto);
        $(document).on('click', '.eliminar_centrocosto', borraRenglonCentrocosto);
        $('#agrega_renglon_concepto_ivacompra').on('click', agregaRenglonConcepto_Ivacompra);
        $(document).on('click', '.eliminar_concepto_ivacompra', borraRenglonConcepto_Ivacompra);
    });

    function agregaRenglonCentrocosto(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-centrocosto').html();
    	$("#tbody-centrocosto-table").append(renglon);
    	actualizaRenglonesCentrocosto();
    }

    function borraRenglonCentrocosto() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCentrocosto();
    }

    function actualizaRenglonesCentrocosto() {
    	var item = 1;

    	$("#tbody-centrocosto-table .iicentrocosto").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonConcepto_Ivacompra(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-concepto_ivacompra').html();

        $("#tbody-concepto-ivacompra-table").append(renglon);
    	actualizaRenglonesConcepto_Ivacompra();
    }

    function borraRenglonConcepto_Ivacompra() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesConcepto_Ivacompra();
    }

    function actualizaRenglonesConcepto_Ivacompra() {
    	var item = 1;

    	$("#tbody-concepto_ivacompra-table .iiconcepto_ivacompra").each(function() {
    		$(this).val(item++);
    	});
    }

