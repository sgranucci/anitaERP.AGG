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
        'att', 'pos_online', 'cant_vehic', 'bingo_cant_carton', 'cant_slots', 'cant_rul'
    ];

    var CAMPOS_COTIZACION = ['cotizacion'];

    var SELECTOR_DECIMAL = CAMPOS_DECIMAL.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_ENTERO = CAMPOS_ENTERO.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_COTIZACION = CAMPOS_COTIZACION.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_TODOS = [SELECTOR_DECIMAL, SELECTOR_ENTERO, SELECTOR_COTIZACION].filter(Boolean).join(', ');

    var ultimoDesgloseWigos = null;
    var cacheOrigenTotal = {};

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

    function parseDecimal(str, decimales) {
        if (str == null || str === '') {
            return 0;
        }
        var dec = decimales == null ? 2 : decimales;
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        var n = parseFloat(t);
        if (isNaN(n)) {
            return 0;
        }
        var factor = Math.pow(10, dec);
        return Math.round(n * factor) / factor;
    }

    function parseEntero(str) {
        return Math.round(parseDecimal(str, 0));
    }

    function fmtDecimal(n, decimales) {
        var dec = decimales == null ? 2 : decimales;
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec
        });
    }

    function fmtEntero(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function formatearDecimalInput(el, decimales) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmtDecimal(parseDecimal(el.value, decimales), decimales);
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

    function desformatearDecimalInput(el, decimales) {
        if (!el || el.value === '') {
            return;
        }
        var n = parseDecimal(el.value, decimales);
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
            this.value = String(parseDecimal(this.value, 2));
        });
        $(form).find(SELECTOR_COTIZACION).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseDecimal(this.value, 4));
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
            formatearDecimalInput(this, 2);
        });
        $(SELECTOR_COTIZACION).each(function () {
            formatearDecimalInput(this, 4);
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

    function renderOrigenTotal(origen) {
        if (!origen) {
            return '<p class="text-muted mb-0">Sin datos de origen.</p>';
        }
        var html = '';
        html += '<p class="mb-2"><strong>' + escaparHtml(origen.titulo || origen.campo) + '</strong>';
        html += ' <span class="badge badge-secondary">' + escaparHtml(origen.origen || '') + '</span>';
        html += ' · Empresa ' + escaparHtml(origen.empresa_id) + ' · ' + escaparHtml(origen.fecha) + '</p>';
        html += '<div class="alert alert-info py-2 mb-3">' + escaparHtml(origen.explicacion || '') + '</div>';

        if (origen.aviso) {
            html += '<div class="alert alert-warning py-2 mb-3">' + escaparHtml(origen.aviso) + '</div>';
        }

        html += '<div class="table-responsive mb-3"><table class="table table-sm table-bordered mb-0">';
        html += '<thead style="background:#85C1E9;color:#17202A;"><tr><th>Referencia</th><th class="text-right">Monto</th></tr></thead><tbody>';
        if (origen.valor_pantalla != null) {
            html += '<tr><td>Valor en pantalla (formulario)</td><td class="text-right text-monospace">'
                + escaparHtml(fmtDecimal(origen.valor_pantalla)) + '</td></tr>';
        }
        html += '<tr><td>Valor según fórmula ERP actual (Wigos'
            + (String(origen.origen || '').indexOf('rendicion') >= 0 ? ' − impuestos turno C' : '')
            + ')</td><td class="text-right text-monospace">'
            + escaparHtml(fmtDecimal(origen.total_formula != null ? origen.total_formula : origen.total_flash))
            + '</td></tr>';
        if (origen.diferencia_pantalla != null && Math.abs(Number(origen.diferencia_pantalla)) >= 0.02) {
            html += '<tr class="table-warning"><td>Diferencia (pantalla − fórmula)</td><td class="text-right text-monospace">'
                + escaparHtml(fmtDecimal(origen.diferencia_pantalla)) + '</td></tr>';
        }
        html += '</tbody></table></div>';

        html += '<h6 class="mb-1">Fórmula</h6>';
        html += '<p class="small text-monospace mb-3">' + escaparHtml(origen.formula || '') + '</p>';

        html += '<h6 class="mb-2">Cuenta que arma el total (fórmula ERP)</h6>';
        html += '<div class="table-responsive mb-2"><table class="table table-sm table-bordered mb-0">';
        html += '<thead style="background:#85C1E9;color:#17202A;"><tr><th>Concepto</th><th class="text-right">Monto</th></tr></thead><tbody>';
        (origen.cuenta || []).forEach(function (linea) {
            html += '<tr><td>' + escaparHtml(linea.signo || '') + ' ' + escaparHtml(linea.label || '')
                + '</td><td class="text-right text-monospace">' + escaparHtml(fmtDecimal(linea.valor)) + '</td></tr>';
        });
        html += '<tr class="font-weight-bold"><td>Suma cuenta</td><td class="text-right text-monospace">'
            + escaparHtml(fmtDecimal(origen.suma_cuenta)) + '</td></tr>';
        html += '<tr class="font-weight-bold"><td>Total fórmula ERP</td><td class="text-right text-monospace">'
            + escaparHtml(fmtDecimal(origen.total_formula != null ? origen.total_formula : origen.total_flash))
            + '</td></tr>';
        html += '</tbody></table></div>';
        html += '<p class="mb-3"><span class="badge badge-' + (origen.coincide ? 'success' : 'warning') + '">'
            + (origen.coincide ? 'Cuenta = fórmula ERP' : 'Diferencia entre cuenta y fórmula ERP') + '</span></p>';

        if (origen.impuestos_rendicion && (Number(origen.impuestos_rendicion.total || 0) !== 0
            || origen.impuestos_rendicion.origen)) {
            var imp = origen.impuestos_rendicion;
            html += '<div class="small text-muted mb-3">Impuestos turno C ('
                + escaparHtml(imp.origen || 'ninguno') + '): drop '
                + escaparHtml(fmtDecimal(imp.impuesto_drop)) + ' + venta '
                + escaparHtml(fmtDecimal(imp.impuesto_venta)) + ' = '
                + escaparHtml(fmtDecimal(imp.total));
            if (imp.nro_oper) {
                html += ' · nro_oper ' + escaparHtml(imp.nro_oper);
            }
            html += '</div>';
        }

        (origen.secciones || []).forEach(function (sec) {
            html += '<hr><h6 class="mb-1">' + escaparHtml(sec.titulo || 'Detalle') + '</h6>';
            if (sec.sp) {
                html += '<div class="small text-muted mb-1"><code>' + escaparHtml(sec.sp) + '</code>';
                if (sec.params) {
                    html += ' · ' + escaparHtml(sec.params);
                }
                html += '</div>';
            }
            if (sec.nota) {
                html += '<p class="small text-muted mb-2">' + escaparHtml(sec.nota) + '</p>';
            }
            if (!sec.columnas || !sec.columnas.length || !sec.filas || !sec.filas.length) {
                if (sec.subtotal != null) {
                    html += '<p class="mb-2">Subtotal: <strong class="text-monospace">'
                        + escaparHtml(fmtDecimal(sec.subtotal)) + '</strong></p>';
                }
                return;
            }
            html += '<div class="table-responsive mb-2" style="max-height:22rem;overflow:auto;">';
            html += '<table class="table table-sm table-bordered mb-0"><thead style="background:#85C1E9;color:#17202A;"><tr>';
            (sec.columnas || []).forEach(function (col) {
                html += '<th' + (col.num ? ' class="text-right"' : '') + '>'
                    + escaparHtml(col.label || col.key) + '</th>';
            });
            html += '</tr></thead><tbody>';
            (sec.filas || []).forEach(function (fila) {
                html += '<tr>';
                (sec.columnas || []).forEach(function (col) {
                    var val = fila[col.key];
                    if (col.num) {
                        html += '<td class="text-right text-monospace">' + escaparHtml(fmtDecimal(val)) + '</td>';
                    } else {
                        html += '<td>' + escaparHtml(val == null ? '' : val) + '</td>';
                    }
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '<div class="small mb-2">' + escaparHtml(fmtEntero((sec.filas || []).length)) + ' movimiento(s)';
            if (sec.subtotal != null) {
                html += ' · Subtotal listado: <strong class="text-monospace">'
                    + escaparHtml(fmtDecimal(sec.subtotal)) + '</strong>';
            }
            if (sec.truncado) {
                html += ' · <span class="text-danger">Listado truncado (máx. filas)</span>';
            }
            html += '</div>';
        });

        return html;
    }

    function mostrarModalOrigenTotal(origen) {
        $('#modal-flash-origen-total-titulo').text(origen && origen.titulo
            ? ('Origen — ' + origen.titulo)
            : 'Origen del total');
        $('#flash-origen-total-body').html(renderOrigenTotal(origen));
        $('#modal-flash-origen-total').modal('show');
    }

    function valorCampoPantalla(campo) {
        var $el = $('#' + campo);
        if (!$el.length) {
            return null;
        }
        if ($el.hasClass('flash-campo-entero')) {
            return parseEntero($el.val());
        }
        return parseDecimal($el.val(), 2);
    }

    function consultarOrigenTotal(campo) {
        var empresaId = $('#empresa_id').val();
        var fecha = $('#fecha').val();
        if (!empresaId || !fecha) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var valorPantalla = valorCampoPantalla(campo);
        var cacheKey = empresaId + '|' + fecha + '|' + campo + '|' + String(valorPantalla);
        if (cacheOrigenTotal[cacheKey]) {
            mostrarModalOrigenTotal(cacheOrigenTotal[cacheKey]);
            return;
        }

        var $btns = $('.flash-btn-origen').prop('disabled', true);
        mostrarAvisoCalculo(true, 'Consultando origen…', 'Recalculando el total y listando movimientos Wigos/ERP. No cierre la página.');

        var data = {
            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
            empresa_id: empresaId,
            fecha: fecha,
            campo: campo
        };
        if (valorPantalla != null && !isNaN(valorPantalla)) {
            data.valor_pantalla = valorPantalla;
        }

        $.ajax({
            url: window.flashOrigenTotalUrl || '/caja/flash/api/origen-total',
            method: 'POST',
            timeout: 300000,
            data: data
        }).done(function (resp) {
            if (resp && resp.ok && resp.origen) {
                cacheOrigenTotal[cacheKey] = resp.origen;
                mostrarModalOrigenTotal(resp.origen);
            } else {
                alert((resp && resp.message) ? resp.message : 'No se pudo obtener el origen del total.');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : 'Error al consultar origen del total.';
            alert(msg);
        }).always(function () {
            mostrarAvisoCalculo(false);
            $btns.prop('disabled', false);
        });
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
                cacheOrigenTotal = {};
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
        desformatearDecimalInput(this, 2);
        this.select();
    });

    $(document).on('blur', SELECTOR_DECIMAL, function () {
        formatearDecimalInput(this, 2);
    });

    $(document).on('focus', SELECTOR_COTIZACION, function () {
        desformatearDecimalInput(this, 4);
        this.select();
    });

    $(document).on('blur', SELECTOR_COTIZACION, function () {
        formatearDecimalInput(this, 4);
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
        cacheOrigenTotal = {};
    });

    $(document).on('click', '.flash-btn-origen', function () {
        var campo = $(this).data('campo');
        if (!campo) {
            return;
        }
        consultarOrigenTotal(String(campo));
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
