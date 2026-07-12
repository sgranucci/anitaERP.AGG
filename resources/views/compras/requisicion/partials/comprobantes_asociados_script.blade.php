@once
<script>
$(function () {
    var baseUrl = String(typeof carpetaBase !== 'undefined' ? carpetaBase : '').replace(/\/$/, '') + '/compras/requisicion';
    var $modal = $('#modalRequisicionComprobantes');
    var $titulo = $('#modalRequisicionComprobantesTitulo');
    var $cuerpo = $('#requisicionComprobantesCuerpo');
    var $prox = $('#requisicionComprobantesProximamente');

    $(document).on('click', '.js-requisicion-comprobantes', function () {
        var id = $(this).data('id');
        var num = $(this).data('numero') || '';
        $titulo.text('Órdenes de compra vinculadas — Requisición ' + num);
        $cuerpo.html('<p class="text-muted mb-0">Cargando…</p>');
        $prox.empty();
        $modal.modal('show');

        $.getJSON(baseUrl + '/' + id + '/comprobantes-asociados')
            .done(function (data) {
                var filas = data.filas || [];
                if (filas.length === 0) {
                    $cuerpo.html('<p class="text-muted mb-0">No hay órdenes de compra vinculadas por ahora.</p>');
                } else {
                    var html = '<div class="table-responsive"><table class="table table-sm table-striped table-bordered mb-0">';
                    html += '<thead><tr><th>Tipo</th><th>Número</th><th>Fecha</th><th>Estado</th><th class="text-center" style="min-width:11rem">Acciones</th></tr></thead><tbody>';
                    filas.forEach(function (r) {
                        html += '<tr>';
                        html += '<td><small>' + $('<div>').text(r.tipo_etiqueta || r.tipo).html() + '</small></td>';
                        html += '<td><small>' + $('<div>').text(r.numero || '').html() + '</small></td>';
                        html += '<td><small>' + $('<div>').text(r.fecha || '').html() + '</small></td>';
                        html += '<td><small>' + $('<div>').text(r.estado || '').html() + '</small></td>';
                        html += '<td class="text-center text-nowrap">';
                        if (r.url_ver) {
                            html += '<a href="' + r.url_ver + '" class="btn btn-outline-secondary btn-sm mr-1 mb-1" title="Consultar" target="_blank" rel="noopener noreferrer"><i class="fa fa-eye"></i></a>';
                        }
                        if (r.url_editar) {
                            html += '<a href="' + r.url_editar + '" class="btn btn-outline-primary btn-sm mr-1 mb-1" title="Editar" target="_blank" rel="noopener noreferrer"><i class="fa fa-edit"></i></a>';
                        }
                        if (r.url_imprimir_vertical) {
                            html += '<a href="' + r.url_imprimir_vertical + '" class="btn btn-outline-secondary btn-sm mr-1 mb-1" title="Imprimir PDF (vertical)" target="_blank" rel="noopener noreferrer"><i class="fa fa-print"></i></a>';
                        }
                        if (r.url_imprimir_apaisado) {
                            html += '<a href="' + r.url_imprimir_apaisado + '" class="btn btn-outline-secondary btn-sm mb-1" title="PDF Legal apaisado" target="_blank" rel="noopener noreferrer"><i class="fa fa-arrows-alt-h"></i></a>';
                        }
                        if (!r.url_ver && !r.url_editar && !r.url_imprimir_vertical) {
                            html += '<span class="text-muted small">Sin permisos para ver o imprimir OC</span>';
                        }
                        html += '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $cuerpo.html(html);
                }
                if (data.proximamente && data.proximamente.length) {
                    var p = '<p class="text-muted small mb-0 mt-2"><strong>Próximamente:</strong> ';
                    p += data.proximamente.map(function (t) { return $('<div>').text(t).html(); }).join(' · ');
                    p += '</p>';
                    $prox.html(p);
                } else {
                    $prox.empty();
                }
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'No se pudo cargar la información.';
                $cuerpo.html('<p class="text-danger mb-0">' + $('<div>').text(msg).html() + '</p>');
            });
    });
});
</script>
@endonce
