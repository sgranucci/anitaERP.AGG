<div class="modal fade" id="modal-control-firmas-uif" tabindex="-1" role="dialog" aria-labelledby="modal-control-firmas-uif-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="modal-control-firmas-uif-titulo">Control de firmas UIF</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="control-firmas-resultado">
                <p class="text-muted mb-0">Consultando…</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var salida = document.getElementById('control-firmas-resultado');
    var url = @json(route('control_firmas_cliente_uif'));
    if (!salida) {
        return;
    }

    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pintarMensaje(texto, clase) {
        salida.innerHTML = '<div class="alert ' + clase + ' py-2 mb-0">' + esc(texto) + '</div>';
    }

    function pintarCuadro(cuadro) {
        var cliente = cuadro.cliente || {};
        var clase = cuadro.hay_aviso ? (cuadro.solo_riesgo_alto ? 'alert-warning' : 'alert-danger') : 'alert-success';
        var html = '<div class="alert ' + clase + ' py-2">';
        html += '<strong>' + esc(cliente.nombre) + '</strong>';
        html += ' · legajo ' + esc(cliente.id);
        html += ' · DNI ' + esc(cliente.documento);
        if (cliente.origen) {
            html += ' · ' + esc(cliente.origen);
        }
        html += '<div class="mt-1">' + esc(cuadro.resumen) + '</div>';
        if (cliente.url) {
            html += '<div class="mt-1"><a href="' + esc(cliente.url) + '">Abrir ficha</a></div>';
        }
        html += '</div>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered bg-white mb-2">';
        html += '<thead style="background:#85C1E9;color:#17202A;"><tr><th>Requisito</th><th>Fecha cargada</th><th>¿Aviso?</th></tr></thead><tbody>';
        (cuadro.filas || []).forEach(function (fila) {
            html += '<tr' + (fila.alerta ? ' class="table-warning"' : '') + '>';
            html += '<td>' + esc(fila.requisito) + '</td>';
            html += '<td>' + esc(fila.cargado) + '</td>';
            html += '<td>' + esc(fila.aviso) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        if (cuadro.nota) {
            html += '<p class="text-muted small mb-0">' + esc(cuadro.nota) + '</p>';
        }
        salida.innerHTML = html;
    }

    function consultar(id) {
        salida.innerHTML = '<p class="text-muted mb-0">Consultando…</p>';
        fetch(url + '?id=' + encodeURIComponent(id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { okHttp: response.ok, data: data };
            });
        }).then(function (resultado) {
            var data = resultado.data || {};
            if (!resultado.okHttp || data.ok === false || !data.cuadro) {
                pintarMensaje(data.mensaje || 'No se pudo consultar el cliente.', 'alert-danger');
                return;
            }
            pintarCuadro(data.cuadro);
        }).catch(function () {
            pintarMensaje('No se pudo consultar el cliente.', 'alert-danger');
        });
    }

    document.addEventListener('click', function (event) {
        var boton = event.target.closest('.js-control-firmas');
        if (!boton) {
            return;
        }
        event.preventDefault();
        if (window.jQuery) {
            window.jQuery('#modal-control-firmas-uif').modal('show');
        }
        consultar(boton.getAttribute('data-id'));
    });
})();
</script>
