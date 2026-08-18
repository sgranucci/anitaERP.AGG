(function () {
    function mostrarOverlay() {
        var overlay = document.getElementById('rsd-overlay');
        if (!overlay) return;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultarOverlay() {
        var overlay = document.getElementById('rsd-overlay');
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-ejecutar-rsd');
        if (form) {
            form.addEventListener('submit', function () {
                if (form.checkValidity()) mostrarOverlay();
            });
        }
        document.querySelectorAll('a[href*="listar-reporte-sueldos-definible"]').forEach(function (a) {
            a.addEventListener('click', mostrarOverlay);
        });
        window.addEventListener('pageshow', ocultarOverlay);

        document.querySelectorAll('.rsd-drill').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var reporteId = a.getAttribute('data-reporte-id');
                var url = (window.carpetaBase || '') + '/sueldos/reporte-definible/' + reporteId + '/drill';
                var params = new URLSearchParams({
                    columna_id: a.getAttribute('data-columna-id'),
                    legajo: a.getAttribute('data-legajo'),
                    liquidacion_id: a.getAttribute('data-liquidacion-id'),
                });
                fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var body = document.getElementById('drill-rsd-body');
                        if (!body) return;
                        body.innerHTML = '';
                        (data.lineas || []).forEach(function (ln) {
                            var tr = document.createElement('tr');
                            tr.innerHTML = '<td>' + ln.concepto_codigo + '</td><td>' + (ln.concepto_descripcion || '') +
                                '</td><td class="text-right">' + ln.cantidad + '</td><td class="text-right">' + ln.valor +
                                '</td><td class="text-right">' + ln.importe + '</td>';
                            body.appendChild(tr);
                        });
                        if (window.jQuery) {
                            window.jQuery('#modal-drill-rsd').modal('show');
                        }
                    });
            });
        });
    });
})();
