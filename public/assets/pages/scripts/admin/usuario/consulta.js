var ptrusuario_id;
var ptrusuario_codigo;
var ptrnombreusuario;

function aplicarUsuarioResuelto($row, data) {
    if (!data || !data.ok) {
        alert((data && data.mensaje) ? data.mensaje : 'Usuario no encontrado');
        return false;
    }
    if (data.empresa_ok === false) {
        alert('El usuario no pertenece a la empresa seleccionada.');
        return false;
    }
    var $hid = $row.find('.usuario_id_arbol');
    var $cod = $row.find('.usuario_codigo_arbol');
    var $nom = $row.find('.nombreusuario');
    var $plain = $row.find('.usuario_id').filter(function () {
        return !$(this).hasClass('usuario_id_arbol');
    });

    if ($hid.length) {
        $hid.val(data.id);
    }
    if ($cod.length) {
        $cod.val(data.usuario);
    }
    if ($plain.length) {
        $plain.val(data.id);
    }
    if ($nom.length) {
        $nom.val(data.nombre);
    }

    return true;
}

function buscar_datos_usuario(consulta) {
    let empresa_id = $("#empresa_id").val();

    $.ajax({
        url: carpetaBase+'/configuracion/consultausuario',
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
        $("#datosusuario").html("");
        $("#datosusuario").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

$(document).on('keydown', 'input', function (e) {
    var keyCode = e.which;
    if (keyCode == 13) {
      e.preventDefault();
      return false;
    }
});

$(document).on('keyup', '#consultausuario', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_usuario(valor);
    } else {
        buscar_datos_usuario();
    }
});

$(document).on('blur', '.usuario_codigo_arbol', function () {
    var $row = $(this).closest('tr');
    var valor = $.trim($(this).val());
    var empresa_id = $("#empresa_id").val();

    if (!valor) {
        $row.find('.usuario_id_arbol').val('');
        $row.find('.nombreusuario').val('');
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', { valor: valor, empresa_id: empresa_id })
        .done(function (data) {
            if (!aplicarUsuarioResuelto($row, data)) {
                $row.find('.usuario_id_arbol').val('');
                $row.find('.nombreusuario').val('');
            }
        });
});

$(document).on('change', '.usuario_id', function (event) {
    event.preventDefault();
    var $inp = $(this);
    if ($inp.hasClass('usuario_id_arbol')) {
        return;
    }
    var $row = $inp.closest('tr');
    var valor = $.trim($inp.val());
    var empresa_id = $("#empresa_id").val();

    if (!valor) {
        $row.find('.nombreusuario').val('');
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', { valor: valor, empresa_id: empresa_id })
        .done(function (data) {
            if (!aplicarUsuarioResuelto($row, data)) {
                $inp.val('');
                $row.find('.nombreusuario').val('');
            }
        });
});

$(document).on('click', '.eligeconsultausuario', function () {
    var $trModal = $(this).closest('tr');
    var seleccion = $trModal.find('.id').first().text().trim();
    var nombre = $trModal.find('.nombre').first().text().trim();
    var codigo = $trModal.find('.usuariologin').first().text().trim();

    if (ptrusuario_id && $(ptrusuario_id).length) {
        $(ptrusuario_id).val(seleccion);
    }
    $(ptrnombreusuario).val(nombre);
    if (ptrusuario_codigo && $(ptrusuario_codigo).length) {
        $(ptrusuario_codigo).val(codigo);
    }

    if ($("#usuario_id").length) {
        $("#usuario_id").val(seleccion);
    }
    if ($("#nombreusuario").length) {
        $("#nombreusuario").val(nombre);
    }

    $('#consultausuarioModal').modal('hide');
});

$('#usuario_id').on('change', function (event) {
    event.preventDefault();

    let usuario_id = $("#usuario_id").val();

    if ($.isNumeric(usuario_id))
    {
        let url_res = carpetaBase+'/configuracion/leerunusuario/'+usuario_id;

        $.get(url_res, function(data){
            if (data)
            {
                $("#usuario_id").val(data.id);
                $("#nombreusuario").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);
    }
    else
        $("#nombreusuario").val("");
});

function activa_eventos_consultausuario()
{
    $('.consultausuario').on('click', function (event) {
        let usuario_id = $("#usuario_id").val();

        ptrusuario_id = $(this).closest("tr").find(".usuario_id_arbol, .usuario_id").first();
        ptrusuario_codigo = $(this).closest("tr").find(".usuario_codigo_arbol");
		ptrnombreusuario = $(this).closest("tr").find(".nombreusuario");

        $("#consultausuarioModal").modal('show');

        ($.isNumeric(usuario_id))
            buscar_datos_usuario();
    });

    $('#consultausuarioModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultausuarioModal').on('click', function () {
        $('#consultausuarioModal').modal('hide');
    });

    $('#empresa_id').on('change', function (event) {
        event.preventDefault();

        $("#datosusuario").html("");
    });
}
