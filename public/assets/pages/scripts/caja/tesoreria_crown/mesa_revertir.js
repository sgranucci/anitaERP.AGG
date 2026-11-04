(function () {
    var modal = document.getElementById('modal-revertir-mesa');
    if (!modal || modal.getAttribute('data-listo') === '1' || !window.jQuery) {
        return;
    }
    modal.setAttribute('data-listo', '1');
    document.querySelectorAll('.js-revertir-mesa').forEach(function (boton) {
        boton.addEventListener('click', function () {
            document.getElementById('form-revertir-mesa').setAttribute('action', boton.getAttribute('data-url') || '');
            document.getElementById('revertir-mesa-numero').textContent = boton.getAttribute('data-numero') || '';
            document.getElementById('revertir-mesa-motivo').value = '';
            document.getElementById('revertir-mesa-usuario').value = '';
            document.getElementById('revertir-mesa-clave').value = '';
            window.jQuery('#modal-revertir-mesa').modal('show');
        });
    });
    window.jQuery('#modal-revertir-mesa').on('shown.bs.modal', function () {
        var campo = document.getElementById('revertir-mesa-motivo');
        if (campo) {
            campo.focus();
        }
    });
})();
