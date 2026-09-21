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

    function formatoImporte(n) {
        var partes = (Math.round(n * 100) / 100).toFixed(2).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes[0] + ',' + partes[1];
    }

    function totalSeleccion() {
        var total = 0;
        document.querySelectorAll('.tn-linea-check').forEach(function (chk) {
            if (!chk.checked) return;
            var cantInp = document.querySelector('.tn-linea-cant[data-linea="' + chk.getAttribute('data-linea') + '"]');
            var cant = cantInp ? (parseFloat(cantInp.value || '0') || 0) : 0;
            var precio = parseFloat(chk.getAttribute('data-precio') || '0') || 0;
            total += cant * precio;
        });
        return Math.round(total * 100) / 100;
    }

    function aplicarTotalSeleccion() {
        var total = totalSeleccion();
        var texto = total.toFixed(2);
        var cabecera = document.getElementById('tn-total-cabecera');
        if (cabecera) cabecera.textContent = formatoImporte(total);
        var el = document.getElementById('tn-total-pedido');
        if (el) el.textContent = texto;
        document.querySelectorAll('.tn-linea-check').forEach(function (chk) {
            var id = chk.getAttribute('data-linea');
            var celda = document.querySelector('.tn-linea-subtotal[data-linea="' + id + '"]');
            if (!celda) return;
            if (!chk.checked) {
                celda.textContent = formatoImporte(0);
                return;
            }
            var cantInp = document.querySelector('.tn-linea-cant[data-linea="' + id + '"]');
            var cant = cantInp ? (parseFloat(cantInp.value || '0') || 0) : 0;
            var precio = parseFloat(chk.getAttribute('data-precio') || '0') || 0;
            celda.textContent = formatoImporte(cant * precio);
        });
        var montos = document.querySelectorAll('#tabla-medios-tn .monto-medio');
        if (montos.length === 1) {
            montos[0].value = texto;
        }
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('.tn-linea-check, .tn-linea-cant')) {
            aplicarTotalSeleccion();
        }
    });
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t || !t.classList || !t.classList.contains('tn-linea-cant')) return;
        aplicarTotalSeleccion();
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                return;
            }
            var checks = document.querySelectorAll('.tn-linea-check');
            if (checks.length && !document.querySelector('.tn-linea-check:checked')) {
                e.preventDefault();
                alert('Elegí al menos un artículo para esta factura.');
                return;
            }
            var totalPedido = parseFloat((document.getElementById('tn-total-pedido') || {}).textContent || '0') || 0;
            var suma = 0;
            form.querySelectorAll('.monto-medio').forEach(function (inp) {
                suma += parseFloat(inp.value || '0') || 0;
            });
            if (Math.abs(suma - totalPedido) > 0.05) {
                e.preventDefault();
                alert('La suma de medios (' + suma.toFixed(2) + ') debe coincidir con el total a facturar (' + totalPedido.toFixed(2) + ').');
                return;
            }
            if (totalPedido <= 0) {
                e.preventDefault();
                alert('El total a facturar debe ser mayor a cero.');
                return;
            }
            mostrarOverlay();
        });
    }

    window.addEventListener('pageshow', ocultarOverlay);
})();
