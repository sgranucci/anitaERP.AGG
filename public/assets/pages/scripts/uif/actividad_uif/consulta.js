var ptractividad_uif_id;
var ptrnombreactividad_uif;

function buscar_datos_actividad_uif(consulta) {
    $.ajax({
        url: '/anitaERP/public/uif/consultaactividad_uif',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datosactividad_uif").html("");
        $("#datosactividad_uif").html(resp);
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

$(document).on('keyup', '#consultaactividad_uif', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_actividad_uif(valor);
    } else {
        buscar_datos_actividad_uif();
    }
});

function activa_eventos_consultaactividad_uif()
{
    // Consulta de servicios
    $('.consultaactividad_uif').on('click', function (event) {
        let actividad_uif_id = $("#actividad_uif_id").val();

        ptractividad_uif_id = $(this).parents("tr").find(".actividad_uif_id");
		ptrnombreactividad_uif = $(this).parents("tr").find(".nombreactividad_uif");

        // Abre modal de consulta
        $("#consultaactividad_uifModal").modal('show');

        // Si tiene algo en el filtro de categorias abre directo la consulta
        ($.isNumeric(actividad_uif_id))
            buscar_datos_actividad_uif();
    });

    $('#consultaactividad_uifModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaactividad_uifModal').on('click', function () {
        $('#consultaactividad_uifModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaactividad_uif', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        $(ptractividad_uif_id).val(seleccion);
        $(ptrnombreactividad_uif).val(nombre);

        $("#actividad_uif_id").val(seleccion);
        $("#nombreactividad_uif").val(nombre);

        $('#consultaactividad_uifModal').modal('hide');
    });

    $('#actividad_uif_id').on('change', function (event) {
        event.preventDefault();
        let actividad_uif_id = $("#actividad_uif_id").val();

        if ($.isNumeric(actividad_uif_id))
        {
            let url_res = '/anitaERP/public/uif/leerunaactividad_uif/'+actividad_uif_id;

            $("#actividad_uif_id").val("");
            $("#nombreactividad_uif").val("");
            
            $.get(url_res, function(data){
                if (data)
                {
                    $("#actividad_uif_id").val(data.id);
                    $("#nombreactividad_uif").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombreactividad_uif").val("");
    });

    $('.actividad_uif_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee concepto gasto
        let actividad_uif_id = $(this).val();
        let url_res = '/anitaERP/public/uif/leerunaactividad_uif/'+actividad_uif_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".actividad_uif_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".nombreactividad_uif").val(data.nombre);

                $("#actividad_uif_id").val(data.id);
                $("#nombreactividad_uif").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




