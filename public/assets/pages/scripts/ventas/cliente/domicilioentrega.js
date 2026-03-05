// Carga de domicilio provincia/localidad/codigo postal
// Para abm de clientes en carga de lugares de Entrega sobre tabla

    function completarLocalidadesEntrega(provincia){
        var loc_id;
		var provincia_id = $(provincia).val();
        $.get('/anitaERP/public/configuracion/leerlocalidades/'+provincia_id, function(data){
            var loc = $.map(data, function(value, index){
                return [value];
            });
			$(provincia).parents("tr").find(".localidades").empty();
			$(provincia).parents("tr").find(".localidades").append('<option value=""></option>');
            $.each(loc, function(index,value){
				$(provincia).parents("tr").find(".localidades").append('<option value="'+value.id+'">'+value.nombre+'</option>');
            });
        });
        setTimeout(() => {
                var loc_id = $(provincia).parents("tr").find(".localidades").val();
				var codigopostal = $(provincia).parents("tr").find(".codigospostales");
                if (loc_id != undefined) {
                    completarCPEntrega(loc_id, codigopostal);
                }
        }, 3000);
    }

    function completarCPEntrega(localidad_id, codigopostal){
        $.get('/anitaERP/public/configuracion/leercodigopostal/'+localidad_id, function(data){
            if(data!=0){
                $(codigopostal).val(data);
            }
        });
    }

	function activaEventoEntrega()
	{
		$(".provincias").off('change');
		$(".localidades").off('change');

       	$(".provincias").on('change', function() {
           	var  provincia = $(this);
           	completarLocalidadesEntrega(provincia);
       	});
	
       	$(".localidades").change(function(){
           	var localidad_id = $(this).val();
			var codigopostal = $(this).parents("tr").find(".codigospostales");
			var localidad_id_previa = $(this).parents("tr").find(".localidad_id_previas");

			$(localidad_id_previa).val(localidad_id);
           	completarCPEntrega(localidad_id, codigopostal);
       	});
	}
