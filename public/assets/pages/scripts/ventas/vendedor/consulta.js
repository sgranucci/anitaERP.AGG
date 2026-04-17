var ptrVendedor_id;
var ptrCodigoVendedor_id;
var ptrNombreVendedor;

function buscar_datos_vendedor(consulta) {
    $.ajax({
        url: carpetaBase+'/ventas/vendedor/consultavendedor',
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
        $("#datosvendedor").html("");
        $("#datosvendedor").html(resp);
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

$(document).on('keyup', '#consultavendedor', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_vendedor(valor);
    } else {
        buscar_datos_vendedor();
    }
});

function activa_eventos_consultavendedor()
{
    // Consulta de zona de venta
    $('.consultavendedor').on('click', function (event) {
        
        ptrVendedor_id = $(this).parents("tr").find(".vendedor_id");
        ptrCodigoVendedor_id = $(this).parents("tr").find(".codigovendedor");
		ptrNombreVendedor = $(this).parents("tr").find(".nombrevendedor");

        // Abre modal de consulta
        $("#consultavendedorModal").modal('show');
    });

    $('#consultavendedorModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultavendedorModal').on('click', function () {
        $('#consultavendedorModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultavendedor', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        $(ptrVendedor_id).val(seleccion);
        $(ptrCodigoVendedor_id).val(codigo);
        $(ptrNombreVendedor).val(nombre);

        $("#vendedor_id").val(seleccion);
        $("#nombrevendedor").val(nombre);
        $("#codigovendedor").val(codigo);

        $('#consultavendedorModal').modal('hide');
    });

    $(document).on('click', '.consultaunvendedor', function () {
        let id = $(this).parents("tr").children().html();

        if (id > 0)
        {
            let urlConsultaZonavta = route('editar_vendedor_remoto', ':id');
            let url = urlConsultaZonavta;
            url = url.replace(':id', id);
            document.location.href=url;
        }
    });

    $('#codigovendedor').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigovendedor = $("#codigovendedor").val();
        let url_res = carpetaBase+'/ventas/leervendedor/'+codigovendedor;

        $.get(url_res, function(data){
            if (data)
            {
                $("#vendedor_id").val(data.id);
                $("#nombrevendedor").val(data.nombre);
                $("#vendedor").val(data.nombre);
                $("#codigovendedor").val(data.codigo);
            }
        });
    });

    $('.codigovendedor').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        let codigovendedor = $(this).val();
        let url_res = carpetaBase+'/ventas/leervendedor/'+codigovendedor;

        $(ptrrenglon).parents("tr").find(".vendedor_id").val("");
        $(ptrrenglon).parents("tr").find(".codigovendedor").val("");
		$(ptrrenglon).parents("tr").find(".nombrevendedor").val("");        

        $("#vendedor_id").val("");
        $("#nombrevendedor").val("");        

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".vendedor_id").val(data.id);
                $(ptrrenglon).parents("tr").find(".codigovendedor").val(data.codigo);
                $(ptrrenglon).parents("tr").find(".nombrevendedor").val(data.nombre);

                $("#vendedor_id").val(data.id);
                $("#nombrevendedor").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });    
}

function desactiva_eventos_consulta_vendedor()
{
    $('.consultavendedor').off('click')
    $('#aceptaconsultavendedorModal').off('click')
    $(document).off('click')
    $(document).off('click')
    $('#codigovendedor').off('change')
    $('.codigovendedor').off('change')
}




