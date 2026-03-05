
    $(function () {
        $('#agrega_renglon_vendedorasociado').on('click', agregaRenglon);
        $(document).on('click', '.eliminar_vendedorasociado', borraRenglon);

        $("#botonform1").click(function(){
            $(".form1").show();
            $(".form2").hide();
        });

        $("#botonform2").click(function(){
            $(".form1").hide();
            $(".form2").show();

            // Si no tiene items agrega el primero
            if($('.item-vendedor-asociado').length == 0)
                agregaRenglon(event);
        });

        activa_eventos(true);
    });

    function agregaRenglon(event){
        event.preventDefault();
        var renglon = $('#template-renglon-vendedor-asociado').html();

        $("#tbody-vendedor-asociado-table").append(renglon);

        activa_eventos(false);

        $('#vendedor-asociado-table').find('tr').last().find('.codigovendedor').focus();
    }

    function borraRenglon(event) {
        event.preventDefault();

        $(this).parents('tr').remove();
    }

    function activa_eventos(flInicio)
	{
		// Si esta agregando items desactiva los eventos
		if (!flInicio)
		{
            desactiva_eventos_consulta_vendedor();

            $(document).off('click', '.eliminar_vendedorasociado');
            $('.codigovendedor').off('change');
		}

		activa_eventos_consultavendedor();

        $(document).on('click', '.eliminar_vendedorasociado', borraRenglon);

        $(".codigovendedor").change(function(){
            setTimeout(() => {
                let codigovendedor = $(this).parents("tr").find('.codigovendedor').val();
                let ptrActual = this;

                // Verifica si cargo duplicado un vendedor
                $("#tbody-vendedor-asociado-table .codigovendedor").each(function(){

                    let codigoVendedorTabla = $(this).val();

                    if (codigoVendedorTabla == codigovendedor && this != ptrActual)
                    {
                        alert("Vendedor ya cargado");

                        $(ptrActual).parents("tr").find('.codigovendedor').val('');
                        $(ptrActual).parents("tr").find('.nombrevendedor').val('');
                    }
                });
                    
            }, 300);	
        });

    }

    function enviaFormulario()
	{
        $('#formgeneral').submit();
    }

