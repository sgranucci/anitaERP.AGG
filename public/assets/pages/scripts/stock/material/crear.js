$(document).ready(function () {
    Biblioteca.validacionGeneral('form-general');
    $('#codigo').on('change',function(){
        $('#codigo').val(zfill($(this).val(), 8))
    })
});
