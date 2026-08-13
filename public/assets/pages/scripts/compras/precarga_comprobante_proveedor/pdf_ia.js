$(function () {
    var $modal = $('#modal-precarga-pdf-ia');
    if (!$modal.length) {
        return;
    }

    var previewUrl = String($modal.data('preview-url') || '');
    var resolverOcUrl = String($modal.data('resolver-oc-url') || '');
    var confirmarUrl = String($modal.data('confirmar-url') || '');
    var descartarUrl = String($modal.data('descartar-url') || '');
    var proveedorIdSelector = String($modal.data('proveedor-id-selector') || '');
    var overlayId = String($modal.data('overlay-id') || '');
    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    var previewPayload = null;
    var decisionConfirmada = false;

    function decisionIdActual() {
        if (!previewPayload) {
            return null;
        }
        if (previewPayload.decision_id) {
            return parseInt(previewPayload.decision_id, 10) || null;
        }
        var meta = (previewPayload.extraccion && previewPayload.extraccion._meta) || {};
        if (meta.ai_decision_id) {
            return parseInt(meta.ai_decision_id, 10) || null;
        }
        return null;
    }

    function descartarDecisionPendiente() {
        var decisionId = decisionIdActual();
        if (decisionConfirmada || !decisionId || !descartarUrl) {
            return;
        }
        // Beacon / sync: el modal ya se cerró; no bloquear la UI.
        try {
            $.ajax({
                url: descartarUrl,
                method: 'POST',
                data: { _token: csrf, decision_id: decisionId },
                dataType: 'json',
                async: true
            });
        } catch (e) {
            // ignore
        }
    }

    function proveedorIdPortal() {
        if (!proveedorIdSelector) {
            return '';
        }
        return String($(proveedorIdSelector).val() || '').trim();
    }

    function agregarProveedorPortal(datos) {
        var proveedorId = proveedorIdPortal();
        if (proveedorId) {
            if (datos instanceof FormData) {
                datos.append('proveedor_id', proveedorId);
            } else {
                datos.proveedor_id = proveedorId;
            }
        }
        return datos;
    }

    function mostrarOverlay(titulo) {
        if (!overlayId) {
            return;
        }
        var overlay = document.getElementById(overlayId);
        if (!overlay) {
            return;
        }
        var tituloNodo = document.getElementById(overlayId + '-titulo')
            || overlay.querySelector('strong');
        if (tituloNodo && titulo) {
            tituloNodo.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlayId) {
            return;
        }
        var overlay = document.getElementById(overlayId);
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function mostrarError(msg) {
        var texto = msg || 'Error desconocido.';
        $('#precarga-pdf-ia-error').removeClass('d-none').text(texto);
        // Si estamos en el paso OC, también refrescar el banner amarillo (queda a la vista).
        if (!$('#precarga-pdf-ia-paso-oc-manual').hasClass('d-none')) {
            $('#precarga-pdf-ia-oc-mensaje').text(texto);
        }
    }

    function limpiarError() {
        $('#precarga-pdf-ia-error').addClass('d-none').empty();
    }

    /** Fallas que no devuelven JSON: el mensaje genérico no dice nada útil. */
    function mensajePorEstadoHttp(xhr) {
        var status = xhr && typeof xhr.status === 'number' ? xhr.status : null;
        if (status === 419) {
            return 'La sesión expiró o el PDF superó el tamaño permitido por el servidor. '
                + 'Recargue la página (F5) y reintente con un PDF más liviano.';
        }
        if (status === 413) {
            return 'El PDF es demasiado grande para el servidor. Reduzca el tamaño del archivo.';
        }
        if (status === 401 || status === 403) {
            return 'No tiene permisos para esta operación o la sesión caducó. Vuelva a ingresar.';
        }
        if (status === 504 || status === 408) {
            return 'El análisis tardó más de lo permitido y el servidor cortó la conexión. Reintente.';
        }
        if (status === 0) {
            return 'Se perdió la conexión con el servidor durante el análisis. Reintente.';
        }
        if (status === 500) {
            return 'Error interno del servidor al procesar la factura. Revise el log del sistema.';
        }
        return null;
    }

    function mensajeAjax(xhrOrData, fallback) {
        var porEstado = mensajePorEstadoHttp(xhrOrData);
        var data = xhrOrData && xhrOrData.responseJSON ? xhrOrData.responseJSON : (xhrOrData || {});
        if (data.message) {
            return String(data.message);
        }
        if (data.errors && typeof data.errors === 'object') {
            var partes = [];
            Object.keys(data.errors).forEach(function (campo) {
                var msgs = data.errors[campo];
                if (Array.isArray(msgs)) {
                    msgs.forEach(function (m) { partes.push(m); });
                } else if (msgs) {
                    partes.push(String(msgs));
                }
            });
            if (partes.length) {
                return partes.join(' ');
            }
        }
        return porEstado || fallback || 'Error desconocido.';
    }

    /** Solo precarga OC si ya es un número Anita válido (6 dígitos). */
    function ocManualSugerida(data) {
        var candidatos = [
            data && data.numero_oc_intentado,
            data && data.extraccion && data.extraccion.numero_oc
        ];
        for (var i = 0; i < candidatos.length; i++) {
            var digitos = String(candidatos[i] || '').replace(/\D/g, '');
            if (digitos.length === 0) {
                continue;
            }
            // Evitar basura del OCR (CUIT/CAE/etc.): solo 1–6 dígitos numéricos útiles.
            if (digitos.length <= 6) {
                return digitos.padStart(6, '0');
            }
        }
        return '';
    }

    function resetModal() {
        descartarDecisionPendiente();
        previewPayload = null;
        decisionConfirmada = false;
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
        var tipoTxt = res.tipo_abreviatura || '';
        if (res.tipo_solicitado) {
            tipoTxt = (res.tipo_solicitado_etiqueta || res.tipo_solicitado)
                + (tipoTxt ? ' → ' + tipoTxt : '');
        }
        $('#precarga-pdf-ia-tipo').text(tipoTxt);
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
        $('#precarga-pdf-ia-vencimiento').text(
            res.fecha_vencimiento
            || (data.extraccion && data.extraccion.fecha_vencimiento)
            || '—'
        );
        $('#precarga-pdf-ia-vto-cae').text(
            res.fecha_vto_cai_cae
            || (data.extraccion && data.extraccion.fecha_vto_cai_cae)
            || '—'
        );
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

        var articulos = (res.articulos || data.articulos || []);
        var $tbodyArt = $('#precarga-pdf-ia-articulos-tbody').empty();
        if (!articulos.length) {
            $tbodyArt.append('<tr class="text-muted"><td colspan="5">Sin ítems detectados.</td></tr>');
        } else {
            articulos.forEach(function (a) {
                $tbodyArt.append(
                    '<tr>' +
                    '<td>' + $('<div>').text(a.sku || '').html() + '</td>' +
                    '<td>' + $('<div>').text(a.codigo_proveedor || '').html() + '</td>' +
                    '<td>' + $('<div>').text(a.descripcion || '').html() + '</td>' +
                    '<td class="text-right">' + (a.cantidad != null ? a.cantidad : '') + '</td>' +
                    '<td class="text-right">' + (a.precio_unitario != null ? a.precio_unitario : '') + '</td>' +
                    '</tr>'
                );
            });
        }

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
                (meta.articulos_detectados != null ? ' · ' + meta.articulos_detectados + ' artículos' : '') +
                (meta.ocr_chars != null ? ' · OCR ' + meta.ocr_chars + ' caracteres' : '') +
                '</small>';
            $advBox.removeClass('d-none').append('<div class="mt-2">' + metaHtml + '</div>');
        }

        $('#precarga-pdf-ia-paso-upload').addClass('d-none');
        $('#precarga-pdf-ia-paso-oc-manual').addClass('d-none');
        $('#precarga-pdf-ia-paso-preview').removeClass('d-none');
        $('#precarga-pdf-ia-btn-analizar').addClass('d-none');
        var $btnConf = $('#precarga-pdf-ia-btn-confirmar');
        $btnConf.removeClass('d-none');
        if (data.ai_auto_aplicable) {
            $btnConf
                .removeClass('btn-primary')
                .addClass('btn-success')
                .html('<i class="fa fa-magic"></i> Aplicar automático (score alto)');
            if (!$('#precarga-pdf-ia-auto-badge').length) {
                $('#precarga-pdf-ia-paso-preview').prepend(
                    '<div id="precarga-pdf-ia-auto-badge" class="alert alert-success py-2">' +
                    'La IA sugiere auto-aplicar (score ≥ umbral). Revisá el preview y confirmá.</div>'
                );
            }
        } else {
            $btnConf
                .removeClass('btn-success')
                .addClass('btn-primary')
                .html('<i class="fa fa-check"></i> Confirmar precarga');
            $('#precarga-pdf-ia-auto-badge').remove();
        }
    }

    function mostrarPasoOcManual(data) {
        previewPayload = data;
        limpiarError();
        $('#precarga-pdf-ia-oc-mensaje').text(data.message || 'Ingrese la orden de compra.');
        // No reinyectar OC inválidas del OCR (ej. 12 dígitos tipo CAE/CUIT): el input
        // maxlength=6 no trunca .val() programático y al "Continuar" reenvía basura.
        $('#precarga-pdf-ia-numero-oc-manual').val(ocManualSugerida(data));
        $('#precarga-pdf-ia-btn-aplicar-oc').prop('disabled', false);
        $('#precarga-pdf-ia-paso-upload').addClass('d-none');
        $('#precarga-pdf-ia-paso-preview').addClass('d-none');
        $('#precarga-pdf-ia-paso-oc-manual').removeClass('d-none');
        $('#precarga-pdf-ia-btn-confirmar').addClass('d-none');
        $('#precarga-pdf-ia-btn-analizar').addClass('d-none');
        setTimeout(function () {
            $('#precarga-pdf-ia-numero-oc-manual').trigger('focus').select();
        }, 100);
    }

    function analizarPdf() {
        limpiarError();
        if (proveedorIdSelector && !proveedorIdPortal()) {
            mostrarError('Seleccione el proveedor antes de analizar la factura.');
            return;
        }
        var fileInput = document.getElementById('precarga-pdf-ia-archivo');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            mostrarError('Seleccione un archivo PDF.');
            return;
        }

        var fd = new FormData();
        fd.append('pdf', fileInput.files[0]);
        fd.append('_token', csrf);
        agregarProveedorPortal(fd);
        var oc = String($('#precarga-pdf-ia-numero-oc').val() || '').replace(/\D/g, '');
        if (oc.length > 0) {
            fd.append('numero_oc', oc.padStart(6, '0'));
        }

        var $btn = $('#precarga-pdf-ia-btn-analizar').prop('disabled', true);
        mostrarOverlay('Analizando factura…');

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
            mostrarError(mensajeAjax(data, 'No se pudo analizar el PDF.'));
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var data = xhr.responseJSON || {};
            if (data.oc_requerida) {
                mostrarPasoOcManual(data);
                return;
            }
            mostrarError(mensajeAjax(xhr, 'Error al comunicarse con el servidor.'));
            $btn.prop('disabled', false);
        }).always(function () {
            ocultarOverlay();
        });
    }

    function aplicarOcManual() {
        limpiarError();
        if (!previewPayload || !previewPayload.extraccion) {
            mostrarError('No hay datos de extracción. Analice el PDF primero.');
            return;
        }

        var oc = String($('#precarga-pdf-ia-numero-oc-manual').val() || '').replace(/\D/g, '');
        if (oc.length === 0 || oc.length > 6) {
            mostrarError('Ingrese el número de OC (exactamente 6 dígitos, ej. 215923).');
            $('#precarga-pdf-ia-numero-oc-manual').trigger('focus').select();
            return;
        }

        var $btn = $('#precarga-pdf-ia-btn-aplicar-oc').prop('disabled', true);
        var numeroOc = oc.padStart(6, '0');

        var datosResolver = agregarProveedorPortal({
            _token: csrf,
            extraccion: JSON.stringify(previewPayload.extraccion),
            numero_oc: numeroOc
        });
        mostrarOverlay('Validando orden de compra…');

        $.ajax({
            url: resolverOcUrl,
            method: 'POST',
            data: datosResolver,
            dataType: 'json'
        }).done(function (data) {
            if (data && data.ok) {
                previewPayload = data;
                renderPreview(data);
                return;
            }
            mostrarError(mensajeAjax(data, 'No se pudo validar la OC.'));
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var data = xhr.responseJSON || {};
            // Mantener el paso OC con el mensaje del servidor (OC inexistente, CUIT, etc.).
            if (data.oc_requerida && data.extraccion) {
                previewPayload = data;
            }
            mostrarError(mensajeAjax(xhr, 'Error al resolver con la OC.'));
            $btn.prop('disabled', false);
            $('#precarga-pdf-ia-numero-oc-manual').trigger('focus').select();
        }).always(function () {
            ocultarOverlay();
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
        agregarProveedorPortal(fd);
        mostrarOverlay('Creando precarga…');

        $.ajax({
            url: confirmarUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (data) {
            if (data && data.ok && data.redirect) {
                decisionConfirmada = true;
                window.location.href = data.redirect;
                return;
            }
            mostrarError(mensajeAjax(data, 'No se pudo crear la precarga.'));
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            mostrarError(mensajeAjax(xhr, 'Error al grabar precarga.'));
            $btn.prop('disabled', false);
        }).always(function () {
            ocultarOverlay();
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);
});
