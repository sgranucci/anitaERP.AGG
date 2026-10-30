(function () {
    function editables(form) {
        return Array.prototype.filter.call(form.querySelectorAll('input, select, textarea'), function (campo) {
            if (campo.disabled || campo.readOnly) {
                return false;
            }
            var tipo = (campo.type || '').toLowerCase();
            if (tipo === 'hidden' || tipo === 'submit' || tipo === 'button' || tipo === 'reset' || tipo === 'image') {
                return false;
            }
            return campo.tabIndex >= 0;
        });
    }

    function esSaltoDeLinea(evento) {
        return evento.key === 'Enter' && evento.target.tagName === 'TEXTAREA';
    }

    document.querySelectorAll('form[data-enter-campos]').forEach(function (form) {
        var campos = editables(form);
        form.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter' || esSaltoDeLinea(evento)) {
                return;
            }
            evento.preventDefault();
            var indice = campos.indexOf(evento.target);
            if (indice < 0) {
                return;
            }
            var siguiente = campos[indice + 1];
            if (!siguiente) {
                var boton = form.querySelector('button[type="submit"], input[type="submit"]');
                if (boton) {
                    boton.focus();
                }
                return;
            }
            siguiente.focus();
            if (siguiente.tagName !== 'SELECT' && typeof siguiente.select === 'function') {
                siguiente.select();
            }
        });
        form.addEventListener('keypress', function (evento) {
            if (evento.key !== 'Enter' || esSaltoDeLinea(evento)) {
                return;
            }
            evento.preventDefault();
        });
    });
})();
