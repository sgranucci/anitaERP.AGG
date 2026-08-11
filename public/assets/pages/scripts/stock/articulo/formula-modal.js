$(function () {
    if (typeof jQuery === 'undefined') {
        return;
    }

    if (typeof window.carpetaBase === 'undefined') {
        var __locCb = window.location.pathname || '';
        var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
        window.carpetaBase = __mCb ? __mCb[1] : '';
    }

    var cfg = window.consultaFormulaArticuloConfig || {};
    var urlResolverBase =
        cfg.urlResolverBase || carpetaBase + '/stock/formula-articulo/resolver-por-articulo';
    var urlFormulaBase = cfg.urlFormulaBase || carpetaBase + '/stock/formula-articulo';
    var puedeEditarCrud =
        cfg.puedeEditar === true ||
        (cfg.puedeEditar === undefined &&
            window.consultaFormulaArticuloPuedeEditar !== false);

    var formulaResueltaId = null;
    var formulaResueltaMensaje = null;

    function obtenerArticuloId() {
        var id = parseInt($('#articulo_id').val(), 10) || 0;
        if (!id) {
            id = parseInt($('input[name="articulo_id"]').val(), 10) || 0;
        }
        return id;
    }

    function actualizarTooltipFormula(texto) {
        var $wrap = $('#tooltip-consulta-formula-articulo');
        if (!$wrap.length) {
            return;
        }
        var titulo = texto || 'Consultar fórmula vinculada a este artículo';
        $wrap.attr('title', titulo).attr('data-original-title', titulo);
        if (typeof $wrap.tooltip === 'function') {
            try {
                $wrap.tooltip('dispose');
            } catch (e) {
                /* sin instancia previa */
            }
            $wrap.tooltip({ container: 'body', trigger: 'hover' });
        }
    }

    function refrescarBotonConsultaFormula(formulaId) {
        var $btn = $('#btn-consulta-formula-articulo');
        if (!$btn.length) {
            return;
        }

        var fid =
            formulaId !== undefined && formulaId !== null
                ? parseInt(formulaId, 10)
                : formulaResueltaId;

        if (isNaN(fid) || fid <= 0) {
            fid = null;
            formulaResueltaId = null;
        } else {
            formulaResueltaId = fid;
        }

        var titulo;
        if (fid > 0) {
            titulo = puedeEditarCrud
                ? 'Abrir fórmula ERP (id ' + fid + ') en el CRUD'
                : 'Ver fórmula ERP (id ' + fid + ')';
        } else if (formulaResueltaMensaje) {
            titulo = formulaResueltaMensaje;
        } else {
            titulo = 'Consultar fórmula (sin vínculo detectado; clic para más información)';
        }

        $btn.prop('disabled', false);
        actualizarTooltipFormula(titulo);
    }

    function resolverFormulaParaArticulo(callback) {
        var articuloId = obtenerArticuloId();
        if (!articuloId) {
            formulaResueltaId = null;
            formulaResueltaMensaje = 'Guarde el artículo antes de consultar la fórmula.';
            refrescarBotonConsultaFormula(null);
            if (typeof callback === 'function') {
                callback(null, formulaResueltaMensaje);
            }
            return;
        }

        var urlResolver = urlResolverBase.replace(/\/$/, '') + '/' + articuloId;

        $.getJSON(urlResolver)
            .done(function (data) {
                formulaResueltaId =
                    data.formula_id && parseInt(data.formula_id, 10) > 0
                        ? parseInt(data.formula_id, 10)
                        : null;
                formulaResueltaMensaje = data.mensaje || null;
                refrescarBotonConsultaFormula(formulaResueltaId);
                if (typeof callback === 'function') {
                    callback(formulaResueltaId, formulaResueltaMensaje);
                }
            })
            .fail(function (xhr) {
                formulaResueltaId = null;
                formulaResueltaMensaje =
                    (xhr.responseJSON && xhr.responseJSON.message) ||
                    'No se pudo resolver la fórmula del artículo.';
                refrescarBotonConsultaFormula(null);
                if (typeof callback === 'function') {
                    callback(null, formulaResueltaMensaje);
                }
            });
    }

    function urlEditarFormula(fid, desdeModalConsulta) {
        var url = urlFormulaBase.replace(/\/$/, '') + '/' + parseInt(fid, 10) + '/editar';
        if (desdeModalConsulta) {
            return url + '?origen=modal_consulta';
        }
        var articuloId = obtenerArticuloId();
        if (articuloId > 0) {
            url += '?retorno_articulo_id=' + articuloId + '&retorno_origen=editar';
        }
        return url;
    }

    function abrirModalFormula(fid) {
        if (!$('#modalVerFormulaArticulo').length) {
            alert('No está disponible la vista modal de fórmula en esta pantalla.');
            return;
        }
        var url = urlFormulaBase.replace(/\/$/, '') + '/' + fid + '/modal';
        $('#modalVerFormulaArticuloBody').html('<p class="text-muted">Cargando...</p>');
        $('#modalVerFormulaArticuloIrCrud')
            .attr('href', urlEditarFormula(fid, true))
            .removeClass('d-none');
        $('#modalVerFormulaArticulo').modal('show');
        $.get(url, function (html) {
            $('#modalVerFormulaArticuloBody').html(html);
        }).fail(function () {
            $('#modalVerFormulaArticuloBody').html(
                '<p class="text-danger">No se pudo cargar la fórmula.</p>'
            );
        });
    }

    function abrirCrudFormula(fid) {
        window.location.href = urlEditarFormula(fid);
    }

    function ejecutarConsultaFormula(fid, mensaje) {
        if (!fid || fid <= 0) {
            alert(
                mensaje ||
                    'No hay fórmula vinculada a este artículo. Verifique el CRUD de fórmulas o ejecute "Vincular con artículos".'
            );
            return;
        }
        if (puedeEditarCrud) {
            abrirCrudFormula(fid);
        } else {
            abrirModalFormula(fid);
        }
    }

    if ($('#btn-consulta-formula-articulo').length) {
        resolverFormulaParaArticulo();

        $(document).on('click', '#btn-consulta-formula-articulo', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (formulaResueltaId > 0) {
                ejecutarConsultaFormula(formulaResueltaId, formulaResueltaMensaje);
                return;
            }

            resolverFormulaParaArticulo(ejecutarConsultaFormula);
        });
    }
});
