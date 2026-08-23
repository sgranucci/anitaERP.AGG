(function () {
    function renumerar(tbody) {
        var filas = tbody.querySelectorAll('tr.posfin-orden-fila');
        filas.forEach(function (fila, indice) {
            var input = fila.querySelector('.posfin-orden-input');
            if (input) {
                input.value = String((indice + 1) * 10);
            }
        });
        actualizarPreview(tbody);
    }

    function actualizarPreview(tbody) {
        var pane = tbody.closest('.tab-pane');
        if (! pane) {
            return;
        }
        var lista = pane.querySelector('.posfin-orden-preview-lista');
        if (! lista) {
            return;
        }
        lista.innerHTML = '';
        tbody.querySelectorAll('tr.posfin-orden-fila .posfin-orden-concepto').forEach(function (celda) {
            var item = document.createElement('li');
            item.textContent = (celda.textContent || '').trim();
            lista.appendChild(item);
        });
    }

    function intercambiar(fila, direccion) {
        var destino = direccion === 'up' ? fila.previousElementSibling : fila.nextElementSibling;
        if (! destino || ! destino.classList.contains('posfin-orden-fila')) {
            return;
        }
        if (direccion === 'up') {
            fila.parentNode.insertBefore(fila, destino);
        } else {
            fila.parentNode.insertBefore(destino, fila);
        }
        renumerar(fila.parentNode);
    }

    document.addEventListener('click', function (event) {
        var subir = event.target.closest('.posfin-orden-subir');
        var bajar = event.target.closest('.posfin-orden-bajar');
        if (! subir && ! bajar) {
            return;
        }
        event.preventDefault();
        var fila = event.target.closest('tr.posfin-orden-fila');
        if (! fila) {
            return;
        }
        intercambiar(fila, subir ? 'up' : 'down');
    });

    document.querySelectorAll('.posfin-orden-tbody').forEach(function (tbody) {
        actualizarPreview(tbody);
    });
})();
