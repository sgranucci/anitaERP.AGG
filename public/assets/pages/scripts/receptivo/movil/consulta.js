function buscar_datos_movil(consulta) {
    $.ajax({
        url: '/anitaERP/public/receptivo/movil/consultamovil',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datosmovil").html("");
        $("#datosmovil").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    // Capturamos qué tecla ha sido
    var keyCode= e.which;
    // Si la tecla es el Intro/Enter
    if (keyCode == 13){
      // Evitamos que se ejecute eventos
      e.preventDefault();
      // Devolvemos falso
      return false;
    }
  });

$(document).on('keyup', '#consultamovil', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_movil(valor);
    } else {
        buscar_datos_movil();
    }
});

function activa_eventos_consultamovil()
{
    // Consulta de servicios
    $('.consultamovil').on('click', function (event) {
        // Abre modal de consulta
        $("#consultamovilModal").modal('show');
    });

    $('#consultamovilModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultamovilModal').on('click', function () {
        $('#consultamovilModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultamovil', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $("#movil_id").val(seleccion);
        $("#nombremovil").val(nombre);
        $("#codigomovil").val(codigo);

        $('#consultamovilModal').modal('hide');
    });

    $('#codigomovil').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigomovil = $("#codigomovil").val();
        let url_res = '/anitaERP/public/receptivo/leermovil/'+codigomovil;

        $.get(url_res, function(data){
            if (data)
            {
                $("#movil_id").val(data.id);
                $("#nombremovil").val(data.nombre);
                $("#movil").val(data.nombre);
                $("#codigomovil").val(data.codigo);
            }
        });
    });

}




