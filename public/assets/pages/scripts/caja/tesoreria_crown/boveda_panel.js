/* Panel bóveda: revertir + detalle de billete en arqueo (escritorio y menú). */
(function () {
    'use strict';

    document.querySelectorAll('.js-revertir-boveda').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var form = document.getElementById('form-revertir-boveda');
            if (!form || !window.jQuery) {
                return;
            }
            form.action = boton.getAttribute('data-url') || '';
            form.querySelector('[name="motivo"]').value = '';
            form.querySelector('[name="firma_usuario"]').value = '';
            form.querySelector('[name="firma_password"]').value = '';
            document.getElementById('revertir-boveda-numero').textContent = boton.getAttribute('data-numero') || '';
            window.jQuery('#modal-revertir-boveda').modal('show');
        });
    });

    (function () {
        var panel = document.getElementById('tc-herramienta-arqueo');
        var modal = document.getElementById('modal-arqueo-billete');
        if (!panel || !modal || !window.jQuery) {
            return;
        }
        function texto(numero) {
            return Number(numero).toLocaleString('es-AR', { maximumFractionDigits: 2 });
        }
        function escapar(valor) {
            return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function filaHtml(fila) {
            var cantidad = Number(fila.cantidad);
            var clase = cantidad < 0 ? 'text-danger' : '';
            return '<tr>'
                + '<td>' + escapar(fila.fecha) + '</td>'
                + '<td><a class="text-primary" target="_blank" rel="noopener" href="' + escapar(fila.url) + '">' + escapar(fila.numero) + '</a></td>'
                + '<td>' + escapar(fila.tipo) + '</td>'
                + '<td>' + escapar(fila.efecto) + '</td>'
                + '<td class="text-right ' + clase + '">' + texto(cantidad) + '</td>'
                + '<td class="text-right">' + texto(fila.saldo) + '</td>'
                + '<td>' + escapar(fila.comentario) + '</td>'
                + '</tr>';
        }
        document.querySelectorAll('.js-arqueo-billete').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var cuerpo = document.getElementById('arqueo-billete-filas');
                var titulo = document.getElementById('arqueo-billete-titulo');
                var hasta = document.getElementById('arqueo-billete-hasta');
                cuerpo.innerHTML = '<tr><td colspan="7" class="text-muted">Leyendo…</td></tr>';
                titulo.textContent = 'Operaciones';
                hasta.textContent = '';
                window.jQuery(modal).modal('show');
                var url = new URL(panel.getAttribute('data-url'), window.location.origin);
                url.searchParams.set('valor_id', boton.getAttribute('data-valor') || '');
                window.fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (respuesta) {
                        if (!respuesta.ok) {
                            throw new Error('respuesta');
                        }
                        return respuesta.json();
                    })
                    .then(function (datos) {
                        titulo.textContent = datos.titulo || 'Operaciones';
                        hasta.textContent = 'Hasta el ' + (datos.hasta || '') + '. Entra suma y sale resta. La última fila es el stock del arqueo: ' + texto(datos.saldo) + '.';
                        if (!datos.filas || !datos.filas.length) {
                            cuerpo.innerHTML = '<tr><td colspan="7" class="text-muted">Este billete no tiene movimientos que muevan el saldo.</td></tr>';
                            return;
                        }
                        cuerpo.innerHTML = datos.filas.map(filaHtml).join('');
                    })
                    .catch(function () {
                        cuerpo.innerHTML = '<tr><td colspan="7" class="text-danger">No se pudo leer el detalle.</td></tr>';
                    });
            });
        });
    })();

    if (window.jQuery) {
        window.jQuery('#modal-revertir-boveda').on('shown.bs.modal', function () {
            var campo = document.getElementById('revertir-boveda-motivo');
            if (campo) {
                campo.focus();
            }
        });
    }
})();
