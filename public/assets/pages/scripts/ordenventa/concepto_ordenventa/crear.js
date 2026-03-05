    $(function () {

        $('#agrega_renglon_cuentacontable').on('click', agregaRenglonCuentacontable);
        $(document).on('click', '.eliminar_cuentacontable', borraRenglonCuentacontable);

        $( ".botonsubmit" ).click(function() {
            $( "#form-general" ).submit();
        });

        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
        });

		activa_eventos(true);
    });

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuentacontable').off('click');
			$('.codigocuentacontable').off('change');
		}
		activa_eventos_consulta_cuentacontable();
	}

    function agregaRenglonCuentacontable(event){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuentacontable').html();

		$("#tbody-cuentacontable-table").append(renglon);
    	actualizaRenglonesCuentacontable();

        activa_eventos(false);
    }

    function borraRenglonCuentacontable(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuentacontable();
    }

    function actualizaRenglonesCuentacontable() {
    	var item = 1;

    	$("#tbody-cuentacontable-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }
		
    


