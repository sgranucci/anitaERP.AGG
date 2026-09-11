
    $(function () {
        $('#agrega_renglon_concepto').on('click', agregaRenglonConcepto);
        $(document).on('click', '.eliminar_concepto', borraRenglonConcepto);

        $('#empresa_id').on('change', actualizarCodigoEmpresa);
        $('#tipotransaccion_compra_id').on('change', actualizarTipoComprobante);
        $('#moneda_id').on('change', sincronizarCotizacionPorMoneda);
        $('#fechafactura').on('change', function () {
            if (parseInt($('#moneda_id').val() || '1', 10) > 1) {
                refrescarCotizacionDia(false);
            }
        });

        actualizarCodigoEmpresa();
        actualizarTipoComprobante();
        sincronizarCotizacionPorMoneda(true);
    });


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
        var mid = parseInt($('#moneda_id').val() || '1', 10) || 1;
        var $cot = $('#cotizacion');
        if (!$cot.length || $cot.prop('readonly')) {
            return;
        }
        if (mid <= 1) {
            $cot.val('1');
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
        if (mid <= 1) {
            return;
        }
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

