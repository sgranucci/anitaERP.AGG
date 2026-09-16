(function () {
    'use strict';

    var form = document.getElementById('form-tn-facturar');
    var overlay = document.getElementById('tn-facturar-overlay');
    var btnAdd = document.getElementById('btn-agregar-medio');
    var tbody = document.querySelector('#tabla-medios-tn tbody');
    var tpl = document.getElementById('tpl-medio-tn');

    function mostrarOverlay() {
        if (!overlay) return;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    if (btnAdd && tbody && tpl) {
        btnAdd.addEventListener('click', function () {
            var node = tpl.content.cloneNode(true);
            tbody.appendChild(node);
        });
    }

    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-quitar-medio');
            if (!btn) return;
            var row = btn.closest('tr');
            if (row && tbody.querySelectorAll('tr.medio-row').length > 1) {
                row.remove();
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                return;
            }
            var totalPedido = parseFloat((document.getElementById('tn-total-pedido') || {}).textContent || '0') || 0;
            var suma = 0;
            form.querySelectorAll('.monto-medio').forEach(function (inp) {
                suma += parseFloat(inp.value || '0') || 0;
            });
            if (Math.abs(suma - totalPedido) > 0.05) {
                e.preventDefault();
                alert('La suma de medios (' + suma.toFixed(2) + ') debe coincidir con el total del pedido (' + totalPedido.toFixed(2) + ').');
                return;
            }
            mostrarOverlay();
        });
    }

    window.addEventListener('pageshow', ocultarOverlay);
})();
