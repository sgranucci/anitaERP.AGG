(function () {
    var empresa = document.getElementById('empresa_id');
    if (!empresa) {
        return;
    }

    function filtrar() {
        var id = String(empresa.value || '');
        document.querySelectorAll('.js-sala-opcion').forEach(function (el) {
            var ok = id === '' || el.getAttribute('data-empresa') === id;
            el.style.display = ok ? '' : 'none';
            var input = el.querySelector('input[type="radio"]');
            if (!ok && input && input.checked) {
                input.checked = false;
            }
        });
    }

    empresa.addEventListener('change', filtrar);
    filtrar();
})();
