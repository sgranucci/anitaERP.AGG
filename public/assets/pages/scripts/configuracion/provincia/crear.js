    $(function () {

        $('#agrega_renglon_tasaiibb').on('click', agregaRenglonTasaiibb);
        $(document).on('click', '.eliminar_tasaiibb', borraRenglonTasaiibb);
        $('#agrega_renglon_cuentacontableiibb').on('click', agregaRenglonCuentacontableiibb);
        $(document).on('click', '.eliminar_cuentacontableiibb', borraRenglonCuentacontableiibb);

        $( ".botonsubmit" ).click(function() {
            $( "#form-general" ).submit();
        });

        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
            $(".form3").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();
            $(".form3").hide();
        });

        $("#botonform3").click(function(){
            $(".form1").hide();
            $(".form2").hide();
            $(".form3").show();
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

    function agregaRenglonTasaiibb(event){
    	event.preventDefault();
    	let renglon = $('#template-renglon-tasaiibb').html();

		$("#tbody-tasaiibb-table").append(renglon);
    	actualizaRenglonesTasaiibb();
    }

    function borraRenglonTasaiibb(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesTasaiibb();
    }

    function actualizaRenglonesTasaiibb() {
    	var item = 1;

    	$("#tbody-tasaiibb-table .iitasaiibb").each(function() {
    		$(this).val(item++);
    	});
    }

    function agregaRenglonCuentacontableiibb(event){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuentacontableiibb').html();

		$("#tbody-cuentacontableiibb-table").append(renglon);
    	actualizaRenglonesCuentacontableiibb();

        activa_eventos(false);
    }

    function borraRenglonCuentacontableiibb(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuentacontableiibb();
    }

    function actualizaRenglonesCuentacontableiibb() {
    	var item = 1;

    	$("#tbody-cuentacontableiibb-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }
		
    


