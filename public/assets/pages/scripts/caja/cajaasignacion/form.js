function asegurarUsuarioAntesDeGuardar($form, event) {
    if ($('#usuario_id').val()) {
        return true;
    }

    var codigo = $.trim($('#usuario_codigo').val());
    if (!codigo) {
        event.preventDefault();
        alert('Debe seleccionar un usuario (código o consulta).');
        $('#usuario_codigo').focus();
        return false;
    }

    event.preventDefault();
    var empresa_id = $('#empresa_id').val();

    $.ajax({
        url: carpetaBase + '/configuracion/resolverusuario',
        data: { valor: codigo, empresa_id: empresa_id },
        dataType: 'json',
        async: false,
        success: function (data) {
            var $cont = contenedorUsuarioConsulta($('#usuario_codigo'));
            if (typeof aplicarUsuarioResuelto === 'function' && aplicarUsuarioResuelto($cont, data) && $('#usuario_id').val()) {
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

    var empresaInicial = String($('#empresa_id').val() || '');
    $('#empresa_id').on('change', function () {
        var actual = String($(this).val() || '');
        if (actual === empresaInicial) {
            return;
        }
        $('#usuario_id').val('');
        $('#usuario_codigo').val('');
        $('#nombreusuario').val('');
    });

    $('#form-general').on('submit.cajaasignacion', function (event) {
        return asegurarUsuarioAntesDeGuardar($(this), event);
    });

    $(document).on('click', '#form-general .botonsubmit', function (event) {
        event.preventDefault();
        $('#form-general').trigger('submit');
    });
});
