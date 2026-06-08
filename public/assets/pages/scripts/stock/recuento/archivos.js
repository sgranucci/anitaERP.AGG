(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const template = document.getElementById('template-renglon-archivo');
        const tbody = document.getElementById('tbody-tabla-archivo');
        if (!template || !tbody) return;

        document.getElementById('agrega_renglon_archivo')?.addEventListener('click', function () {
            tbody.appendChild(template.content.firstElementChild.cloneNode(true));
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.closest('.eliminararchivo')) {
                const row = e.target.closest('tr');
                if (tbody.querySelectorAll('tr.item-archivo').length > 1) {
                    row.remove();
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.eliminar-archivo-recuento')) {
                e.target.closest('.col-md-6, .col-lg-4')?.remove();
            }
        });
    });
})();
