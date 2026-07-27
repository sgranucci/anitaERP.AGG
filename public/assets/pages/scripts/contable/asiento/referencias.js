(function (window, $) {
    'use strict';

    var TIPO_NINGUNA = 'ninguna';
    var TIPO_OC = 'ordencompra';
    var TIPO_CP = 'comprobante_proveedor';
    var TIPO_VENTA = 'venta';
    var TIPO_OC_CP = 'ordencompra_y_comprobante';

    var TIPO_META = {};
    TIPO_META[TIPO_NINGUNA] = { label: 'Sin referencia', icon: 'fa-unlink' };
    TIPO_META[TIPO_OC] = { label: 'Orden de compra', icon: 'fa-shopping-cart' };
    TIPO_META[TIPO_CP] = { label: 'Factura proveedor', icon: 'fa-file-invoice' };
    TIPO_META[TIPO_VENTA] = { label: 'Factura venta', icon: 'fa-cash-register' };
    TIPO_META[TIPO_OC_CP] = { label: 'OC + factura compra', icon: 'fa-link' };

    function carpeta() {
        return typeof carpetaBase !== 'undefined' ? carpetaBase : '';
    }

    function tokenCsrf() {
        return $('#csrf_token').val() || $('input[name="_token"]').val() || '';
    }

    function empresaIdForm() {
        return parseInt($('#empresa_id').val(), 10) || 0;
    }

    function tipoActual() {
        return $('#referencia_tipo').val() || TIPO_NINGUNA;
    }

    function estaExpandido() {
        return !$('#asiento-referencias').hasClass('is-collapsed');
    }

    function expandirEditor() {
        var $card = $('#asiento-referencias');
        $card.removeClass('is-collapsed').addClass('is-expanded').attr('data-collapsed', '0');
        $('#asiento-ref-editor').prop('hidden', false);
        $('#asiento-ref-toggle').attr('aria-expanded', 'true');
        $('#asiento-ref-toggle-icon').removeClass('fa-pen fa-plus').addClass('fa-chevron-up');
        $('#asiento-ref-toggle-label').text('Cerrar');
        refrescarResumenCompacto();
    }

    function colapsarEditor() {
        var $card = $('#asiento-referencias');
        $card.removeClass('is-expanded').addClass('is-collapsed').attr('data-collapsed', '1');
        $('#asiento-ref-editor').prop('hidden', true);
        $('#asiento-ref-toggle').attr('aria-expanded', 'false');
        refrescarResumenCompacto();
    }

    function toggleEditor() {
        if (estaExpandido()) {
            colapsarEditor();
        } else {
            expandirEditor();
        }
    }

    function tieneValores() {
        return !!(
            parseInt($('#ordencompra_id').val(), 10) ||
            parseInt($('#comprobante_proveedor_id').val(), 10) ||
            parseInt($('#venta_id').val(), 10)
        );
    }

    function refrescarResumenCompacto() {
        var tipo = tipoActual();
        var meta = TIPO_META[tipo] || TIPO_META[TIPO_NINGUNA];
        var ocId = parseInt($('#ordencompra_id').val(), 10) || 0;
        var cpId = parseInt($('#comprobante_proveedor_id').val(), 10) || 0;
        var ventaId = parseInt($('#venta_id').val(), 10) || 0;
        var ocTxt = $.trim($('#ordencompra_descripcion').val() || '');
        var cpTxt = $.trim($('#comprobante_proveedor_descripcion').val() || '');
        var ventaTxt = $.trim($('#venta_descripcion').val() || '');

        $('#asiento-ref-tipo-label').text(meta.label);
        $('#asiento-ref-tipo-icon').attr('class', 'fa ' + meta.icon + ' mr-1');
        $('#asiento-ref-tipo-pill').toggleClass('is-muted', tipo === TIPO_NINGUNA && !tieneValores());

        $('#asiento-ref-pill-oc').text(ocTxt).toggleClass('d-none', !(ocId > 0 && ocTxt));
        $('#asiento-ref-pill-cp').text(cpTxt).toggleClass('d-none', !(cpId > 0 && cpTxt));
        $('#asiento-ref-pill-venta').text(ventaTxt).toggleClass('d-none', !(ventaId > 0 && ventaTxt));

        var hayPills = ocId > 0 || cpId > 0 || ventaId > 0;
        $('#asiento-ref-compact-hint').toggleClass('d-none', hayPills);

        if (!estaExpandido()) {
            var label = hayPills || tipo !== TIPO_NINGUNA ? 'Cambiar' : 'Agregar';
            var icon = hayPills || tipo !== TIPO_NINGUNA ? 'fa-pen' : 'fa-plus';
            $('#asiento-ref-toggle-label').text(label);
            $('#asiento-ref-toggle-icon').attr('class', 'fa ' + icon + ' mr-1');
        }

        $('#asiento-ref-clear-oc').toggleClass('d-none', !(ocId > 0));
        $('#asiento-ref-clear-cp').toggleClass('d-none', !(cpId > 0));
        $('#asiento-ref-clear-venta').toggleClass('d-none', !(ventaId > 0));
    }

    function setTipo(tipo) {
        $('#referencia_tipo').val(tipo);
        $('.asiento-ref-chip').removeClass('is-active');
        $('.asiento-ref-chip[data-referencia-tipo="' + tipo + '"]').addClass('is-active');

        var showOc = tipo === TIPO_OC || tipo === TIPO_OC_CP;
        var showCp = tipo === TIPO_CP || tipo === TIPO_OC_CP;
        var showVenta = tipo === TIPO_VENTA;

        $('#asiento-ref-empty').toggleClass('d-none', tipo !== TIPO_NINGUNA);
        $('#asiento-ref-panel-oc').toggleClass('d-none', !showOc);
        $('#asiento-ref-panel-cp').toggleClass('d-none', !showCp);
        $('#asiento-ref-panel-venta').toggleClass('d-none', !showVenta);

        if (!showOc) {
            limpiarOc(true);
        }
        if (!showCp) {
            limpiarCp(true);
        }
        if (!showVenta) {
            limpiarVenta(true);
        }

        refrescarResumenCompacto();
    }

    function actualizarLink($link, rutaBase, id) {
        if (!$link.length) {
            return;
        }
        id = parseInt(id, 10) || 0;
        if (id > 0) {
            $link.attr('href', carpeta() + rutaBase + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function aplicarOc(item) {
        if (!item) {
            return;
        }
        $('#ordencompra_id').val(item.id || '');
        $('#ordencompra_codigo').val(item.codigo || item.numeroordencompra || '');
        $('#ordencompra_descripcion').val(item.descripcion || '');
        actualizarLink($('.btn-link-editar-asiento-oc'), '/compras/ordencompra/', item.id);
        refrescarResumenCompacto();
    }

    function aplicarCp(item) {
        if (!item) {
            return;
        }
        $('#comprobante_proveedor_id').val(item.id || '');
        $('#comprobante_proveedor_codigo').val(item.codigo || '');
        $('#comprobante_proveedor_descripcion').val(item.descripcion || '');
        actualizarLink($('.btn-link-editar-asiento-cp'), '/compras/comprobante-proveedor/', item.id);

        if (tipoActual() === TIPO_OC_CP && item.ordencompra_id > 0) {
            aplicarOc({
                id: item.ordencompra_id,
                codigo: item.numeroordencompra || '',
                descripcion: item.numeroordencompra
                    ? ('OC ' + item.numeroordencompra + ' · (desde factura)')
                    : item.descripcion,
                numeroordencompra: item.numeroordencompra
            });
            return;
        }
        refrescarResumenCompacto();
    }

    function aplicarVenta(item) {
        if (!item) {
            return;
        }
        $('#venta_id').val(item.id || '');
        $('#venta_codigo').val(item.codigo || '');
        $('#venta_descripcion').val(item.descripcion || '');
        actualizarLink($('.btn-link-editar-asiento-venta'), '/ventas/factura/', item.id);
        refrescarResumenCompacto();
    }

    function limpiarOc(silent) {
        $('#ordencompra_id').val('');
        $('#ordencompra_codigo').val('');
        $('#ordencompra_descripcion').val('');
        actualizarLink($('.btn-link-editar-asiento-oc'), '/compras/ordencompra/', 0);
        if (!silent) {
            refrescarResumenCompacto();
        }
    }

    function limpiarCp(silent) {
        $('#comprobante_proveedor_id').val('');
        $('#comprobante_proveedor_codigo').val('');
        $('#comprobante_proveedor_descripcion').val('');
        actualizarLink($('.btn-link-editar-asiento-cp'), '/compras/comprobante-proveedor/', 0);
        if (!silent) {
            refrescarResumenCompacto();
        }
    }

    function limpiarVenta(silent) {
        $('#venta_id').val('');
        $('#venta_codigo').val('');
        $('#venta_descripcion').val('');
        actualizarLink($('.btn-link-editar-asiento-venta'), '/ventas/factura/', 0);
        if (!silent) {
            refrescarResumenCompacto();
        }
    }

    function esF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function buscarModal(url, consulta, $tbody) {
        $.ajax({
            url: carpeta() + url,
            method: 'POST',
            data: {
                _token: tokenCsrf(),
                empresa_id: empresaIdForm(),
                consulta: consulta || ''
            },
            success: function (resp) {
                $tbody.html((resp && resp.data) ? resp.data : '<tr><td colspan="5">Sin resultados</td></tr>');
            },
            error: function () {
                $tbody.html('<tr><td colspan="5">Error al consultar</td></tr>');
            }
        });
    }

    function resolver(url, valor, onOk) {
        if (!valor) {
            return;
        }
        $.ajax({
            url: carpeta() + url,
            method: 'GET',
            data: {
                empresa_id: empresaIdForm(),
                valor: valor
            },
            success: function (resp) {
                if (resp && resp.ok && resp.item) {
                    onOk(resp.item);
                } else {
                    alert((resp && resp.mensaje) || 'No encontrado');
                }
            },
            error: function () {
                alert('Error al resolver referencia');
            }
        });
    }

    function abrirModalOc() {
        $('#consulta-asiento-oc').val('');
        buscarModal('/contable/asiento/consulta-ordencompra', '', $('#datos-asiento-oc'));
        $('#consultaAsientoOcModal').modal('show');
        setTimeout(function () { $('#consulta-asiento-oc').focus(); }, 300);
    }

    function abrirModalCp() {
        $('#consulta-asiento-cp').val('');
        buscarModal('/contable/asiento/consulta-comprobante-proveedor', '', $('#datos-asiento-cp'));
        $('#consultaAsientoCpModal').modal('show');
        setTimeout(function () { $('#consulta-asiento-cp').focus(); }, 300);
    }

    function abrirModalVenta() {
        $('#consulta-asiento-venta').val('');
        buscarModal('/contable/asiento/consulta-venta', '', $('#datos-asiento-venta'));
        $('#consultaAsientoVentaModal').modal('show');
        setTimeout(function () { $('#consulta-asiento-venta').focus(); }, 300);
    }

    function wire() {
        $(document).on('click', '#asiento-ref-toggle', function (e) {
            e.preventDefault();
            toggleEditor();
        });
        $(document).on('click', '#asiento-ref-done', function (e) {
            e.preventDefault();
            colapsarEditor();
        });

        $(document).on('click', '.asiento-ref-chip', function () {
            setTipo($(this).data('referencia-tipo'));
        });

        $(document).on('click', '.asiento-ref-clear', function () {
            var clear = $(this).data('clear');
            if (clear === 'ordencompra') {
                limpiarOc();
            } else if (clear === 'comprobante_proveedor') {
                limpiarCp();
            } else if (clear === 'venta') {
                limpiarVenta();
            }
        });

        $(document).on('click', '.consulta-asiento-oc', function (e) {
            e.preventDefault();
            abrirModalOc();
        });
        $(document).on('click', '.consulta-asiento-cp', function (e) {
            e.preventDefault();
            abrirModalCp();
        });
        $(document).on('click', '.consulta-asiento-venta', function (e) {
            e.preventDefault();
            abrirModalVenta();
        });

        $(document).on('input', '.codigo-asiento-oc', function () {
            $('#ordencompra_id').val('');
        });
        $(document).on('input', '.codigo-asiento-cp', function () {
            $('#comprobante_proveedor_id').val('');
        });
        $(document).on('input', '.codigo-asiento-venta', function () {
            $('#venta_id').val('');
        });

        $(document).on('keydown', '.codigo-asiento-oc', function (e) {
            if (esF1(e)) {
                e.preventDefault();
                abrirModalOc();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                resolver('/contable/asiento/resolver-ordencompra', $(this).val(), aplicarOc);
            }
        });
        $(document).on('blur', '.codigo-asiento-oc', function () {
            var v = $.trim($(this).val() || '');
            if (!v) {
                limpiarOc();
                return;
            }
            if (!$('#ordencompra_id').val()) {
                resolver('/contable/asiento/resolver-ordencompra', v, aplicarOc);
            }
        });

        $(document).on('keydown', '.codigo-asiento-cp', function (e) {
            if (esF1(e)) {
                e.preventDefault();
                abrirModalCp();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                resolver('/contable/asiento/resolver-comprobante-proveedor', $(this).val(), aplicarCp);
            }
        });
        $(document).on('blur', '.codigo-asiento-cp', function () {
            var v = $.trim($(this).val() || '');
            if (!v) {
                limpiarCp();
                return;
            }
            if (!$('#comprobante_proveedor_id').val()) {
                resolver('/contable/asiento/resolver-comprobante-proveedor', v, aplicarCp);
            }
        });

        $(document).on('keydown', '.codigo-asiento-venta', function (e) {
            if (esF1(e)) {
                e.preventDefault();
                abrirModalVenta();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                resolver('/contable/asiento/resolver-venta', $(this).val(), aplicarVenta);
            }
        });
        $(document).on('blur', '.codigo-asiento-venta', function () {
            var v = $.trim($(this).val() || '');
            if (!v) {
                limpiarVenta();
                return;
            }
            if (!$('#venta_id').val()) {
                resolver('/contable/asiento/resolver-venta', v, aplicarVenta);
            }
        });

        var timerOc = null;
        $(document).on('keyup', '#consulta-asiento-oc', function () {
            clearTimeout(timerOc);
            var v = $(this).val();
            timerOc = setTimeout(function () {
                buscarModal('/contable/asiento/consulta-ordencompra', v, $('#datos-asiento-oc'));
            }, 280);
        });
        var timerCp = null;
        $(document).on('keyup', '#consulta-asiento-cp', function () {
            clearTimeout(timerCp);
            var v = $(this).val();
            timerCp = setTimeout(function () {
                buscarModal('/contable/asiento/consulta-comprobante-proveedor', v, $('#datos-asiento-cp'));
            }, 280);
        });
        var timerVenta = null;
        $(document).on('keyup', '#consulta-asiento-venta', function () {
            clearTimeout(timerVenta);
            var v = $(this).val();
            timerVenta = setTimeout(function () {
                buscarModal('/contable/asiento/consulta-venta', v, $('#datos-asiento-venta'));
            }, 280);
        });

        $(document).on('click', '.eligeconsulta-asiento-oc', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            aplicarOc({
                id: parseInt($tr.find('.ordencompra_id').text(), 10) || 0,
                codigo: $.trim($tr.find('.numeroordencompra').text()),
                descripcion: $.trim($tr.find('.descripcion_oc').text()) || ('OC ' + $.trim($tr.find('.numeroordencompra').text())),
                numeroordencompra: $.trim($tr.find('.numeroordencompra').text())
            });
            $('#consultaAsientoOcModal').modal('hide');
        });

        $(document).on('click', '.eligeconsulta-asiento-cp', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            aplicarCp({
                id: parseInt($tr.find('.comprobante_proveedor_id').text(), 10) || 0,
                codigo: $.trim($tr.find('.comprobante_codigo').text()),
                descripcion: $.trim($tr.find('.descripcion_cp').text()) || $.trim($tr.find('.comprobante_codigo').text()),
                ordencompra_id: parseInt($tr.find('.ordencompra_id').text(), 10) || 0,
                numeroordencompra: $.trim($tr.find('.numeroordencompra').text())
            });
            $('#consultaAsientoCpModal').modal('hide');
        });

        $(document).on('click', '.eligeconsulta-asiento-venta', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            aplicarVenta({
                id: parseInt($tr.find('.venta_id').text(), 10) || 0,
                codigo: $.trim($tr.find('.venta_codigo').text()),
                descripcion: $.trim($tr.find('.descripcion_venta').text()) || $.trim($tr.find('.venta_codigo').text())
            });
            $('#consultaAsientoVentaModal').modal('hide');
        });

        $(document).on('keydown', '.codigo-asiento-oc, .codigo-asiento-cp, .codigo-asiento-venta', function (e) {
            if (esF1(e)) {
                e.preventDefault();
            }
        });
    }

    $(function () {
        if (!$('#asiento-referencias').length) {
            return;
        }
        wire();
        setTipo(tipoActual());
        colapsarEditor();
    });
})(window, jQuery);
