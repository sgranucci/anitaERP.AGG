function normalizarUsuarioPrecargado() {
    var id = $.trim($('#usuario_id').val());
    if (!id || !$.isNumeric(id)) {
        return;
    }

    if ($.trim($('#usuario_codigo').val()) === '') {
        $('#usuario_codigo').val(id);
    }

    if ($.trim($('#nombreusuario').val()) !== '') {
        return;
    }

    $.get(carpetaBase + '/configuracion/leerunusuario/' + id, function (data) {
        if (!data) {
            return;
        }

        $('#usuario_id').val(data.id);
        $('#usuario_codigo').val(data.id);
        $('#nombreusuario').val(data.nombre);
    });
}

function asegurarUsuarioAntesDeGuardar($form, event) {
    if ($('#usuario_id').val()) {
        return true;
    }

    var idVisible = $.trim($('#usuario_codigo').val());
    if (!idVisible || !$.isNumeric(idVisible)) {
        event.preventDefault();
        alert('Debe seleccionar un usuario (ID o consulta).');
        $('#usuario_codigo').focus();
        return false;
    }

    event.preventDefault();
    var empresa_id = $('#empresa_id').val();

    $.ajax({
        url: carpetaBase + '/configuracion/resolverusuario',
        data: { valor: idVisible, empresa_id: empresa_id },
        dataType: 'json',
        async: false,
        success: function (data) {
            var $cont = contenedorUsuarioConsulta($('#usuario_codigo'));
            if (typeof aplicarUsuarioResuelto === 'function') {
                aplicarUsuarioResuelto($cont, data);
                if ($('#usuario_id').val()) {
                    $('#usuario_codigo').val($('#usuario_id').val());
                }
            }
            if ($('#usuario_id').val()) {
                $form.off('submit.cajaasignacion').submit();
                return;
            }
            alert('Debe seleccionar un usuario válido para la empresa.');
            $('#usuario_codigo').focus();
        },
        error: function () {
            alert('No se pudo validar el usuario.');
        }
    });

    return false;
}

$(function () {
    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }

    $('#form-general').on('submit.cajaasignacion', function (event) {
        return asegurarUsuarioAntesDeGuardar($(this), event);
    });

    $(document).on('click', '#form-general .botonsubmit', function (event) {
        event.preventDefault();
        $('#form-general').trigger('submit');
    });

    $(document).on('blur', '#usuario_codigo', function () {
        setTimeout(function () {
            var id = $.trim($('#usuario_id').val());
            if (id && $.isNumeric(id)) {
                $('#usuario_codigo').val(id);
            }
        }, 350);
    });

    $(document).on('click', '.eligeconsultausuario', function () {
        var id = $.trim($('#usuario_id').val());
        if (id) {
            $('#usuario_codigo').val(id);
        }
    });

    normalizarUsuarioPrecargado();
});
