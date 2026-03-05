function buscar_datos_servicioterrestre(consulta) {
    $.ajax({
        url: '/anitaERP/public/receptivo/servicioterrestre/consultaservicioterrestre',
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
        $("#datosservicioterrestre").html("");
        $("#datosservicioterrestre").html(resp);
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

$(document).on('keyup', '#consultaservicioterrestre', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_servicioterrestre(valor);
    } else {
        buscar_datos_servicioterrestre();
    }
});

function activa_eventos_consultaservicioterrestre()
{
    // Consulta de servicios
    $('.consultaservicioterrestre').on('click', function (event) {
        // Abre modal de consulta
        $("#consultaservicioterrestreModal").modal('show');
    });

    $('#consultaservicioterrestreModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaservicioterrestreModal').on('click', function () {
        $('#consultaservicioterrestreModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaservicioterrestre', function () {
        let seleccion = $(this).parents("tr").children().html();
        let descripcion = $(this).parents("tr").find(".descripcion").html();

        $("#servicioterrestre_id").val(seleccion);
        $("#nombreservicioterrestre").val(descripcion);
        $("#servicioterrestre").val(descripcion);

        $('#consultaservicioterrestreModal').modal('hide');
    });

    $('#codigoservicioterrestre').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigoservicioterrestre = $("#codigoservicioterrestre").val();
        let url_res = '/anitaERP/public/receptivo/leerservicioterrestre/'+codigoservicioterrestre;

        $.get(url_res, function(data){
            if (data)
            {
                $("#servicioterrestre_id").val(data.id);
                $("#nombreservicioterrestre").val(data.nombre);
                $("#servicioterrestre").val(data.nombre);
                $("#codigoservicioterrestre").val(data.codigo);
            }
        });
    });

}




