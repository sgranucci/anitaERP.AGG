// Carga de domicilio provincia/localidad/codigo postal
// Para abm de clientes en carga de lugares de entrega sobre tabla

    function completarLocalidadesNacimientos(provincia_id){
        var loc_id;
        $.get('/anitaERP/public/uif/leerlocalidadesuif/'+provincia_id, function(data){
            var loc = $.map(data, function(value, index){
                return [value];
            });
			$("#localidadnacimiento_id").empty();
			$("#localidadnacimiento_id").append('<option value=""></option>');
            $.each(loc, function(index,value){
				$("#localidadnacimiento_id").append('<option value="'+value.id+'">'+value.nombre+'</option>');
            });
        });
        setTimeout(() => {
                var loc_id = $("#localidadnacimiento_id").val();
        }, 3000);
    }

    $(function () {
        $("#provincianacimiento_id").change(function(){
            let  provincia_id = $(this).val();
            completarLocalidadesNacimientos(provincia_id);
        });

        let  provincianacimiento_id = $("#provincianacimiento_id").val();
        completarLocalidadesNacimientos(provincianacimiento_id);
        if ($("#localidadnacimiento_id_previa").val() != "") {
           	setTimeout(() => {
                   	$("#localidadnacimiento_id").val($("#localidadnacimiento_id_previa").val());
           	}, 1000);
        }

		// Llena variable desc_localidad
		$(document).on('change', '#localidadnacimiento_id', function(event) {
			var desc = $(this).children("option:selected").text();
        	$("#desc_localidadnacimiento").val(desc);
		});
    });

