@once
<script>
$(function () {
    var $modal = $('#modalCircuitoDocumentosRelacionados');
    var $titulo = $('#modalCircuitoDocumentosRelacionadosTitulo');
    var $cuerpo = $('#circuitoDocumentosRelacionadosCuerpo');

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function btnGrupo(doc, labelCorto) {
        if (!doc) {
            return '<span class="text-muted small">—</span>';
        }
        var html = '<div class="d-inline-flex flex-wrap align-items-center">';
        html += '<span class="mr-1 small font-weight-bold">' + esc(labelCorto || doc.etiqueta || doc.numero || '') + '</span>';
        if (doc.url_ver) {
            html += '<a href="' + doc.url_ver + '" class="btn btn-outline-secondary btn-sm mr-1 mb-1" title="Consultar" target="_blank" rel="noopener noreferrer"><i class="fa fa-eye"></i></a>';
        }
        if (doc.url_pdf) {
            html += '<a href="' + doc.url_pdf + '" class="btn btn-outline-danger btn-sm mr-1 mb-1" title="PDF" target="_blank" rel="noopener noreferrer"><i class="fa fa-file-pdf-o"></i></a>';
        }
        if (doc.url_pdf_apaisado) {
            html += '<a href="' + doc.url_pdf_apaisado + '" class="btn btn-outline-secondary btn-sm mb-1" title="PDF apaisado" target="_blank" rel="noopener noreferrer"><i class="fa fa-arrows-alt-h"></i></a>';
        }
        if (!doc.url_ver && !doc.url_pdf) {
            html += '<span class="text-muted small">Sin permiso</span>';
        }
        html += '</div>';
        return html;
    }

    function celdaLista(docs, prefijo) {
        if (!docs || !docs.length) {
            return '<span class="text-muted small">—</span>';
        }
        var html = '';
        docs.forEach(function (c, idx) {
            if (idx > 0) {
                html += '<hr class="my-1">';
            }
            var label = prefijo
                ? (prefijo + ' ' + (c.numero || c.etiqueta || ''))
                : (c.etiqueta || c.numero || '');
            html += btnGrupo(c, label);
        });
        return html;
    }

    function filaVacia(r) {
        return !r.requisicion && !r.ordencompra && !(r.coms && r.coms.length) && !r.factura && !(r.ops && r.ops.length);
    }

    $(document).on('click', '.js-circuito-documentos-relacionados', function () {
        var url = $(this).data('url');
        var label = $(this).data('numero') || $(this).data('etiqueta') || '';
        if (!url) {
            return;
        }
        $titulo.text('Documentos relacionados' + (label ? ' — ' + label : ''));
        $cuerpo.html('<p class="text-muted mb-0">Cargando…</p>');
        $modal.modal('show');

        $.getJSON(url)
            .done(function (data) {
                var filas = (data.filas || []).filter(function (r) { return !filaVacia(r); });
                var origen = data.origen || '';

                function tieneVinculo(r) {
                    if (origen !== 'requisicion' && r.requisicion) return true;
                    if (origen !== 'ordencompra' && r.ordencompra) return true;
                    if (origen !== 'recepcion' && r.coms && r.coms.length) return true;
                    if (origen !== 'factura' && r.factura) return true;
                    if (r.ops && r.ops.length) return true;
                    // Desde RQ/OC/COM/factura el “adelante” también cuenta
                    if (origen === 'requisicion' && (r.ordencompra || (r.coms && r.coms.length) || r.factura || (r.ops && r.ops.length))) return true;
                    if (origen === 'ordencompra' && (r.requisicion || (r.coms && r.coms.length) || r.factura || (r.ops && r.ops.length))) return true;
                    if (origen === 'recepcion' && (r.requisicion || r.ordencompra || r.factura || (r.ops && r.ops.length))) return true;
                    if (origen === 'factura' && (r.requisicion || r.ordencompra || (r.coms && r.coms.length) || (r.ops && r.ops.length))) return true;
                    return false;
                }

                if (filas.length === 0 || !filas.some(tieneVinculo)) {
                    $cuerpo.html('<p class="text-muted mb-0">No hay otros documentos vinculados en el circuito de compras.</p>');
                    return;
                }

                var html = '<div class="table-responsive"><table class="table table-sm table-striped table-bordered mb-0">';
                html += '<thead style="background:#85C1E9;color:#17202A;"><tr>';
                html += '<th>Requisición</th>';
                html += '<th>Orden de compra</th>';
                html += '<th>Recepción COM</th>';
                html += '<th>Factura</th>';
                html += '<th>Orden de pago</th>';
                html += '</tr></thead><tbody>';
                filas.forEach(function (r) {
                    html += '<tr>';
                    html += '<td>' + (r.requisicion ? btnGrupo(r.requisicion, 'REQ ' + (r.requisicion.numero || '')) : '<span class="text-muted small">—</span>') + '</td>';
                    html += '<td>' + (r.ordencompra ? btnGrupo(r.ordencompra, 'OC ' + (r.ordencompra.numero || '')) : '<span class="text-muted small">—</span>') + '</td>';
                    html += '<td>' + celdaLista(r.coms, 'COM') + '</td>';
                    html += '<td>' + (r.factura ? btnGrupo(r.factura, '') : '<span class="text-muted small">—</span>') + '</td>';
                    html += '<td>' + celdaLista(r.ops, '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                html += '<p class="text-muted small mb-0 mt-2">Ojo / PDF abren consulta o el archivo en una pestaña nueva. El circuito se arma desde el documento de esta grilla.</p>';
                $cuerpo.html(html);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'No se pudo cargar la información.';
                $cuerpo.html('<p class="text-danger mb-0">' + esc(msg) + '</p>');
            });
    });
});
</script>
@endonce
