var ptrusuario_id;
var ptrusuario_codigo;
var ptrnombreusuario;

function contenedorUsuarioConsulta($el) {
    var $tr = $el.closest('tr');
    if ($tr.length) {
        return $tr;
    }

    return $el.closest('.form-group');
}

function omitirFiltroEmpresaUsuarioConsulta($cont) {
    if (window._consultaUsuarioOmitirFiltroEmpresa) {
        return true;
    }

    return $cont && $cont.length && $cont.closest('#ms_panel_destinatario').length > 0;
}

function paramsConsultaUsuario(consulta) {
    var params = {
        consulta: consulta || '',
        empresa_id: $('#empresa_id').val(),
    };

    if (typeof window.payloadExtraConsultaUsuario === 'function') {
        $.extend(params, window.payloadExtraConsultaUsuario() || {});
    }

    if (params.omitir_filtro_empresa) {
        delete params.empresa_id;
    }

    return params;
}

function paramsResolverUsuario(valor, $cont) {
    var params = { valor: valor };

    if (omitirFiltroEmpresaUsuarioConsulta($cont)) {
        params.omitir_filtro_empresa = 1;
    } else {
        var empresa_id = $('#empresa_id').val();
        if (empresa_id) {
            params.empresa_id = empresa_id;
        }
    }

    return params;
}

function aplicarUsuarioResuelto($row, data) {
    if (!data || !data.ok) {
        alert((data && data.mensaje) ? data.mensaje : 'Usuario no encontrado');
        return false;
    }
    if (data.empresa_ok === false && !omitirFiltroEmpresaUsuarioConsulta($row)) {
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
    $.ajax({
        url: carpetaBase+'/configuracion/consultausuario',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: paramsConsultaUsuario(consulta),
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

function limpiarCamposUsuarioConsulta($cont) {
    $cont.find('.usuario_id_arbol').val('');
    $cont.find('.usuario_id').not('.usuario_id_arbol').val('');
    $cont.find('.nombreusuario').val('');
}

$(document).on('blur', '.usuario_codigo_arbol', function () {
    var $cont = contenedorUsuarioConsulta($(this));
    var valor = $.trim($(this).val());

    if (!valor) {
        limpiarCamposUsuarioConsulta($cont);
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', paramsResolverUsuario(valor, $cont))
        .done(function (data) {
            if (!aplicarUsuarioResuelto($cont, data)) {
                limpiarCamposUsuarioConsulta($cont);
            }
        });
});

$(document).on('change', '.usuario_id', function (event) {
    event.preventDefault();
    var $inp = $(this);
    if ($inp.hasClass('usuario_id_arbol')) {
        return;
    }
    var $cont = contenedorUsuarioConsulta($inp);
    var valor = $.trim($inp.val());

    if (!valor) {
        $cont.find('.nombreusuario').val('');
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', paramsResolverUsuario(valor, $cont))
        .done(function (data) {
            if (!aplicarUsuarioResuelto($cont, data)) {
                $inp.val('');
                $cont.find('.nombreusuario').val('');
                return;
            }
            $inp.trigger('usuario-operativo:resuelto', [data]);
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
                if (data.usuario && $("#usuario_codigo").length) {
                    $("#usuario_codigo").val(data.usuario);
                }
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
        var $btn = $(this);
        window._consultaUsuarioOmitirFiltroEmpresa = $btn.data('omitir_filtro_empresa') === 1
            || $btn.data('omitir_filtro_empresa') === '1'
            || $btn.closest('#ms_panel_destinatario').length > 0;

        var ptrId = $btn.data('ptrusuario_id') || $btn.attr('data-ptrusuario_id');
        var ptrNom = $btn.data('ptrnombre') || $btn.attr('data-ptrnombre');
        var ptrCod = $btn.data('ptrusuario_codigo') || $btn.attr('data-ptrusuario_codigo');

        if (ptrId && ptrNom) {
            ptrusuario_id = ptrId;
            ptrnombreusuario = ptrNom;
            ptrusuario_codigo = ptrCod || null;
        } else {
            ptrusuario_id = $btn.closest('tr').find('.usuario_id_arbol, .usuario_id').first();
            ptrusuario_codigo = $btn.closest('tr').find('.usuario_codigo_arbol');
            ptrnombreusuario = $btn.closest('tr').find('.nombreusuario');
        }

        var usuario_id = $('#usuario_id').val();

        $('#consultausuarioModal').modal('show');

        if ($.isNumeric(usuario_id)) {
            buscar_datos_usuario();
        }
    });

    $('#consultausuarioModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#consultausuarioModal').on('hidden.bs.modal', function () {
        window._consultaUsuarioOmitirFiltroEmpresa = false;
    });

    $('#aceptaconsultausuarioModal').on('click', function () {
        $('#consultausuarioModal').modal('hide');
    });

    $('#empresa_id').on('change', function (event) {
        event.preventDefault();

        $("#datosusuario").html("");
    });
}
