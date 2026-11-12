(function () {
    function checksPendientes() {
        return Array.prototype.slice.call(document.querySelectorAll('.ib-envio-check'));
    }

    function actualizarResumen() {
        var checks = checksPendientes().filter(function (c) {
            return c.checked;
        });
        var total = 0;
        checks.forEach(function (c) {
            total += parseFloat(c.getAttribute('data-importe') || '0') || 0;
        });
        var countEl = document.getElementById('ib-envio-count');
        var impEl = document.getElementById('ib-envio-importe');
        if (countEl) {
            countEl.textContent = String(checks.length);
        }
        if (impEl) {
            impEl.textContent = total.toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }
        var all = document.getElementById('ib-envio-check-all');
        if (all) {
            var pend = checksPendientes();
            all.checked = pend.length > 0 && checks.length === pend.length;
            all.indeterminate = checks.length > 0 && checks.length < pend.length;
        }
    }

    function mostrarOverlay() {
        var overlay = document.getElementById('ib-envio-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('ib-envio-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var all = document.getElementById('ib-envio-check-all');
        if (all) {
            all.addEventListener('change', function () {
                checksPendientes().forEach(function (c) {
                    c.checked = all.checked;
                });
                actualizarResumen();
            });
        }

        checksPendientes().forEach(function (c) {
            c.addEventListener('change', actualizarResumen);
        });

        var btnTodas = document.getElementById('ib-envio-sel-todas');
        if (btnTodas) {
            btnTodas.addEventListener('click', function () {
                checksPendientes().forEach(function (c) {
                    c.checked = true;
                });
                actualizarResumen();
            });
        }

        var btnNinguna = document.getElementById('ib-envio-sel-ninguna');
        if (btnNinguna) {
            btnNinguna.addEventListener('click', function () {
                checksPendientes().forEach(function (c) {
                    c.checked = false;
                });
                actualizarResumen();
            });
        }

        var form = document.getElementById('form-ib-envio-enviar');
        var btnEnviar = document.getElementById('ib-envio-enviar');
        if (form && btnEnviar) {
            btnEnviar.addEventListener('click', function (ev) {
                ev.preventDefault();
                var n = checksPendientes().filter(function (c) {
                    return c.checked;
                }).length;
                if (n <= 0) {
                    alert('Seleccione al menos una transferencia pendiente.');
                    return;
                }
                if (!confirm('¿Enviar ' + n + ' transferencia(s) a Interbanking?')) {
                    return;
                }
                mostrarOverlay();
                form.submit();
            });
        }

        window.addEventListener('pageshow', ocultarOverlay);
        actualizarResumen();
    });
})();
