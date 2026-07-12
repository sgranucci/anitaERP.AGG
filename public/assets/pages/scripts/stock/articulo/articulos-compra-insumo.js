/**
 * Artículos de compra vinculados a un insumo (solapa Fórmulas del ABM artículo).
 */
(function () {
    'use strict';

    function urlArticulosCompraPorInsumo(articuloId) {
        var el = document.getElementById('articulos-compra-por-insumo-url');
        if (el && el.value) {
            return String(el.value).replace(/\/0(\?|$)/, '/' + parseInt(articuloId, 10) + '$1');
        }
        if (typeof carpetaBase !== 'undefined' && carpetaBase) {
            return String(carpetaBase).replace(/\/$/, '') + '/stock/formula-articulo/articulos-compra-por-insumo/' + parseInt(articuloId, 10);
        }
        return '/stock/formula-articulo/articulos-compra-por-insumo/' + parseInt(articuloId, 10);
    }

    function urlEditarArticulo(id) {
        var base = '';
        if (typeof carpetaBase !== 'undefined' && carpetaBase) {
            base = String(carpetaBase).replace(/\/$/, '') + '/stock/articulo';
        } else {
            base = '/stock/articulo';
        }
        return base + '/' + parseInt(id, 10) + '/editar?origen=modal_consulta&vista=consulta';
    }

    function escHtml(texto) {
        return $('<div>').text(texto != null ? String(texto) : '').html();
    }

    $(document).on('click', '#btn-articulos-compra-insumo-articulo', function (e) {
        e.preventDefault();
        var articuloId = parseInt($('#articulo_id').val(), 10) || 0;
        if (articuloId <= 0) {
            alert('Art\u00edculo no disponible.');
            return;
        }

        var url = urlArticulosCompraPorInsumo(articuloId);
        var $tb = $('#tbody-articulos-compra-insumo-modal');
        var $sub = $('#modalArticulosCompraInsumoSubtitulo');
        $tb.html('<tr><td colspan="4" class="text-muted">Cargando\u2026</td></tr>');
        $sub.text('Art\u00edculos de compra cuyo campo SKU alt./insumo apunta a este insumo.');

        $.get(url, function (resp) {
            var rows = (resp && resp.datos) ? resp.datos : [];
            var insumo = (resp && resp.insumo) ? resp.insumo : null;
            if (insumo && (insumo.sku || insumo.descripcion)) {
                var partesInsumo = [];
                if (insumo.sku) {
                    partesInsumo.push(String(insumo.sku));
                }
                if (insumo.descripcion) {
                    partesInsumo.push(String(insumo.descripcion));
                }
                $sub.text('Insumo: ' + partesInsumo.join(' \u2014 ')
                    + '. Art\u00edculos de compra con SKU alt./insumo que apuntan a este insumo.');
            }
            var html = '';
            if (!rows.length) {
                html = '<tr><td colspan="4" class="text-muted">Ning\u00fan art\u00edculo de compra vincula este insumo por SKU alt./insumo.</td></tr>';
            } else {
                rows.forEach(function (r) {
                    var ida = r.id != null ? String(r.id) : '';
                    var sku = r.sku != null ? String(r.sku) : '';
                    var desc = r.descripcion != null ? String(r.descripcion) : '';
                    var skuAlt = r.skualternativo != null ? String(r.skualternativo) : '';
                    var link = urlEditarArticulo(parseInt(ida, 10) || 0);
                    html += '<tr><td>' + escHtml(ida) + '</td>' +
                        '<td><a href="' + link + '" target="_blank" rel="noopener noreferrer" class="text-primary">' + escHtml(sku) + '</a></td>' +
                        '<td>' + escHtml(desc) + '</td>' +
                        '<td class="text-monospace">' + escHtml(skuAlt) + '</td></tr>';
                });
            }
            $tb.html(html);
            $('#modalArticulosCompraInsumo').modal('show');
        }).fail(function (xhr) {
            var msg = 'Error al cargar el listado.';
            if (xhr && xhr.status === 403) {
                msg = 'No tiene permisos para consultar art\u00edculos de compra.';
            }
            $tb.html('<tr><td colspan="4" class="text-danger">' + msg + '</td></tr>');
            $('#modalArticulosCompraInsumo').modal('show');
        });
    });
})();
