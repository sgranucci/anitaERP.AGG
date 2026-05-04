$(document).ready(function () {
    Biblioteca.validacionGeneral('form-general');

	var nombreEl = document.getElementById("nombre");
	if (nombreEl) {
		nombreEl.focus();
	}
});

