
    $(function () {
        $('#agrega_renglon_subcategoria').on('click', agregaRenglonSubcategoria);
        $(document).on('click', '.eliminar_subcategoria', borraRenglonSubcategoria);
    });

    function agregaRenglonSubcategoria(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-subcategoria-ticket').html();

    	$("#tbody-subcategoria-ticket-table").append(renglon);
    	actualizaRenglonesSubcategoria();
    }

    function borraRenglonSubcategoria() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesSubcategoria();
    }

    function actualizaRenglonesSubcategoria() {
    	var item = 1;

    	$("#tbody-subcategoria-ticket-table .iisubcategoria").each(function() {
    		$(this).val(item++);
    	});
    }


