
    $(function () {
        $('#agrega_renglon_condicioniva').on('click', agregaRenglonCondicioniva);
        $(document).on('click', '.eliminar_condicioniva', borraRenglonCondicioniva);
    });

    function agregaRenglonCondicioniva(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-condicioniva').html();

    	$("#tbody-condicioniva-table").append(renglon);
    	actualizaRenglonesCondicioniva();
    }

    function borraRenglonCondicioniva() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCondicioniva();
    }

    function actualizaRenglonesCondicioniva() {
    	var item = 1;

    	$("#tbody-condicioniva-table .iicondicioniva").each(function() {
    		$(this).val(item++);
    	});
    }


