var ptrconceptogasto_id;
var ptrnombreconceptogasto;

function buscar_datos_conceptogasto(consulta) {
    $.ajax({
        url: '/anitaERP/public/caja/conceptogasto/consultaconceptogasto',
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
        $("#datosconceptogasto").html("");
        $("#datosconceptogasto").html(resp);
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

$(document).on('keyup', '#consultaconceptogasto', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_conceptogasto(valor);
    } else {
        buscar_datos_conceptogasto();
    }
});

function activa_eventos_consultaconceptogasto()
{
    // Consulta de servicios
    $('.consultaconceptogasto').on('click', function (event) {
        ptrconceptogasto_id = $(this).parents("tr").find(".conceptogasto_id");
		ptrnombreconceptogasto = $(this).parents("tr").find(".nombreconceptogasto");

        // Abre modal de consulta
        $("#consultaconceptogastoModal").modal('show');
    });

    $('#consultaconceptogastoModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaconceptogastoModal').on('click', function () {
        $('#consultaconceptogastoModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaconceptogasto', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        $(ptrconceptogasto_id).val(seleccion);
        $(ptrnombreconceptogasto).val(nombre);

        $("#conceptogasto_id").val(seleccion);
        $("#nombreconceptogasto").val(nombre);

        $('#consultaconceptogastoModal').modal('hide');
    });

    $('#conceptogasto_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let conceptogasto_id = $("#conceptogasto_id").val();
        let url_res = '/anitaERP/public/caja/leerconceptogasto/'+conceptogasto_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrconceptogasto_id).val(data.id);
                $(ptrnombreconceptogasto).val(data.nombre);

                $("#conceptogasto_id").val(data.id);
                $("#nombreconceptogasto").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

    $('.conceptogasto_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenlong = this;

        // Lee concepto gasto
        let conceptogasto_id = $(this).val();
        let url_res = '/anitaERP/public/caja/leerconceptogasto/'+conceptogasto_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrconceptogasto_id).val(data.id);
                $(ptrnombreconceptogasto).val(data.nombre);

                $(ptrrenlong).parents("tr").find(".conceptogasto_id").val(data.id);
			    $(ptrrenlong).parents("tr").find(".nombreconceptogasto").val(data.nombre);

                $("#conceptogasto_id").val(data.id);
                $("#nombreconceptogasto").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




