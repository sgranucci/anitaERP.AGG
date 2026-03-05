var ptrtecnico_ticket_id;
var ptrnombretecnico_ticket;

function buscar_datos_tecnico_ticket(consulta) {
    let areadestino_id = $("#areadestino_id").val();

    $.ajax({
        url: '/anitaERP/public/ticket/consultatecnico_ticket',
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
        $("#datostecnico_ticket").html("");
        $("#datostecnico_ticket").html(resp);
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

$(document).on('keyup', '#consultatecnico_ticket', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_tecnico_ticket(valor);
    } else {
        buscar_datos_tecnico_ticket();
    }
});

function activa_eventos_consultatecnico_ticket()
{
    $('.consultatecnico_ticket').on('click', function (event) {
        let tecnico_ticket_id = $("#tecnico_ticket_id").val();

        ptrtecnico_ticket_id = $(this).parents("tr").find(".tecnico_ticket_id");
		ptrnombretecnico_ticket = $(this).parents("tr").find(".nombretecnico_ticket");

        // Abre modal de consulta
        $("#consultatecnico_ticketModal").modal('show');

        ($.isNumeric(tecnico_ticket_id))
            buscar_datos_tecnico_ticket();
    });

    $('#consultatecnico_ticketModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultatecnico_ticketModal').on('click', function () {
        $('#consultatecnico_ticketModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultatecnico_ticket', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        // Chequea cambio de tecnico
        let tecnico_ticket_id = $(ptrtecnico_ticket_id).val();
        let ticket_tarea_id = $(ptrtecnico_ticket_id).parents("tr").find(".ticket_tarea_id").val();

        if (tecnico_ticket_id != seleccion)
            // Procesa cambio de tecnico
            cambioTecnico(ticket_tarea_id, seleccion);

        $(ptrtecnico_ticket_id).val(seleccion);
        $(ptrnombretecnico_ticket).val(nombre);

        $("#tecnico_ticket_id").val(seleccion);
        $("#nombretecnico_ticket").val(nombre);

        $('#consultatecnico_ticketModal').modal('hide');
    });

    // Si cambia el filtro blanquea el modal
    $('#areadestino_id').on('change', function (event) {
        event.preventDefault();

        $("#datostecnico_ticket").html("");

    });

    $('#tecnico_ticket_id').on('change', function (event) {
        event.preventDefault();

        let tecnico_ticket_id = $("#tecnico_ticket_id").val();

        if ($.isNumeric(tecnico_ticket_id))
        {
            let url_res = '/anitaERP/public/ticket/leertecnico_ticket/'+tecnico_ticket_id;

            $.get(url_res, function(data){
                if (data)
                {
                    $("#tecnico_ticket_id").val(data.id);
                    $("#nombretecnico_ticket").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombretecnico_ticket").val("");
    });

    $('.tecnico_ticket_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;
        let areadestino_id = $("#areadestino_id").val();
        let ticket_tarea_id = $(ptrrenglon).parents("tr").find(".ticket_tarea_id").val();
        let tecnico_ticket_id = $(ptrrenglon).parents("tr").find(".tecnico_ticket_id").val();

        // Procesa cambio de tecnico
        cambioTecnico(ticket_tarea_id, tecnico_ticket_id);

        let url_res = '/anitaERP/public/ticket/leertecnico_ticket/'+tecnico_ticket_id;

        $(ptrrenglon).parents("tr").find(".tecnico_ticket_id").val("");
		$(ptrrenglon).parents("tr").find(".nombretecnico_ticket").val("");

        $.get(url_res, function(data){
            if (data)
            {
                if (data.areadestino_id != areadestino_id)
                    alert('No coincide area de destino');
                else
                {
                    $(ptrrenglon).parents("tr").find(".tecnico_ticket_id").val(data.id);
                    $(ptrrenglon).parents("tr").find(".nombretecnico_ticket").val(data.nombre);

                    $("#tecnico_ticket_id").val(data.id);
                    $("#nombretecnico_ticket").val(data.nombre);
                }
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




