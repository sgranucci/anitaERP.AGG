
    // Catálogo completo (del template) y conceptos permitidos por el tipo elegido.
    // null = sin filtro (tipo sin conceptos cargados o prorrateado FPB/…: unión de la OC).
    var catalogoConceptos = null;
    var conceptosPermitidos = null;
    var filtroConceptosSeq = 0;

    $(function () {
        $('#agrega_renglon_concepto').on('click', agregaRenglonConcepto);
        $(document).on('click', '.eliminar_concepto', borraRenglonConcepto);

        $('#empresa_id').on('change', actualizarCodigoEmpresa);
        $('#tipotransaccion_compra_id').on('change', function () {
            actualizarTipoComprobante();
            filtrarConceptosPorTipo(true);
        });
        $('#moneda_id').on('change', sincronizarCotizacionPorMoneda);
        $('#fechafactura').on('change', function () {
            if (parseInt($('#moneda_id').val() || '1', 10) > 1) {
                refrescarCotizacionDia(false);
            }
        });

        actualizarCodigoEmpresa();
        actualizarTipoComprobante();
        filtrarConceptosPorTipo(false);
        sincronizarCotizacionPorMoneda(true);
    });
    function leerCatalogoConceptos() {
        if (catalogoConceptos !== null) {
            return catalogoConceptos;
        }
        catalogoConceptos = [];
        var html = $('#template-renglon-concepto').html() || '';
        $('<tbody>' + html + '</tbody>').find('select.concepto_ivacompra_id option').each(function () {
            var v = String($(this).attr('value') || '');
            if (v !== '') {
                catalogoConceptos.push({
                    id: v,
                    nombre: $(this).text(),
                    codigo: String($(this).attr('data-codigo-anita') || '')
                });
            }
        });
        return catalogoConceptos;
    }

    function armarOpcionesConcepto($select, valor, abrev) {
        var catalogo = leerCatalogoConceptos();
        valor = String(valor || '');
        $select.empty().append('<option value="">-- Elija concepto de iva compra --</option>');
        var estaValor = false;
        catalogo.forEach(function (c) {
            var permitido = conceptosPermitidos === null || conceptosPermitidos[c.id];
            if (!permitido && c.id !== valor) {
                return;
            }
            var texto = permitido ? c.nombre : c.nombre + ' (no es de ' + abrev + ')';
            var $opt = $('<option>').val(c.id).attr('data-codigo-anita', c.codigo).text(texto);
            if (c.id === valor) {
                $opt.prop('selected', true);
                estaValor = true;
            }
            $select.append($opt);
        });
        if (!estaValor) {
            $select.val('');
        }
    }

    function filtrarConceptosPorTipo(reubicar) {
        var tipoId = parseInt(String($('#tipotransaccion_compra_id').val() || '0'), 10) || 0;
        var abrev = String($('#tipotransaccion_compra_id').find('option:selected').data('abreviatura') || '');
        var seq = ++filtroConceptosSeq;
        if (tipoId <= 0) {
            conceptosPermitidos = null;
            aplicarFiltroConceptos({}, abrev);
            return;
        }
        var ids = [];
        $('#tbody-concepto-table select.concepto_ivacompra_id').each(function () {
            var v = parseInt(String($(this).val() || '0'), 10) || 0;
            if (v > 0) {
                ids.push(v);
            }
        });
        var params = {};
        var numeroOc = String($('#numeroordencompra').val() || '').trim();
        if (numeroOc) {
            params.numero_oc = numeroOc;
        }
        if (reubicar && ids.length) {
            params['desde_ids[]'] = ids;
        }
        var base = (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';
        $.getJSON(base + '/compras/tipotransaccion_compra/' + tipoId + '/conceptos-iva', params)
            .done(function (res) {
                if (seq !== filtroConceptosSeq) {
                    return;
                }
                var lista = (res && res.conceptos) || [];
                if ((res && res.prorrateado) || !lista.length) {
                    conceptosPermitidos = null;
                } else {
                    conceptosPermitidos = {};
                    lista.forEach(function (c) {
                        conceptosPermitidos[String(c.id)] = true;
                    });
                }
                aplicarFiltroConceptos(reubicar ? ((res && res.equivalencias) || {}) : {}, abrev);
            });
    }

    function aplicarFiltroConceptos(equivalencias, abrev) {
        $('#tbody-concepto-table select.concepto_ivacompra_id').each(function () {
            var actual = String($(this).val() || '');
            var nuevo = equivalencias[actual] ? String(equivalencias[actual]) : actual;
            armarOpcionesConcepto($(this), nuevo, abrev);
        });
    }


    $(function () {
        if (typeof activa_eventos_consultaproveedor === 'function') {
            activa_eventos_consultaproveedor();
        }
    });

    function actualizarCodigoEmpresa() {
        var codigo = $('#empresa_id').find('option:selected').data('codigo') || '';
        $('#codigoempresa').val(codigo);
    }

    function actualizarTipoComprobante() {
        var abreviatura = $('#tipotransaccion_compra_id').find('option:selected').data('abreviatura') || '';
        $('#tipo').val(abreviatura);
    }

    function sincronizarCotizacionPorMoneda(conservarValor) {
        var $cot = $('#cotizacion');
        if (!$cot.length || $cot.prop('readonly')) {
            return;
        }
        if (conservarValor === true) {
            var actual = parseFloat($cot.val() || '0');
            if (actual > 1.0001) {
                return;
            }
        }
        refrescarCotizacionDia(true);
    }

    function refrescarCotizacionDia(forzar) {
        var mid = parseInt($('#moneda_id').val() || '1', 10) || 1;
        var fecha = (($('#fechafactura').val() || '') + '').substring(0, 10);
        if (!fecha) {
            return;
        }
        var base = (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';
        $.getJSON(base + '/compras/comprobante-proveedor/api/cotizacion-moneda-fecha', {
            fecha: fecha,
            moneda_id: mid
        }).done(function (res) {
            var cot = parseFloat(res && res.cotizacion != null ? res.cotizacion : 0);
            if (!(cot > 0)) {
                return;
            }
            var $cot = $('#cotizacion');
            var actual = parseFloat($cot.val() || '0');
            if (forzar || !(actual > 1.0001)) {
                $cot.val(cot.toFixed(4));
            }
        });
    }

    function agregaRenglonConcepto(){
    	event.preventDefault();
    	var renglon = $('#template-renglon-concepto').html();

    	$("#tbody-concepto-table").append(renglon);
    	if (conceptosPermitidos !== null) {
    		var abrev = String($('#tipotransaccion_compra_id').find('option:selected').data('abreviatura') || '');
    		armarOpcionesConcepto($('#tbody-concepto-table select.concepto_ivacompra_id').last(), '', abrev);
    	}
    	actualizaRenglonesConcepto();
    }

    function borraRenglonConcepto() {
    	event.preventDefault();
    	$(this).parents('tr').remove();
    	actualizaRenglonesConcepto();
    }

    function actualizaRenglonesConcepto() {
    	var item = 1;

    	$("#tbody-concepto-table .iiconcepto").each(function() {
    		$(this).val(item++);
    	});
    }

