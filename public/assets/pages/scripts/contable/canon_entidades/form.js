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

    function ultimoDiaMes(anio, mes) {
        return new Date(anio, mes, 0).getDate();
    }

    function actualizarFechas() {
        syncPeriodoFromSelects();
        var periodo = String($('#periodo').val() || '');
        if (!/^\d{6}$/.test(periodo)) {
            return;
        }
        var anio = parseInt(periodo.substring(0, 4), 10);
        var mes = parseInt(periodo.substring(4, 6), 10);
        if (!Number.isFinite(anio) || !Number.isFinite(mes) || mes < 1 || mes > 12) {
            return;
        }
        var ultimo = ultimoDiaMes(anio, mes);
        $('#fecha_desde').val(anio + '-' + pad2(mes) + '-01');
        $('#fecha_hasta').val(anio + '-' + pad2(mes) + '-' + pad2(ultimo));
    }

    $(function () {
        actualizarFechas();
        $('#periodo_mes_num, #periodo_anio').on('change', actualizarFechas);

        var overlay = document.getElementById('canon-entidades-procesando-overlay');

        function ocultarOverlay() {
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

        $('#form-canon-entidades').on('submit', function () {
            var form = this;
            syncPeriodoFromSelects();
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return true;
            }
            mostrarOverlay();
            return true;
        });

        /* ---- Generar solicitudes de pago (confirmación) ---- */
        var $btnSp = $('#btn-generar-solicitud-entidades');
        var $modalSp = $('#modal-canon-ent-solicitudpago');
        var $formSp = $('#form-canon-ent-solicitudpago');

        function esc(s) {
            return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
        }

        function ocultarTodosAvisos() {
            $('#canon-ent-sp-error, #canon-ent-sp-aviso-duplicado, #canon-ent-sp-aviso-dias, #canon-ent-sp-aviso-bingo').addClass('d-none');
            $('#canon-ent-sp-generar-igual, #canon-ent-sp-confirmo-dias').prop('checked', false);
        }

        function resetModalSp() {
            ocultarTodosAvisos();
            $('#canon-ent-sp-loading, #canon-ent-sp-contenido').addClass('d-none');
            $('#canon-ent-sp-vencimiento, #canon-ent-sp-observacion').val('');
            $('#canon-ent-sp-bloques, #canon-ent-sp-filtros-hidden').empty();
            $('#canon-ent-sp-btn-confirmar').prop('disabled', true);
        }

        function mostrarErrorSp(msg) {
            $('#canon-ent-sp-loading, #canon-ent-sp-contenido').addClass('d-none');
            $('#canon-ent-sp-error').text(msg || 'No se pudo armar la vista previa.').removeClass('d-none');
        }

        function actualizarBotonSp() {
            var ok = !!$('#canon-ent-sp-vencimiento').val();
            if (!$('#canon-ent-sp-aviso-duplicado').hasClass('d-none') && !$('#canon-ent-sp-generar-igual').is(':checked')) {
                ok = false;
            }
            if (!$('#canon-ent-sp-aviso-dias').hasClass('d-none') && !$('#canon-ent-sp-confirmo-dias').is(':checked')) {
                ok = false;
            }
            $('#canon-ent-sp-btn-confirmar').prop('disabled', !ok);
        }

        function bloqueSolicitud(sol, numero) {
            var titulo = sol.tipo === 'maquinas' ? 'Máquinas' : 'Bingo';
            var h = '<div class="card card-outline card-secondary mb-3"><div class="card-header py-2">'
                + '<strong>Solicitud ' + numero + ' — ' + esc(titulo) + '</strong></div>'
                + '<div class="card-body py-2">'
                + '<div class="row mb-2">'
                + '<div class="col-md-5"><small class="text-muted">Concepto</small><div>' + esc(sol.concepto_label) + '</div></div>'
                + '<div class="col-md-3"><small class="text-muted">Monto</small><div class="font-weight-bold">$ ' + esc(sol.monto_fmt) + '</div></div>'
                + '<div class="col-md-4"><small class="text-muted">Detalle</small><div>' + esc(sol.detalle) + '</div></div>'
                + '</div>'
                + '<table class="table table-sm table-bordered mb-0">'
                + '<thead><tr style="background:#85C1E9;color:#17202A;"><th style="width:70px">Lado</th><th>Cuenta</th><th>Centro de costo</th><th class="text-right" style="width:140px">Importe</th></tr></thead><tbody>';
            (sol.asiento || []).forEach(function (a) {
                h += '<tr><td>' + (a.dh === 'D' ? 'Debe' : 'Haber') + '</td>'
                    + '<td>' + esc(a.cuenta) + '</td>'
                    + '<td>' + esc(a.centrocosto) + '</td>'
                    + '<td class="text-right">' + esc(sol.monto_fmt) + '</td></tr>';
            });
            h += '</tbody></table></div></div>';
            return h;
        }

        function llenarPreviewSp(d) {
            $('#canon-ent-sp-empresa').val(d.empresa_label || '');
            $('#canon-ent-sp-fecha').val(d.fecha || '');
            $('#canon-ent-sp-proveedor').val(d.proveedor_label || '');
            $('#canon-ent-sp-formapago').val(d.formapago_label || '');
            $('#canon-ent-sp-sector').val(d.sector_label || '');
            $('#canon-ent-sp-moneda').val(d.moneda_label || '');
            $('#canon-ent-sp-pdf-nombre').text(d.nombre_pdf || '');
            $('#canon-ent-sp-pdf-ver').attr('href', d.url_pdf || '#');
            $('#modal-canon-ent-solicitudpago-titulo').text(d.titulo || 'Generar solicitudes de pago');

            var bloques = '';
            (d.solicitudes || []).forEach(function (sol, i) {
                bloques += bloqueSolicitud(sol, i + 1);
            });
            $('#canon-ent-sp-bloques').html(bloques);

            var duplicados = (d.solicitudes || []).filter(function (s) { return !!s.duplicado; });
            if (duplicados.length) {
                var msgs = duplicados.map(function (s) { return esc(s.duplicado.mensaje); }).join('<br>');
                $('#canon-ent-sp-aviso-duplicado-msg').html(msgs);
                $('#canon-ent-sp-aviso-duplicado').removeClass('d-none');
            }

            if (d.dias_excluidos && d.dias_excluidos.mensaje) {
                $('#canon-ent-sp-aviso-dias-msg').text(d.dias_excluidos.mensaje);
                $('#canon-ent-sp-aviso-dias').removeClass('d-none');
            }

            if (d.aviso_bingo_cero) {
                $('#canon-ent-sp-aviso-bingo').text(d.aviso_bingo_cero).removeClass('d-none');
            }

            $('#canon-ent-sp-loading').addClass('d-none');
            $('#canon-ent-sp-contenido').removeClass('d-none');
            actualizarBotonSp();
        }

        function copiarFiltrosAlFormulario() {
            var $cont = $('#canon-ent-sp-filtros-hidden').empty();
            $('#form-canon-entidades').serializeArray().forEach(function (f) {
                $cont.append($('<input>', { type: 'hidden', name: f.name, value: f.value }));
            });
        }

        if ($btnSp.length && $modalSp.length) {
            $btnSp.on('click', function () {
                if ($btnSp.prop('disabled')) {
                    return;
                }
                syncPeriodoFromSelects();
                resetModalSp();
                $('#canon-ent-sp-loading').removeClass('d-none');
                $modalSp.modal('show');

                $.ajax({
                    url: $btnSp.attr('data-preview-url'),
                    method: 'GET',
                    data: $('#form-canon-entidades').serialize(),
                    dataType: 'json'
                }).done(function (d) {
                    if (!d || !d.puede_generar) {
                        mostrarErrorSp(d && d.error);
                        return;
                    }
                    copiarFiltrosAlFormulario();
                    llenarPreviewSp(d);
                }).fail(function (xhr) {
                    mostrarErrorSp(xhr && xhr.responseJSON && xhr.responseJSON.error);
                });
            });

            $('#canon-ent-sp-vencimiento').on('input change', actualizarBotonSp);
            $('#canon-ent-sp-generar-igual, #canon-ent-sp-confirmo-dias').on('change', actualizarBotonSp);

            $formSp.on('submit', function () {
                var form = this;
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return true;
                }
                $('#canon-ent-sp-btn-confirmar').prop('disabled', true);
                $modalSp.modal('hide');
                mostrarOverlay();
                return true;
            });
        }

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
