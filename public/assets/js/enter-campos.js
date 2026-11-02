(function () {
    function editables(form) {
        return Array.prototype.filter.call(form.querySelectorAll('input, select, textarea'), function (campo) {
            if (campo.disabled || campo.readOnly || (campo.closest && campo.closest('.is-off'))) {
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

    function esBotonEnviar(campo) {
        return campo && campo.matches && campo.matches('button[type="submit"], input[type="submit"]');
    }

    function enfocar(campo) {
        if (!campo) {
            return;
        }
        var aplicar = function () {
            campo.focus();
            var tipo = (campo.type || '').toLowerCase();
            if (campo.tagName === 'SELECT' || tipo === 'number' || tipo === 'date' || tipo === 'time' || tipo === 'month') {
                return;
            }
            if (typeof campo.select === 'function') {
                try {
                    campo.select();
                } catch (error) {
                    return;
                }
            }
        };
        if ((campo.type || '').toLowerCase() === 'number') {
            window.setTimeout(aplicar, 0);
            return;
        }
        aplicar();
    }

    document.querySelectorAll('form[data-enter-campos]').forEach(function (form) {
        var activaBoton = form.hasAttribute('data-enter-boton');
        form.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter' || esSaltoDeLinea(evento)) {
                return;
            }
            if (activaBoton && esBotonEnviar(evento.target)) {
                return;
            }
            evento.preventDefault();
            if (typeof evento.target.reportValidity === 'function' && !evento.target.reportValidity()) {
                return;
            }
            var campos = editables(form);
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
            enfocar(siguiente);
        });
        form.addEventListener('keypress', function (evento) {
            if (evento.key !== 'Enter' || esSaltoDeLinea(evento)) {
                return;
            }
            if (activaBoton && esBotonEnviar(evento.target)) {
                return;
            }
            evento.preventDefault();
        });
    });
})();
