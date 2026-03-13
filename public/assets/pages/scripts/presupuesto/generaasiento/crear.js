	$(function () 
	{
		completarEscenario();
		
		$('#presupuesto_id').on('change', function(event) {
			completarEscenario();
		});		
	});

	function completarEscenario(){
		let presupuesto_id = $("#presupuesto_id").val();

		// Si marca boton de todas las combinaciones trae sin filtrar las activas o esta leyendo todos los articulos sin filtrar
		let url = '/anitaERP/public/presupuesto/leerescenario/'+presupuesto_id;

        $.get(url, function(data){
            let comb = $.map(data, function(value, index){
                return [value];
            });
			$("#presupuesto_escenario_id").empty();
            $.each(comb, function(index,value){
               	$("#presupuesto_escenario_id").append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
            });
        });
    }


