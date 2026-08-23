$(function () {
    var $alertaContabilizar = $('#cp-alerta-contabilizar');
    if ($alertaContabilizar.length && $alertaContabilizar.offset()) {
        $('html, body').animate({ scrollTop: Math.max(0, $alertaContabilizar.offset().top - 80) }, 250);
    }

    if (!$('#form-comprobante-proveedor').length) {
        return;
    }

    var $form = $('#form-comprobante-proveedor');
    var comprobanteId = parseInt($form.attr('data-comprobante-id') || $form.data('comprobanteId') || '0', 10);
    var contabilizado = String($form.attr('data-contabilizado') || $form.data('contabilizado') || '0') === '1';
    // Preferir attr(): jQuery .data() a veces no lee data-preview-url en kebab-case.
    var previewUrl = String($form.attr('data-preview-url') || $form.data('previewUrl') || '').trim();
    var puedeEditarConceptoIva = String($form.attr('data-puede-editar-concepto-iva') || $form.data('puedeEditarConceptoIva') || '0') === '1';
    var urlEditarConceptoIvaTpl = String($form.attr('data-url-editar-concepto-iva') || $form.data('urlEditarConceptoIva') || '');
    var previewTimer = null;
    var previewSeq = 0;
    var conceptosMeta = {};

    try {
        conceptosMeta = JSON.parse($('#cp-conceptos-cuenta-meta').text() || '{}');
    } catch (e) {
        conceptosMeta = {};
    }

    var TIPOS_NETO = ['N', 'G', 'E'];

    function parseMonto(val) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.parseDecimal === 'function') {
            return window.AsientoMontosFormato.parseDecimal(val);
        }
        var n = parseFloat(String(val || '').replace(/\./g, '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function formatearInputMontoEn($root) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.initEnContenedor === 'function') {
            window.AsientoMontosFormato.initEnContenedor($root && $root.length ? $root[0] : document);
        }
    }

    function mostrarSolapa(sel) {
        var paneId = String(sel || '').replace(/^#/, '');
        if (!paneId) {
            return;
        }
        var $link = $('#cp-tabs-comprobante .nav-link[href="#' + paneId + '"]');
        if ($link.length && typeof $link.tab === 'function') {
            $link.tab('show');
            return;
        }
        // Fallback legacy (por si falta Bootstrap tabs)
        $('.cp-solapa').hide().removeClass('show active');
        $('#' + paneId).show().addClass('show active');
    }

    function abrirSolapaCom() {
        if ($('#cp-solapa-recepciones-com').length) {
            mostrarSolapa('#cp-solapa-recepciones-com');
            actualizarUiRecepcionesCom();
            return;
        }
        if ($('#cp-solapa-recepciones-com-inline').length) {
            mostrarSolapa('#cp-solapa-principal');
            $('html, body').animate({
                scrollTop: $('#cp-solapa-recepciones-com-inline').offset().top - 80
            }, 200);
            actualizarUiRecepcionesCom();
        }
    }

    function abrirSolapaOc() {
        if ($('#cp-solapa-ordencompra').length) {
            mostrarSolapa('#cp-solapa-ordencompra');
        }
    }

    function marcarTabActivo(btnDomId) {
        var $b = $('#' + btnDomId);
        if ($b.length && $b.hasClass('nav-link') && typeof $b.tab === 'function') {
            $b.tab('show');
        }
    }

    function esModoAsignaRecepcion() {
        return $('#modo_carga').val() === 'ASIGNA_RECEPCION';
    }

    function contratoImputacionManual() {
        return String($form.attr('data-contrato-imputacion') || '') === 'manual'
            && String($form.attr('data-contrato-vigente') || '') === '1'
            && String($form.attr('data-contrato-requiere-recepcion') || '') !== '1';
    }

    function contratoImputacionArticulos() {
        return String($form.attr('data-contrato-imputacion') || '') === 'articulos'
            && String($form.attr('data-contrato-vigente') || '') === '1'
            && String($form.attr('data-contrato-requiere-recepcion') || '') !== '1';
    }

    function contratoCuentaManualDatos() {
        return {
            id: parseInt($form.attr('data-contrato-cuentacontable-id') || '0', 10) || 0,
            codigo: String($form.attr('data-contrato-cuentacontable-codigo') || ''),
            nombre: String($form.attr('data-contrato-cuentacontable-nombre') || '')
        };
    }

    function aplicarCuentaContratoEnFila($row, forzar) {
        if (!contratoImputacionManual()) {
            return;
        }
        var $campo = $row.find('.cp-celda-cuenta-debe');
        $campo.removeClass('d-none');
        var datos = contratoCuentaManualDatos();
        if (datos.id <= 0) {
            return;
        }
        var actual = parseInt($campo.find('.cuentacontable_id').val() || '0', 10) || 0;
        if (!forzar && actual > 0) {
            return;
        }
        $campo.find('.cuentacontable_id').val(String(datos.id));
        $campo.find('.codigocuentacontable').val(datos.codigo);
        $campo.find('.nombrecuentacontable').val(datos.nombre);
        if (typeof actualizarLinkEditarCuentaContable === 'function') {
            actualizarLinkEditarCuentaContable($campo, datos.id);
        }
    }

    function conceptoRequiereCuentaDebe(tipoConcepto) {
        var esNeto = TIPOS_NETO.indexOf(String(tipoConcepto || '')) >= 0;
        if (esModoAsignaRecepcion() && esNeto) {
            return false;
        }
        if (contratoImputacionArticulos() && esNeto) {
            return false;
        }
        if (contratoImputacionManual() && esNeto) {
            return false;
        }
        return true;
    }

    function actualizarBadgeAsiento(tieneProblema) {
        var $tab = $('#cp-boton-asiento-contable');
        if (!$tab.length) {
            return;
        }
        $tab.find('.cp-badge-asiento-error').remove();
        if (tieneProblema) {
            $tab.append('<span class="badge badge-warning ml-1 cp-badge-asiento-error" title="Revise el cuadre antes de contabilizar">!</span>');
        }
    }

    function actualizarBadgeConceptos(tieneProblema) {
        var $tab = $('#cp-boton-conceptos');
        if (!$tab.length) {
            return;
        }
        $tab.find('.cp-badge-conceptos-error').remove();
        if (tieneProblema) {
            $tab.append('<span class="badge badge-warning ml-1 cp-badge-conceptos-error" title="Revise coherencia neto gravado / IVA">!</span>');
        }
    }

    function lineasConceptosDesdeFormulario() {
        var lineas = [];
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            if (conceptoId <= 0 || monto === 0) {
                return;
            }
            lineas.push({ concepto_ivacompra_id: conceptoId, monto: monto });
        });
        return lineas;
    }

    function validarCoherenciaConceptosPantalla() {
        if (typeof window.ConceptosIvacompraCoherencia === 'undefined') {
            return { valido: true, errores: [], advertencias: [] };
        }
        return window.ConceptosIvacompraCoherencia.validar(lineasConceptosDesdeFormulario(), conceptosMeta);
    }

    function renderBannerCoherenciaConceptos(result) {
        var $err = $('#cp-conceptos-iva-coherencia-banner');
        var $aviso = $('#cp-conceptos-iva-coherencia-aviso');
        if (!$err.length) {
            return;
        }

        if (result.errores && result.errores.length) {
            var htmlErr = '<strong><i class="fa fa-exclamation-triangle"></i> Coherencia IVA:</strong><ul class="mb-0 mt-1 pl-3">';
            result.errores.forEach(function (msg) {
                htmlErr += '<li>' + $('<div>').text(msg).html() + '</li>';
            });
            htmlErr += '</ul>';
            $err.removeClass('d-none').html(htmlErr);
        } else {
            $err.addClass('d-none').empty();
        }

        if (result.advertencias && result.advertencias.length) {
            var htmlAv = '<strong><i class="fa fa-info-circle"></i> </strong>' + $('<div>').text(result.advertencias[0]).html();
            $aviso.removeClass('d-none').html(htmlAv);
        } else {
            $aviso.addClass('d-none').empty();
        }

        actualizarBadgeConceptos(!result.valido);
    }

    function marcarAvisosConceptosLocales() {
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var $select = $row.find('.concepto_ivacompra_id');
            var $monto = $row.find('.monto');
            var $aviso = $row.find('.cp-aviso-concepto-cuenta');
            var conceptoId = parseInt($select.val() || '0', 10);
            var monto = parseMonto($monto.val() || '0');

            $row.removeClass('table-warning');
            $aviso.removeClass('text-danger fa fa-exclamation-triangle').text('').attr('title', '');

            if (conceptoId <= 0 || monto <= 0) {
                return;
            }

            var meta = conceptosMeta[conceptoId] || {};
            var esNeto = TIPOS_NETO.indexOf(String(meta.tipoconcepto || '')) >= 0;
            if (contratoImputacionArticulos() && esNeto) {
                $aviso.addClass('text-muted').text('OC').attr('title', 'Neto: cuenta de los artículos de la OC');
                return;
            }
            if (contratoImputacionManual() && esNeto) {
                var cuentaManual = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
                if (cuentaManual <= 0) {
                    cuentaManual = contratoCuentaManualDatos().id;
                }
                if (cuentaManual <= 0) {
                    $row.addClass('table-warning');
                    $aviso.addClass('text-danger fa fa-exclamation-triangle')
                        .attr('title', 'Falta la cuenta DEBE del neto (indicarla en el contrato o en este renglón)');
                } else {
                    $aviso.addClass('text-success fa fa-check').attr('title', 'Cuenta DEBE del contrato / renglón');
                }
                return;
            }
            if (!conceptoRequiereCuentaDebe(meta.tipoconcepto)) {
                $aviso.addClass('text-muted').text('—').attr('title', 'Neto: revierte provisión COM');
                return;
            }

            var empresaIdForm = parseInt($('#empresa_id').val() || '0', 10) || 0;
            var cuentaDebe = parseInt(meta.cuenta_debe_id || '0', 10) || 0;
            if (meta.cuentas_por_empresa && empresaIdForm > 0 && meta.cuentas_por_empresa[empresaIdForm]) {
                cuentaDebe = parseInt(meta.cuentas_por_empresa[empresaIdForm], 10) || 0;
            }

            if (!cuentaDebe || cuentaDebe <= 0) {
                $row.addClass('table-warning');
                if (puedeEditarConceptoIva && urlEditarConceptoIvaTpl && conceptoId > 0) {
                    var urlEditar = urlEditarConceptoIvaTpl.replace('__ID__', String(conceptoId));
                    $aviso.html(
                        '<a href="' + urlEditar + '" class="text-danger" target="_blank" rel="noopener noreferrer" ' +
                        'title="Falta cuenta DEBE en concepto IVA «' + (meta.nombre || '') + '»">' +
                        '<i class="fa fa-exclamation-triangle"></i></a>'
                    );
                } else {
                    $aviso.addClass('text-danger fa fa-exclamation-triangle')
                        .attr('title', 'Falta cuenta DEBE en concepto IVA «' + (meta.nombre || '') + '»');
                }
            } else {
                $aviso.addClass('text-success fa fa-check').attr('title', 'Cuenta DEBE configurada');
            }
        });
    }

    function renderBannerAvisos(avisos, error) {
        var $banner = $('#cp-asiento-avisos-banner');
        if (!$banner.length) {
            return;
        }

        var mensajes = [];
        if (error) {
            mensajes.push(error);
        }
        (avisos || []).forEach(function (aviso) {
            if (aviso && aviso.mensaje) {
                mensajes.push(aviso.mensaje);
            }
        });

        if (mensajes.length === 0) {
            $banner.addClass('d-none').empty();
            return;
        }

        var html = '<strong><i class="fa fa-exclamation-triangle"></i> Asiento contable:</strong><ul class="mb-0 mt-1 pl-3">';
        mensajes.forEach(function (msg) {
            html += '<li>' + $('<div>').text(msg).html() + '</li>';
        });
        html += '</ul>';
        $banner.removeClass('d-none').html(html);
    }

    function aplicarAvisosProveedor(avisos) {
        var $aviso = $('#cp-aviso-proveedor-cuenta');
        if (!$aviso.length) {
            return;
        }

        var problema = (avisos || []).some(function (a) {
            return a.tipo === 'proveedor_sin_cuenta'
                || a.tipo === 'proveedor_sin_cuenta_me'
                || a.tipo === 'proveedor_sin_seleccionar';
        });

        if (problema) {
            $aviso.removeClass('d-none').text('Sin cuenta contable de proveedores');
        } else {
            $aviso.addClass('d-none').text('');
        }
    }

    function targetsPreviewAsiento() {
        return $('.cp-asiento-preview-target');
    }

    function sincronizarTotalesDesdeConceptos() {
        var total = 0;
        var subtotal = 0;
        var hayLineas = false;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            if (conceptoId <= 0 || Math.abs(monto) < 0.0001) {
                return;
            }
            hayLineas = true;
            total += monto;
            var tip = String((conceptosMeta[conceptoId] || {}).tipoconcepto || '');
            if (TIPOS_NETO.indexOf(tip) >= 0) {
                subtotal += monto;
            }
        });
        if (!hayLineas) {
            var fmtVacio = function (n) {
                if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
                    return window.AsientoMontosFormato.fmt(n);
                }
                return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };
            if ($('#total').length) {
                $('#total').val(fmtVacio(0));
            }
            if ($('#subtotal').length) {
                $('#subtotal').val(fmtVacio(0));
            }
            return;
        }
        var fmt = function (n) {
            if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
                return window.AsientoMontosFormato.fmt(n);
            }
            return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        if ($('#total').length) {
            $('#total').val(fmt(total));
        }
        if ($('#subtotal').length && subtotal > 0) {
            $('#subtotal').val(fmt(subtotal));
        }
    }

    function serializarFormularioPreview() {
        sincronizarTotalesDesdeConceptos();

        // Armar payload explícito: montos en decimal (evita es-AR y desfase de #total).
        // `_method` (PUT del form de edición) haría que Laravel enrute el POST como PUT → 405.
        var params = $form.serializeArray().filter(function (p) {
            return p.name !== '_method'
                && p.name !== 'montos[]'
                && p.name !== 'concepto_ivacompra_ids[]'
                && p.name !== 'cuentacontabledebe_ids[]'
                && p.name !== 'total'
                && p.name !== 'subtotal';
        });

        var total = 0;
        var subtotal = 0;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            params.push({ name: 'concepto_ivacompra_ids[]', value: String(conceptoId > 0 ? conceptoId : '') });
            params.push({ name: 'montos[]', value: conceptoId > 0 ? String(monto) : '' });
            var cuentaDebeId = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
            params.push({ name: 'cuentacontabledebe_ids[]', value: cuentaDebeId > 0 ? String(cuentaDebeId) : '' });
            if (conceptoId <= 0 || Math.abs(monto) < 0.0001) {
                return;
            }
            total += monto;
            var tip = String((conceptosMeta[conceptoId] || {}).tipoconcepto || '');
            if (TIPOS_NETO.indexOf(tip) >= 0) {
                subtotal += monto;
            }
        });

        total = Math.round(total * 100) / 100;
        subtotal = Math.round(subtotal * 100) / 100;
        if (total > 0) {
            params.push({ name: 'total', value: String(total) });
        } else {
            params.push({ name: 'total', value: String(parseMonto($('#total').val() || '0')) });
        }
        if (subtotal > 0) {
            params.push({ name: 'subtotal', value: String(subtotal) });
        } else {
            params.push({ name: 'subtotal', value: String(parseMonto($('#subtotal').val() || '0')) });
        }

        return $.param(params);
    }

    function recargarPreviewAsiento() {
        if (contabilizado || !previewUrl) {
            if (!previewUrl && window.console && console.warn) {
                console.warn('CP preview: falta data-preview-url en #form-comprobante-proveedor');
            }
            return;
        }

        var seq = ++previewSeq;
        var $targets = targetsPreviewAsiento();
        if ($targets.length) {
            $targets.data('loading', 1);
            $targets.css('opacity', 0.55);
        }

        $.ajax({
            url: previewUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            data: serializarFormularioPreview()
        })
            .done(function (res) {
                if (seq !== previewSeq) {
                    return;
                }
                if ($targets.length && res && typeof res.html === 'string') {
                    $targets.html(res.html);
                }
                $targets.css('opacity', 1).removeData('loading');
                var ahora = new Date();
                var hh = String(ahora.getHours()).padStart(2, '0');
                var mm = String(ahora.getMinutes()).padStart(2, '0');
                var ss = String(ahora.getSeconds()).padStart(2, '0');
                $('#cp-preview-asiento-status').text('Actualizado ' + hh + ':' + mm + ':' + ss);
                renderBannerAvisos(res.avisos || [], res.error || null);
                aplicarAvisosProveedor(res.avisos || []);
                var coherencia = validarCoherenciaConceptosPantalla();
                actualizarBadgeAsiento(!!(res.error || (res.avisos && res.avisos.length) || !coherencia.valido));
            })
            .fail(function (xhr, status) {
                if (seq !== previewSeq) {
                    return;
                }
                $targets.css('opacity', 1).removeData('loading');
                if (status === 'abort') {
                    return;
                }
                var msg = 'No se pudo actualizar el preview del asiento';
                if (xhr && xhr.status) {
                    msg += ' (HTTP ' + xhr.status + ')';
                }
                $('#cp-preview-asiento-status').text('Error al actualizar');
                renderBannerAvisos([], msg);
            });
    }

    function programarPreviewAsiento() {
        if (contabilizado) {
            return;
        }
        sincronizarTotalesDesdeConceptos();
        marcarAvisosConceptosLocales();
        renderBannerCoherenciaConceptos(validarCoherenciaConceptosPantalla());
        clearTimeout(previewTimer);
        previewTimer = setTimeout(recargarPreviewAsiento, 280);
    }

    window.refrescarPreviewAsiento = function (inmediato) {
        if (inmediato) {
            clearTimeout(previewTimer);
            sincronizarTotalesDesdeConceptos();
            recargarPreviewAsiento();
            return;
        }
        programarPreviewAsiento();
    };

    $('#cp-tabs-comprobante a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var href = $(e.target).attr('href') || '';
        if (href === '#cp-solapa-recepciones-com') {
            actualizarUiRecepcionesCom();
        }
        if (href === '#cp-solapa-asiento-contable' || href === '#cp-solapa-conceptos') {
            window.refrescarPreviewAsiento(true);
        }
    });

    $('#agrega_renglon_concepto').on('click', function (e) {
        e.preventDefault();
        var renglon = $('#template-renglon-concepto').html();
        var $nuevo = $(renglon);
        if (contratoImputacionManual()) {
            aplicarCuentaContratoEnFila($nuevo, true);
        } else {
            $nuevo.find('.cp-celda-cuenta-debe').addClass('d-none');
            $nuevo.find('.cp-celda-cuenta-debe .cuentacontable_id').val('');
        }
        $('#tbody-concepto-table').append($nuevo);
        formatearInputMontoEn($nuevo);
        setTimeout(function () {
            $nuevo.find('.codigo_concepto_ivacompra').trigger('focus').select();
        }, 0);
        // Renglón vacío: no refrescar asiento (evita abortar un preview en curso).
    });

    $('#agrega_renglon_cp_articulo').on('click', function (e) {
        e.preventDefault();
        var tpl = document.getElementById('template-renglon-cp-articulo');
        if (!tpl || !tpl.content) {
            return;
        }
        var $nuevo = $(tpl.content.cloneNode(true));
        $('#tbody-cp-articulo-table').append($nuevo);
        formatearInputMontoEn($('#tbody-cp-articulo-table tr.item-cp-articulo').last());
        setTimeout(function () {
            $('#tbody-cp-articulo-table tr.item-cp-articulo').last().find('.codigoarticulo').trigger('focus').select();
        }, 0);
    });

    $(document).on('click', '.eliminar_cp_articulo', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    // F1 / Enter en SKU de la solapa Artículos (mismo patrón que conceptos IVA).
    function esTeclaF1ArticuloCp(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112 || e.which === 112);
    }

    function abrirConsultaArticuloDesdeCodigoCp($input) {
        var $btn = $input.closest('tr.item-cp-articulo').find('.consultaarticulo').first();
        if ($btn.length) {
            $btn.trigger('click');
            return;
        }
        $input.closest('td').find('.consultaarticulo').first().trigger('click');
    }

    $(document).on('keydown', '#cp-articulo-table .codigoarticulo', function (e) {
        if (esTeclaF1ArticuloCp(e)) {
            e.preventDefault();
            e.stopPropagation();
            if ($('#consultaarticuloModal').hasClass('show') || $('#consultaarticuloModal').is(':visible')) {
                return;
            }
            abrirConsultaArticuloDesdeCodigoCp($(this));
            return;
        }
        if (e.key !== 'Enter' && e.which !== 13) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var $input = $(this);
        var sku = String($input.val() || '').trim();
        if (sku === '') {
            abrirConsultaArticuloDesdeCodigoCp($input);
            return;
        }
        // Dispara el resolver por SKU de stock/articulo/consulta.js
        $input.trigger('change');
        var $next = $input.closest('tr.item-cp-articulo').find('input[name="articulo_codigos_proveedor[]"]');
        if ($next.length) {
            setTimeout(function () {
                $next.trigger('focus').select();
            }, 50);
        }
    });

    if (!window.__cpArticuloF1CaptureActivo) {
        document.addEventListener('keydown', function (e) {
            if (!esTeclaF1ArticuloCp(e)) {
                return;
            }
            if (!$('#form-comprobante-proveedor').length && !$('#cp-articulo-table').length) {
                return;
            }
            var t = e.target;
            if (!t || !t.classList || !t.classList.contains('codigoarticulo')) {
                return;
            }
            if (!t.closest || !t.closest('#cp-articulo-table')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaArticuloDesdeCodigoCp($(t));
        }, true);
        window.__cpArticuloF1CaptureActivo = true;
    }

    $(document).on('click', '.eliminar_concepto', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        programarPreviewAsiento();
    });

    $(document).on('keydown', '#tbody-concepto-table .monto', function (e) {
        if (e.key !== 'Enter' && e.which !== 13) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var $row = $(this).closest('tr');
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.formatearInput === 'function') {
            window.AsientoMontosFormato.formatearInput(this);
        }
        var coherencia = validarCoherenciaConceptosPantalla();
        renderBannerCoherenciaConceptos(coherencia);
        marcarAvisosConceptosLocales();
        // No agregar renglón vacío al Enter: abortaba el preview y podía confundir al grabar.
        sincronizarTotalesDesdeConceptos();
        window.refrescarPreviewAsiento(true);
        if (!coherencia.valido) {
            return;
        }
        var $nextCodigo = $row.nextAll('tr.item-concepto').first().find('.codigo_concepto_ivacompra');
        if ($nextCodigo.length) {
            $nextCodigo.trigger('focus').select();
            return;
        }
        $(this).trigger('blur');
    });

    $(document).on('click', '#cp-abrir-solapa-com-desde-datos, #cp-abrir-solapa-com-desde-oc', function (e) {
        e.preventDefault();
        abrirSolapaCom();
    });

    $(document).on('click', '.cp-abrir-solapa-oc', function (e) {
        e.preventDefault();
        abrirSolapaOc();
    });

    $(document).on('click', '#cp-refrescar-preview-conceptos', function (e) {
        e.preventDefault();
        window.refrescarPreviewAsiento(true);
    });

    $(document).on('click', '#cp-ir-solapa-asiento', function (e) {
        e.preventDefault();
        mostrarSolapa('#cp-solapa-asiento-contable');
    });

    function formatearMonto(n) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
            return window.AsientoMontosFormato.fmt(n);
        }
        return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function actualizarUiRecepcionesCom() {
        var $bloque = $('#cp-bloque-recepciones-com');
        if (!$bloque.length) {
            return;
        }

        var modo = $('#modo_carga').val();
        if (modo === 'ASIGNA_RECEPCION') {
            $bloque.show();
        } else {
            $bloque.hide();
        }

        var toleranciaPct = parseFloat($bloque.attr('data-tolerancia-pct')) || 0;
        var importeRef = parseFloat($bloque.attr('data-importe-ref')) || 0;

        var sumaCom = 0;
        var checks = 0;
        var etiquetasCom = [];
        $('.cp-com-check:checked').each(function () {
            checks++;
            var $fila = $(this).closest('.cp-com-fila');
            sumaCom += parseFloat($fila.attr('data-importe-com')) || 0;
            var id = String($fila.attr('data-recepcion-id'));
            var numero = String($fila.attr('data-numerorecepcion') || '').trim();
            etiquetasCom.push(numero ? (numero + ' (ID ' + id + ')') : ('#' + id));
            $('.cp-com-articulos-bloque[data-recepcion-id="' + id + '"]').show();
        });
        $('.cp-com-check:not(:checked)').each(function () {
            var id = String($(this).closest('.cp-com-fila').attr('data-recepcion-id'));
            $('.cp-com-articulos-bloque[data-recepcion-id="' + id + '"]').hide();
        });

        var $bannerTexto = $('#cp-banner-com-texto');
        if ($bannerTexto.length) {
            if (etiquetasCom.length > 0) {
                $bannerTexto.html('COM asignada(s): <strong>' + etiquetasCom.join(', ') + '</strong>.');
            } else if (String($('#cp-banner-com-datos').attr('data-com-obligatoria') || '') === '1') {
                $bannerTexto.text('Debe asignar recepción(es) COM obligatoria(s).');
            } else {
                $bannerTexto.text('Este comprobante usa modo asignación de recepción COM.');
            }
        }

        if (checks > 0) {
            $('#cp-com-articulos-vacio').hide();
        } else {
            $('#cp-com-articulos-vacio').show();
        }

        var $resumen = $('#cp-com-resumen-diferencia');
        if (checks === 0) {
            $resumen
                .removeClass('alert-success alert-warning alert-danger')
                .addClass('alert-secondary')
                .html('<i class="fa fa-info-circle"></i> Seleccione al menos una recepción COM.')
                .show();
            return;
        }

        var diff = Math.abs(importeRef - sumaCom);
        var pct = sumaCom > 0.00001 ? (diff / Math.abs(sumaCom)) * 100 : (diff > 0.05 ? 100 : 0);
        var okCentavos = diff <= 0.05;
        var okTol = okCentavos || pct <= toleranciaPct + 0.0001;
        var cls = okTol ? 'alert-success' : 'alert-danger';
        var msg = 'Provisión COM (moneda factura): <strong>' + formatearMonto(sumaCom) +
            '</strong> · Ref. factura: <strong>' + formatearMonto(importeRef) +
            '</strong> · Diferencia: <strong>' + formatearMonto(diff) +
            '</strong> (' + formatearMonto(pct) + '%) · Tolerancia: ' + formatearMonto(toleranciaPct) + '%';
        if (!okTol) {
            msg += ' — <strong>fuera de tolerancia</strong> (al guardar se devolverá el legajo a Compras).';
        } else if (!okCentavos && diff > 0) {
            msg += ' — dentro de tolerancia; el excedente neto se prorratea en el asiento sobre artículos COM.';
        } else {
            msg += ' — coincide con la provisión.';
        }
        $resumen.removeClass('alert-secondary alert-success alert-warning alert-danger').addClass(cls).html(msg).show();
    }

    function toggleBloqueRecepcionesCom() {
        actualizarUiRecepcionesCom();
        programarPreviewAsiento();
    }

    $('#modo_carga').on('change', toggleBloqueRecepcionesCom);
    toggleBloqueRecepcionesCom();

    $form.on('input change', 'input, select, textarea', function () {
        programarPreviewAsiento();
    });

    // Tras formatear es-AR en blur (montos_formato.js dispara asiento:monto-actualizado).
    $(document).on('asiento:monto-actualizado', function () {
        window.refrescarPreviewAsiento(true);
    });

    $(document).on('change', '#tbody-concepto-table .concepto_ivacompra_id, #tbody-concepto-table .monto, #tbody-concepto-table .cp-celda-cuenta-debe .cuentacontable_id', function () {
        marcarAvisosConceptosLocales();
        programarPreviewAsiento();
    });

    $(document).on('cp:concepto-ivacompra-elegido', function () {
        programarPreviewAsiento();
    });

    function actualizarAbreviaturaTipoComprobante() {
        // Compat: la abreviatura vive en el campo modal (.abreviaturatipotransaccioncompra).
        var abrev = String($('#tipotransaccion_compra_id_abreviatura').val() || '').trim();
        if ($('#cp-tipotransaccion-abreviatura').length) {
            $('#cp-tipotransaccion-abreviatura').val(abrev);
        }
    }

    function formatearMontoConcepto(n) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
            return window.AsientoMontosFormato.fmt(n);
        }
        return (Math.round((n || 0) * 100) / 100).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function limpiarFilasConceptos() {
        $('#tbody-concepto-table').empty();
    }

    function agregarFilaConcepto(concepto, monto) {
        var tpl = document.getElementById('template-renglon-concepto');
        if (!tpl || !tpl.content) {
            return;
        }
        var $row = $(tpl.content.cloneNode(true));
        var id = parseInt(concepto && concepto.id ? concepto.id : '0', 10) || 0;
        $row.find('.concepto_ivacompra_id').val(id > 0 ? String(id) : '');
        $row.find('.codigo_concepto_ivacompra').val((concepto && concepto.codigo) || '');
        $row.find('.nombre_concepto_ivacompra').val((concepto && concepto.nombre) || '');
        var montoTxt = '';
        if (monto !== undefined && monto !== null && String(monto) !== '') {
            montoTxt = formatearMontoConcepto(parseFloat(monto) || 0);
        }
        $row.find('.monto').val(montoTxt);
        if (contratoImputacionManual()) {
            aplicarCuentaContratoEnFila($row, true);
        } else {
            $row.find('.cp-celda-cuenta-debe').addClass('d-none');
            $row.find('.cp-celda-cuenta-debe .cuentacontable_id').val('');
        }
        if (id > 0 && concepto) {
            conceptosMeta[id] = {
                tipoconcepto: String(concepto.tipoconcepto || ''),
                cuentacontable_id: concepto.cuentacontable_id || null,
            };
        }
        $('#tbody-concepto-table').append($row);
    }

    function precargarConceptosPorTipo(tipoId, forzar) {
        var id = parseInt(tipoId || '0', 10) || 0;
        if (id <= 0 || contabilizado) {
            return;
        }
        var hayMontos = false;
        if (!forzar) {
            $('#tbody-concepto-table tr.item-concepto').each(function () {
                var monto = parseMonto($(this).find('.monto').val() || '0');
                if (Math.abs(monto) >= 0.0001) {
                    hayMontos = true;
                }
            });
            if (hayMontos) {
                return;
            }
        }

        var $aviso = $('#cp-conceptos-tipo-aviso');
        var base = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
        $.getJSON(base + '/compras/tipotransaccion_compra/' + id + '/conceptos-iva')
            .done(function (res) {
                var lista = (res && res.conceptos) || [];
                limpiarFilasConceptos();
                if (!lista.length) {
                    agregarFilaConcepto({}, '');
                    if ($aviso.length) {
                        $aviso.removeClass('d-none').html(
                            '<i class="fa fa-info-circle"></i> El tipo no tiene conceptos IVA configurados. Agréguelos manualmente.'
                        );
                    }
                } else {
                    lista.forEach(function (c) {
                        agregarFilaConcepto(c, '');
                    });
                    if ($aviso.length) {
                        $aviso.removeClass('d-none').html(
                            '<i class="fa fa-check-circle"></i> Conceptos precargados según el tipo de comprobante. Complete los montos.'
                        );
                    }
                }
                sincronizarTotalesDesdeConceptos();
                programarPreviewAsiento();
            })
            .fail(function () {
                if ($aviso.length) {
                    $aviso.removeClass('d-none').html(
                        '<i class="fa fa-exclamation-triangle"></i> No se pudieron precargar los conceptos del tipo.'
                    );
                }
            });
    }

    window.payloadExtraConsultaTipotransaccionCompra = function () {
        var cc = parseInt(String(window.cpCentrocostoOcId || '0'), 10) || 0;
        var fromCampo = parseInt(
            String($('.tm-tipotransaccion-compra-campo').first().attr('data-centrocosto-id') || '0'),
            10
        ) || 0;
        var id = cc || fromCampo;
        return id > 0 ? { centrocosto_id: id } : {};
    };

    if (typeof activa_eventos_consultatipotransaccioncompra === 'function') {
        activa_eventos_consultatipotransaccioncompra();
    }

    actualizarAbreviaturaTipoComprobante();

    $(document).on('cp:tipotransaccion-compra-elegido', function (e, tipoId) {
        actualizarAbreviaturaTipoComprobante();
        precargarConceptosPorTipo(tipoId, true);
    });

    // Si el alta viene con tipo y sin conceptos con monto, precargar plantilla del tipo.
    (function precargaInicialConceptosTipo() {
        if (contabilizado) {
            return;
        }
        var tipoId = parseInt(String($('#tipotransaccion_compra_id').val() || '0'), 10) || 0;
        if (tipoId <= 0) {
            return;
        }
        var filas = $('#tbody-concepto-table tr.item-concepto').length;
        var conConcepto = 0;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            if (parseInt(String($(this).find('.concepto_ivacompra_id').val() || '0'), 10) > 0) {
                conConcepto++;
            }
        });
        if (filas === 0 || conConcepto === 0) {
            precargarConceptosPorTipo(tipoId, true);
        }
    })();

    // Compat legado: si queda un select (otras pantallas), mantener el aviso.
    $('#tipotransaccion_compra_id').on('change', function () {
        if ($(this).is('select')) {
            actualizarAbreviaturaTipoComprobante();
            precargarConceptosPorTipo($(this).val(), true);
        }
    });

    $(document).on('change', '#cp-bloque-recepciones-com input[type=checkbox]', function () {
        actualizarUiRecepcionesCom();
        programarPreviewAsiento();
    });

    $form.on('submit', function (e) {
        if (contabilizado) {
            return;
        }
        var modo = $('#modo_carga').val();
        if (modo === 'ASIGNA_RECEPCION' && $('#cp-bloque-recepciones-com').length) {
            if ($('.cp-com-check:checked').length === 0) {
                e.preventDefault();
                if ($('#cp-solapa-recepciones-com').length) {
                    mostrarSolapa('#cp-solapa-recepciones-com');
                    marcarTabActivo('cp-boton-recepciones-com');
                }
                alert('Debe seleccionar al menos una recepción COM para asociar a la factura del legajo.');
                return;
            }
        }
        var coherencia = validarCoherenciaConceptosPantalla();
        renderBannerCoherenciaConceptos(coherencia);
        if (!coherencia.valido) {
            e.preventDefault();
            mostrarSolapa('#cp-solapa-conceptos');
            marcarTabActivo('cp-boton-conceptos');
            alert(coherencia.errores.join('\n'));
            return;
        }
        if (contratoImputacionManual()) {
            var faltaCuenta = false;
            $('#tbody-concepto-table tr.item-concepto').each(function () {
                var $row = $(this);
                var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
                var monto = parseMonto($row.find('.monto').val() || '0');
                if (conceptoId <= 0 || monto <= 0) {
                    return;
                }
                var meta = conceptosMeta[conceptoId] || {};
                if (TIPOS_NETO.indexOf(String(meta.tipoconcepto || '')) < 0) {
                    return;
                }
                var cuentaId = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
                if (cuentaId <= 0) {
                    cuentaId = contratoCuentaManualDatos().id;
                }
                if (cuentaId <= 0) {
                    faltaCuenta = true;
                }
            });
            if (faltaCuenta) {
                e.preventDefault();
                mostrarSolapa('#cp-solapa-conceptos');
                marcarTabActivo('cp-boton-conceptos');
                alert('El contrato exige una cuenta contable del neto. Cárguela en el contrato o en el renglón de neto.');
            }
        }
    });

    // Archivos adjuntos
    $('#cp-agrega-renglon-archivo').on('click', function (e) {
        e.preventDefault();
        var tpl = document.getElementById('cp-template-renglon-archivo');
        if (tpl && tpl.content) {
            $('#cp-tbody-tabla-archivo').append(tpl.content.cloneNode(true));
        }
    });

    $(document).on('click', '.cp-eliminararchivo', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $(document).on('click', '.cp-eliminar-archivo', function (e) {
        e.preventDefault();
        $(this).closest('.cp-archivo-item').remove();
    });

    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }

    (function initProveedorArcaComprobante() {
        var $cfg = $('#cp-proveedor-arca-config');
        if (!$cfg.length || typeof window.ArcaPadronValidacionAsync === 'undefined') {
            return;
        }

        window.cpLimpiarAvisoProveedorArca = function () {
            window.ArcaPadronValidacionAsync.limpiarUltimoModal();
        };

        window.cpValidarProveedorArca = function (proveedorId, condicionivaId) {
            var id = parseInt(proveedorId || '0', 10);
            if (id <= 0) {
                window.cpLimpiarAvisoProveedorArca();
                return;
            }
            window.ArcaPadronValidacionAsync.encolar({
                $config: $cfg,
                proveedorId: id,
                condicionivaId: condicionivaId,
                suspenderUi: false,
            });
        };

        var proveedorInicial = parseInt($('#proveedor_id').val() || '0', 10);
        if (proveedorInicial > 0) {
            window.cpValidarProveedorArca(proveedorInicial);
            if (typeof window.cpValidarProveedorArcaApoc === 'function') {
                window.cpValidarProveedorArcaApoc(proveedorInicial);
            }
        }
    })();

    (function initProveedorArcaApocComprobante() {
        var $cfg = $('#cp-proveedor-arca-apoc-config');
        if (!$cfg.length || typeof window.ArcaApocValidacionAsync === 'undefined') {
            return;
        }

        window.cpLimpiarAvisoProveedorArcaApoc = function () {
            window.ArcaApocValidacionAsync.limpiarUltimoModal();
        };

        window.cpValidarProveedorArcaApoc = function (proveedorId) {
            var id = parseInt(proveedorId || '0', 10);
            if (id <= 0) {
                window.cpLimpiarAvisoProveedorArcaApoc();
                return;
            }
            window.ArcaApocValidacionAsync.encolar({
                $config: $cfg,
                proveedorId: id,
                suspenderUi: false,
            });
        };
    })();

    var paramsUrl = new URLSearchParams(window.location.search);
    if (paramsUrl.get('solapa') === 'asiento' && $('#cp-solapa-asiento-contable').length) {
        mostrarSolapa('#cp-solapa-asiento-contable');
        marcarTabActivo('cp-boton-asiento-contable');
    } else if (paramsUrl.get('solapa') === 'oc' && $('#cp-solapa-ordencompra').length) {
        abrirSolapaOc();
    } else if (paramsUrl.get('solapa') === 'com' && $('#cp-solapa-recepciones-com').length) {
        abrirSolapaCom();
    } else if (paramsUrl.get('solapa') === 'archivos' && $('#cp-solapa-archivos').length) {
        mostrarSolapa('#cp-solapa-archivos');
        marcarTabActivo('cp-boton-archivos');
    } else {
        // Cualquier forma de carga: empezar siempre en datos principales.
        mostrarSolapa('#cp-solapa-principal');
        marcarTabActivo('cp-boton-principal');
    }

    formatearInputMontoEn($form);

    (function initCotizacionDiaComprobante() {
        if (contabilizado) {
            return;
        }
        var carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
        var cotizacionManual = false;
        var refrescoXhr = null;
        var $cot = $('#cotizacion');
        var origenInicial = String($cot.data('cotizacion-origen') || '');
        // Si viene de precarga/factura, no pisar el valor al refrescar del día.
        if (origenInicial === 'precarga' || origenInicial === 'factura') {
            cotizacionManual = true;
        }

        function monedaIdForm() {
            return parseInt($('#moneda_id').val() || '1', 10) || 1;
        }

        function fechaComprobanteForm() {
            return (($('#fechacomprobante').val() || '') + '').substring(0, 10);
        }

        function actualizarHintDia(cotDia, cotFactura) {
            var $hint = $('#cp-cotizacion-dia-hint');
            if (!$hint.length) {
                return;
            }
            var mid = monedaIdForm();
            if (mid <= 1) {
                $hint.html('Moneda local: cotización = 1');
                return;
            }
            var diaTxt = (cotDia > 0) ? cotDia.toFixed(4).replace('.', ',') : '—';
            var html = 'Cotización del día (venta): <strong id="cp-cotizacion-dia-valor">' + diaTxt + '</strong>';
            if (cotFactura && cotFactura > 1 && Math.abs(cotFactura - cotDia) > 0.0000005) {
                html += ' · En factura/precarga: <strong id="cp-cotizacion-factura-valor">'
                    + cotFactura.toFixed(4).replace('.', ',') + '</strong> (campo usa la de factura/precarga)';
            }
            $hint.html(html);
        }

        function refrescarCotizacionDia(opciones) {
            opciones = opciones || {};
            var forzarCampo = !!opciones.forzarCampo;
            var mid = monedaIdForm();
            var fecha = fechaComprobanteForm();
            if (mid <= 1) {
                $cot.val(1);
                actualizarHintDia(1, null);
                return;
            }
            if (!fecha) {
                return;
            }
            if (refrescoXhr && refrescoXhr.readyState !== 4) {
                refrescoXhr.abort();
            }
            refrescoXhr = $.getJSON(carpetaBase + '/compras/comprobante-proveedor/api/cotizacion-moneda-fecha', {
                fecha: fecha,
                moneda_id: mid
            }).done(function (res) {
                var cot = parseFloat(res && res.cotizacion);
                if (!(cot > 0)) {
                    return;
                }
                var cotActual = parseFloat($cot.val() || '0') || 0;
                var cotFacturaAttr = parseFloat($cot.attr('data-cotizacion-factura') || '0') || 0;
                actualizarHintDia(cot, cotFacturaAttr > 1 ? cotFacturaAttr : (cotActual > 1 ? cotActual : null));
                // Completar si no hay cotización; no pisar la de factura/precarga.
                if (forzarCampo || (!cotizacionManual && cotActual <= 1)) {
                    $cot.val(cot);
                    $cot.attr('data-cotizacion-origen', 'dia');
                }
            });
        }

        $cot.on('input change', function () {
            cotizacionManual = true;
            $cot.attr('data-cotizacion-origen', 'manual');
        });

        $('#fechacomprobante, #moneda_id').on('change', function () {
            cotizacionManual = false;
            $cot.attr('data-cotizacion-factura', '');
            refrescarCotizacionDia({ forzarCampo: true });
            if (!contabilizado) {
                programarPreviewAsiento();
            }
        });

        // Al abrir: traer cotización del día (hint + campo si falta).
        refrescarCotizacionDia({ forzarCampo: false });
    })();

    if (!contabilizado) {
        marcarAvisosConceptosLocales();
        programarPreviewAsiento();
    }
});
