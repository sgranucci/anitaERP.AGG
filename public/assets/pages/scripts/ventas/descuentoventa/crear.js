
    $(function () {
	    $("#tipodescuento").change(function(){
			activaCampos();
        });
	    
		activaCampos();
		
		$("#nombre").focus(); 
    });

	function activaCampos()
	{
		let tipodescuento = $('#tipodescuento').val();

		switch (tipodescuento) {
            case 'POR PORCENTAJE':
				$("#div-porcentajedescuento").show();
				$("#div-montodescuento").hide();
				$("#div-cantidadventa").hide();
				$("#div-cantidaddescuento").hide();				
                break;
            case 'POR MONTO FIJO':
				$("#div-porcentajedescuento").hide();
				$("#div-montodescuento").show();
				$("#div-cantidadventa").hide();
				$("#div-cantidaddescuento").hide();
                break;
            case 'POR CANTIDAD VENDIDA':
				$("#div-porcentajedescuento").show();
				$("#div-montodescuento").hide();
				$("#div-cantidadventa").show();
				$("#div-cantidaddescuento").show();
				break;
			}
	}


