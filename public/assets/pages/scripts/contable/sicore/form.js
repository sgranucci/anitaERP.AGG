(function ($) {
    'use strict';

    function parseIsoDate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
            return null;
        }
        return new Date(y, m, d);
    }

    function formatIsoDate(date) {
        var y = date.getFullYear();
        var m = date.getMonth() + 1;
        var d = date.getDate();
        return y + '-' + (m < 10 ? '0' : '') + m + '-' + (d < 10 ? '0' : '') + d;
    }

    function finDeMes(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0);
    }

    function mesAnteriorAlActual(date) {
        var hoy = new Date();
        var mesDesde = date.getFullYear() * 12 + date.getMonth();
        var mesActual = hoy.getFullYear() * 12 + hoy.getMonth();
        return mesDesde < mesActual;
    }

    function ajustarHastaPorDesde() {
        var $desde = $('#fecha_desde');
        var $hasta = $('#fecha_hasta');
        if (!$desde.length || !$hasta.length) {
            return;
        }

        var desde = parseIsoDate($desde.val());
        if (!desde || !mesAnteriorAlActual(desde)) {
            return;
        }

        $hasta.val(formatIsoDate(finDeMes(desde)));
    }

    function syncExportControlPeriodo(activo) {
        document.querySelectorAll('a[href*="listar_sicore"], a[href*="exportar_sicore"]').forEach(function (link) {
            try {
                var url = new URL(link.href, window.location.origin);
                if (activo) {
                    url.searchParams.set('control_periodo', '1');
                } else {
                    url.searchParams.delete('control_periodo');
                }
                link.href = url.pathname + url.search + url.hash;
            } catch (e) {
                // ignore malformed href
            }
        });
    }

    function setControlPeriodoVisible(activo) {
        document.querySelectorAll('.sicore-fila-control-periodo').forEach(function (tr) {
            tr.classList.toggle('d-none', !activo);
        });
        document.querySelectorAll('.sicore-ayuda-control-periodo').forEach(function (el) {
            el.classList.toggle('d-none', !activo);
        });

        var btn = document.getElementById('sicore-btn-control-periodo');
        if (btn) {
            btn.classList.toggle('btn-warning', activo);
            btn.classList.toggle('btn-outline-warning', !activo);
            btn.setAttribute('aria-pressed', activo ? 'true' : 'false');
        }

        var chk = document.getElementById('control_periodo');
        if (chk) {
            chk.checked = !!activo;
        }

        syncExportControlPeriodo(activo);
    }

    $(function () {
        $('#fecha_desde').on('change', ajustarHastaPorDesde);

        function syncCheckboxControlPeriodoConConciliar() {
            var conciliar = document.getElementById('conciliar_contable');
            var control = document.getElementById('control_periodo');
            if (!conciliar || !control) {
                return;
            }
            control.disabled = !conciliar.checked;
            if (!conciliar.checked) {
                control.checked = false;
            }
        }

        syncCheckboxControlPeriodoConConciliar();
        $('#conciliar_contable').on('change', syncCheckboxControlPeriodoConConciliar);

        $(document).on('click', '#sicore-btn-control-periodo', function (e) {
            e.preventDefault();
            var pressed = this.getAttribute('aria-pressed') === 'true';
            setControlPeriodoVisible(!pressed);
        });

        // Si el checkbox del filtro cambia y ya hay grilla, muestra/oculta el renglón.
        $('#control_periodo').on('change', function () {
            if (document.querySelector('.sicore-fila-control-periodo')) {
                setControlPeriodoVisible(this.checked);
            }
        });

        var overlay = document.getElementById('sicore-liquidacion-overlay');
        var hideTimer = null;

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

        function mostrarOverlay() {
            if (!overlay) {
                return;
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }

        function parseRangosModal($modal, tipo) {
            var raw = $modal.attr(tipo === 'dj' ? 'data-rangos-dj' : 'data-rangos-1q') || '{}';
            try {
                return JSON.parse(raw);
            } catch (e) {
                return {};
            }
        }

        function aplicarRangosTipo(tipo) {
            var $modal = $('#modal-sicore-liquidacion');
            var rangos = parseRangosModal($modal, tipo);
            if (rangos.compras_desde) {
                $('#compras_fecha_desde').val(rangos.compras_desde);
            }
            if (rangos.compras_hasta) {
                $('#compras_fecha_hasta').val(rangos.compras_hasta);
            }
            if (rangos.sueldos_desde) {
                $('#sueldos_fecha_desde').val(rangos.sueldos_desde);
            }
            if (rangos.sueldos_hasta) {
                $('#sueldos_fecha_hasta').val(rangos.sueldos_hasta);
            }
        }

        function setSafHabilitado(esDj) {
            $('#form-sicore-liquidacion')
                .find('.js-sicore-saf, .js-sicore-ing-fecha, .js-sicore-ing-imp')
                .prop('disabled', !esDj);
            $('.js-sicore-saf-badge')
                .toggleClass('badge-secondary', !esDj)
                .toggleClass('badge-warning', esDj)
                .text(esDj ? 'DJ — editable' : 'solo DJ');
        }

        function onTipoLiquidacionChange() {
            var tipo = $('input[name="tipo_liquidacion"]:checked').val() || '1q';
            var esDj = tipo === 'dj';
            aplicarRangosTipo(tipo);
            setSafHabilitado(esDj);
        }

        $(document).on('click', '.js-sicore-liquidacion-abrir', function (e) {
            e.preventDefault();
            onTipoLiquidacionChange();
            $('#modal-sicore-liquidacion').modal('show');
        });

        $(document).on('change', '.js-sicore-tipo-liq', onTipoLiquidacionChange);

        // Descarga en iframe oculto: la pantalla original no navega.
        // Overlay informativo; se oculta al load del iframe, al foco o por timeout de seguridad.
        $('#form-sicore-liquidacion').on('submit', function () {
            var form = this;
            var tipo = $('input[name="tipo_liquidacion"]:checked').val() || '1q';
            if (tipo === 'dj') {
                var faltaFecha = false;
                $(form).find('.js-sicore-ing-imp').each(function () {
                    var imp = parseFloat(String(this.value || '0').replace(',', '.')) || 0;
                    if (imp > 0.0005) {
                        var $fecha = $(this).closest('tr').find('.js-sicore-ing-fecha');
                        if (!$fecha.val()) {
                            faltaFecha = true;
                            $fecha.focus();
                            return false;
                        }
                    }
                });
                if (faltaFecha) {
                    window.alert('Si hay ingreso a cuenta, la fecha es obligatoria.');
                    return false;
                }
            }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            // Campos disabled no viajan: habilitar SAF en 1Q con 0 para no romper parseo.
            $(form).find('.js-sicore-saf, .js-sicore-ing-fecha, .js-sicore-ing-imp').prop('disabled', false);

            $('#modal-sicore-liquidacion').modal('hide');
            mostrarOverlay();

            var $iframe = $('#sicore-liq-dl-frame');
            $iframe.off('load.sicoreLiq').on('load.sicoreLiq', function () {
                ocultarOverlay();
            });

            if (hideTimer) {
                clearTimeout(hideTimer);
            }
            hideTimer = setTimeout(ocultarOverlay, 180000);

            var onFocus = function () {
                setTimeout(ocultarOverlay, 400);
                window.removeEventListener('focus', onFocus);
            };
            window.addEventListener('focus', onFocus);

            return true;
        });

        window.addEventListener('pageshow', ocultarOverlay);

        // ——— Solicitud de pago desde liquidación ———
        var overlaySp = document.getElementById('sicore-solicitudpago-overlay');

        function mostrarOverlaySp() {
            if (!overlaySp) {
                return;
            }
            overlaySp.classList.remove('d-none');
            overlaySp.style.display = 'flex';
            overlaySp.setAttribute('aria-hidden', 'false');
        }

        function ocultarOverlaySp() {
            if (!overlaySp) {
                return;
            }
            overlaySp.classList.add('d-none');
            overlaySp.style.display = '';
            overlaySp.setAttribute('aria-hidden', 'true');
        }

        function fmtNumSp(n) {
            var x = Number(n) || 0;
            return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function pintarAsientoSp(asiento) {
            var $tb = $('#sicore-sp-asiento').empty();
            (asiento || []).forEach(function (linea) {
                $tb.append(
                    $('<tr>')
                        .append($('<td>').text(linea.cuenta || ''))
                        .append($('<td>').text(linea.centrocosto || ''))
                        .append($('<td class="text-center">').text(linea.dh || ''))
                        .append($('<td class="text-right">').text(linea.debe != null ? fmtNumSp(linea.debe) : ''))
                        .append($('<td class="text-right">').text(linea.haber != null ? fmtNumSp(linea.haber) : ''))
                );
            });
        }

        function armarHiddenFiltrosSp(filtros) {
            var $box = $('#sicore-sp-filtros-hidden').empty();
            function add(name, value) {
                if (value === undefined || value === null) {
                    return;
                }
                $box.append($('<input>', { type: 'hidden', name: name, value: value }));
            }
            Object.keys(filtros || {}).forEach(function (key) {
                var val = filtros[key];
                if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
                    Object.keys(val).forEach(function (sub) {
                        add(key + '[' + sub + ']', val[sub]);
                    });
                } else if (Array.isArray(val)) {
                    val.forEach(function (item, idx) {
                        add(key + '[' + idx + ']', item);
                    });
                } else {
                    add(key, val);
                }
            });
        }

        function paramsDesdeFormLiquidacion() {
            var $form = $('#form-sicore-liquidacion');
            var params = {};
            $form.serializeArray().forEach(function (item) {
                if (item.name.slice(-2) === '[]') {
                    var base = item.name.slice(0, -2);
                    if (!Array.isArray(params[base])) {
                        params[base] = [];
                    }
                    params[base].push(item.value);
                    return;
                }
                var m = item.name.match(/^([^\[]+)\[([^\]]+)\]$/);
                if (m) {
                    if (!params[m[1]] || typeof params[m[1]] !== 'object') {
                        params[m[1]] = {};
                    }
                    params[m[1]][m[2]] = item.value;
                    return;
                }
                params[item.name] = item.value;
            });
            // Campos disabled (SAF en 1Q) no viajan: forzar 0.
            if (!params.saf) {
                params.saf = { '217': '0', '767': '0', '787': '0' };
            }
            return params;
        }

        function validarFormLiquidacionParaSp() {
            var $form = $('#form-sicore-liquidacion')[0];
            var tipo = $('input[name="tipo_liquidacion"]:checked').val() || '1q';
            if (tipo === 'dj') {
                var faltaFecha = false;
                $('#form-sicore-liquidacion').find('.js-sicore-ing-imp').each(function () {
                    var imp = parseFloat(String(this.value || '0').replace(',', '.')) || 0;
                    if (imp > 0.0005) {
                        var $fecha = $(this).closest('tr').find('.js-sicore-ing-fecha');
                        if (!$fecha.val()) {
                            faltaFecha = true;
                            $fecha.focus();
                            return false;
                        }
                    }
                });
                if (faltaFecha) {
                    window.alert('Si hay ingreso a cuenta, la fecha es obligatoria.');
                    return false;
                }
            }
            if (typeof $form.checkValidity === 'function' && !$form.checkValidity()) {
                $form.reportValidity();
                return false;
            }
            return true;
        }

        function actualizarBotonConfirmarSp() {
            var ok = !!$('#sicore-sp-vencimiento').val();
            if (!$('#sicore-sp-aviso-duplicado').hasClass('d-none') && !$('#sicore-sp-generar-igual').is(':checked')) {
                ok = false;
            }
            $('#sicore-sp-btn-confirmar').prop('disabled', !ok);
        }

        function abrirModalSolicitudSp() {
            var cfg = window.SICORE || {};
            if (!cfg.urlPreviewSolicitud || !cfg.puedeCrearSolicitud) {
                return;
            }
            if (!validarFormLiquidacionParaSp()) {
                return;
            }

            // Habilitar SAF disabled para que serialice (1Q con 0).
            $('#form-sicore-liquidacion')
                .find('.js-sicore-saf, .js-sicore-ing-fecha, .js-sicore-ing-imp')
                .prop('disabled', false);

            var params = paramsDesdeFormLiquidacion();
            var tipo = params.tipo_liquidacion || '1q';
            setSafHabilitado(tipo === 'dj');

            var $modal = $('#modal-sicore-solicitudpago');
            $('#sicore-sp-loading').removeClass('d-none');
            $('#sicore-sp-contenido, #sicore-sp-error').addClass('d-none');
            $('#sicore-sp-btn-confirmar').prop('disabled', true);
            $('#sicore-sp-generar-igual').prop('checked', false);
            $('#sicore-sp-vencimiento').val('');
            $('#sicore-sp-observacion').val('');
            $('#sicore-sp-adjuntos').val('');
            $('#modal-sicore-liquidacion').modal('hide');
            $modal.modal('show');

            $.getJSON(cfg.urlPreviewSolicitud, params)
                .done(function (data) {
                    $('#sicore-sp-loading').addClass('d-none');
                    if (data.error || !data.puede_generar) {
                        $('#sicore-sp-error').removeClass('d-none').text(data.error || 'No se puede generar.');
                        return;
                    }
                    $('#modal-sicore-solicitudpago-titulo').text(data.titulo || 'Generar solicitud de pago SICORE');
                    $('#sicore-sp-empresa').val(data.empresa_label || '');
                    $('#sicore-sp-fecha').val(data.fecha || '');
                    $('#sicore-sp-proveedor').val(data.proveedor_label || '');
                    $('#sicore-sp-sector').val(data.sector_label || '');
                    $('#sicore-sp-concepto').val(data.concepto_label || '');
                    $('#sicore-sp-formapago').val(data.formapago_label || '');
                    $('#sicore-sp-moneda').val(data.moneda_label || '');
                    $('#sicore-sp-monto').val(data.monto_fmt || '');
                    $('#sicore-sp-detalle').val(data.detalle || '');
                    $('#sicore-sp-pdf-nombre').text(data.nombre_pdf || 'liquidacion_sicore.pdf');
                    pintarAsientoSp(data.asiento || []);
                    armarHiddenFiltrosSp(data.filtros || params);

                    if (data.duplicado) {
                        var msgDup = data.duplicado.mensaje || '';
                        if (data.duplicado.url) {
                            msgDup = msgDup.replace(
                                '#' + data.duplicado.codigo,
                                '<a href="' + data.duplicado.url + '" target="_blank" rel="noopener">#' + data.duplicado.codigo + '</a>'
                            );
                        }
                        $('#sicore-sp-aviso-duplicado-msg').html('⚠ ' + msgDup);
                        $('#sicore-sp-aviso-duplicado').removeClass('d-none');
                    } else {
                        $('#sicore-sp-aviso-duplicado').addClass('d-none');
                    }

                    $('#sicore-sp-contenido').removeClass('d-none');
                    actualizarBotonConfirmarSp();
                    setTimeout(function () {
                        $('#sicore-sp-vencimiento').trigger('focus');
                    }, 300);
                })
                .fail(function (xhr) {
                    $('#sicore-sp-loading').addClass('d-none');
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cargar la vista previa.';
                    $('#sicore-sp-error').removeClass('d-none').text(msg);
                });
        }

        $(document).on('click', '.js-sicore-solicitudpago-abrir', function (e) {
            e.preventDefault();
            abrirModalSolicitudSp();
        });

        $('#sicore-sp-vencimiento, #sicore-sp-generar-igual').on('change input', actualizarBotonConfirmarSp);

        $('#form-sicore-solicitudpago').on('submit', function () {
            actualizarBotonConfirmarSp();
            if ($('#sicore-sp-btn-confirmar').prop('disabled')) {
                return false;
            }
            mostrarOverlaySp();
            $('#sicore-sp-btn-confirmar').prop('disabled', true);
            return true;
        });

        window.addEventListener('pageshow', ocultarOverlaySp);
    });
})(jQuery);
