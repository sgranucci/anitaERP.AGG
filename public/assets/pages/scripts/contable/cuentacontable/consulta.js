function buscar_datos(consulta) {
    let empresa_id = $("#consultaempresa_id").val();

    if (empresa_id == 0)
        empresa_id = $("#empresa_id").val();

    $.ajax({
        url: '/anitaERP/public/contable/cuentacontable/consultacuentacontable',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
            empresa_id: empresa_id
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datos").html("");
        $("#datos").html(resp);
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

$(document).on('keyup', '#consultacuentacontable', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos(valor);
    } else {
        buscar_datos();
    }
});

function activa_eventos_consulta_cuentacontable()
{
    $('.codigocuentacontable').on('change', function (event) {
        event.preventDefault();
        var codigo = $(this);
        var codigo_ant = $(this).parents("tr").find(".codigo_previo").val();
        var codigo_nuevo = codigo.val();
        let empresa_id = $(this).parents("tr").find(".empresa").val();

        let url_cta = '/anitaERP/public/contable/cuentacontable/leercuentacontableporcodigo/'+empresa_id+'/'+codigo_nuevo;

        $.get(url_cta, function(data){
            if (data.id > 0)
            {
                $(codigo).parents("tr").find('.cuentacontable_id').val(data.id);
                $(codigo).parents("tr").find(".cuentacontable_id_previa").val(data.id);
                $(codigo).parents("tr").find(".nombrecuentacontable").val(data.nombre);
            }
            else
            {
                alert("No existe la cuenta");

                // Borra el renglon
                $(codigo).parents('tr').remove();
                return;
            }
        });

        if (codigo_nuevo != codigo_ant && empresa_id)
            leeCentroCosto(this);
    });

    $('.consultacuentacontable').on('click', function (event) {
        cuentacontablexcodigo = $(this).parents("tr").find(".cuentacontable_id");
        nombrexcodigo = $(this).parents("tr").find(".nombrecuentacontable");
        codigoxcodigo = $(this).parents("tr").find(".codigocuentacontable");
        let empresa_id = $(this).parents("tr").find(".empresa").val();

        // Abre modal de consulta
        if (empresa_id > 0)
        {
            $("#consultacuentaModal").modal('show');
            $("#consultaempresa_id").val(empresa_id);
        }
        else	
            alert('Debe ingresar empresa');
    });

    $('#consultacuentaModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultacuentaModal').on('click', function () {
        $('#consultacuentaModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultacuentacontable', function () {
        var seleccion = $(this).parents("tr").children().html();
        var nombre = $(this).parents("tr").find(".nombrecuentacontable").html();
        var codigo = $(this).parents("tr").find(".codigocuentacontable").html();

        // Asigna a grilla los valores devueltos por consulta
        $(cuentacontablexcodigo).val(seleccion);
        $(nombrexcodigo).val(nombre);
        $(codigoxcodigo).val(codigo);

        //* Asigna nueva cuentacontable
        $(cuentacontablexcodigo).parents("tr").find(".cuentacontable_id_previa").val($(cuentacontablexcodigo).val());
    
        $('#consultacuentaModal').modal('hide');
    });

}




