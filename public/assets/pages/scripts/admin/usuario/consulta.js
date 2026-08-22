var ptrusuario_id;
var ptrnombreusuario;
var ptrusuario_codigo;
var abriendoModalUsuario = false;

function contenedorUsuarioConsulta($el) {
    var $tm = $el.closest('.tm-usuario-campo');
    if ($tm.length) {
        return $tm;
    }

    var $tr = $el.closest('tr');
    if ($tr.length) {
        return $tr;
    }

    return $el.closest('.form-group, [id$="-campo"], .d-flex');
}

function esTeclaF1Usuario(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalUsuarioAbierto() {
    var $modal = $('#consultausuarioModal');
    return abriendoModalUsuario || ($modal.length > 0 && ($modal.hasClass('show') || $modal.hasClass('in')));
}

function avisarUsuarioNoEncontrado($input, valor, mensaje, avisar) {
    $input.data('usuarioCodigoInvalido', valor);
    if (!avisar) {
        return;
    }
    if (typeof window.liberarPantallaModalesBloqueados === 'function') {
        window.liberarPantallaModalesBloqueados();
    }
    setTimeout(function () {
        alert(mensaje);
        $input.trigger('focus');
        if ($input.get(0) && typeof $input.get(0).select === 'function') {
            $input.get(0).select();
        }
    }, 0);
}

/**
 * @param {JQuery} $input
 * @param {{avisar?: boolean, avanzarFoco?: boolean}} [opciones]
 */
function resolverUsuarioDesdeCodigo($input, opciones) {
    opciones = opciones || {};
    var avisar = opciones.avisar === true;
    var avanzarFoco = opciones.avanzarFoco === true;
    var $cont = contenedorUsuarioConsulta($input);
    var valor = $.trim($input.val());

    if (modalUsuarioAbierto()) {
        return;
    }

    if (!valor) {
        $input.removeData('usuarioCodigoInvalido');
        limpiarCamposUsuarioConsulta($cont);
        return;
    }

    if ($input.data('usuarioResolviendo')) {
        return;
    }

    // Blur no repite aviso por el mismo código inválido (evita bucle alert ↔ blur).
    if (!avisar && $input.data('usuarioCodigoInvalido') === valor) {
        return;
    }

    $input.data('usuarioResolviendo', true);
    $input.removeData('usuarioCodigoInvalido');

    $.getJSON(carpetaBase + '/configuracion/resolverusuario', paramsResolverUsuario(valor, $cont))
        .done(function (data) {
            if (!aplicarUsuarioResuelto($cont, data, { avisar: avisar, $input: $input, valor: valor })) {
                return;
            }
            $input.removeData('usuarioCodigoInvalido');
            if (avanzarFoco) {
                avanzarFocoUsuario($cont, $input);
            }
        })
        .fail(function () {
            avisarUsuarioNoEncontrado(
                $input,
                valor,
                'Usuario no encontrado o suspendido.',
                avisar
            );
            limpiarCamposUsuarioConsulta($cont);
            $input.val(valor);
        })
        .always(function () {
            $input.removeData('usuarioResolviendo');
        });
}

function avanzarFocoUsuario($cont, $input) {
    var $form = $cont.closest('form');
    if (!$form.length) {
        $form = $(document);
    }
    var $focusables = $form.find(
        'input:not([type="hidden"]):not([readonly]):not(:disabled), select:not(:disabled), textarea:not([readonly]):not(:disabled), button:not(:disabled)'
    );
    var indice = $focusables.index($input);
    if (indice >= 0 && indice + 1 < $focusables.length) {
        $focusables.eq(indice + 1).trigger('focus');
    }
}

function omitirFiltroEmpresaUsuarioConsulta($cont) {
    if (window._consultaUsuarioOmitirFiltroEmpresa) {
        return true;
    }

    if (! $cont || ! $cont.length) {
        return false;
    }

    if ($cont.closest('#ms_panel_destinatario').length > 0) {
        return true;
    }

    var omitirBtn = $cont.find('.consultausuario').first().data('omitir_filtro_empresa');

    return omitirBtn === 1 || omitirBtn === '1';
}

function paramsConsultaUsuario(consulta) {
    var params = {
        consulta: consulta || '',
        empresa_id: $('#empresa_id').val(),
    };

    if (window._consultaUsuarioOmitirFiltroEmpresa) {
        params.omitir_filtro_empresa = 1;
    }

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

/**
 * @param {JQuery} $row
 * @param {object} data
 * @param {{avisar?: boolean, $input?: JQuery, valor?: string}} [opciones]
 */
function aplicarUsuarioResuelto($row, data, opciones) {
    opciones = opciones || {};
    var avisar = opciones.avisar === true;
    var $input = opciones.$input || $row.find('.usuario_codigo_arbol').first();
    var valor = opciones.valor != null ? opciones.valor : $.trim($input.val() || '');

    if (!data || !data.ok) {
        limpiarCamposUsuarioConsulta($row);
        if ($input.length && valor) {
            $input.val(valor);
        }
        avisarUsuarioNoEncontrado(
            $input,
            valor,
            (data && data.mensaje) ? data.mensaje : 'Usuario no encontrado',
            avisar
        );
        return false;
    }
    if (data.empresa_ok === false && !omitirFiltroEmpresaUsuarioConsulta($row)) {
        limpiarCamposUsuarioConsulta($row);
        if ($input.length && valor) {
            $input.val(valor);
        }
        avisarUsuarioNoEncontrado(
            $input,
            valor,
            'El usuario no pertenece a la empresa seleccionada.',
            avisar
        );
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

$(document).on('input', '.usuario_codigo_arbol', function () {
    $(this).removeData('usuarioCodigoInvalido');
    var $cont = contenedorUsuarioConsulta($(this));
    $cont.find('.usuario_id_arbol').val('');
    $cont.find('.nombreusuario').val('');
});

$(document).on('blur', '.usuario_codigo_arbol', function () {
    // Sin alert en blur: evita bucle con el diálogo nativo al fallar Enter.
    resolverUsuarioDesdeCodigo($(this), { avisar: false, avanzarFoco: false });
});

$(document).on('keydown', '.usuario_codigo_arbol', function (e) {
    if (esTeclaF1Usuario(e)) {
        e.preventDefault();
        var $btn = contenedorUsuarioConsulta($(this)).find('.consultausuario').first();
        if ($btn.length) {
            $btn.trigger('click');
        }
        return false;
    }

    if (e.which === 13 || e.key === 'Enter') {
        e.preventDefault();
        resolverUsuarioDesdeCodigo($(this), { avisar: true, avanzarFoco: true });
        return false;
    }
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
            if (!aplicarUsuarioResuelto($cont, data, { avisar: true, $input: $inp, valor: valor })) {
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
        $(ptrusuario_codigo).removeData('usuarioCodigoInvalido');
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
    $('.consultausuario').off('click.consultaUsuario').on('click.consultaUsuario', function (event) {
        event.preventDefault();
        var $btn = $(this);
        abriendoModalUsuario = true;
        window._consultaUsuarioOmitirFiltroEmpresa = $btn.data('omitir_filtro_empresa') === 1
            || $btn.data('omitir_filtro_empresa') === '1'
            || $btn.closest('#ms_panel_destinatario').length > 0
            || !!window._consultaUsuarioOmitirFiltroEmpresaFijo;

        var ptrId = $btn.data('ptrusuario_id') || $btn.attr('data-ptrusuario_id');
        var ptrNom = $btn.data('ptrnombre') || $btn.attr('data-ptrnombre');
        var ptrCod = $btn.data('ptrusuario_codigo') || $btn.attr('data-ptrusuario_codigo');

        if (ptrId && ptrNom) {
            ptrusuario_id = ptrId;
            ptrnombreusuario = ptrNom;
            ptrusuario_codigo = ptrCod || null;
        } else {
            var $contBtn = contenedorUsuarioConsulta($btn);
            ptrusuario_id = $contBtn.find('.usuario_id_arbol, .usuario_id').first();
            ptrusuario_codigo = $contBtn.find('.usuario_codigo_arbol');
            ptrnombreusuario = $contBtn.find('.nombreusuario');
        }

        $('#consultausuarioModal').modal('show');
        buscar_datos_usuario();
    });

    $('#consultausuarioModal').off('shown.bs.modal.consultaUsuario').on('shown.bs.modal.consultaUsuario', function () {
        abriendoModalUsuario = false;
        $(this).find('[autofocus]').focus();
    });

    $('#consultausuarioModal').off('hidden.bs.modal.consultaUsuario').on('hidden.bs.modal.consultaUsuario', function () {
        abriendoModalUsuario = false;
        if (!window._consultaUsuarioOmitirFiltroEmpresaFijo) {
            window._consultaUsuarioOmitirFiltroEmpresa = false;
        } else {
            window._consultaUsuarioOmitirFiltroEmpresa = true;
        }
        if (typeof window.liberarPantallaModalesBloqueados === 'function') {
            window.liberarPantallaModalesBloqueados();
        }
    });

    $('#aceptaconsultausuarioModal').off('click.consultaUsuario').on('click.consultaUsuario', function () {
        $('#consultausuarioModal').modal('hide');
    });

    $('#empresa_id').off('change.consultaUsuario').on('change.consultaUsuario', function (event) {
        event.preventDefault();

        $("#datosusuario").html("");
    });
}
