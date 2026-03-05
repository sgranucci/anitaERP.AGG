function buscar_datos_zonavta(consulta) {
    $.ajax({
        url: '/anitaERP/public/ventas/zonavta/consultazonavta',
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
        $("#datoszonavta").html("");
        $("#datoszonavta").html(resp);
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

$(document).on('keyup', '#consultazonavta', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_zonavta(valor);
    } else {
        buscar_datos_zonavta();
    }
});

function activa_eventos_consultazonavta()
{
    // Consulta de zona de venta
    $('.consultazonavta').on('click', function (event) {
        // Abre modal de consulta
        $("#consultazonavtaModal").modal('show');
    });

    $('#consultazonavtaModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultazonavtaModal').on('click', function () {
        $('#consultazonavtaModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultazonavta', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $("#zonavta_id").val(seleccion);
        $("#nombrezonavta").val(nombre);
        $("#codigozonavta").val(codigo);

        $('#consultazonavtaModal').modal('hide');
    });

    $(document).on('click', '.consultaunazonavta', function () {
        let id = $(this).parents("tr").children().html();

        if (id > 0)
        {
            let urlConsultaZonavta = route('editar_zonavta', ':id');
            let url = urlConsultaZonavta;
            url = url.replace(':id', id);
            document.location.href=url;
        }
    });

    $('#codigozonavta').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigozonavta = $("#codigozonavta").val();
        let url_res = '/anitaERP/public/ventas/leerzonavta/'+codigozonavta;

        $.get(url_res, function(data){
            if (data)
            {
                $("#zonavta_id").val(data.id);
                $("#nombrezonavta").val(data.nombre);
                $("#zonavta").val(data.nombre);
                $("#codigozonavta").val(data.codigo);
            }
        });
    });
}

function leeZonaVta()
{
    let zonavta_id = $("#zonavta_id").val();
    let url_res = '/anitaERP/public/ventas/leerzonavtaporid/'+zonavta_id;

    $.get(url_res, function(data){
        if (data)
        {
            $("#zonavta_id").val(data.id);
            $("#nombrezonavta").val(data.nombre);
            $("#zonavta").val(data.nombre);
            $("#codigozonavta").val(data.codigo);
        }
    });
}




