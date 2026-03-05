// Carga de domicilio provincia/localidad/codigo postal
// Para abm de clientes en carga de lugares de entrega sobre tabla

    function completarLocalidades(provincia_id){
        var loc_id;
        $.get('/anitaERP/public/configuracion/leerlocalidades/'+provincia_id, function(data){
            var loc = $.map(data, function(value, index){
                return [value];
            });
			$("#localidad_id").empty();
			$("#localidad_id").append('<option value=""></option>');
            $.each(loc, function(index,value){
				$("#localidad_id").append('<option value="'+value.id+'">'+value.nombre+'</option>');
            });
        });
        setTimeout(() => {
                var loc_id = $("#localidad_id").val();
                if (loc_id != undefined) {
                    completarCP(loc_id);
                }
        }, 3000);
    }

    function completarCP(localidad_id){
        $.get('/anitaERP/public/configuracion/leercodigopostal/'+localidad_id, function(data){
            if(data!=0){
                $("#codigopostal").val(data);
            }
        });
    }

    $(function () {
        $("#provincia_id").change(function(){
            var  provincia_id = $(this).val();
            completarLocalidades(provincia_id);
        });

        $("#localidad_id").change(function(){
            var  localidad_id = $(this).val();
            completarCP(localidad_id);
        });

        var  provincia_id = $("#provincia_id").val();
        completarLocalidades(provincia_id);
        if ($("#localidad_id_previa").val() != "") {
           	setTimeout(() => {
                   	$("#localidad_id").val($("#localidad_id_previa").val());
           	}, 1000);
        }

		// Llena variable desc_localidad
		$(document).on('change', '#localidad_id', function(event) {
			var desc = $(this).children("option:selected").text();
        	$("#desc_localidad").val(desc);
		});
    });

