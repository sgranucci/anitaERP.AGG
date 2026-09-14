$(document).ready(function () {
    Biblioteca.validacionGeneral('form-general');

    // Preferir código cuando el ABM lo tiene (locales, maestros, etc.).
    var codigoEl = document.getElementById('codigo');
    if (codigoEl && !codigoEl.readOnly && !codigoEl.disabled) {
        codigoEl.focus();
        if (typeof codigoEl.select === 'function') {
            codigoEl.select();
        }
        return;
    }

    var nombreEl = document.getElementById('nombre');
    if (nombreEl && !nombreEl.readOnly && !nombreEl.disabled) {
        nombreEl.focus();
    }
});
