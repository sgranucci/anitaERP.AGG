(function ($) {
    'use strict';

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function syncPeriodoFromSelects() {
        var $periodo = $('#periodo');
        var mes = String($('#periodo_mes_num').val() || '');
        var anio = String($('#periodo_anio').val() || '');
        if (!$periodo.length || !/^\d{2}$/.test(mes) || !/^\d{4}$/.test(anio)) {
            return;
        }
        $periodo.val(anio + mes);
    }

    function empresaIdActual() {
        return parseInt($('#empresa_id').val(), 10) || 0;
    }

    function refrescarLiquidaciones(mantenerSeleccion) {
        syncPeriodoFromSelects();
        var empresaId = empresaIdActual();
        var periodo = String($('#periodo').val() || '');
        var cfg = (window.CANON_MUNICIPAL && window.CANON_MUNICIPAL.mapaConfig) || {};
        var meta = cfg[empresaId] || cfg[String(empresaId)] || null;
        if (meta && meta.periodicidad) {
            $('#periodicidad').val(meta.periodicidad);
        }
        if (!empresaId || !/^\d{6}$/.test(periodo) || !window.CANON_MUNICIPAL || !window.CANON_MUNICIPAL.urlLiquidaciones) {
            return;
        }

        var liquidacion = mantenerSeleccion
            ? (parseInt($('#liquidacion').val(), 10) || window.CANON_MUNICIPAL.liquidacionActual || 1)
            : 1;

        $.getJSON(window.CANON_MUNICIPAL.urlLiquidaciones, {
            empresa_id: empresaId,
            periodo: periodo,
            liquidacion: liquidacion
        }).done(function (data) {
            var $sel = $('#liquidacion');
            $sel.empty();
            var opciones = data.opciones || {};
            Object.keys(opciones).forEach(function (key) {
                $sel.append($('<option>', { value: key, text: opciones[key] }));
            });
            var liq = String(data.liquidacion || liquidacion);
            if ($sel.find('option[value="' + liq + '"]').length) {
                $sel.val(liq);
            } else {
                $sel.prop('selectedIndex', 0);
            }
            if (data.periodicidad) {
                $('#periodicidad').val(data.periodicidad);
            }
            if (data.fecha_desde) {
                $('#fecha_desde').val(data.fecha_desde);
            }
            if (data.fecha_hasta) {
                $('#fecha_hasta').val(data.fecha_hasta);
            }
            window.CANON_MUNICIPAL.liquidacionActual = parseInt($sel.val(), 10) || 1;
        });
    }

    $(function () {
        refrescarLiquidaciones(true);

        $('#empresa_id').on('change', function () {
            refrescarLiquidaciones(false);
        });
        $('#periodo_mes_num, #periodo_anio').on('change', function () {
            refrescarLiquidaciones(true);
        });
        $('#liquidacion').on('change', function () {
            refrescarLiquidaciones(true);
        });

        var overlay = document.getElementById('canon-municipal-procesando-overlay');
        var hideTimer = null;
        var $iframe = $('#canon-municipal-dl-frame');

        function ocultarOverlay() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            if (!overlay) {
                return;
            }
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }

        function mostrarOverlay(titulo, subtitulo) {
            if (!overlay) {
                return;
            }
            if (titulo) {
                var t = document.getElementById('canon-municipal-procesando-titulo');
                if (t) {
                    t.textContent = titulo;
                }
            }
            if (subtitulo) {
                var s = document.getElementById('canon-municipal-procesando-subtitulo');
                if (s) {
                    s.textContent = subtitulo;
                }
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }

        function prepararDescargaOverlay() {
            mostrarOverlay(
                'Generando PDF…',
                'Esta pantalla sigue usable; el archivo se descarga al terminar.'
            );
            $iframe.off('load.canonDl').on('load.canonDl', function () {
                ocultarOverlay();
            });
            if (hideTimer) {
                clearTimeout(hideTimer);
            }
            // Seguridad: attachment a veces no dispara load del iframe.
            hideTimer = setTimeout(ocultarOverlay, 120000);
            var onFocus = function () {
                setTimeout(ocultarOverlay, 400);
                window.removeEventListener('focus', onFocus);
            };
            window.addEventListener('focus', onFocus);
        }

        $('#form-canon-municipal').on('submit', function () {
            var form = this;
            syncPeriodoFromSelects();
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            mostrarOverlay(
                'Consultando canon municipal…',
                'Cruza Flash y Posición financiera. Puede demorar. No cierre la página.'
            );
            return true;
        });

        // Nota municipal y PDF/Excel/CSV del listado: descarga en iframe oculto.
        $(document).on('click', '.js-canon-municipal-dl, a[href*="listar-canon-municipal"]', function (e) {
            var $a = $(this);
            var href = $a.attr('href');
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            prepararDescargaOverlay();
            if ($iframe.length) {
                $iframe.attr('src', href);
            } else {
                window.location.href = href;
            }
        });

        function fmtImporte(n) {
            var v = Number(n || 0);
            return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function armarHiddenFiltros(filtros) {
            var $box = $('#canon-sp-filtros-hidden').empty();
            Object.keys(filtros || {}).forEach(function (key) {
                $box.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: filtros[key]
                }));
            });
        }

        function pintarAsiento(lineas) {
            var $tb = $('#canon-sp-asiento').empty();
            var totD = 0;
            var totH = 0;
            (lineas || []).forEach(function (l) {
                var debe = l.debe != null ? Number(l.debe) : null;
                var haber = l.haber != null ? Number(l.haber) : null;
                if (debe != null) {
                    totD += debe;
                }
                if (haber != null) {
                    totH += haber;
                }
                $tb.append(
                    $('<tr>')
                        .append($('<td>').text(l.cuenta || ''))
                        .append($('<td class="text-left">').text(l.centrocosto || ''))
                        .append($('<td class="text-center">').text(l.dh || ''))
                        .append($('<td class="text-right">').text(debe != null ? fmtImporte(debe) : ''))
                        .append($('<td class="text-right">').text(haber != null ? fmtImporte(haber) : ''))
                );
            });
            $tb.append(
                $('<tr>')
                    .append($('<th>').text('Totales'))
                    .append($('<th>'))
                    .append($('<th>'))
                    .append($('<th class="text-right">').text(fmtImporte(totD)))
                    .append($('<th class="text-right">').text(fmtImporte(totH)))
            );
        }

        function actualizarBotonConfirmar() {
            var $btn = $('#canon-sp-btn-confirmar');
            var faltaVencimiento = !$('#canon-sp-vencimiento').val();
            var faltaGenerarIgual = !$('#canon-sp-aviso-duplicado').hasClass('d-none')
                && !$('#canon-sp-generar-igual').is(':checked');
            var faltaConfirmoDias = !$('#canon-sp-aviso-dias').hasClass('d-none')
                && !$('#canon-sp-confirmo-dias').is(':checked');
            var ok = !faltaVencimiento && !faltaGenerarIgual && !faltaConfirmoDias;
            $btn.prop('disabled', !ok);
            if (ok) {
                $btn.removeAttr('title');
            } else if (faltaVencimiento) {
                $btn.attr('title', 'Completá la fecha de vencimiento para habilitar el botón.');
            } else if (faltaGenerarIgual) {
                $btn.attr('title', 'Marcá «Generar igual» para confirmar pese al aviso de duplicado.');
            } else {
                $btn.attr('title', 'Marcá «Confirmo que es correcto» para continuar.');
            }
        }

        function abrirModalSolicitud() {
            var cfg = window.CANON_MUNICIPAL || {};
            if (!cfg.urlPreviewSolicitud) {
                return;
            }
            syncPeriodoFromSelects();
            var params = $.extend({}, cfg.filtrosQuery || {}, {
                empresa_id: $('#empresa_id').val(),
                periodo: $('#periodo').val(),
                liquidacion: $('#liquidacion').val(),
                fecha_desde: $('#fecha_desde').val(),
                fecha_hasta: $('#fecha_hasta').val(),
                periodicidad: $('#periodicidad').val()
            });

            var $modal = $('#modal-canon-solicitudpago');
            $('#canon-sp-loading').removeClass('d-none');
            $('#canon-sp-contenido, #canon-sp-error').addClass('d-none');
            $('#canon-sp-btn-confirmar').prop('disabled', true);
            $('#canon-sp-generar-igual, #canon-sp-confirmo-dias').prop('checked', false);
            $('#canon-sp-vencimiento').val('');
            $('#canon-sp-observacion').val('');
            $modal.modal('show');

            $.getJSON(cfg.urlPreviewSolicitud, params)
                .done(function (data) {
                    $('#canon-sp-loading').addClass('d-none');
                    if (data.error || !data.puede_generar) {
                        $('#canon-sp-error').removeClass('d-none').text(data.error || 'No se puede generar.');
                        return;
                    }
                    $('#modal-canon-solicitudpago-titulo').text(data.titulo || 'Generar solicitud de pago');
                    $('#canon-sp-empresa').val(data.empresa_label || '');
                    $('#canon-sp-fecha').val(data.fecha || '');
                    $('#canon-sp-proveedor').val(data.proveedor_label || '');
                    $('#canon-sp-sector').val(data.sector_label || '');
                    $('#canon-sp-concepto').val(data.concepto_label || '');
                    $('#canon-sp-formapago').val(data.formapago_label || '');
                    $('#canon-sp-moneda').val(data.moneda_label || '');
                    $('#canon-sp-monto').val(data.monto_fmt || '');
                    $('#canon-sp-detalle').val(data.detalle || '');
                    $('#canon-sp-pdf-nombre').text(data.nombre_pdf || '');
                    $('#canon-sp-pdf-ver').attr('href', data.url_nota || '#');
                    pintarAsiento(data.asiento || []);
                    armarHiddenFiltros(data.filtros || params);

                    if (data.duplicado) {
                        var msgDup = data.duplicado.mensaje || '';
                        if (data.duplicado.url) {
                            msgDup = msgDup.replace(
                                '#' + data.duplicado.codigo,
                                '<a href="' + data.duplicado.url + '" target="_blank" rel="noopener">#' + data.duplicado.codigo + '</a>'
                            );
                        }
                        $('#canon-sp-aviso-duplicado-msg').html('⚠ ' + msgDup);
                        $('#canon-sp-aviso-duplicado').removeClass('d-none');
                    } else {
                        $('#canon-sp-aviso-duplicado').addClass('d-none');
                    }

                    if (data.dias_sin_venta) {
                        $('#canon-sp-aviso-dias-msg').text('⚠ ' + (data.dias_sin_venta.mensaje || ''));
                        $('#canon-sp-aviso-dias').removeClass('d-none');
                    } else {
                        $('#canon-sp-aviso-dias').addClass('d-none');
                    }

                    $('#canon-sp-contenido').removeClass('d-none');
                    actualizarBotonConfirmar();
                    setTimeout(function () {
                        $('#canon-sp-vencimiento').trigger('focus');
                    }, 300);
                })
                .fail(function (xhr) {
                    $('#canon-sp-loading').addClass('d-none');
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cargar la vista previa.';
                    $('#canon-sp-error').removeClass('d-none').text(msg);
                });
        }

        $('#btn-generar-solicitud-canon').on('click', function () {
            abrirModalSolicitud();
        });

        $('#canon-sp-vencimiento, #canon-sp-generar-igual, #canon-sp-confirmo-dias').on('change input', actualizarBotonConfirmar);

        $('#form-canon-solicitudpago').on('submit', function () {
            actualizarBotonConfirmar();
            if ($('#canon-sp-btn-confirmar').prop('disabled')) {
                return false;
            }
            mostrarOverlay(
                'Generando solicitud de pago…',
                'Recalcula el canon, adjunta la nota y envía a aprobación. No cierre la página.'
            );
            $('#canon-sp-btn-confirmar').prop('disabled', true);
            return true;
        });

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
