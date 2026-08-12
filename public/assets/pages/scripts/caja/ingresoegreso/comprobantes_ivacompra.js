(function ($) {
    'use strict';

    if (!$('#tabla-comprobantes-iva-ie').length) {
        return;
    }

    var comprobantesIva = [];
    var conceptosMeta = {};
    var ptrFilaCuentaConcepto = null;
    window.ptrIeCpFilaCuentaConcepto = null;
    var previewTimer = null;
    var iaDecisionId = null;
    var iaSugerenciaHash = null;
    // true solo mientras la sugerencia IA está en el modal y aún no se aceptó a la grilla
    var iaDecisionPendienteModal = false;

    function parseJsonEl(id, fallback) {
        try {
            return JSON.parse($(id).text() || 'null') || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function descartarDecisionPendiente() {
        var decisionId = parseInt(iaDecisionId || '0', 10);
        var url = $('#modal-ie-comprobante-iva').data('descartar-url');
        if (!iaDecisionPendienteModal || !decisionId || !url) {
            return;
        }
        iaDecisionPendienteModal = false;
        try {
            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
                    decision_id: decisionId,
                },
                dataType: 'json',
                async: true,
            });
        } catch (e) {
            // ignore
        }
    }

    function init() {
        conceptosMeta = parseJsonEl('#ie-conceptos-cuenta-meta', {});
        comprobantesIva = parseJsonEl('#ie-comprobantes-iva-inicial', []);
        renderGrilla();
        syncHidden();
    }

    function syncHidden() {
        $('#comprobantes_ivacompra_json').val(JSON.stringify(comprobantesIva));
        if (typeof window.ieComprobantesIvaCambiaron === 'function') {
            window.ieComprobantesIvaCambiaron(comprobantesIva);
        }
    }

    function formatoNumero(n) {
        return (parseFloat(n) || 0).toFixed(2);
    }

    function etiquetaComprobante(c) {
        return (c.letra || '') + ' ' + (c.sucursal || '') + '-' + (c.numerocomprobante || '');
    }

    function renderGrilla() {
        var $tbody = $('#tbody-comprobantes-iva-ie');
        $tbody.empty();
        var total = 0;

        comprobantesIva.forEach(function (c, idx) {
            total += parseFloat(c.total) || 0;
            var tipoLabel = c.tipo_tesoreria === 'GASTO_BANCO' ? 'Gasto banco' : 'Fondo fijo';
            var prov = c.proveedor_nombre || c.proveedor_nombre_eventual || '—';
            var pdfBadge = (c.tiene_pdf || c.pdf_temp_id) ? ' <i class="fa fa-file-pdf text-danger" title="PDF adjunto"></i>' : '';
            $tbody.append(
                '<tr>' +
                '<td>' + tipoLabel + '</td>' +
                '<td>' + etiquetaComprobante(c) + pdfBadge + '</td>' +
                '<td>' + $('<div>').text(prov).html() + '</td>' +
                '<td>' + (c.fechaiva || '') + '</td>' +
                '<td class="text-right">' + formatoNumero(c.total) + '</td>' +
                '<td class="text-center text-nowrap">' +
                '<button type="button" class="btn btn-warning btn-sm ie-edit-comprobante" data-idx="' + idx + '">Editar</button> ' +
                '<button type="button" class="btn btn-danger btn-sm ie-del-comprobante" data-idx="' + idx + '"><i class="fa fa-trash"></i></button>' +
                '</td></tr>'
            );
        });

        $('#ie-total-comprobantes-iva').text(formatoNumero(total));
        syncHidden();
    }

    function agregarFilaConcepto(data) {
        var $tpl = $($('#ie-cp-template-concepto').html());
        if (data) {
            $tpl.find('.ie-cp-concepto-id').val(data.concepto_ivacompra_id || '');
            $tpl.find('.ie-cp-monto').val(data.monto || 0);
            if (data.cuentacontabledebe_id) {
                $tpl.find('.ie-cp-cuenta-id').val(data.cuentacontabledebe_id);
            }
        }
        $('#ie-cp-tbody-conceptos').append($tpl);
        refrescarCuentaFila($tpl);
        return $tpl;
    }

    function refrescarCuentaFila($row) {
        var conceptoId = parseInt($row.find('.ie-cp-concepto-id').val() || '0', 10);
        var meta = conceptosMeta[String(conceptoId)] || {};
        var cuentaId = parseInt($row.find('.ie-cp-cuenta-id').val() || '0', 10);
        if (cuentaId <= 0) {
            var empresaIdForm = parseInt($('#empresa_id').val() || '0', 10) || 0;
            if (meta.cuentas_por_empresa && empresaIdForm > 0 && meta.cuentas_por_empresa[empresaIdForm]) {
                cuentaId = parseInt(meta.cuentas_por_empresa[empresaIdForm], 10) || 0;
            } else if (meta.cuenta_debe_id) {
                cuentaId = parseInt(meta.cuenta_debe_id, 10) || 0;
            }
            if (cuentaId > 0) {
                $row.find('.ie-cp-cuenta-id').val(cuentaId);
            }
        }
        if (cuentaId <= 0) {
            $row.find('.ie-cp-cuenta-codigo').val('');
            $row.find('.ie-cp-cuenta-nombre').text('Sin cuenta — seleccione');
            $row.addClass('table-warning');
        } else {
            $row.removeClass('table-warning');
            $row.find('.ie-cp-cuenta-nombre').text('Cuenta #' + cuentaId);
        }
    }

    function limpiarModal() {
        descartarDecisionPendiente();
        iaDecisionId = null;
        iaSugerenciaHash = null;
        iaDecisionPendienteModal = false;
        $('#ie-cp-edit-index').val('');
        $('#ie-cp-tbody-conceptos').empty();
        agregarFilaConcepto(null);
        $('#ie-cp-tipo-tesoreria').val('FONDO_FIJO');
        $('#ie-cp-tipotransaccion-compra-id').val('');
        $('#ie-cp-letra, #ie-cp-sucursal, #ie-cp-numero, #ie-cp-total, #ie-cp-cae').val('');
        $('#ie-cp-tipo-autorizacion').val('');
        $('#ie-cp-proveedor-id, #ie-cp-proveedor-nombre').val('');
        $('#ie-cp-eventual-nombre, #ie-cp-eventual-documento').val('');
        $('#ie-cp-eventual-condicioniva').val('');
        var hoy = new Date().toISOString().slice(0, 10);
        $('#ie-cp-fecha-comprobante, #ie-cp-fecha-iva').val(hoy);
        $('#ie-cp-preview-asiento').empty();
        $('#ie-cp-preview-total-debe, #ie-cp-preview-total-haber').text('0.00');
        $('#ie-cp-preview-error, #ie-cp-asiento-avisos').addClass('d-none').empty();
        $('#ie-cp-conceptos-coherencia-error, #ie-cp-conceptos-coherencia-aviso').addClass('d-none').empty();
        $('#ie-cp-pdf-temp-id').val('');
    }

    function abrirModal(idx) {
        limpiarModal();
        if (idx !== null && idx !== undefined && comprobantesIva[idx]) {
            var c = comprobantesIva[idx];
            $('#ie-cp-edit-index').val(String(idx));
            $('#modal-ie-comprobante-iva-titulo').text('Editar comprobante IVA');
            $('#ie-cp-tipo-tesoreria').val(c.tipo_tesoreria || 'FONDO_FIJO');
            $('#ie-cp-tipotransaccion-compra-id').val(c.tipotransaccion_compra_id || '');
            $('#ie-cp-letra').val(c.letra || '');
            $('#ie-cp-sucursal').val(c.sucursal || '');
            $('#ie-cp-numero').val(c.numerocomprobante || '');
            $('#ie-cp-proveedor-id').val(c.proveedor_id || '');
            $('#ie-cp-proveedor-nombre').val(c.proveedor_nombre || '');
            $('#ie-cp-eventual-nombre').val(c.proveedor_nombre_eventual || '');
            $('#ie-cp-eventual-documento').val(c.proveedor_documento_eventual || '');
            $('#ie-cp-eventual-condicioniva').val(c.proveedor_condicioniva_id_eventual || '');
            $('#ie-cp-fecha-comprobante').val(c.fechacomprobante || '');
            $('#ie-cp-fecha-iva').val(c.fechaiva || '');
            $('#ie-cp-total').val(c.total || 0);
            $('#ie-cp-moneda-id').val(c.moneda_id || 1);
            $('#ie-cp-cae').val(c.numerocae || '');
            $('#ie-cp-tipo-autorizacion').val(c.tipo_autorizacion || (c.numerocae ? 'CAE' : ''));
            $('#ie-cp-pdf-temp-id').val(c.pdf_temp_id || '');
            // Ya estaba en grilla: no descartar al cerrar sin re-aceptar.
            iaDecisionId = c.ai_decision_id || null;
            iaSugerenciaHash = c.ai_sugerencia_hash || null;
            iaDecisionPendienteModal = false;
            $('#ie-cp-tbody-conceptos').empty();
            (c.conceptos || []).forEach(function (concepto) {
                agregarFilaConcepto(concepto);
            });
            if ((c.conceptos || []).length === 0) {
                agregarFilaConcepto(null);
            }
        } else {
            $('#modal-ie-comprobante-iva-titulo').text('Nuevo comprobante IVA');
        }
        $('#modal-ie-comprobante-iva').modal('show');
        programarPreview();
    }

    function lineasConceptosDesdeModal() {
        var conceptos = [];
        $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.ie-cp-concepto-id').val() || '0', 10);
            var monto = parseFloat($row.find('.ie-cp-monto').val() || '0');
            if (conceptoId <= 0 || monto === 0) {
                return;
            }
            conceptos.push({
                concepto_ivacompra_id: conceptoId,
                monto: monto,
            });
        });
        return conceptos;
    }

    function validarCoherenciaConceptosModal() {
        if (typeof window.ConceptosIvacompraCoherencia === 'undefined') {
            return { valido: true, errores: [], advertencias: [] };
        }
        return window.ConceptosIvacompraCoherencia.validar(lineasConceptosDesdeModal(), conceptosMeta);
    }

    function renderCoherenciaConceptosModal(result) {
        var $err = $('#ie-cp-conceptos-coherencia-error');
        var $aviso = $('#ie-cp-conceptos-coherencia-aviso');
        if (!$err.length) {
            return;
        }

        if (result.errores && result.errores.length) {
            var htmlErr = '<strong>Coherencia IVA:</strong><ul class="mb-0 pl-3">';
            result.errores.forEach(function (msg) {
                htmlErr += '<li>' + $('<div>').text(msg).html() + '</li>';
            });
            htmlErr += '</ul>';
            $err.removeClass('d-none').html(htmlErr);
        } else {
            $err.addClass('d-none').empty();
        }

        if (result.advertencias && result.advertencias.length) {
            $aviso.removeClass('d-none').text(result.advertencias[0]);
        } else {
            $aviso.addClass('d-none').empty();
        }
    }

    function serializarModal() {
        var conceptos = [];
        $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.ie-cp-concepto-id').val() || '0', 10);
            var monto = parseFloat($row.find('.ie-cp-monto').val() || '0');
            if (conceptoId <= 0 || monto === 0) {
                return;
            }
            conceptos.push({
                concepto_ivacompra_id: conceptoId,
                monto: monto,
                cuentacontabledebe_id: parseInt($row.find('.ie-cp-cuenta-id').val() || '0', 10) || null,
            });
        });

        var proveedorId = parseInt($('#ie-cp-proveedor-id').val() || '0', 10);
        var editIdx = parseInt($('#ie-cp-edit-index').val(), 10);
        var previo = (editIdx >= 0 && comprobantesIva[editIdx]) ? comprobantesIva[editIdx] : {};
        return {
            id: previo.id || null,
            tipo_tesoreria: $('#ie-cp-tipo-tesoreria').val(),
            tipotransaccion_compra_id: parseInt($('#ie-cp-tipotransaccion-compra-id').val() || '0', 10),
            proveedor_id: proveedorId,
            proveedor_nombre: $('#ie-cp-proveedor-nombre').val(),
            proveedor_nombre_eventual: proveedorId > 0 ? '' : $('#ie-cp-eventual-nombre').val(),
            proveedor_documento_eventual: proveedorId > 0 ? '' : $('#ie-cp-eventual-documento').val(),
            proveedor_condicioniva_id_eventual: proveedorId > 0 ? null : (parseInt($('#ie-cp-eventual-condicioniva').val() || '0', 10) || null),
            letra: ($('#ie-cp-letra').val() || 'B').toUpperCase(),
            sucursal: parseInt($('#ie-cp-sucursal').val() || '0', 10),
            numerocomprobante: parseInt($('#ie-cp-numero').val() || '0', 10),
            fechacomprobante: $('#ie-cp-fecha-comprobante').val(),
            fechaiva: $('#ie-cp-fecha-iva').val(),
            total: parseFloat($('#ie-cp-total').val() || '0'),
            moneda_id: parseInt($('#ie-cp-moneda-id').val() || '1', 10),
            cotizacion: 1,
            numerocae: $('#ie-cp-cae').val() || null,
            tipo_autorizacion: $('#ie-cp-tipo-autorizacion').val() || null,
            pdf_temp_id: ($('#ie-cp-pdf-temp-id').val() || '').trim() || null,
            ai_decision_id: iaDecisionId,
            ai_sugerencia_hash: iaSugerenciaHash,
            tiene_pdf: previo.tiene_pdf || false,
            conceptos: conceptos,
        };
    }

    function programarPreview() {
        renderCoherenciaConceptosModal(validarCoherenciaConceptosModal());
        clearTimeout(previewTimer);
        previewTimer = setTimeout(recargarPreviewAsiento, 400);
    }

    function recargarPreviewAsiento() {
        var $modal = $('#modal-ie-comprobante-iva');
        var url = $modal.data('preview-url');
        var empresaId = $('#empresa_id').val();
        if (!url || !empresaId) {
            return;
        }

        var payload = serializarModal();
        $.post(url, {
            _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
            empresa_id: empresaId,
            comprobante_json: JSON.stringify(payload),
        }).done(function (data) {
            if (data.mensaje !== 'ok') {
                return;
            }
            var $tbody = $('#ie-cp-preview-asiento');
            $tbody.empty();
            (data.lineas || []).forEach(function (linea) {
                $tbody.append(
                    '<tr><td>' + (linea.codigo || '') + ' ' + (linea.nombre || '') + '</td>' +
                    '<td class="text-right">' + formatoNumero(linea.debe) + '</td>' +
                    '<td class="text-right">' + formatoNumero(linea.haber) + '</td></tr>'
                );
            });
            $('#ie-cp-preview-total-debe').text(formatoNumero(data.total_debe));
            $('#ie-cp-preview-total-haber').text(formatoNumero(data.total_haber));

            var $err = $('#ie-cp-preview-error');
            var coherencia = validarCoherenciaConceptosModal();
            if (data.error) {
                $err.removeClass('d-none').text(data.error);
            } else if (!coherencia.valido) {
                $err.removeClass('d-none').text(coherencia.errores.join(' '));
            } else {
                $err.addClass('d-none').empty();
            }

            var avisos = data.avisos || [];
            var $banner = $('#ie-cp-asiento-avisos');
            if (avisos.length) {
                var html = '<ul class="mb-0 pl-3">';
                avisos.forEach(function (a) {
                    html += '<li>' + $('<div>').text(a.mensaje || '').html() + '</li>';
                });
                html += '</ul>';
                $banner.removeClass('d-none').html(html);
            } else {
                $banner.addClass('d-none').empty();
            }
        });
    }

    function guardarDesdeModal() {
        var payload = serializarModal();
        if (!payload.tipotransaccion_compra_id) {
            alert('Seleccione tipo de comprobante.');
            return;
        }
        if ((payload.conceptos || []).length === 0) {
            alert('Agregue al menos un concepto con importe.');
            return;
        }
        var coherencia = validarCoherenciaConceptosModal();
        renderCoherenciaConceptosModal(coherencia);
        if (!coherencia.valido) {
            alert(coherencia.errores.join('\n'));
            return;
        }
        if (payload.proveedor_id <= 0 && !payload.proveedor_nombre_eventual) {
            alert('Indique proveedor del maestro o datos de proveedor eventual.');
            return;
        }
        if (payload.proveedor_id <= 0 && !payload.proveedor_documento_eventual) {
            alert('El proveedor eventual debe tener CUIT (11 dígitos).');
            return;
        }

        var urlDup = $('#modal-ie-comprobante-iva').data('duplicado-url');
        var empresaId = $('#empresa_id').val();

        function persistirEnGrilla() {
            iaDecisionPendienteModal = false;
            var idx = $('#ie-cp-edit-index').val();
            if (idx !== '') {
                comprobantesIva[parseInt(idx, 10)] = payload;
            } else {
                comprobantesIva.push(payload);
            }
            renderGrilla();
            $('#modal-ie-comprobante-iva').modal('hide');
        }

        if (!urlDup || !empresaId) {
            persistirEnGrilla();
            return;
        }

        $.post(urlDup, {
            _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
            empresa_id: empresaId,
            comprobante_json: JSON.stringify(payload),
        }).done(function (data) {
            if (data.mensaje !== 'ok' || !data.valido) {
                alert(data.error || 'Comprobante duplicado para este CUIT.');
                return;
            }
            persistirEnGrilla();
        }).fail(function (xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'No se pudo validar duplicado.';
            alert(msg);
        });
    }

    function procesarPdf() {
        var file = $('#ie-cp-pdf-archivo')[0].files[0];
        var empresaId = $('#empresa_id').val();
        var url = $('#modal-ie-comprobante-iva').data('pdf-ia-url');
        if (!file || !empresaId || !url) {
            return;
        }

        var fd = new FormData();
        fd.append('pdf', file);
        fd.append('empresa_id', empresaId);
        fd.append('_token', $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val());

        $('#ie-cp-pdf-procesar').prop('disabled', true);
        $.ajax({
            url: url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        }).done(function (data) {
            if (data.mensaje !== 'ok' || !data.ok) {
                $('#ie-cp-pdf-error').removeClass('d-none').text(data.error || 'Error al procesar PDF');
                return;
            }
            $('#modal-ie-comprobante-iva-pdf').modal('hide');
            abrirModal(null);
            if (data.pdf_temp_id) {
                $('#ie-cp-pdf-temp-id').val(data.pdf_temp_id);
            }
            iaDecisionId = data.ai_decision_id || null;
            iaSugerenciaHash = data.ai_sugerencia_hash || null;
            iaDecisionPendienteModal = !!iaDecisionId;
            var cab = data.cabecera || {};
            if (cab.proveedor_id) {
                $('#ie-cp-proveedor-id').val(cab.proveedor_id);
                $('#ie-cp-proveedor-nombre').val(cab.proveedor_nombre || '');
            } else {
                $('#ie-cp-eventual-nombre').val(cab.proveedor_nombre || '');
                $('#ie-cp-eventual-documento').val(cab.proveedor_documento_eventual || '');
            }
            $('#ie-cp-letra').val(cab.letra || 'B');
            $('#ie-cp-sucursal').val(cab.sucursal || '');
            $('#ie-cp-numero').val(cab.numerocomprobante || '');
            $('#ie-cp-fecha-comprobante').val((cab.fechacomprobante || '').slice(0, 10));
            $('#ie-cp-fecha-iva').val((cab.fechaiva || '').slice(0, 10));
            $('#ie-cp-total').val(cab.total || 0);
            $('#ie-cp-cae').val(cab.numerocae || '');
            $('#ie-cp-tipo-autorizacion').val(cab.tipo_autorizacion || (cab.numerocae ? 'CAE' : ''));
            $('#ie-cp-tbody-conceptos').empty();
            (data.conceptos || []).forEach(function (c) {
                agregarFilaConcepto(c);
            });
            if ((data.conceptos || []).length === 0) {
                agregarFilaConcepto(null);
            }
            var adv = data.advertencias || [];
            if (adv.length) {
                $('#ie-cp-asiento-avisos').removeClass('d-none').html('<ul><li>' + adv.join('</li><li>') + '</li></ul>');
            }
            programarPreview();
        }).fail(function (xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al procesar PDF';
            $('#ie-cp-pdf-error').removeClass('d-none').text(msg);
        }).always(function () {
            $('#ie-cp-pdf-procesar').prop('disabled', false);
        });
    }

    $(function () {
        init();

        $('#ie-btn-nuevo-comprobante-iva').on('click', function () {
            abrirModal(null);
        });

        $('#ie-btn-pdf-ia-comprobante').on('click', function () {
            $('#ie-cp-pdf-archivo').val('');
            $('#ie-cp-pdf-error, #ie-cp-pdf-advertencias').addClass('d-none').empty();
            $('#modal-ie-comprobante-iva-pdf').modal('show');
        });

        $('#ie-cp-pdf-procesar').on('click', procesarPdf);
        $('#ie-cp-agregar-concepto').on('click', function () {
            agregarFilaConcepto(null);
        });

        $(document).on('click', '.ie-del-comprobante', function () {
            var idx = parseInt($(this).data('idx'), 10);
            if (confirm('¿Quitar este comprobante de la grilla?')) {
                comprobantesIva.splice(idx, 1);
                renderGrilla();
            }
        });

        $(document).on('click', '.ie-edit-comprobante', function () {
            abrirModal(parseInt($(this).data('idx'), 10));
        });

        $('#ie-cp-guardar-modal').on('click', guardarDesdeModal);

        $('#modal-ie-comprobante-iva').on('hidden.bs.modal', function () {
            descartarDecisionPendiente();
            iaDecisionId = null;
            iaSugerenciaHash = null;
            iaDecisionPendienteModal = false;
        });

        $(document).on('change input', '#ie-cp-tbody-conceptos .ie-cp-concepto-id, #ie-cp-tbody-conceptos .ie-cp-monto, #ie-cp-total', function () {
            var $row = $(this).closest('.ie-cp-fila-concepto');
            if ($row.length) {
                refrescarCuentaFila($row);
            }
            programarPreview();
        });

        $(document).on('click', '.ie-cp-quitar-concepto', function () {
            $(this).closest('.ie-cp-fila-concepto').remove();
            programarPreview();
        });

        $(document).on('click', '.ie-cp-consulta-cuenta', function () {
            ptrFilaCuentaConcepto = $(this).closest('.ie-cp-fila-concepto');
            window.ptrIeCpFilaCuentaConcepto = ptrFilaCuentaConcepto;
            $('#consultacuentaModal').modal('show');
        });

        window.ieComprobanteIvaAplicarCuenta = function (cuentaId, codigo, nombre) {
            if (!ptrFilaCuentaConcepto) {
                return;
            }
            ptrFilaCuentaConcepto.find('.ie-cp-cuenta-id').val(cuentaId);
            ptrFilaCuentaConcepto.find('.ie-cp-cuenta-codigo').val(codigo || '');
            ptrFilaCuentaConcepto.find('.ie-cp-cuenta-nombre').text(nombre || '');
            ptrFilaCuentaConcepto.removeClass('table-warning');
            ptrFilaCuentaConcepto = null;
            window.ptrIeCpFilaCuentaConcepto = null;
            programarPreview();
        };

        window.ieComprobanteIvaAplicarProveedor = function (id, nombre) {
            if (!$('#modal-ie-comprobante-iva').hasClass('show')) {
                return;
            }
            $('#ie-cp-proveedor-id').val(id);
            $('#ie-cp-proveedor-nombre').val(nombre);
            $('#ie-cp-eventual-nombre, #ie-cp-eventual-documento').val('');
        };

        window.obtenerComprobantesIvaIngresoEgreso = function () {
            return comprobantesIva;
        };

        window.validarComprobantesIvaContraCaja = function (callback) {
            var url = $('#tabla-comprobantes-iva-ie').data('validar-url');
            if (!url || comprobantesIva.length === 0) {
                if (typeof callback === 'function') {
                    callback(true);
                }
                return;
            }

            var montos = [];
            var monedaIds = [];
            var cotizaciones = [];
            $('#cuenta-caja-table .item-cuenta-caja').each(function () {
                montos.push($(this).find('.monto').val() || 0);
                monedaIds.push($(this).find('.moneda').val() || 1);
                cotizaciones.push($(this).find('.cotizacion').val() || 1);
            });

            $.post(url, {
                _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
                comprobantes_ivacompra_json: JSON.stringify(comprobantesIva),
                montos: montos,
                moneda_ids: monedaIds,
                cotizaciones: cotizaciones,
            }).done(function (data) {
                if (data.mensaje !== 'ok' || !data.valido) {
                    alert(data.error || 'La suma de comprobantes IVA no coincide con el total del pago.');
                    if (typeof callback === 'function') {
                        callback(false);
                    }
                    return;
                }
                if (typeof callback === 'function') {
                    callback(true);
                }
            }).fail(function () {
                alert('No se pudo validar los comprobantes IVA contra el pago.');
                if (typeof callback === 'function') {
                    callback(false);
                }
            });
        };
    });
})(jQuery);
