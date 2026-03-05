    $(function () {

        $('#agrega_renglon_cuentacontable').on('click', agregaRenglonCuentaContable);
        $(document).on('click', '.eliminar_cuentacontable', borraRenglonCuentaContable);

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
        activa_tiporetencion();

        $("#tiporetencion").change(function(){
            activa_tiporetencion();
        });
    });

    function activa_tiporetencion()
    {
        let tiporetencion = $("#tiporetencion").val();

        if (tiporetencion != 'Ingresos Brutos')
            $("#provincia").hide();
        else
            $("#provincia").show();
    }

	function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
			$('.consultacuentacontable').off('click');
			$('.codigocuentacontable').off('change');
            $('.empresa').off('change');
		}
        activa_eventos_consulta_cuentacontable();
        activa_eventos_consultaprovincia();

        $('.empresa').change(function(){
            let empresa_id = $(this).val();
            let ptrActual = this;

            $("#tbody-cuentacontable-table .empresa").each(function() {
    		    let empresa_tbl_id = $(this).val();

                if (empresa_tbl_id == empresa_id && this != ptrActual)
                {
                    alert('Empresa ya cargada');

                    $(ptrActual).val('');
                }
    	    });
        });
	}

    function agregaRenglonCuentaContable(event){
    	event.preventDefault();
    	let renglon = $('#template-renglon-cuentacontable').html();

		$("#tbody-cuentacontable-table").append(renglon);
    	actualizaRenglonesCuentaContable();

        activa_eventos(false);
    }

    function borraRenglonCuentaContable(event) {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesCuentaContable();
    }

    function actualizaRenglonesCuentaContable() {
    	var item = 1;

    	$("#tbody-cuentacontable-table .iicuenta").each(function() {
    		$(this).val(item++);
    	});
    }
		
    


