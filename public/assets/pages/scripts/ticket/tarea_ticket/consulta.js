var ptrtarea_ticket_id;
var ptrnombretarea_ticket;

function buscar_datos_tarea_ticket(consulta) {
    let areadestino_id = $("#areadestino_id").val();

    $.ajax({
        url: '/anitaERP/public/ticket/consultatarea_ticket',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            areadestino_id: areadestino_id
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datostarea_ticket").html("");
        $("#datostarea_ticket").html(resp);
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

$(document).on('keyup', '#consultatarea_ticket', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_tarea_ticket(valor);
    } else {
        buscar_datos_tarea_ticket();
    }
});

function activa_eventos_consultatarea_ticket()
{
    $('.consultatarea_ticket').on('click', function (event) {
        let tarea_ticket_id = $("#tarea_ticket_id").val();

        ptrtarea_ticket_id = $(this).parents("tr").find(".tarea_ticket_id");
		ptrnombretarea_ticket = $(this).parents("tr").find(".nombretarea_ticket");

        // Abre modal de consulta
        $("#consultatarea_ticketModal").modal('show');

        ($.isNumeric(tarea_ticket_id))
            buscar_datos_tarea_ticket();
    });

    $('#consultatarea_ticketModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultatarea_ticketModal').on('click', function () {
        $('#consultatarea_ticketModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultatarea_ticket', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        $(ptrtarea_ticket_id).val(seleccion);
        $(ptrnombretarea_ticket).val(nombre);

        $("#tarea_ticket_id").val(seleccion);
        $("#nombretarea_ticket").val(nombre);

        $('#consultatarea_ticketModal').modal('hide');
    });

    // Si cambia el filtro blanquea el modal
    $('#areadestino_id').on('change', function (event) {
        event.preventDefault();

        $("#datostarea_ticket").html("");

    });

    $('#tarea_ticket_id').on('change', function (event) {
        event.preventDefault();

        let tarea_ticket_id = $("#tarea_ticket_id").val();

        if ($.isNumeric(tarea_ticket_id))
        {
            let url_res = '/anitaERP/public/ticket/leertarea_ticket/'+tarea_ticket_id;

            $.get(url_res, function(data){
                if (data)
                {
                    $("#tarea_ticket_id").val(data.id);
                    $("#nombretarea_ticket").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombretarea_ticket").val("");
    });

    $('.tarea_ticket_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;
        let areadestino_id = $("#areadestino_id").val();

        let tarea_ticket_id = $(this).val();
        let url_res = '/anitaERP/public/ticket/leertarea_ticket/'+tarea_ticket_id;

        $(ptrrenglon).parents("tr").find(".tarea_ticket_id").val("");
		$(ptrrenglon).parents("tr").find(".nombretarea_ticket").val("");        

        $("#tarea_ticket_id").val("");
        $("#nombretarea_ticket").val("");        

        $.get(url_res, function(data){
            if (data)
            {
                if (data.areadestino_id != areadestino_id)
                    alert('No coincide area de destino');
                else
                {
                    $(ptrrenglon).parents("tr").find(".tarea_ticket_id").val(data.id);
                    $(ptrrenglon).parents("tr").find(".nombretarea_ticket").val(data.nombre);

                    $("#tarea_ticket_id").val(data.id);
                    $("#nombretarea_ticket").val(data.nombre);
                }
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




