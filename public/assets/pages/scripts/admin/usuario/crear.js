$(document).ready(function () {
    if (typeof activa_eventos_consultavendedor === 'function') {
        activa_eventos_consultavendedor();
    }

    $.validator.addMethod('asignacionRequerida', function (value) {
        return String(value || '').trim() !== '';
    }, 'Debe asignar al menos un ítem.');

    const reglas = {
        re_password: {
            equalTo: "#password"
        },
        rol_id_validacion: {
            asignacionRequerida: true
        },
        empresa_ids_validacion: {
            asignacionRequerida: true
        }
    };
    const mensajes = {
        re_password:
        {
            equalTo: 'Las contraseñas no coinciden'
        },
        rol_id_validacion: {
            asignacionRequerida: 'Debe asignar al menos un rol.'
        },
        empresa_ids_validacion: {
            asignacionRequerida: 'Debe asignar al menos una empresa.'
        }
    };
    Biblioteca.validacionGeneral('form-general', reglas, mensajes);
    $('#password').on('change', function(){
        const valor = $(this).val();
        if(valor != ''){
            $('#re_password').prop('required', true);
        }else{
            $('#re_password').prop('required', false);
        }
    });

    $('#foto').each(function () {
        var $foto = $(this);
        var previewUrl = ($foto.data('initial-preview') || '').toString().trim();
        var tieneFoto = String($foto.data('tiene-foto') || '0') === '1' && previewUrl !== '';

        $foto.fileinput({
            language: 'es',
            allowedFileExtensions: ['jpg', 'jpeg', 'png'],
            maxFileSize: 1000,
            showUpload: false,
            showClose: false,
            initialPreviewAsData: true,
            initialPreview: tieneFoto ? [previewUrl] : [],
            initialPreviewConfig: tieneFoto ? [{ caption: 'Foto actual', showRemove: true, key: 1 }] : [],
            overwriteInitial: true,
            dropZoneEnabled: false,
            theme: 'fa',
            browseClass: 'btn btn-outline-primary btn-sm',
            removeClass: 'btn btn-outline-danger btn-sm',
        });

        $foto.on('filecleared fileremoved', function () {
            $('#quitar_foto').val('1');
        });

        $foto.on('fileselect fileloaded', function () {
            $('#quitar_foto').val('0');
        });
    });
});
