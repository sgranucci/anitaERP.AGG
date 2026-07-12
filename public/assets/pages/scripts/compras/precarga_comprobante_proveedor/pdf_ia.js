$(function () {
    var $modal = $('#modal-precarga-pdf-ia');
    if (!$modal.length) {
        return;
    }

    var previewUrl = String($modal.data('preview-url') || '');
    var resolverOcUrl = String($modal.data('resolver-oc-url') || '');
    var confirmarUrl = String($modal.data('confirmar-url') || '');
    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    var previewPayload = null;

    function mostrarError(msg) {
        $('#precarga-pdf-ia-error').removeClass('d-none').text(msg || 'Error desconocido.');
    }

    function limpiarError() {
        $('#precarga-pdf-ia-error').addClass('d-none').empty();
    }

    function resetModal() {
        previewPayload = null;
        limpiarError();
        $('#precarga-pdf-ia-archivo').val('');
        $('#precarga-pdf-ia-numero-oc').val('');
        $('#precarga-pdf-ia-numero-oc-manual').val('');
        $('#precarga-pdf-ia-paso-preview').addClass('d-none');
        $('#precarga-pdf-ia-paso-oc-manual').addClass('d-none');
        $('#precarga-pdf-ia-paso-upload').removeClass('d-none');
        $('#precarga-pdf-ia-btn-confirmar').addClass('d-none');
        $('#precarga-pdf-ia-btn-analizar').prop('disabled', false).removeClass('d-none');
        $('#precarga-pdf-ia-advertencias').addClass('d-none').empty();
        $('#precarga-pdf-ia-constatacion').addClass('d-none').empty();
    }

    function renderPreview(data) {
        var res = data.resuelto || {};
        $('#precarga-pdf-ia-empresa').text((res.empresa_nombre || '') + ' (CC OC: ' + (res.centro_costo_codigo || '—') + ')');
        $('#precarga-pdf-ia-proveedor').text(res.proveedor_nombre || '');
        $('#precarga-pdf-ia-oc').text(res.numero_oc || '');
        $('#precarga-pdf-ia-tipo').text(res.tipo_abreviatura || '');
        $('#precarga-pdf-ia-moneda').text(res.moneda || '');
        $('#precarga-pdf-ia-cotizacion').text(res.cotizacion != null ? res.cotizacion : '—');
        var numero = [res.letra || '', res.sucursal || '', res.numero_factura || ''].join(' ').trim();
        $('#precarga-pdf-ia-numero').text(numero);
        $('#precarga-pdf-ia-total').text(res.total != null ? res.total : '');

        var constArca = res.constatacion_arca || {};
        $('#precarga-pdf-ia-total-arca').text(
            constArca.total_arca != null ? constArca.total_arca : (constArca.ejecutada ? '—' : 'No constatado')
        );
        $('#precarga-pdf-ia-cae').text(res.numerocae || (data.extraccion && data.extraccion.numerocae) || '');
        $('#precarga-pdf-ia-fecha').text(res.fecha_factura || '');
        var estadoArca = '—';
        if (constArca.ejecutada === false) {
            estadoArca = 'No ejecutada';
        } else if (constArca.resultado === 'A' && constArca.ok) {
            estadoArca = 'Autorizado (OK)';
        } else if (constArca.resultado === 'A') {
            estadoArca = 'Autorizado con observaciones';
        } else if (constArca.resultado === 'R') {
            estadoArca = 'Rechazado';
        } else if (constArca.ok === false) {
            estadoArca = 'Error';
        }
        $('#precarga-pdf-ia-estado-arca').text(estadoArca);

        var $constBox = $('#precarga-pdf-ia-constatacion');
        $constBox.addClass('d-none').empty();
        if (constArca.ejecutada) {
            var constClass = (constArca.ok && constArca.resultado === 'A') ? 'alert-success' : 'alert-danger';
            if (constArca.ok && constArca.resultado === 'A') {
                constClass = 'alert-success';
            } else if (constArca.resultado === 'A') {
                constClass = 'alert-warning';
            } else {
                constClass = 'alert-danger';
            }
            var constHtml = '<strong>Constatación ARCA (WSCDC):</strong> ' + estadoArca;
            if (res.pararevisar) {
                constHtml += ' — <span class="font-weight-bold">Precarga con errores (para revisar)</span>';
            }
            if (constArca.discrepancias && constArca.discrepancias.length) {
                constHtml += '<ul class="mb-0 pl-3 mt-1">';
                constArca.discrepancias.forEach(function (d) {
                    constHtml += '<li>' + $('<div>').text(d).html() + '</li>';
                });
                constHtml += '</ul>';
            }
            $constBox.removeClass('d-none alert-info alert-success alert-warning alert-danger')
                .addClass(constClass)
                .html(constHtml);
        }

        var $tbody = $('#precarga-pdf-ia-conceptos-tbody').empty();
        (data.conceptos || []).forEach(function (c) {
            $tbody.append(
                '<tr>' +
                '<td>' + (c.id_concepto || '') + '</td>' +
                '<td>' + $('<div>').text(c.concepto_nombre || '').html() + '</td>' +
                '<td>' + $('<div>').text(c.descripcion_ia || '').html() + '</td>' +
                '<td class="text-right">' + (c.importe != null ? c.importe : '') + '</td>' +
                '</tr>'
            );
        });

        var adv = res.advertencias || data.advertencias || [];
        var $advBox = $('#precarga-pdf-ia-advertencias');
        $advBox.addClass('d-none').empty();
        if (adv.length) {
            var html = '<strong>Advertencias:</strong><ul class="mb-0 pl-3">';
            adv.forEach(function (a) { html += '<li>' + $('<div>').text(a).html() + '</li>'; });
            html += '</ul>';
            $advBox.removeClass('d-none').html(html);
        }

        var meta = data.extraccion_meta || (data.extraccion && data.extraccion._meta) || {};
        if (meta.fuentes && meta.fuentes.length) {
            var metaHtml = '<small class="text-muted">Motor: ' + meta.fuentes.join(' + ') +
                (meta.lineas_detectadas != null ? ' · ' + meta.lineas_detectadas + ' conceptos detectados' : '') +
                (meta.ocr_chars != null ? ' · OCR ' + meta.ocr_chars + ' caracteres' : '') +
                '</small>';
            $advBox.removeClass('d-none').append('<div class="mt-2">' + metaHtml + '</div>');
        }

        $('#precarga-pdf-ia-paso-upload').addClass('d-none');
        $('#precarga-pdf-ia-paso-oc-manual').addClass('d-none');
        $('#precarga-pdf-ia-paso-preview').removeClass('d-none');
        $('#precarga-pdf-ia-btn-analizar').addClass('d-none');
        $('#precarga-pdf-ia-btn-confirmar').removeClass('d-none');
    }

    function mostrarPasoOcManual(data) {
        previewPayload = data;
        $('#precarga-pdf-ia-oc-mensaje').text(data.message || 'Ingrese la orden de compra.');
        var ocPrev = (data.extraccion && data.extraccion.numero_oc) || data.numero_oc_intentado || '';
        $('#precarga-pdf-ia-numero-oc-manual').val(ocPrev);
        $('#precarga-pdf-ia-paso-upload').addClass('d-none');
        $('#precarga-pdf-ia-paso-preview').addClass('d-none');
        $('#precarga-pdf-ia-paso-oc-manual').removeClass('d-none');
        $('#precarga-pdf-ia-btn-confirmar').addClass('d-none');
        $('#precarga-pdf-ia-btn-analizar').addClass('d-none');
    }

    function analizarPdf() {
        limpiarError();
        var fileInput = document.getElementById('precarga-pdf-ia-archivo');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            mostrarError('Seleccione un archivo PDF.');
            return;
        }

        var fd = new FormData();
        fd.append('pdf', fileInput.files[0]);
        fd.append('_token', csrf);
        var oc = String($('#precarga-pdf-ia-numero-oc').val() || '').replace(/\D/g, '');
        if (oc.length > 0) {
            fd.append('numero_oc', oc.padStart(6, '0'));
        }

        var $btn = $('#precarga-pdf-ia-btn-analizar').prop('disabled', true);

        $.ajax({
            url: previewUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            if (data && data.ok) {
                previewPayload = data;
                renderPreview(data);
                return;
            }
            if (data && data.oc_requerida) {
                mostrarPasoOcManual(data);
                return;
            }
            mostrarError((data && data.message) || 'No se pudo analizar el PDF.');
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var data = xhr.responseJSON || {};
            if (data.oc_requerida) {
                mostrarPasoOcManual(data);
                return;
            }
            mostrarError(data.message || 'Error al comunicarse con el servidor.');
            $btn.prop('disabled', false);
        });
    }

    function aplicarOcManual() {
        limpiarError();
        if (!previewPayload || !previewPayload.extraccion) {
            mostrarError('No hay datos de extracción. Analice el PDF primero.');
            return;
        }

        var oc = String($('#precarga-pdf-ia-numero-oc-manual').val() || '').replace(/\D/g, '');
        if (oc.length === 0) {
            mostrarError('Ingrese el número de OC (6 dígitos).');
            return;
        }

        var $btn = $('#precarga-pdf-ia-btn-aplicar-oc').prop('disabled', true);

        $.ajax({
            url: resolverOcUrl,
            method: 'POST',
            data: {
                _token: csrf,
                extraccion: JSON.stringify(previewPayload.extraccion),
                numero_oc: oc.padStart(6, '0')
            },
            dataType: 'json'
        }).done(function (data) {
            if (data && data.ok) {
                previewPayload = data;
                renderPreview(data);
                return;
            }
            mostrarError((data && data.message) || 'No se pudo validar la OC.');
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var data = xhr.responseJSON || {};
            mostrarError(data.message || 'Error al resolver con la OC.');
            $btn.prop('disabled', false);
        });
    }

    $modal.on('hidden.bs.modal', resetModal);

    $('#precarga-pdf-ia-btn-analizar').on('click', analizarPdf);

    $('#precarga-pdf-ia-btn-aplicar-oc').on('click', aplicarOcManual);

    $('#precarga-pdf-ia-editar-oc').on('click', function () {
        if (previewPayload) {
            mostrarPasoOcManual({
                oc_requerida: true,
                message: 'Modifique la orden de compra y vuelva a validar.',
                extraccion: previewPayload.extraccion || previewPayload.resuelto
            });
        }
    });

    $('#precarga-pdf-ia-btn-confirmar').on('click', function () {
        if (!previewPayload || !previewPayload.ok) {
            mostrarError('No hay datos válidos para confirmar.');
            return;
        }

        var $btn = $(this).prop('disabled', true);
        limpiarError();

        var fileInput = document.getElementById('precarga-pdf-ia-archivo');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            mostrarError('Seleccione el PDF de la factura para grabar en Facturas_scan.');
            $btn.prop('disabled', false);
            return;
        }

        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('payload', JSON.stringify(previewPayload));
        fd.append('pdf', fileInput.files[0]);

        $.ajax({
            url: confirmarUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            if (data && data.ok && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            mostrarError((data && data.message) || 'No se pudo crear la precarga.');
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error al grabar precarga.';
            mostrarError(msg);
            $btn.prop('disabled', false);
        });
    });
});
