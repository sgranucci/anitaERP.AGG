/**
 * Fórmulas que incluyen el artículo (solapa Fórmulas del ABM artículo).
 */
(function () {
    'use strict';

    function urlFormulasPorArticulo(articuloId) {
        var el = document.getElementById('formulas-por-articulo-url');
        if (el && el.value) {
            return String(el.value).replace(/\/0(\?|$)/, '/' + parseInt(articuloId, 10) + '$1');
        }
        if (typeof carpetaBase !== 'undefined' && carpetaBase) {
            return String(carpetaBase).replace(/\/$/, '') + '/stock/formula-articulo/formulas-por-articulo/' + parseInt(articuloId, 10);
        }
        return '/stock/formula-articulo/formulas-por-articulo/' + parseInt(articuloId, 10);
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

    function urlEditarFormula(id) {
        var cfg = window.consultaFormulaArticuloConfig || {};
        var base = cfg.urlFormulaBase || '';
        if (!base && typeof carpetaBase !== 'undefined' && carpetaBase) {
            base = String(carpetaBase).replace(/\/$/, '') + '/stock/formula-articulo';
        }
        if (!base) {
            base = '/stock/formula-articulo';
        }
        return String(base).replace(/\/$/, '') + '/' + parseInt(id, 10) + '/editar?origen=modal_consulta&vista=consulta';
    }

    function urlModalFormula(id) {
        var cfg = window.consultaFormulaArticuloConfig || {};
        var base = cfg.urlFormulaBase || '';
        if (!base && typeof carpetaBase !== 'undefined' && carpetaBase) {
            base = String(carpetaBase).replace(/\/$/, '') + '/stock/formula-articulo';
        }
        if (!base) {
            base = '/stock/formula-articulo';
        }
        return String(base).replace(/\/$/, '') + '/' + parseInt(id, 10) + '/modal';
    }

    function escHtml(texto) {
        return $('<div>').text(texto != null ? String(texto) : '').html();
    }

    function formatoCantidad(valor) {
        var n = parseFloat(valor);
        if (isNaN(n)) {
            return '';
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
    }

    function abrirVistaFormula(formulaId) {
        var fid = parseInt(formulaId, 10) || 0;
        if (fid <= 0 || !$('#modalVerFormulaArticulo').length) {
            return;
        }
        $('#modalVerFormulaArticuloBody').html('<p class="text-muted">Cargando...</p>');
        var $ir = $('#modalVerFormulaArticuloIrCrud');
        if ($ir.length) {
            $ir.attr('href', urlEditarFormula(fid)).removeClass('d-none');
        }
        $('#modalFormulasUsoArticulo').one('hidden.bs.modal', function () {
            $('#modalVerFormulaArticulo').one('hidden.bs.modal', function () {
                $('#modalFormulasUsoArticulo').modal('show');
            });
            $('#modalVerFormulaArticulo').modal('show');
            $.get(urlModalFormula(fid), function (html) {
                $('#modalVerFormulaArticuloBody').html(html);
            }).fail(function () {
                $('#modalVerFormulaArticuloBody').html('<p class="text-danger">No se pudo cargar la f\u00f3rmula.</p>');
            });
        });
        $('#modalFormulasUsoArticulo').modal('hide');
    }

    $(document).on('click', '#btn-formulas-uso-articulo', function (e) {
        e.preventDefault();
        var articuloId = parseInt($('#articulo_id').val(), 10) || 0;
        if (articuloId <= 0) {
            alert('Art\u00edculo no disponible.');
            return;
        }

        var $tb = $('#tbody-formulas-uso-articulo');
        var $sub = $('#modalFormulasUsoArticuloSubtitulo');
        $tb.html('<tr><td colspan="8" class="text-muted">Cargando\u2026</td></tr>');
        $sub.text('F\u00f3rmulas que incluyen este art\u00edculo como componente o como subf\u00f3rmula.');

        $.get(urlFormulasPorArticulo(articuloId), function (resp) {
            var rows = (resp && resp.datos) ? resp.datos : [];
            var articulo = (resp && resp.articulo) ? resp.articulo : null;
            if (articulo && (articulo.sku || articulo.descripcion)) {
                var partes = [];
                if (articulo.sku) {
                    partes.push(String(articulo.sku));
                }
                if (articulo.descripcion) {
                    partes.push(String(articulo.descripcion));
                }
                $sub.text(partes.join(' \u2014 ') + '. F\u00f3rmulas que lo incluyen como componente o subf\u00f3rmula.');
            }
            var html = '';
            if (!rows.length) {
                html = '<tr><td colspan="8" class="text-muted">Este art\u00edculo no figura en ninguna f\u00f3rmula.</td></tr>';
            } else {
                rows.forEach(function (r) {
                    var formulaId = parseInt(r.formula_id, 10) || 0;
                    var numero = r.numero != null && String(r.numero) !== '' ? String(r.numero) : String(formulaId);
                    var sku = r.articulo_sku != null ? String(r.articulo_sku) : '';
                    var desc = r.articulo_descripcion != null ? String(r.articulo_descripcion) : '';
                    var detalle = r.detalle != null ? String(r.detalle) : '';
                    var estado = r.estado != null ? String(r.estado) : '';
                    var via = r.via != null ? String(r.via) : '';
                    var opcional = r.esopcional ? 'S\u00ed' : 'No';
                    var padreId = parseInt(r.articulo_padre_id, 10) || 0;
                    var skuHtml = escHtml(sku);
                    if (padreId > 0 && sku !== '') {
                        skuHtml = '<a href="' + urlEditarArticulo(padreId) + '" target="_blank" rel="noopener noreferrer" class="text-primary">' + skuHtml + '</a>';
                    }
                    html += '<tr>' +
                        '<td><button type="button" class="btn btn-link btn-sm p-0 text-primary js-ver-formula-uso" data-formula-id="' + formulaId + '">' + escHtml(numero) + '</button></td>' +
                        '<td>' + skuHtml + '</td>' +
                        '<td>' + escHtml(desc) + '</td>' +
                        '<td>' + escHtml(detalle) + '</td>' +
                        '<td class="text-right">' + escHtml(formatoCantidad(r.cantidad)) + '</td>' +
                        '<td>' + escHtml(via) + '</td>' +
                        '<td>' + opcional + '</td>' +
                        '<td>' + escHtml(estado) + '</td>' +
                        '</tr>';
                });
            }
            $tb.html(html);
            $('#modalFormulasUsoArticulo').modal('show');
        }).fail(function (xhr) {
            var msg = 'Error al cargar el listado.';
            if (xhr && xhr.status === 403) {
                msg = 'No tiene permisos para consultar f\u00f3rmulas.';
            }
            $tb.html('<tr><td colspan="8" class="text-danger">' + msg + '</td></tr>');
            $('#modalFormulasUsoArticulo').modal('show');
        });
    });

    $(document).on('click', '.js-ver-formula-uso', function (e) {
        e.preventDefault();
        abrirVistaFormula($(this).data('formula-id'));
    });
})();
