var ptrproveedor_id;
var ptrnombreproveedor;

function buscar_datos_proveedor(consulta) {
    $.ajax({
        url: '/anitaERP/public/compras/proveedor/consultaproveedor',
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
        $("#datosproveedor").html("");
        $("#datosproveedor").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    // Capturamos qué telca ha sido
    var keyCode= e.which;
    // Si la tecla es el Intro/Enter
    if (keyCode == 13){
      // Evitamos que se ejecute eventos
      e.preventDefault();
      // Devolvemos falso
      return false;
    }
  });

$(document).on('keyup', '#consultaproveedor', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_proveedor(valor);
    } else {
        buscar_datos_proveedor();
    }
});

function activa_eventos_consultaproveedor()
{
    // Consulta de proveedores
    $('.consultaproveedor').on('click', function (event) {
        proveedorxcodigo = $(this).parents("tr").find(".proveedor_id");

        ptrproveedor_id = $(this).parents("tr").find(".proveedor_id");
		ptrnombreproveedor = $(this).parents("tr").find(".nombreproveedor");

        // Abre modal de consulta
        $("#consultaproveedorModal").modal('show');
    });

    $('#consultaproveedorModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaproveedorModal').on('click', function () {
        $('#consultaproveedorModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultaproveedor', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombreproveedor").html();

        // Asigna a grilla los valores devueltos por consulta
        $(proveedorxcodigo).val(seleccion);
        $(ptrproveedor_id).val(seleccion);
        $(ptrnombreproveedor).val(nombre);

        // Asigna nueva reserva
        $("#proveedor_id").val(seleccion);
        $("#nombreproveedor").val(nombre);
        $("#proveedor").val(nombre);

        $('#consultaproveedorModal').modal('hide');
    });

    $(document).on('click', '.consultaproveedor', function () {
        let id = $(this).parents("tr").children().html();

        if (id > 0)
        {
            let url = urlConsultaProveedor;
            url = url.replace(':id', id);
            document.location.href=url;
        }
    });

    $('#proveedor_id').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let proveedor_id = $("#proveedor_id").val();
        let url_res = '/anitaERP/public/compras/leerproveedor/'+proveedor_id;

        $.get(url_res, function(data){
            if (data)
            {
                $("#proveedor_id").val(data.id);
                $("#nombreproveedor").val(data.nombre);
                $("#proveedor").val(data.nombre);
            }
        });
    });

    $('.proveedor_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        let proveedor_id = $(this).val();
        let url_res = '/anitaERP/public/compras/leerproveedor/'+proveedor_id;

        $(ptrrenglon).parents("tr").find(".proveedor_id").val("");
        $(ptrrenglon).parents("tr").find(".codigoproveedor").val("");
		$(ptrrenglon).parents("tr").find(".nombreproveedor").val("");        

        $("#proveedor_id").val("");
        $("#nombreproveedor").val("");        

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".proveedor_id").val(data.id);
                $(ptrrenglon).parents("tr").find(".codigoproveedor").val(data.codigo);
                $(ptrrenglon).parents("tr").find(".nombreproveedor").val(data.nombre);

                $("#proveedor_id").val(data.id);
                $("#nombreproveedor").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });
}


