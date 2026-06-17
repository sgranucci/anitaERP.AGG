$(document).ready(function () {
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

    $('#foto').fileinput({
        language: 'es',
        allowedFileExtensions: ['jpg', 'jpeg', 'png'],
        maxFileSize: 1000,
        showUpload: false,
        showClose: false,
        initialPreviewAsData: true,
        dropZoneEnabled: false,
        theme: "fa",
    });
});
