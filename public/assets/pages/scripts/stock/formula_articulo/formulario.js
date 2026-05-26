/**
 * Fórmulas de artículo: cabecera (consulta artículo), líneas (consulta artículo estándar),
 * subfórmula (modal propio), agregar/quitar filas.
 */
var ptrFormulaHijaRow = null;

function urlFormulaArticuloBase() {
    var cfg = window.formulaArticuloSubformulaConsulta || {};
    if (cfg.urlFormulaBase) {
        return String(cfg.urlFormulaBase).replace(/\/$/, '');
    }
    if (typeof carpetaBase !== 'undefined' && carpetaBase) {
        return String(carpetaBase).replace(/\/$/, '') + '/stock/formula-articulo';
    }
    return '/stock/formula-articulo';
}

function urlCostosUltimaCompraFormula() {
    var cfg = window.formulaArticuloSubformulaConsulta || {};
    if (cfg.urlCostosUltimaCompra) {
        return String(cfg.urlCostosUltimaCompra);
    }
    if (typeof carpetaBase !== 'undefined' && carpetaBase) {
        return String(carpetaBase).replace(/\/$/, '') + '/stock/formula-articulo/costos-ultima-compra';
    }
    return '/stock/formula-articulo/costos-ultima-compra';
}

function formateaCostoUltimaCompra(valor) {
    if (valor === null || valor === undefined || valor === '') {
        return '';
    }
    var n = parseFloat(valor);
    if (isNaN(n)) {
        return '';
    }
    return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function actualizaCostoUltimaCompraFila($row) {
    var $inp = $row.find('.js-costo-ultima-compra');
    if (!$inp.length) {
        return;
    }
    var sku = ($row.find('input.codigoarticulo').val() || '').trim();
    if (sku === '') {
        $inp.val('');
        return;
    }
    $.get(urlCostosUltimaCompraFormula(), { skus: [sku] }, function (resp) {
        var costos = (resp && resp.costos) ? resp.costos : {};
        var c = costos[sku];
        if (c === undefined || c === null) {
            $inp.val('');
        } else {
            $inp.val(formateaCostoUltimaCompra(c));
        }
    }).fail(function () {
        $inp.val('');
    });
}

function urlEditarArticulo(id) {
    var cfg = window.formulaArticuloSubformulaConsulta || {};
    var base = cfg.urlArticuloBase;
    if (!base && typeof carpetaBase !== 'undefined' && carpetaBase) {
        base = String(carpetaBase).replace(/\/$/, '') + '/stock/articulo';
    }
    if (!base) {
        base = '/stock/articulo';
    }
    return String(base).replace(/\/$/, '') + '/' + parseInt(id, 10) + '/editar?origen=modal_consulta';
}

function actualizaLinkSkuArticuloLinea($row) {
    var $link = $row.find('.js-sku-link-articulo-linea');
    if (!$link.length) {
        return;
    }
    var id = parseInt($row.find('.articulo_id').val(), 10) || 0;
    var sku = ($row.find('input.codigoarticulo').val() || '').trim();
    if (id > 0 && sku !== '') {
        $link
            .attr('href', urlEditarArticulo(id))
            .attr('target', '_blank')
            .attr('rel', 'noopener')
            .removeAttr('aria-disabled tabindex')
            .text(sku)
            .removeClass('text-muted sin-articulo')
            .addClass('text-primary');
    } else {
        $link
            .attr('href', '#')
            .removeAttr('target rel')
            .attr('aria-disabled', 'true')
            .attr('tabindex', '-1')
            .text(sku !== '' ? sku : '—')
            .removeClass('text-primary')
            .addClass('text-muted sin-articulo');
    }
}

function leeFormulaHijaIdFila($row) {
    var fid = parseInt($row.find('.fh_formula_hija_id').val(), 10) || 0;
    if (fid <= 0) {
        fid = parseInt($row.find('.js-ver-subformula-linea').data('formula-id'), 10) || 0;
    }
    return fid;
}

function actualizaBotonVerSubformula($row) {
    var fid = leeFormulaHijaIdFila($row);
    var $btn = $row.find('.js-ver-subformula-linea');
    $btn.data('formula-id', fid > 0 ? fid : '');
    $btn.toggleClass('sin-subformula', fid <= 0);
}

function abrirModalSubformula(fid) {
    if (!$('#modalVerFormulaArticulo').length) {
        alert('No est\u00e1 disponible la vista modal de f\u00f3rmula en esta pantalla.');
        return;
    }
    var id = parseInt(fid, 10) || 0;
    if (id <= 0) {
        alert('Seleccione una subf\u00f3rmula primero (bot\u00f3n con icono de matraz).');
        return;
    }
    var base = urlFormulaArticuloBase();
    var urlModal = base + '/' + id + '/modal';
    var urlEditar = base + '/' + id + '/editar?origen=modal_consulta';
    $('#modalVerFormulaArticuloBody').html('<p class="text-muted">Cargando...</p>');
    $('#modalVerFormulaArticuloIrCrud').attr('href', urlEditar).removeClass('d-none');
    $('#modalVerFormulaArticulo').modal('show');
    $.ajax({
        url: urlModal,
        method: 'GET',
        dataType: 'html',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .done(function (html) {
            $('#modalVerFormulaArticuloBody').html(html);
        })
        .fail(function (xhr) {
            var msg = 'No se pudo cargar la subf\u00f3rmula.';
            if (xhr.status === 403) {
                msg = 'No tiene permisos para consultar esta f\u00f3rmula.';
            } else if (xhr.status === 404) {
                msg = 'La subf\u00f3rmula seleccionada no existe o fue eliminada.';
            }
            $('#modalVerFormulaArticuloBody').html('<p class="text-danger">' + msg + '</p>');
        });
}

function actualizaLabelCabeceraFormula() {
    var sku = $('#formula_cabecera_sku').val() || '';
    var d = $('#formula_cabecera_desc').val() || '';
    var id = $('#formula_cabecera_articulo_id').val();
    $('#formula_cabecera_sku_show').val(id ? sku : '');
    $('#formula_cabecera_desc_show').val(id ? d : '');
}

function leeHistoriaFormulaArticulo() {
    var id = $('#formula_articulo_id_edit').val();
    if (!id) {
        return;
    }
    var url = carpetaBase + '/stock/leer_historia_formula_articulo/' + id;
    $.get(url, function (historia) {
        var $w = $('.container-historia-formula').empty();
        if (!historia || !historia.length) {
            $w.append('<tr><td colspan="4" class="text-muted">Sin registros.</td></tr>');
            return;
        }
        $.each(historia, function (_, value) {
            var rawFecha = value.fecha != null ? String(value.fecha) : '';
            var fechaTxt = rawFecha.replace('T', ' ');
            if (fechaTxt.length >= 19) {
                fechaTxt = fechaTxt.substring(0, 19);
            }
            var usr = (value.usuarios && value.usuarios.nombre) ? value.usuarios.nombre : '';
            var obs = value.observacion || '';
            var est = value.estado || '';
            $w.append(
                '<tr><td>' + $('<div>').text(fechaTxt).html() + '</td>' +
                '<td>' + $('<div>').text(est).html() + '</td>' +
                '<td>' + $('<div>').text(usr).html() + '</td>' +
                '<td>' + $('<div>').text(obs).html() + '</td></tr>'
            );
        });
    }).fail(function () {
        $('.container-historia-formula').html('<tr><td colspan="4" class="text-danger">No se pudo cargar la historia.</td></tr>');
    });
}

function mostrarCodigoComoNumeroFormula() {
    var cfg = window.formulaArticuloSubformulaConsulta || {};
    return cfg.mostrarCodigoComoNumero === true;
}

function numeroFormulaParaUI(r) {
    if (!r) {
        return '';
    }
    var codigo = (r.codigo == null ? '' : String(r.codigo)).trim();
    if (mostrarCodigoComoNumeroFormula()) {
        return codigo !== '' ? codigo : '#' + r.id;
    }
    return r.id == null ? '' : String(r.id);
}

function renderTablaBusquedaFormula(rows) {
    var mostrarCod = mostrarCodigoComoNumeroFormula();
    var colspan = mostrarCod ? 6 : 7;
    var html = '';
    if (!rows || !rows.length) {
        html = '<tr><td colspan="' + colspan + '" class="text-muted">Sin resultados</td></tr>';
        $('#datos-formula-consulta').html(html);
        return;
    }
    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }
    rows.forEach(function (r) {
        var idReal = (r.id == null ? '' : String(r.id));
        html += '<tr data-formula-id="' + esc(idReal) + '">';
        html += '<td class="fid">' + esc(numeroFormulaParaUI(r)) + '</td>';
        if (!mostrarCod) {
            html += '<td class="text-monospace">' + esc(r.codigo || '') + '</td>';
        }
        html += '<td class="fsku">' + esc(r.sku || '') + '</td>';
        html += '<td class="fdesc">' + esc(r.descripcion || '') + '</td>';
        html += '<td class="fdetalle">' + esc(r.detalle || '') + '</td>';
        html += '<td>' + esc(r.estado || '') + '</td>';
        html += '<td><button type="button" class="btn btn-sm btn-primary eligeconsultaformula">Elegir</button></td>';
        html += '</tr>';
    });
    $('#datos-formula-consulta').html(html);
}

function formulaArticuloToggleOrdenOpcional($row) {
	var $tbl = $('#tabla-formula-hijos');
	if (!$tbl.length || String($tbl.data('gastronomia-opcional')) !== '1') {
		return;
	}
	var $sel = $row.find('select.js-esopcional-formula');
	var $inp = $row.find('input.js-ordenopcional-formula');
	if (!$sel.length || !$inp.length) {
		return;
	}
	if ($sel.val() === '1') {
		$inp.prop('disabled', false);
	} else {
		$inp.prop('disabled', true).val('');
	}
}

function buscarFormulasAjax() {
    var q = ($('#consulta_formula').val() || '').trim();
    var exclude = ptrFormulaHijaRow ? ($(ptrFormulaHijaRow).find('.js-consulta-formula-linea').data('exclude') || 0) : 0;
    var url = urlFormulaArticuloBase() + '/buscar';
    var colspanErr = mostrarCodigoComoNumeroFormula() ? 6 : 7;
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        data: { consulta: q, exclude_id: exclude },
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).done(function (resp) {
        renderTablaBusquedaFormula((resp && resp.datos) || []);
    }).fail(function (xhr) {
        var msg = 'Error al buscar';
        if (xhr && xhr.status === 403) {
            msg = 'Sin permisos para buscar fórmulas';
        } else if (xhr && xhr.status === 419) {
            msg = 'Sesión expirada — recargue la página';
        } else if (xhr && xhr.status === 404) {
            msg = 'Endpoint de búsqueda no encontrado';
        }
        if (window.console && console.error) {
            console.error('formula-articulo/buscar fallo', xhr && xhr.status, xhr && xhr.responseText);
        }
        $('#datos-formula-consulta').html('<tr><td colspan="' + colspanErr + '" class="text-danger">' + msg + '</td></tr>');
    });
}

$(document).ready(function () {
    $('.formula-solapa-archivos.form4').hide();
    $('.formula-solapa-historia.form3').hide();
    $('.form1').show();

    $('#botonform1').on('click', function () {
        $('.form1').show();
        $('.formula-solapa-archivos.form4').hide();
        $('.formula-solapa-historia.form3').hide();
    });
    if ($('#botonform3').length) {
        $('#botonform3').on('click', function () {
            $('.form1').hide();
            $('.formula-solapa-archivos.form4').hide();
            $('.formula-solapa-historia.form3').show();
            leeHistoriaFormulaArticulo();
            var sol = document.getElementById('formula-solapa-historia');
            if (sol) {
                sol.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
    $('#botonform4').on('click', function () {
        $('.form1').hide();
        $('.formula-solapa-historia.form3').hide();
        $('.formula-solapa-archivos.form4').show();
        var sol = document.getElementById('formula-solapa-archivos');
        if (sol) {
            sol.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    $('#agrega_renglon_archivo_formula').on('click', function (e) {
        e.preventDefault();
        var tpl = $('#template-renglon-archivo-formula').html();
        if (!tpl) {
            return;
        }
        $('#formula-tbody-tabla-archivo').append(tpl);
    });

    $(document).on('click', '#formula-tbody-tabla-archivo .eliminararchivo-formula', function (e) {
        e.preventDefault();
        var $tb = $('#formula-tbody-tabla-archivo');
        $(this).closest('tr.item-archivo-formula').remove();
        if ($tb.find('tr.item-archivo-formula').length === 0) {
            $('#agrega_renglon_archivo_formula').trigger('click');
        }
    });

    $(document).on('click', '.eliminar-archivo-formula', function (e) {
        e.preventDefault();
        $(this).closest('.formula-archivo-item').remove();
    });

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    /* Tras elegir artículo en el modal: foco en cantidad (línea) o cantidad unidad (cabecera) */
    $(document).on('click.formulaArticuloFocusCantidad', '.eligeconsultaarticulo', function () {
        if (typeof ptrarticulo_id === 'undefined' || !ptrarticulo_id || !ptrarticulo_id.length) {
            return;
        }
        var $art = $(ptrarticulo_id);
        var $rowLinea = $art.closest('tr.fila-formula-hijo');
        $('#consultaarticuloModal').off('hidden.bs.modal.formulaFocusCant').one('hidden.bs.modal.formulaFocusCant', function () {
            if ($rowLinea.length && $rowLinea.closest('#tabla-formula-hijos').length) {
                actualizaLinkSkuArticuloLinea($rowLinea);
                actualizaCostoUltimaCompraFila($rowLinea);
                var $cant = $rowLinea.find('input[name="cantidades[]"]').first();
                if ($cant.length) {
                    setTimeout(function () {
                        $cant.trigger('focus');
                    }, 0);
                }
                return;
            }
            if ($art.is('#formula_cabecera_articulo_id')) {
                var $cu = $('#cantidadunidad');
                if ($cu.length) {
                    setTimeout(function () {
                        $cu.trigger('focus');
                    }, 0);
                }
            }
        });
    });

    actualizaLabelCabeceraFormula();

    $(document).on('click', '.js-modal-articulos-formula', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        if (!url) {
            return;
        }
        var $tb = $('#tbody-articulos-formula-modal');
        $tb.html('<tr><td colspan="3" class="text-muted">Cargando…</td></tr>');
        $.get(url, function (resp) {
            var rows = (resp && resp.datos) ? resp.datos : [];
            var html = '';
            if (!rows.length) {
                html = '<tr><td colspan="3" class="text-muted">Ning&uacute;n art&iacute;culo vincula esta f&oacute;rmula (ni una f&oacute;rmula que la incluya como subf&oacute;rmula).</td></tr>';
            } else {
                rows.forEach(function (r) {
                    var ida = r.id != null ? String(r.id) : '';
                    var sku = r.sku != null ? String(r.sku) : '';
                    var desc = r.descripcion != null ? String(r.descripcion) : '';
                    var link = urlEditarArticulo(parseInt(ida, 10) || 0);
                    html += '<tr><td>' + $('<div>').text(ida).html() + '</td>' +
                        '<td><a href="' + link + '" target="_blank" rel="noopener noreferrer">' + $('<div>').text(sku).html() + '</a></td>' +
                        '<td>' + $('<div>').text(desc).html() + '</td></tr>';
                });
            }
            $tb.html(html);
            $('#modalArticulosFormula').modal('show');
        }).fail(function () {
            $tb.html('<tr><td colspan="3" class="text-danger">Error al cargar el listado.</td></tr>');
            $('#modalArticulosFormula').modal('show');
        });
    });

    $('.fila-formula-hijo').each(function () {
        var $row = $(this);
        formulaArticuloToggleOrdenOpcional($row);
        actualizaBotonVerSubformula($row);
        actualizaLinkSkuArticuloLinea($row);
    });

    $(document).on('change', 'select.js-esopcional-formula', function () {
        formulaArticuloToggleOrdenOpcional($(this).closest('tr'));
    });

    $(document).on('click', '.js-consulta-articulo-cabecera', function (e) {
        e.preventDefault();
        ptrarticulo_id = $('#formula_cabecera_articulo_id');
        ptrcodigoarticulo = $('#formula_cabecera_sku');
        ptrnombrearticulo = $('#formula_cabecera_desc');
        ptrunidadmedida = $('<input type="hidden">');
        ptrcategoria_id = $('<input type="hidden">');
        ptrsubcategoria_id = $('<input type="hidden">');
        $('#consultaarticuloModal').modal('show');
    });

    $('#consultaarticuloModal').on('hidden.bs.modal', function () {
        actualizaLabelCabeceraFormula();
    });

    $(document).on('click', '.js-consulta-formula-linea', function (e) {
        e.preventDefault();
        ptrFormulaHijaRow = $(this).closest('tr');
        $('#consulta_formula').val('');
        var colspanIni = mostrarCodigoComoNumeroFormula() ? 6 : 7;
        $('#datos-formula-consulta').html('<tr><td colspan="' + colspanIni + '" class="text-muted">Escriba para buscar</td></tr>');
        $('#consultaformulaModal').modal('show');
        buscarFormulasAjax();
    });

    $(document).on('click', '.js-ver-subformula-linea', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $row = $(this).closest('tr.fila-formula-hijo');
        abrirModalSubformula(leeFormulaHijaIdFila($row));
    });

    $('#consultaformulaModal').on('shown.bs.modal', function () {
        $('#consulta_formula').focus();
    });

    var tmrFormula = null;
    $(document).on('input', '#consulta_formula', function () {
        clearTimeout(tmrFormula);
        tmrFormula = setTimeout(buscarFormulasAjax, 300);
    });

    $(document).on('click', '.eligeconsultaformula', function () {
        var tr = $(this).closest('tr');
        var idReal = String(tr.data('formula-id') || '').trim();
        if (idReal === '') {
            idReal = tr.find('.fid').text().trim();
        }
        var numeroVisible = tr.find('.fid').text().trim();
        var sku = tr.find('.fsku').text().trim();
        var desc = tr.find('.fdesc').text().trim();
        var detalle = tr.find('.fdetalle').text().trim();
        if (ptrFormulaHijaRow) {
            var $rowSel = $(ptrFormulaHijaRow);
            var partes = [];
            if (sku) { partes.push(sku); }
            if (desc) { partes.push(desc); }
            if (detalle) { partes.push(detalle); }
            var sufijo = partes.length ? ' - ' + partes.join(' - ') : '';
            var label = 'F ' + (numeroVisible || idReal) + sufijo;
            $rowSel.find('.fh_formula_hija_id').val(idReal);
            $rowSel.find('.fh_formula_hija_label').val(label).attr('title', label);
            $rowSel.find('.js-ver-subformula-linea').data('formula-id', idReal);
            actualizaBotonVerSubformula($rowSel);
        }
        $('#consultaformulaModal').modal('hide');
    });

    $('#js-agregar-fila-formula').on('click', function () {
        var $r = $('.fila-formula-hijo').first().clone();
        $r.find('input[type=hidden]').val('');
        $r.find('input[type=text]').not('.codigoarticulo').val('');
        $r.find('input.codigoarticulo').val('');
        $r.find('input[type=number]').not('.js-ordenopcional-formula').val('');
        $r.find('.js-costo-ultima-compra').val('');
        $r.find('.fh_formula_hija_label').removeAttr('title');
        $r.find('select[name="esopcional[]"]').prop('selectedIndex', 0);
        $r.find('select[name="deposito_ids[]"]').prop('selectedIndex', 0);
        var $oo = $r.find('input.js-ordenopcional-formula');
        if ($oo.length) {
            $oo.val('').prop('disabled', true);
        }
        $('#tabla-formula-hijos tbody').append($r);
        formulaArticuloToggleOrdenOpcional($r);
        actualizaBotonVerSubformula($r);
        actualizaLinkSkuArticuloLinea($r);
    });

    $(document).on('click', '.js-eliminar-fila-formula', function () {
        var $tb = $('#tabla-formula-hijos tbody');
        if ($tb.find('tr').length <= 1) {
            return;
        }
        $(this).closest('tr').remove();
    });
});
