(function ($) {
    'use strict';

    var OVERLAY_ID = 'flash-calculo-aviso';
    var TITULO_ID = 'flash-calculo-aviso-titulo';
    var SUBTITULO_ID = 'flash-calculo-aviso-subtitulo';
    var TITULO_DEFAULT = 'Calculando flash…';
    var SUBTITULO_DEFAULT = 'Consultando ERP, Wigos y Anita. Por favor espere. No cierre ni recargue la página.';

    var CAMPOS_DECIMAL = [
        'ayb', 'estac', 'vending', 'bingo_total_venta', 'bingo_resultado',
        'slot_coin_in', 'slot_d', 'slot_r', 'soft_count', 'hard_count', 'win_ol_slot',
        'rul_coin_in', 'rul_d', 'rul_r', 'soft_rul', 'hard_rul', 'win_ol_rul',
        'show'
    ];

    var CAMPOS_ENTERO = [
        'cant_vehic', 'bingo_cant_carton', 'cant_slots', 'cant_rul'
    ];

    var SELECTOR_DECIMAL = CAMPOS_DECIMAL.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_ENTERO = CAMPOS_ENTERO.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_TODOS = [SELECTOR_DECIMAL, SELECTOR_ENTERO].filter(Boolean).join(', ');

    var ultimoDesgloseWigos = null;

    var LABELS_COMPONENTE = {
        bill_slots: 'Drop efectivo billetes slots (BRUTO)',
        bill_rul: 'Drop efectivo billetes ruletas (BRUTO)',
        bill_poker: 'Bill poker (BRUTO)',
        ventas_caja: 'Ventas caja (tickets, BRUTO)',
        ventas_slots: 'Ventas slots (tickets, BRUTO)',
        ventas_ruletas: 'Ventas ruletas (tickets, BRUTO)',
        pagos_caja: 'Pagos caja (tickets)',
        pagos_slots: 'Pagos slots (tickets)',
        pagos_ruletas: 'Pagos ruletas (tickets)',
        monto_qr: 'Monto QR (bruto)',
        monto_neto_qr: 'Monto neto QR',
        impuesto_qr: 'Impuesto QR',
        pagos_manuales: 'Pagos manuales',
        tito_slots: 'Tito slots',
        tito_rul: 'Tito ruletas',
        tito_poker: 'Tito poker',
        coin_in_slots: 'Coin in slots',
        coin_in_rul: 'Coin in ruletas',
        coin_in_poker: 'Coin in poker',
        win_slots: 'Win slots (on-line)',
        win_rul: 'Win ruletas (on-line)'
    };

    var LABELS_TOTAL = {
        slot_d: 'Drop slots (slot_d)',
        slot_r: 'Win slots (slot_r)',
        slot_coin_in: 'Coin in slots',
        win_ol_slot: 'Win on-line slots',
        soft_count: 'Soft count / drop efectivo slots (BRUTO)',
        hard_count: 'Hard count slots',
        cant_slots: 'Cant. slots',
        rul_d: 'Drop ruletas (rul_d)',
        rul_r: 'Win ruletas (rul_r)',
        rul_coin_in: 'Coin in ruletas',
        win_ol_rul: 'Win on-line ruletas',
        soft_rul: 'Soft count ruletas (BRUTO)',
        hard_rul: 'Hard count ruletas',
        cant_rul: 'Cant. ruletas'
    };

    function parseDecimal(str) {
        if (str == null || str === '') {
            return 0;
        }
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function parseEntero(str) {
        return Math.round(parseDecimal(str));
    }

    function fmtDecimal(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function fmtEntero(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function formatearDecimalInput(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmtDecimal(parseDecimal(el.value));
    }

    function formatearEnteroInput(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmtEntero(parseEntero(el.value));
    }

    function desformatearDecimalInput(el) {
        if (!el || el.value === '') {
            return;
        }
        var n = parseDecimal(el.value);
        el.value = String(n);
    }

    function desformatearEnteroInput(el) {
        if (!el || el.value === '') {
            return;
        }
        el.value = String(parseEntero(el.value));
    }

    function marcarValoresDesdeFormulario() {
        var $flag = $('#flash_valores_desde_formulario');
        if ($flag.length) {
            $flag.val('1');
        }
    }

    function aplicarDatos(datos) {
        if (!datos) {
            return;
        }
        CAMPOS_DECIMAL.forEach(function (campo) {
            if (typeof datos[campo] !== 'undefined') {
                $('#' + campo).val(fmtDecimal(datos[campo]));
            }
        });
        CAMPOS_ENTERO.forEach(function (campo) {
            if (typeof datos[campo] !== 'undefined') {
                $('#' + campo).val(fmtEntero(datos[campo]));
            }
        });
        marcarValoresDesdeFormulario();
    }

    function normalizarAntesDeEnviar(form) {
        $(form).find(SELECTOR_DECIMAL).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseDecimal(this.value));
        });
        $(form).find(SELECTOR_ENTERO).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseEntero(this.value));
        });
    }

    function initFormatoCampos() {
        $(SELECTOR_DECIMAL).each(function () {
            formatearDecimalInput(this);
        });
        $(SELECTOR_ENTERO).each(function () {
            formatearEnteroInput(this);
        });
    }

    function mostrarAvisoCalculo(visible, titulo, subtitulo) {
        var $aviso = $('#' + OVERLAY_ID);
        if (!$aviso.length) {
            return;
        }

        if (visible) {
            $('#' + TITULO_ID).text(titulo || TITULO_DEFAULT);
            $('#' + SUBTITULO_ID).text(subtitulo || SUBTITULO_DEFAULT);
            $aviso.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
            return;
        }

        $aviso.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    }

    function escaparHtml(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function filaKv(label, valor, esEntero) {
        var v = esEntero ? fmtEntero(valor) : fmtDecimal(valor);
        return '<tr><td>' + escaparHtml(label) + '</td><td class="text-right text-monospace">' + escaparHtml(v) + '</td></tr>';
    }

    function tablaKv(titulo, mapa, labels, enteros) {
        enteros = enteros || {};
        var html = '<h6 class="mt-3 mb-2">' + escaparHtml(titulo) + '</h6>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
        html += '<thead style="background:#85C1E9;color:#17202A;"><tr><th>Concepto</th><th class="text-right">Monto</th></tr></thead><tbody>';
        Object.keys(mapa || {}).forEach(function (clave) {
            html += filaKv(labels[clave] || clave, mapa[clave], !!enteros[clave]);
        });
        html += '</tbody></table></div>';
        return html;
    }

    function renderVerificacion(verificacion) {
        var html = '<h6 class="mt-3 mb-2">Verificación de fórmulas (suma de partes vs total flash)</h6>';
        Object.keys(verificacion || {}).forEach(function (campo) {
            var item = verificacion[campo] || {};
            var ok = Number(item.suma_partes || 0) === Number(item.total_flash || 0);
            html += '<div class="border rounded p-2 mb-2 ' + (ok ? 'border-success' : 'border-danger') + '">';
            html += '<div><strong>' + escaparHtml(LABELS_TOTAL[campo] || campo) + '</strong>';
            html += ' <span class="badge badge-' + (ok ? 'success' : 'danger') + '">' + (ok ? 'OK' : 'Dif.') + '</span></div>';
            html += '<div class="small text-muted mb-1">' + escaparHtml(item.formula || '') + '</div>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-1">';
            html += '<tbody>';
            Object.keys(item.partes || {}).forEach(function (p) {
                html += filaKv(LABELS_COMPONENTE[p] || p, item.partes[p], false);
            });
            html += filaKv('Suma partes', item.suma_partes, false);
            html += filaKv('Total flash', item.total_flash, false);
            html += '</tbody></table></div></div>';
        });
        return html;
    }

    function renderSala(sala) {
        var html = '<div class="border rounded p-2 mb-3">';
        html += '<h6 class="mb-2">Sala ' + escaparHtml(sala.sala)
            + ' — slots: ' + escaparHtml(fmtEntero(sala.cant_slots))
            + ' / ruletas: ' + escaparHtml(fmtEntero(sala.cant_rul)) + '</h6>';
        html += tablaKv('Totales sala', sala.totales_sala || {}, LABELS_TOTAL, { cant_slots: true, cant_rul: true });
        html += '<h6 class="mt-3 mb-2">Por turno (valores aplicados a la fórmula)</h6>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead style="background:#85C1E9;color:#17202A;"><tr>'
            + '<th>Turno</th><th class="text-right">Bill slots</th><th class="text-right">Ventas slots</th>'
            + '<th class="text-right">Ventas caja</th><th class="text-right">Neto QR</th>'
            + '<th class="text-right">Pagos man.</th><th class="text-right">Δ slot_d</th>'
            + '<th class="text-right">Δ slot_r</th><th class="text-right">Bill rul</th>'
            + '<th class="text-right">Ventas rul</th><th class="text-right">Δ rul_d</th>'
            + '<th class="text-right">Δ rul_r</th></tr></thead><tbody>';
        (sala.turnos || []).forEach(function (t) {
            html += '<tr>'
                + '<td>' + escaparHtml(t.turno) + (t.aplica_bill_tickets_qr ? ' (bill/tickets/QR)' : ' (solo man./tito)') + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.bill_slots)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.ventas_slots)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.ventas_caja)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.monto_neto_qr)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.pagos_manuales)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.delta_slot_d)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.delta_slot_r)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.bill_rul)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.ventas_ruletas)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.delta_rul_d)) + '</td>'
                + '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(t.delta_rul_r)) + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div>';

        html += '<h6 class="mt-3 mb-2">Raw Wigos por turno (respuesta SP, sin anular T/N)</h6>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead style="background:#85C1E9;color:#17202A;"><tr><th>Clave</th><th class="text-right">M</th>'
            + '<th class="text-right">T</th><th class="text-right">N</th></tr></thead><tbody>';
        var clavesRaw = [
            'bill_slots', 'bill_rul', 'bill_poker', 'ventas_caja', 'ventas_slots', 'ventas_ruletas',
            'pagos_caja', 'pagos_slots', 'pagos_ruletas', 'monto_qr', 'monto_neto_qr', 'impuesto_qr',
            'pagos_manuales', 'tito_slots', 'tito_rul', 'tito_poker',
            'coin_in_slots', 'coin_in_rul', 'coin_in_poker', 'win_slots', 'win_rul',
            'units_slots', 'units_rul', 'units_poker'
        ];
        var porTurno = {};
        (sala.turnos || []).forEach(function (t) {
            porTurno[t.turno] = t.raw_wigos || {};
        });
        clavesRaw.forEach(function (clave) {
            var esEntero = clave.indexOf('units_') === 0;
            html += '<tr><td>' + escaparHtml(LABELS_COMPONENTE[clave] || clave) + '</td>';
            ['M', 'T', 'N'].forEach(function (turno) {
                var val = (porTurno[turno] || {})[clave] || 0;
                html += '<td class="text-right text-monospace">'
                    + escaparHtml(esEntero ? fmtEntero(val) : fmtDecimal(val)) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div></div>';
        return html;
    }

    function renderOrigenComponentes(origen) {
        if (!origen || !origen.length) {
            return '';
        }
        var html = '<h6 class="mt-3 mb-2">Origen de componentes Wigos (cómo se obtienen)</h6>';
        html += '<p class="small text-muted mb-2">Detalle de SP, parámetros, filtro y si el monto es bruto o neto. '
            + 'Usar esta sección para conciliar Drop efectivo y Ventas tickets.</p>';
        (origen || []).forEach(function (item) {
            var base = String(item.base || '').toUpperCase();
            var badge = base === 'NETO' ? 'success' : (base === 'BRUTO' ? 'warning' : 'secondary');
            html += '<div class="border rounded p-2 mb-2">';
            html += '<div class="d-flex justify-content-between align-items-start flex-wrap">';
            html += '<div><strong>' + escaparHtml(item.etiqueta || item.clave || '') + '</strong>';
            html += ' <span class="badge badge-' + badge + '">' + escaparHtml(base || '?') + '</span></div>';
            html += '<div class="text-right text-monospace font-weight-bold">'
                + escaparHtml(fmtDecimal(item.valor)) + '</div></div>';
            html += '<div class="table-responsive mt-2 mb-0"><table class="table table-sm table-bordered mb-0">';
            html += '<tbody>';
            html += filaKvTexto('SP', item.sp);
            html += filaKvTexto('Parámetros', item.params);
            html += filaKvTexto('Filtro', item.filtro);
            html += filaKvTexto('Campo monto', item.campo_monto);
            html += filaKvTexto('Nota', item.nota);
            html += '</tbody></table></div></div>';
        });
        return html;
    }

    function filaKvTexto(label, valor) {
        return '<tr><td style="width:8rem;">' + escaparHtml(label) + '</td><td class="small">'
            + escaparHtml(valor == null ? '' : valor) + '</td></tr>';
    }

    function renderDesglose(desglose) {
        if (!desglose) {
            return '<p class="text-muted mb-0">Sin datos de desglose.</p>';
        }
        var html = '';
        html += '<p class="mb-2"><strong>Empresa</strong> ' + escaparHtml(desglose.empresa_id)
            + ' · <strong>Fecha</strong> ' + escaparHtml(desglose.fecha) + '</p>';
        if (desglose.formulas && desglose.formulas.nota) {
            html += '<div class="alert alert-info py-2">' + escaparHtml(desglose.formulas.nota) + '</div>';
        }
        html += '<h6 class="mb-2">Fórmulas</h6><ul class="small mb-3">';
        Object.keys(desglose.formulas || {}).forEach(function (k) {
            if (k === 'nota') {
                return;
            }
            html += '<li><code>' + escaparHtml(k) + '</code> = ' + escaparHtml(desglose.formulas[k]) + '</li>';
        });
        html += '</ul>';
        html += renderOrigenComponentes(desglose.origen_componentes || []);
        html += tablaKv('Componentes Wigos aplicados (suma salas / turnos)', desglose.componentes_aplicados || {}, LABELS_COMPONENTE);
        html += tablaKv('Totales gaming flash', desglose.totales_gaming || {}, LABELS_TOTAL, { cant_slots: true, cant_rul: true });
        html += renderVerificacion(desglose.verificacion || {});
        html += '<hr><h5 class="mb-3">Detalle por sala</h5>';
        (desglose.salas || []).forEach(function (sala) {
            html += renderSala(sala);
        });
        return html;
    }

    function mostrarModalDesglose(desglose) {
        ultimoDesgloseWigos = desglose || null;
        $('#flash-desglose-wigos-body').html(renderDesglose(desglose));
        $('#modal-flash-desglose-wigos').modal('show');
    }

    function calcularFlash(opciones) {
        opciones = opciones || {};
        var empresaId = $('#empresa_id').val();
        var fecha = $('#fecha').val();
        if (!empresaId || !fecha) {
            alert('Seleccione empresa y fecha.');
            return $.Deferred().reject().promise();
        }

        var $btnCalcular = $('#btn-flash-calcular').prop('disabled', true);
        var $btnDesglose = $('#btn-flash-desglose-wigos').prop('disabled', true);
        mostrarAvisoCalculo(true, opciones.tituloOverlay, opciones.subtituloOverlay);

        return $.ajax({
            url: window.flashCalcularUrl || '/caja/flash/api/calcular',
            method: 'POST',
            timeout: 300000,
            data: {
                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                empresa_id: empresaId,
                fecha: fecha
            }
        }).done(function (resp) {
            if (resp && resp.ok && resp.datos) {
                if (!opciones.soloDesglose) {
                    aplicarDatos(resp.datos);
                }
                if (resp.datos.desglose_wigos) {
                    ultimoDesgloseWigos = resp.datos.desglose_wigos;
                }
                if (opciones.mostrarDesglose) {
                    mostrarModalDesglose(resp.datos.desglose_wigos || null);
                }
                if (resp.datos.advertencias_wigos && resp.datos.advertencias_wigos.length && !opciones.silenciarAdvertencias) {
                    alert('Flash calculado con advertencias Wigos:\n' + resp.datos.advertencias_wigos.join('\n'));
                }
            } else {
                alert((resp && resp.message) ? resp.message : 'No se recibieron datos de cálculo.');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al calcular flash.';
            alert(msg);
        }).always(function () {
            mostrarAvisoCalculo(false);
            $btnCalcular.prop('disabled', false);
            $btnDesglose.prop('disabled', false);
        });
    }

    $(document).on('focus', SELECTOR_DECIMAL, function () {
        desformatearDecimalInput(this);
        this.select();
    });

    $(document).on('blur', SELECTOR_DECIMAL, function () {
        formatearDecimalInput(this);
    });

    $(document).on('focus', SELECTOR_ENTERO, function () {
        desformatearEnteroInput(this);
        this.select();
    });

    $(document).on('blur', SELECTOR_ENTERO, function () {
        formatearEnteroInput(this);
    });

    $(document).on('change input', SELECTOR_TODOS, function () {
        marcarValoresDesdeFormulario();
    });

    $(document).on('submit', '#form-general', function () {
        normalizarAntesDeEnviar(this);
    });

    $('#btn-flash-calcular').on('click', function () {
        calcularFlash({ mostrarDesglose: false });
    });

    $('#btn-flash-desglose-wigos').on('click', function () {
        if (ultimoDesgloseWigos) {
            var mismaConsulta = String(ultimoDesgloseWigos.empresa_id) === String($('#empresa_id').val())
                && String(ultimoDesgloseWigos.fecha) === String($('#fecha').val());
            if (mismaConsulta) {
                mostrarModalDesglose(ultimoDesgloseWigos);
                return;
            }
        }
        calcularFlash({
            mostrarDesglose: true,
            soloDesglose: false,
            tituloOverlay: 'Consultando desglose Wigos…',
            subtituloOverlay: 'Se calcula el flash y se muestran los componentes. No cierre la página.'
        });
    });

    $('#empresa_id, #fecha').on('change', function () {
        ultimoDesgloseWigos = null;
    });

    $('#btn-flash-desglose-excel').on('click', function () {
        var empresaId = $('#empresa_id').val();
        var fecha = $('#fecha').val();
        if (!empresaId || !fecha) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var base = window.flashDesgloseExcelUrl || '/caja/flash/api/desglose-wigos-excel';
        var url = base
            + (base.indexOf('?') >= 0 ? '&' : '?')
            + 'empresa_id=' + encodeURIComponent(empresaId)
            + '&fecha=' + encodeURIComponent(fecha);
        var $btn = $(this).prop('disabled', true);
        mostrarAvisoCalculo(true, 'Generando Excel…', 'Consultando Wigos y armando el desglose. No cierre la página.');
        // Descarga por navegación: el overlay se limpia al pageshow / timeout de seguridad.
        window.setTimeout(function () {
            mostrarAvisoCalculo(false);
            $btn.prop('disabled', false);
        }, 2500);
        window.location.href = url;
    });

    window.addEventListener('pageshow', function () {
        mostrarAvisoCalculo(false);
        $('#btn-flash-desglose-excel').prop('disabled', false);
    });

    $(function () {
        initFormatoCampos();
    });
})(jQuery);
