
    $(function () {
        $('#agrega_renglon_concepto').on('click', agregaRenglonConcepto);
        $(document).on('click', '.eliminar_concepto', borraRenglonConcepto);
    });

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


