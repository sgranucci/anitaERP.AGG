var ptrsubcategoria_ticket_id;
var ptrnombresubcategoria_ticket;

// Consulta de datos con filtros por categoria y area de destino

function buscar_datos_subcategoria_ticket(consulta) {
    let categoria_ticket_id = $("#categoria_ticket_id").val();
    let areadestino_id = $("#areadestino_id").val();

    $.ajax({
        url: '/anitaERP/public/ticket/consultasubcategoria_ticket',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            categoria_ticket_id: categoria_ticket_id,
            areadestino_id: areadestino_id
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datossubcategoria_ticket").html("");
        $("#datossubcategoria_ticket").html(resp);
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

$(document).on('keyup', '#consultasubcategoria_ticket', function () {
    var valor = $(this).val();

    if (valor != "") {
        buscar_datos_subcategoria_ticket(valor);
    } else {
        buscar_datos_subcategoria_ticket();
    }
});

function activa_eventos_consultasubcategoria_ticket()
{
    // Consulta de servicios
    $('.consultasubcategoria_ticket').on('click', function (event) {
        let categoria_ticket_id = $("#categoria_ticket_id").val();

        ptrsubcategoria_ticket_id = $(this).parents("tr").find(".subcategoria_ticket_id");
		ptrsubnombrecategoria_ticket = $(this).parents("tr").find(".nombresubcategoria_ticket");

        // Abre modal de consulta
        $("#consultasubcategoria_ticketModal").modal('show');

        // Si tiene algo en el filtro de categorias abre directo la consulta
        ($.isNumeric(categoria_ticket_id))
            buscar_datos_subcategoria_ticket();
    });

    // Si cambia el filtro blanquea el modal
    $('#categoria_ticket_id').on('change', function (event) {
        event.preventDefault();

        $("#datossubcategoria_ticket").html("");
        $("#subcategoria_ticket_id").val("");
        $("#nombresubcategoria_ticket").val("");
    });

    $('#consultasubcategoria_ticketModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultasubcategoria_ticketModal').on('click', function () {
        $('#consultasubcategoria_ticketModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultasubcategoria_ticket', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let nombrecategoria_ticket = $(this).parents("tr").find(".nombrecategoria_ticket").html();
        let categoria_ticket_id = $(this).parents("tr").find(".idcategoria_ticket").html();
        let areadestino_id = $(this).parents("tr").find(".idareadestino").html();

        $(ptrsubcategoria_ticket_id).val(seleccion);
        $(ptrnombresubcategoria_ticket).val(nombre);

        $("#subcategoria_ticket_id").val(seleccion);
        $("#nombresubcategoria_ticket").val(nombre);

        $("#categoria_ticket_id").val(categoria_ticket_id);
        $("#nombrecategoria_ticket").val(nombrecategoria_ticket);

        $("#areadestino_id").val(areadestino_id);

        $('#consultasubcategoria_ticketModal').modal('hide');
    });

    $('#subcategoria_ticket_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let subcategoria_ticket_id = $("#subcategoria_ticket_id").val();

        // Si cargo una subcategoria lee
        if (subcategoria_ticket_id > 0)
        {
            let url_res = '/anitaERP/public/ticket/leersubcategoria_ticket/'+subcategoria_ticket_id;

            $.get(url_res, function(data){
                if (data)
                {
                    $("#categoria_ticket_id").val(data.categoria_tickets.id);
                    $("#nombrecategoria_ticket").val(data.categoria_tickets.nombre);

                    $("#areadestino_id").val(data.categoria_tickets.areadestino_id);

                    $("#subcategoria_ticket_id").val(data.id);
                    $("#nombresubcategoria_ticket").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        }
        else
            $("#nombresubcategoria_ticket").val("");
    });

    $('.subcategoria_ticket_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee subcategoria
        let subcategoria_id = $(this).val();
        let url_res = '/anitaERP/public/ticket/leersubcategoria_ticket/'+subcategoria_ticket_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".subcategoria_ticket_id").val(data.id);
			    $(ptrrenlong).parents("tr").find(".nombresubcategoria_ticket").val(data.nombre);

                $("#subcategoria_ticket_id").val(data.id);
                $("#nombresubcategoria_ticket").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




