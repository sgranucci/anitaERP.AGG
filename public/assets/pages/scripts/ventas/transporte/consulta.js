function buscar_datos_transporte(consulta) {
    $.ajax({
        url: '/anitaERP/public/ventas/transporte/consultatransporte',
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
        $("#datostransporte").html("");
        $("#datostransporte").html(resp);
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

$(document).on('keyup', '#consultatransporte', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_transporte(valor);
    } else {
        buscar_datos_transporte();
    }
});

function activa_eventos_consultatransporte()
{
    // Consulta de servicios
    $('.consultatransporte').on('click', function (event) {
        // Abre modal de consulta
        $("#consultatransporteModal").modal('show');
    });

    $('#consultatransporteModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultatransporteModal').on('click', function () {
        $('#consultatransporteModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultatransporte', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $("#transporte_id").val(seleccion);
        $("#nombretransporte").val(nombre);
        $("#codigotransporte").val(codigo);

        $('#consultatransporteModal').modal('hide');
        $("#fechaentrega").focus();
    });

    $('#codigotransporte').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigotransporte = $("#codigotransporte").val();
        let url_res = '/anitaERP/public/ventas/leertransporte/'+codigotransporte;

        $.get(url_res, function(data){
            if (data)
            {
                $("#transporte_id").val(data.id);
                $("#nombretransporte").val(data.nombre);
                $("#transporte").val(data.nombre);
                $("#codigotransporte").val(data.codigo);

                $("#fechaentrega").focus();
            }
        });
    });

}




