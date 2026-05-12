var ptrproveedor_id;
var ptrnombreproveedor;

function actualizarCondicionPagoProveedorDesdeJson(data) {
    if (!$('#condicionpago_proveedor_show').length) {
        return;
    }
    if (data && data.condicionpagos && data.condicionpagos.nombre) {
        $('#condicionpago_proveedor_show').val(data.condicionpagos.nombre);
    } else {
        $('#condicionpago_proveedor_show').val('');
    }
}

function buscar_datos_proveedor(consulta) {
    $.ajax({
        url: carpetaBase+'/compras/proveedor/consultaproveedor',
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

// Enter en input: no dispara submit accidental, salvo en formulario de orden de compra.
$(document).off('keydown.ocNoEnterSubmitProveedor', 'input').on('keydown.ocNoEnterSubmitProveedor', 'input', function (e) {
	var keyCode = e.which;
	if (keyCode !== 13) {
		return;
	}
	if ($(this).closest('#form-ordencompra-general').length) {
		return;
	}
	e.preventDefault();
	return false;
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
        let codigo = $(this).parents("tr").find(".codigoproveedor").html();

        // Asigna a grilla los valores devueltos por consulta
        $(proveedorxcodigo).val(seleccion);
        $(ptrproveedor_id).val(seleccion);
        $(ptrnombreproveedor).val(nombre);

        // Asigna nueva reserva
        $("#proveedor_id").val(seleccion);
        $("#nombreproveedor").val(nombre);
        $("#proveedor").val(nombre);
        $("#codigoproveedor").val(codigo);

        $('#proveedor_id').trigger('change');

        $('#consultaproveedorModal').modal('hide');
    });

    $(document).on('click', '.consultaproveedor', function () {
        let id = $(this).parents("tr").children().html();

        if (id > 0)
        {
            let urlConsultaProveedor = route('editar_proveedor', ':id');
            let url = urlConsultaProveedor;
            url = url.replace(':id', id);
            document.location.href=url;            
        }
    });

    $('#codigoproveedor').on('change', function (event) {
        event.preventDefault();

        // Lee servicio terrestre por codigo
        let codigoproveedor = $("#codigoproveedor").val();
        let url_res = carpetaBase+'/compras/leerproveedorporcodigo/'+codigoproveedor;

        $("#proveedor_id").val('');
        $("#nombreproveedor").val('');
        actualizarCondicionPagoProveedorDesdeJson(null);

        $.get(url_res, function(data){
            if (data)
            {
                $("#proveedor_id").val(data.id);
                $("#nombreproveedor").val(data.nombre);
                actualizarCondicionPagoProveedorDesdeJson(data);
            }
        });
    });

    $('#proveedor_id').on('change', function (event) {
        event.preventDefault();

        let proveedor_id = $("#proveedor_id").val();
        if (!proveedor_id) {
            $("#nombreproveedor").val('');
            $("#codigoproveedor").val('');
            $("#proveedor").val('');
            actualizarCondicionPagoProveedorDesdeJson(null);
            return;
        }

        let url_res = carpetaBase+'/compras/leerproveedor/'+proveedor_id;

        $.get(url_res, function(data){
            if (data)
            {
                $("#proveedor_id").val(data.id);
                $("#codigoproveedor").val(data.codigo || '');
                $("#nombreproveedor").val(data.nombre);
                $("#proveedor").val(data.nombre);
                actualizarCondicionPagoProveedorDesdeJson(data);
            }
        });
    });

    $('.proveedor_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        let proveedor_id = $(this).val();
        let url_res = carpetaBase+'/compras/leerproveedor/'+proveedor_id;

        $(ptrrenglon).parents("tr").find(".proveedor_id").val("");
        $(ptrrenglon).parents("tr").find(".codigoproveedor").val("");
		$(ptrrenglon).parents("tr").find(".nombreproveedor").val("");        

        $("#proveedor_id").val("");
        $("#nombreproveedor").val("");
        actualizarCondicionPagoProveedorDesdeJson(null);

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".proveedor_id").val(data.id);
                $(ptrrenglon).parents("tr").find(".codigoproveedor").val(data.codigo);
                $(ptrrenglon).parents("tr").find(".nombreproveedor").val(data.nombre);

                $("#proveedor_id").val(data.id);
                $("#nombreproveedor").val(data.nombre);
                actualizarCondicionPagoProveedorDesdeJson(data);
            }
        });

        setTimeout(() => {
        }, 1000);

    });
}


