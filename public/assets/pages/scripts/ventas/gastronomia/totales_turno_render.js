(function () {
    'use strict';

    var CLS_PANEL = 'gastro-totales-panel';

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Resumen: comprobantes emitidos vs cobranzas registradas en esas facturas.
     * @param {object} t totales_turno / totales_dia
     */
    function renderConciliacionHtml(t) {
        if (!t || t.total_ventas === undefined) {
            return '';
        }
        var ok = !!t.conciliacion_ok;
        var diff = Number(t.diferencia_cobranza || 0);
        var estado = ok
            ? 'Cuadra'
            : '$' + fmt(Math.abs(diff)) + (diff > 0 ? ' (sobra)' : ' (falta)');
        var badgeCls = ok ? 'badge-success' : 'badge-warning';
        var ncTotal = Number(t.total_notas_credito || 0);
        var ncCant = Number(t.cantidad_notas_credito || 0);
        var hayNc = ncCant > 0 || Math.abs(ncTotal) >= 0.005;
        var totalFacturasBruto = t.total_facturas != null
            ? Number(t.total_facturas)
            : (Number(t.total_ventas || 0) - ncTotal);
        var cantidadFacturas = t.cantidad_facturas != null
            ? Number(t.cantidad_facturas)
            : Math.max(0, Number(t.cantidad_comprobantes || 0) - ncCant);
        var totalFinal = Number(t.total_ventas || 0);

        var html = '<div class="' + CLS_PANEL + ' gastro-conciliacion border rounded p-3 mb-3 bg-white">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">';
        html += '<span class="font-weight-bold h6 mb-0">Comprobantes y cobranzas</span>';
        html += '<span class="badge ' + badgeCls + ' px-2 py-1">' + estado + '</span>';
        html += '</div>';
        html += '<p class="text-muted mb-2 gastro-totales-leyenda">Facturado bruto − Notas de crédito = <strong>Facturado final</strong>, que debe coincidir con lo cobrado (descontando invitaciones).</p>';

        html += '<div class="row text-center small">';
        html += '<div class="col-6 col-md-3 border-right mb-3 mb-md-0">';
        html += '<span class="text-muted d-block">Facturado <em>(sin NC)</em></span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(totalFacturasBruto) + '</span>';
        html += '<span class="text-muted d-block">' + cantidadFacturas + ' facturas</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-3 border-right mb-3 mb-md-0">';
        html += '<span class="text-muted d-block">Invitaciones $0,01</span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_invitaciones) + '</span>';
        html += '<span class="text-muted d-block">' + (t.cantidad_invitaciones || 0) + ' comp.</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-3 border-right mb-3 mb-md-0" style="color:#922b21;">';
        html += '<span class="text-muted d-block">Notas de crédito</span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(ncTotal) + '</span>';
        html += '<span class="text-muted d-block">' + ncCant + ' comp.</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-3 mb-md-0" style="background:#e8f4fc; border-radius:4px;">';
        html += '<span class="text-muted d-block">Facturado final <em>(con NC restadas)</em></span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(totalFinal) + '</span>';
        html += '<span class="text-muted d-block">debe cuadrar con cobrado</span>';
        html += '</div>';
        html += '</div>';

        html += '<hr class="my-2">';
        html += '<div class="row text-center small">';
        html += '<div class="col-6 col-md-4 border-right">';
        html += '<span class="text-muted d-block">Cobrado neto <em>(incluye TOTEM)</em></span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_cobrado) + '</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-4 border-right">';
        html += '<span class="text-muted d-block">A rendir en caja <em>(sin TOTEM)</em></span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$'
            + fmt(t.total_cobrado_a_rendir != null ? t.total_cobrado_a_rendir : t.total_cobrado) + '</span>';
        html += '</div>';
        html += '<div class="col-12 col-md-4 mt-2 mt-md-0">';
        html += '<span class="text-muted d-block">Esperado sistema <em>(final − invitaciones)</em></span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_ventas_cobrables) + '</span>';
        html += '</div>';
        html += '</div>';

        html += '</div>';

        return html;
    }

    function renderMediosPagoTabla(medios, vacio, conTotalFinal, totalCobradoRef, opcionesConciliar, notasCredito, mozoCtx, invitaciones, opcionesArqueo) {
        var hayMedios = medios && medios.length;
        var nc = notasCredito || null;
        var ncCant = nc ? Number(nc.cantidad || 0) : 0;
        var ncTotal = nc ? Number(nc.total || 0) : 0;
        var hayNc = nc && (ncCant > 0 || Math.abs(ncTotal) >= 0.005);
        var inv = invitaciones || null;
        var invCant = inv ? Number(inv.cantidad || 0) : 0;
        var invTotal = inv ? Number(inv.total || 0) : 0;
        var hayInv = inv && (invCant > 0 || Math.abs(invTotal) >= 0.005);

        if (!hayMedios && !hayNc && !hayInv) {
            return '<p class="text-muted mb-0 pl-2">' + esc(vacio || 'Sin cobranzas en comprobantes.') + '</p>';
        }
        var conciliar = opcionesConciliar && opcionesConciliar.habilitar;
        var ccEfectivoId = opcionesArqueo && opcionesArqueo.habilitar
            ? (parseInt(opcionesArqueo.cuentacaja_efectivo_id, 10) || 0)
            : 0;
        var arqueoSoloLectura = !!(opcionesArqueo && opcionesArqueo.solo_lectura);
        if (opcionesArqueo && opcionesArqueo.modoEdicionArqueo) {
            arqueoSoloLectura = false;
        }
        var arqueoActivo = !!(opcionesArqueo && opcionesArqueo.habilitar
            && (ccEfectivoId > 0 || arqueoSoloLectura || opcionesArqueo.forzar));
        var totalFinal = totalCobradoRef != null ? Number(totalCobradoRef) : 0;
        if (totalCobradoRef == null) {
            (medios || []).forEach(function (p) {
                if (arqueoActivo && (p.excluido_arqueo || p.es_facturacion_totem)) {
                    return;
                }
                totalFinal += Number(p.total || 0);
            });
            if (hayNc) {
                totalFinal += ncTotal;
            }
        }

        var mozoId = mozoCtx && mozoCtx.mozo_id != null ? mozoCtx.mozo_id : '';
        var mozoNombre = mozoCtx && mozoCtx.mozo_nombre ? mozoCtx.mozo_nombre : '';

        var html = '<table class="table table-bordered mb-0 gastro-totales-tabla">';
        html += '<thead class="thead-light"><tr><th>Medio de pago</th>';
        if (arqueoActivo) {
            html += '<th class="text-right" style="width:130px;">Esperado sistema</th>';
            html += '<th class="text-right" style="width:160px;">'
                + (arqueoSoloLectura ? 'Contado cajero' : 'Cobrado / contado')
                + '</th>';
        } else {
            html += '<th class="text-right">Cobrado</th>';
        }
        if (conciliar) {
            html += '<th class="text-center" style="width:110px;">Conciliar</th>';
        }
        html += '</tr></thead><tbody>';
        (medios || []).forEach(function (p) {
            if (arqueoActivo && (p.excluido_arqueo || p.es_facturacion_totem)) {
                return;
            }
            var ccId = parseInt(p.cuentacaja_id, 10) || 0;
            var montoEsperado = Number(p.esperado != null ? p.esperado : p.total || 0);
            var montoContado = p.contado != null ? Number(p.contado) : montoEsperado;
            var esEfectivo = arqueoActivo && ccEfectivoId > 0 && ccId === ccEfectivoId;
            var trClass = esEfectivo ? ' class="gastro-cierre-fila-efectivo"' : '';
            html += '<tr' + trClass + '><td>' + esc(p.nombre || p.codigo || '—');
            if (esEfectivo) {
                html += ' <span class="badge badge-info badge-sm">Efectivo</span>';
            }
            if (!arqueoActivo && (p.excluido_arqueo || p.es_facturacion_totem)) {
                html += ' <span class="badge badge-secondary badge-sm">TOTEM — no entregar</span>';
            }
            html += '</td>';
            if (arqueoActivo) {
                html += '<td class="text-right">';
                var clsEsperado = esEfectivo
                    ? 'gastro-rendicion-esperado-efectivo font-weight-bold'
                    : 'font-weight-bold';
                html += '<span class="' + clsEsperado + '">$' + fmt(montoEsperado) + '</span>';
                html += '</td>';
                html += '<td class="text-right">';
                if (arqueoSoloLectura) {
                    var diffLect = Math.round((montoContado - montoEsperado) * 100) / 100;
                    html += '<span class="font-weight-bold">$' + fmt(montoContado) + '</span>';
                    if (Math.abs(diffLect) > 0.02) {
                        var tipoLect = diffLect > 0 ? 'sobra' : 'falta';
                        html += '<small class="d-block text-warning mt-1">Δ $' + fmt(Math.abs(diffLect))
                            + ' (' + tipoLect + ' vs sistema)</small>';
                    }
                } else {
                    html += '<input type="text" inputmode="decimal" class="form-control form-control-sm text-right js-medio-contado-cierre" ';
                    html += 'value="' + esc(fmt(montoContado)) + '" data-esperado="' + esc(String(montoEsperado)) + '" data-cuentacaja-id="' + ccId + '"/>';
                    html += '<div class="js-hint-diff-medio-cierre"></div>';
                }
                html += '</td>';
            } else {
                html += '<td class="text-right font-weight-bold">$' + fmt(montoEsperado) + '</td>';
            }
            if (conciliar && ccId > 0) {
                html += '<td class="text-center">';
                html += '<button type="button" class="btn btn-xs btn-outline-info js-conciliar-medio" data-cuentacaja-id="' + ccId + '" ';
                html += 'data-medio-nombre="' + esc(p.nombre || p.codigo || '') + '"';
                if (mozoId !== '') {
                    html += ' data-mozo-id="' + esc(mozoId) + '"';
                }
                if (mozoNombre) {
                    html += ' data-mozo-nombre="' + esc(mozoNombre) + '"';
                }
                html += ' title="Ver facturas de este medio' + (mozoNombre ? ' de ' + esc(mozoNombre) : '') + '">';
                html += '<i class="fa fa-search"></i> Facturas</button></td>';
            } else if (conciliar) {
                html += '<td></td>';
            }
            html += '</tr>';
        });
        if (hayNc) {
            html += '<tr style="background:#fdecea;">';
            html += '<td style="color:#922b21; font-weight:bold;">Notas de crédito (' + ncCant + ' comp.)</td>';
            if (arqueoActivo) {
                html += '<td class="text-muted text-right">—</td>';
            }
            html += '<td class="text-right font-weight-bold" style="color:#922b21;">$' + fmt(ncTotal) + '</td>';
            if (conciliar) {
                html += '<td class="text-center">';
                html += '<button type="button" class="btn btn-xs btn-outline-danger js-conciliar-nc"';
                if (mozoId !== '') {
                    html += ' data-mozo-id="' + esc(mozoId) + '"';
                }
                if (mozoNombre) {
                    html += ' data-mozo-nombre="' + esc(mozoNombre) + '"';
                }
                html += ' title="Ver notas de crédito' + (mozoNombre ? ' de ' + esc(mozoNombre) : ' del turno') + '">';
                html += '<i class="fa fa-search"></i> NC</button></td>';
            }
            html += '</tr>';
        }
        if (hayInv) {
            html += '<tr style="background:#fff8e1;">';
            html += '<td style="color:#856404; font-weight:bold;">Invitaciones $0,01 (' + invCant + ' comp.)';
            html += '<br><small class="font-weight-normal text-muted">Referencia fiscal — no integra el total cobrado</small></td>';
            if (arqueoActivo) {
                html += '<td class="text-right font-italic text-muted">—</td>';
            }
            html += '<td class="text-right font-italic text-muted">—<br><small>$' + fmt(invTotal) + ' fact.</small></td>';
            if (conciliar) {
                html += '<td class="text-center">';
                html += '<button type="button" class="btn btn-xs btn-outline-warning js-conciliar-invitaciones"';
                if (mozoId !== '') {
                    html += ' data-mozo-id="' + esc(mozoId) + '"';
                }
                if (mozoNombre) {
                    html += ' data-mozo-nombre="' + esc(mozoNombre) + '"';
                }
                html += ' title="Ver facturas $0,01' + (mozoNombre ? ' de ' + esc(mozoNombre) : ' del turno') + '">';
                html += '<i class="fa fa-search"></i> Facturas</button></td>';
            }
            html += '</tr>';
        }
        html += '</tbody>';
        if (conTotalFinal) {
            html += '<tfoot class="thead-light"><tr>';
            html += '<th class="text-right">' + (arqueoActivo ? 'Total a rendir' : 'Total cobrado neto') + '</th>';
            if (arqueoActivo) {
                html += '<th></th>';
            }
            html += '<th class="text-right gastro-totales-monto font-weight-bold">$' + fmt(totalFinal) + '</th>';
            if (conciliar) {
                html += '<th></th>';
            }
            html += '</tr></tfoot>';
        }
        html += '</table>';
        if (conTotalFinal && hayInv) {
            html += '<p class="small text-muted mb-0 mt-1 pl-1">Las invitaciones ($0,01 sin cobranza) no suman al total cobrado.</p>';
        }
        if (arqueoActivo && conTotalFinal) {
            html += '<p class="small text-muted mb-0 mt-1 pl-1">Compare el <strong>esperado sistema</strong> de cada medio con el monto contado. ';
            html += 'Las diferencias se compensan automáticamente en <strong>sobrante / faltante</strong> del formulario de cierre. ';
            html += 'TOTEM no figura aquí: se informa aparte y <strong>no se entrega en caja</strong>.</p>';
        }
        return html;
    }

    function parseDecimalArqueo(str) {
        if (str == null || str === '') {
            return 0;
        }
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function actualizarHintDiffMedioCierre(inp) {
        if (!inp) {
            return;
        }
        var contenedor = inp.parentElement
            ? inp.parentElement.querySelector('.js-hint-diff-medio-cierre')
            : null;
        if (!contenedor) {
            return;
        }
        var esperado = parseFloat(inp.getAttribute('data-esperado')) || 0;
        var contado = parseDecimalArqueo(inp.value);
        var diff = Math.round((contado - esperado) * 100) / 100;
        if (Math.abs(diff) <= 0.02) {
            contenedor.innerHTML = '';
            return;
        }
        var tipo = diff > 0 ? 'sobra' : 'falta';
        contenedor.innerHTML = '<small class="d-block text-warning mt-1">Δ $' + fmt(Math.abs(diff))
            + ' (' + tipo + ' vs sistema). '
            + 'Se compensa en <strong>sobrante / faltante</strong> abajo.</small>';
    }

    function enlazarArqueoMediosCierre(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            if (inp.getAttribute('data-arqueo-bound') === '1') {
                actualizarHintDiffMedioCierre(inp);
                return;
            }
            inp.setAttribute('data-arqueo-bound', '1');
            inp.addEventListener('input', function () {
                actualizarHintDiffMedioCierre(inp);
            });
            inp.addEventListener('blur', function () {
                inp.value = fmt(parseDecimalArqueo(inp.value));
                actualizarHintDiffMedioCierre(inp);
            });
            actualizarHintDiffMedioCierre(inp);
        });
    }

    function enlazarArqueoEfectivoCierre(root) {
        enlazarArqueoMediosCierre(root);
    }

    function recolectarMediosContadoCierreDesdeRoot(root) {
        var list = [];
        if (!root) {
            return list;
        }
        root.querySelectorAll('.js-medio-contado-cierre').forEach(function (inp) {
            var ccId = parseInt(inp.getAttribute('data-cuentacaja-id'), 10) || 0;
            if (ccId <= 0) {
                return;
            }
            list.push({
                cuentacaja_id: ccId,
                monto: parseDecimalArqueo(inp.value),
            });
        });

        return list;
    }

    function renderFacturacionTotemHtml(totales, opcionesConciliar) {
        var bloque = totales && totales.facturacion_totem ? totales.facturacion_totem : null;
        var total = bloque ? Number(bloque.total || 0) : Number(totales && totales.total_facturacion_totem || 0);
        if (!bloque && Math.abs(total) < 0.005) {
            return '';
        }
        var ccId = bloque ? (parseInt(bloque.cuentacaja_id, 10) || 0) : 0;
        var nombre = bloque ? (bloque.nombre || bloque.codigo || 'TOTEM') : 'TOTEM';
        var leyenda = bloque && bloque.leyenda
            ? bloque.leyenda
            : 'Comandas Waitry ya cobradas en el tótem/kiosco. Integran la facturación, pero no se entregan en caja.';
        var html = '<div class="mt-3 border rounded p-3 mb-0" style="background:#eef7fb; border-color:#85C1E9 !important;">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-start">';
        html += '<div>';
        html += '<h6 class="font-weight-bold mb-1">Facturación TOTEM <span class="badge badge-secondary">no entregar en caja</span></h6>';
        html += '<p class="small text-muted mb-0" style="max-width:640px;">' + esc(leyenda) + '</p>';
        html += '</div>';
        html += '<div class="text-right mt-2 mt-md-0">';
        html += '<span class="text-muted small d-block">' + esc(nombre) + '</span>';
        html += '<span class="h5 mb-0 font-weight-bold">$' + fmt(total) + '</span>';
        if (opcionesConciliar && opcionesConciliar.habilitar && ccId > 0) {
            html += '<div class="mt-1">';
            html += '<button type="button" class="btn btn-xs btn-outline-info js-conciliar-medio" data-cuentacaja-id="' + ccId + '" ';
            html += 'data-medio-nombre="' + esc(nombre) + '" title="Ver facturas TOTEM del turno">';
            html += '<i class="fa fa-search"></i> Facturas</button></div>';
        }
        html += '</div></div></div>';
        return html;
    }

    function renderTotalMediosPagoFinalHtml(totales, opcionesConciliar, opcionesRender) {
        var medios = totales.por_medio_pago || [];
        var nc = {
            total: Number(totales.total_notas_credito || 0),
            cantidad: Number(totales.cantidad_notas_credito || 0),
        };
        var inv = {
            total: Number(totales.total_invitaciones || 0),
            cantidad: Number(totales.cantidad_invitaciones || 0),
        };
        var hayNc = nc.cantidad > 0 || Math.abs(nc.total) >= 0.005;
        var hayInv = inv.cantidad > 0 || Math.abs(inv.total) >= 0.005;
        var hayTotem = !!(totales.facturacion_totem) || Number(totales.total_facturacion_totem || 0) > 0.005;
        if (!medios.length && !hayNc && !hayInv && !hayTotem) {
            return '';
        }
        var arqueo = null;
        if (opcionesRender && (opcionesRender.arqueoEfectivo || opcionesRender.arqueoMediosCierre)) {
            arqueo = {
                habilitar: true,
                cuentacaja_efectivo_id: parseInt(opcionesRender.cuentacaja_efectivo_id, 10) || 0,
                solo_lectura: !!opcionesRender.arqueoSoloLectura,
                modoEdicionArqueo: !!opcionesRender.modoEdicionArqueo,
                forzar: !!(opcionesRender.arqueoMediosCierre || totales.arqueo_medios_cierre),
            };
        }
        var totalArqueo = totales.total_cobrado_a_rendir != null
            ? Number(totales.total_cobrado_a_rendir)
            : Number(totales.total_cobrado || 0);
        var html = '<div class="mt-3 pt-3 border-top gastro-totales-medios-final">';
        html += '<h6 class="font-weight-bold mb-2">Medios a rendir en caja</h6>';
        html += '<p class="small text-muted mb-2">Solo medios que el cajero debe entregar o declarar. '
            + 'La facturación TOTEM se muestra aparte (ya cobrada en el kiosco).</p>';
        html += renderMediosPagoTabla(
            medios,
            'Sin cobranzas a rendir en comprobantes del turno.',
            true,
            totalArqueo,
            opcionesConciliar,
            hayNc ? nc : null,
            null,
            hayInv ? inv : null,
            arqueo
        );
        html += renderFacturacionTotemHtml(totales, opcionesConciliar);
        html += '</div>';
        return html;
    }

    function renderPorMozoHtml(porMozo, opcionesConciliar) {
        if (!porMozo || !porMozo.length) {
            return '<p class="text-muted mb-0">Sin comprobantes por mozo.</p>';
        }
        var html = '<div class="gastro-mozos-lista">';
        porMozo.forEach(function (m) {
            var medios = m.por_medio_pago || [];
            var mnc = m.notas_credito || null;
            var mncCant = mnc ? Number(mnc.cantidad || 0) : 0;
            var mncTotal = mnc ? Number(mnc.total || 0) : 0;
            var mozoHayNc = mnc && (mncCant > 0 || Math.abs(mncTotal) >= 0.005);
            var minv = m.invitaciones || null;
            var minvCant = minv ? Number(minv.cantidad || 0) : 0;
            var minvTotal = minv ? Number(minv.total || 0) : 0;
            var mozoHayInv = minv && (minvCant > 0 || Math.abs(minvTotal) >= 0.005);
            var mTotalFinal = Number(m.total != null ? m.total : 0);
            var mTotalFacturas = m.total_facturas != null
                ? Number(m.total_facturas)
                : (mTotalFinal - mncTotal);
            var cobradoNeto = Number(m.total_cobrado != null ? m.total_cobrado : 0);
            html += '<div class="card mb-2 border">';
            html += '<div class="card-header py-2 bg-light">';
            html += '<div class="d-flex flex-wrap justify-content-between align-items-center">';
            html += '<strong class="gastro-mozo-nombre">' + esc(m.mozo_nombre || 'Sin mozo') + '</strong>';
            html += '<span class="small">';
            html += (m.cantidad || 0) + ' comp.';
            if (mozoHayNc) {
                html += ' · Fact. bruto <strong>$' + fmt(mTotalFacturas) + '</strong>';
                html += ' · NC <strong style="color:#922b21;">$' + fmt(mncTotal) + '</strong>';
                html += ' · <span title="Facturado bruto − NC">Fact. final <strong>$' + fmt(mTotalFinal) + '</strong></span>';
            } else {
                html += ' · Facturado <strong>$' + fmt(mTotalFinal) + '</strong>';
            }
            if (mozoHayInv) {
                html += ' · Invitaciones <span class="text-muted">(' + minvCant + ' comp., $' + fmt(minvTotal) + ' fact., sin cobranza)</span>';
            }
            html += ' · Cobrado neto <strong>$' + fmt(cobradoNeto) + '</strong>';
            html += '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="card-body py-2">';
            html += '<div class="font-weight-bold mb-2">Cobranzas por medio de pago</div>';
            html += renderMediosPagoTabla(
                medios,
                'Sin cobranzas en los comprobantes de este mozo.',
                true,
                cobradoNeto,
                opcionesConciliar,
                mozoHayNc ? mnc : null,
                { mozo_id: m.mozo_id != null ? m.mozo_id : '', mozo_nombre: m.mozo_nombre || '' },
                mozoHayInv ? minv : null
            );
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function etiquetaMedioColumna(c) {
        var nombre = trim((c && c.nombre) || '');
        var codigo = trim((c && c.codigo) || '');
        if (nombre !== '') {
            return { etiqueta: nombre, titulo: codigo ? codigo + ' — ' + nombre : nombre };
        }
        return { etiqueta: codigo || '—', titulo: codigo || '' };
    }

    function trim(s) {
        return String(s == null ? '' : s).trim();
    }

    function renderGrillaPlaceholderHtml(grilla) {
        if (!grilla) {
            return '<p class="text-muted p-3 mb-0 small">Sin datos del turno.</p>';
        }
        var total = Number(grilla.total_filas || 0);
        var conDiff = Number(grilla.total_con_diferencia || 0);
        var html = '<div class="p-3">';
        html += '<p class="mb-2 small text-muted">';
        html += 'El turno tiene <strong>' + total + '</strong> comprobante(s)';
        if (conDiff > 0) {
            html += ', <strong>' + conDiff + '</strong> con diferencia entre facturado y cobrado';
        }
        html += '. La grilla no se carga automáticamente para no saturar el navegador.';
        html += '</p>';
        html += '<button type="button" class="btn btn-sm btn-primary js-cargar-grilla-comprobantes" data-grilla-target="">';
        html += '<i class="fa fa-table"></i> Ver comprobantes (paginado)</button>';
        html += ' <span class="small text-muted ml-1">Recomendado: marque «Solo con diferencia» si el turno es muy grande.</span>';
        html += '</div>';
        return html;
    }

    function renderGrillaConciliacionHtml(grilla) {
        if (!grilla) {
            return '<p class="text-muted p-3 mb-0">Sin datos.</p>';
        }
        var columnas = grilla.columnas_medios || [];
        var filas = grilla.filas || [];
        var pag = grilla.paginacion;

        if (!pag || pag.page < 1) {
            return renderGrillaPlaceholderHtml(grilla);
        }

        if (!filas.length) {
            var soloDif = pag && pag.solo_diferencias;
            return '<p class="text-muted p-3 mb-0">' + (soloDif
                ? 'No hay comprobantes con diferencia de cobranza.'
                : 'No hay comprobantes en esta página.') + '</p>';
        }

        var html = '';
        if (pag) {
            html += '<div class="d-flex flex-wrap justify-content-between align-items-center px-2 py-1 border-bottom bg-light small">';
            html += '<span>Página ' + pag.page + ' de ' + (pag.total_pages || 1);
            html += ' · ' + pag.total + ' comprobante(s)';
            if (pag.solo_diferencias) {
                html += ' <span class="badge badge-warning">solo con diferencia</span>';
            }
            html += '</span>';
            html += '<div class="gastro-grilla-paginacion" data-container=""></div>';
            html += '</div>';
        }

        html += '<table class="table table-bordered table-sm mb-0 gastro-totales-tabla">';
        html += '<thead><tr>';
        html += '<th>Comprobante</th><th>Hora</th><th>Cliente</th><th>Mozo</th>';
        html += '<th class="text-right">Facturado</th><th class="text-right">Cobrado</th><th class="text-right">Dif.</th>';
        columnas.forEach(function (c) {
            var med = etiquetaMedioColumna(c);
            html += '<th class="text-right" title="' + esc(med.titulo) + '">' + esc(med.etiqueta) + '</th>';
        });
        html += '<th></th></tr></thead><tbody>';

        filas.forEach(function (f) {
            var diff = Number(f.diferencia || 0);
            var diffCls = Math.abs(diff) < 0.02 ? '' : ' class="table-warning"';
            html += '<tr' + diffCls + '>';
            html += '<td>' + esc(f.codigo) + (f.es_invitacion ? ' <span class="badge badge-secondary">Inv.</span>' : '') + '</td>';
            html += '<td>' + esc(f.hora) + '</td>';
            html += '<td>' + esc(f.cliente) + '</td>';
            html += '<td>' + esc(f.mozo_nombre) + '</td>';
            html += '<td class="text-right">$' + fmt(f.total_facturado) + '</td>';
            html += '<td class="text-right">$' + fmt(f.total_cobrado) + '</td>';
            html += '<td class="text-right">$' + fmt(diff) + '</td>';
            columnas.forEach(function (c) {
                var m = (f.medios && f.medios[c.cuentacaja_id]) ? f.medios[c.cuentacaja_id] : 0;
                html += '<td class="text-right">' + (m > 0.001 ? '$' + fmt(m) : '—') + '</td>';
            });
            html += '<td class="text-nowrap">';
            if (f.venta_id) {
                var baseVer = (typeof window !== 'undefined' && window.GASTRONOMIA_FACTURA_VER_BASE) || '';
                if (baseVer) {
                    var verUrl = baseVer.replace(/\/$/, '') + '/' + f.venta_id + '/ver';
                    if (window.ModoConsulta) {
                        verUrl = window.ModoConsulta.url(verUrl);
                    }
                    html += '<a href="' + esc(verUrl) + '" class="btn btn-xs btn-outline-primary" target="_blank" rel="noopener" title="Ver factura">';
                    html += '<i class="fa fa-eye"></i> Ver</a>';
                } else {
                    html += '<a href="#" class="btn btn-xs btn-outline-primary js-ver-factura-detalle" data-venta-id="' + f.venta_id + '" title="Ver detalle">';
                    html += '<i class="fa fa-eye"></i></a>';
                }
            }
            html += '</td></tr>';
        });

        html += '</tbody></table>';

        if (pag && pag.total_pages > 1) {
            html += '<div class="d-flex justify-content-center py-2 border-top bg-light gastro-grilla-paginacion-footer" data-container=""></div>';
        }

        return html;
    }

    function renderPaginacionGrillaHtml(pag, containerId) {
        if (!pag || pag.total_pages <= 1) {
            return '';
        }
        var html = '<nav class="btn-group btn-group-sm" role="navigation" aria-label="Paginación grilla" data-grilla-container="' + esc(containerId) + '">';
        if (pag.page > 1) {
            html += '<button type="button" class="btn btn-outline-secondary js-grilla-pagina" data-page="' + (pag.page - 1) + '" data-container="' + esc(containerId) + '">« Ant.</button>';
        }
        html += '<span class="btn btn-light disabled">' + pag.page + ' / ' + pag.total_pages + '</span>';
        if (pag.page < pag.total_pages) {
            html += '<button type="button" class="btn btn-outline-secondary js-grilla-pagina" data-page="' + (pag.page + 1) + '" data-container="' + esc(containerId) + '">Sig. »</button>';
        }
        html += '</nav>';
        return html;
    }

    /**
     * Alertas de control: conciliación, cuentas pendientes, enlaces útiles.
     */
    function renderAlertasControlHtml(estado) {
        if (!estado || !estado.turno_habilitado) {
            return '';
        }
        var html = '';
        var t = estado.totales_turno;
        if (t && !t.conciliacion_ok) {
            var diff = Number(t.diferencia_cobranza || 0);
            html += '<div class="alert alert-warning py-2 mb-2">';
            html += '<strong><i class="fa fa-exclamation-triangle"></i> Conciliación pendiente</strong> — ';
            html += 'Diferencia $' + fmt(Math.abs(diff)) + (diff > 0 ? ' (sobra en caja)' : ' (falta en caja)');
            html += '. Revise la grilla o concilie por medio de pago.</div>';
        } else if (t && t.conciliacion_ok) {
            html += '<div class="alert alert-success py-2 mb-2"><i class="fa fa-check"></i> ';
            html += 'Comprobantes y cobranzas del turno <strong>cuadran</strong>.</div>';
        }
        var cuentasConItems = Number(estado.cuentas_abiertas_con_items || estado.cuentas_sin_facturar || 0);
        var cuentasVacias = Number(estado.cuentas_abiertas_vacias || 0);
        var urlSaneamiento = estado.url_saneamiento_turno || '';
        if (window.ModoConsulta && urlSaneamiento) {
            urlSaneamiento = window.ModoConsulta.url(urlSaneamiento);
        }
        if (cuentasConItems > 0) {
            if (estado.es_ultimo_turno_dia) {
                html += '<div class="alert alert-danger py-2 mb-2">';
                html += '<strong>' + cuentasConItems + ' cuenta(s) o mesa(s) ABIERTA(S) con consumos</strong> sin facturar en esta terminal. ';
                html += 'Al cerrar el <strong>último turno del día</strong> deben quedar facturadas o cerradas sin facturar. ';
                if (urlSaneamiento) {
                    html += 'Resuélvalas en <a href="' + esc(urlSaneamiento) + '" class="alert-link" target="_blank" rel="noopener">Saneamiento de turnos</a>.';
                }
                html += '</div>';
            } else {
                html += '<div class="alert alert-info py-2 mb-2">';
                html += '<strong>' + cuentasConItems + ' cuenta(s) abierta(s) con consumos</strong> sin facturar: pueden continuar en el próximo turno del día. ';
                html += 'Solo el último turno del día exige dejarlas resueltas.</div>';
            }
        }
        if (cuentasVacias > 0) {
            html += '<div class="alert alert-info py-2 mb-2">';
            html += '<strong>' + cuentasVacias + ' cuenta(s) abierta(s) sin ítems</strong> en esta terminal. ';
            html += 'Se <strong>descartan automáticamente</strong> al cerrar el último turno del día o la jornada (no requieren saneamiento).';
            html += '</div>';
        }
        var cerradasSinFacturar = Number(estado.cuentas_cerradas_sin_facturar || 0);
        if (cerradasSinFacturar > 0) {
            html += '<div class="alert alert-secondary py-2 mb-2">';
            html += '<strong>' + cerradasSinFacturar + ' cuenta(s) cerrada(s) sin facturar</strong> (estado terminal por saneamiento). ';
            html += 'Quedan registradas para auditoría y no bloquean el cierre del turno ni de la jornada. ';
            if (urlSaneamiento) {
                html += '<a href="' + esc(urlSaneamiento) + '" class="alert-link" target="_blank" rel="noopener">Ver en Saneamiento de turnos</a>.';
            }
            html += '</div>';
        }
        if (estado.url_facturas_dia) {
            var urlFacturasDia = estado.url_facturas_dia;
            if (window.ModoConsulta) {
                urlFacturasDia = window.ModoConsulta.url(urlFacturasDia);
            }
            html += '<div class="mb-2">';
            html += '<a href="' + esc(urlFacturasDia) + '" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">';
            html += '<i class="fa fa-list"></i> Facturas del día (esta empresa)</a>';
            html += '</div>';
        }
        return html;
    }

    function renderListaParcialesHtml(lista, urlPdfParcialBase) {
        if (!lista || !lista.length) {
            return '<p class="text-muted small mb-0">Sin cierres parciales registrados.</p>';
        }
        var base = urlPdfParcialBase || '';
        var html = '<ul class="list-group list-group-flush">';
        lista.forEach(function (p) {
            html += '<li class="list-group-item d-flex justify-content-between align-items-center py-2">';
            html += '<span><strong>#' + esc(p.numero_parcial) + '</strong> — ' + esc(p.fecha || '');
            if (p.solo_totales_mozo) {
                html += ' <span class="badge badge-info">Solo mozo</span>';
            }
            html += ' · $' + fmt(p.total) + '</span>';
            if (p.id && base) {
                html += '<a href="' + esc(base + '/' + p.id + '/comprobante?inline=1') + '" class="btn btn-xs btn-outline-secondary" target="_blank" rel="noopener">';
                html += '<i class="fa fa-file-pdf"></i> PDF</a>';
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    function renderTotalesHtml(totales, titulo, opciones) {
        if (!totales) {
            return '';
        }
        var totalFinal = totales.total_ventas != null ? totales.total_ventas : totales.total_general;
        var ncTotalHdr = Number(totales.total_notas_credito || 0);
        var hayNcHdr = Number(totales.cantidad_notas_credito || 0) > 0 || Math.abs(ncTotalHdr) >= 0.005;
        var labelFinal = hayNcHdr ? 'Facturado final (NC restadas)' : 'Total facturado';
        var html = '<div class="' + CLS_PANEL + ' gastro-totales-bloque border rounded p-3 mb-3 bg-white">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">';
        html += '<span class="h6 font-weight-bold mb-0">' + esc(titulo) + '</span>';
        html += '<span>' + labelFinal + ': <strong class="gastro-totales-monto">$' + fmt(totalFinal) + '</strong></span>';
        html += '</div>';
        var optsConc = (opciones && opciones.conciliarMedios) ? { habilitar: true } : null;
        var compacto = opciones && opciones.soloMozo;
        var ocultarMozo = opciones && opciones.ocultarDetalleMozo;

        if (!compacto) {
            html += renderConciliacionHtml(totales);
        }
        if (!ocultarMozo) {
            html += '<h6 class="font-weight-bold mt-1 mb-2">Detalle por mozo</h6>';
            html += renderPorMozoHtml(totales.por_mozo, optsConc);
        }
        if (!compacto) {
            html += renderTotalMediosPagoFinalHtml(totales, optsConc, opciones);
        }
        html += '</div>';

        return html;
    }

    function fmtEntero(n) {
        return Number(n || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });
    }

    /**
     * Bloque compacto (colapsado por defecto) con últimos números CAE/CAEA del turno.
     * @param {{filas?:Array}} numeracion
     */
    function renderNumeracionFiscalHtml(numeracion) {
        var filas = (numeracion && numeracion.filas) || [];
        if (!filas.length) {
            return '';
        }

        var resumen = filas.map(function (f) {
            var partes = [f.rol_etiqueta || '', 'PV ' + (f.puntoventa_codigo || '—')];
            if (f.ultimo_ticket) {
                partes.push('ticket ' + fmtEntero(f.ultimo_ticket));
            }
            if (f.ultimo_nota_credito) {
                partes.push('NC ' + fmtEntero(f.ultimo_nota_credito));
            }
            return partes.join(' · ');
        }).join(' | ');

        var html = '<div class="card card-outline card-secondary mb-0 gastro-numeracion-fiscal">';
        html += '<div class="card-header py-1 px-2 d-flex align-items-center" role="button" data-toggle="collapse" data-target="#collapse-numeracion-fiscal-turno" aria-expanded="false" aria-controls="collapse-numeracion-fiscal-turno">';
        html += '<i class="fa fa-hashtag text-muted mr-2"></i>';
        html += '<span class="small font-weight-bold mr-2 text-nowrap">Numeración fiscal</span>';
        html += '<span class="small text-muted text-truncate flex-grow-1" title="' + esc(resumen) + '">' + esc(resumen) + '</span>';
        html += '<i class="fa fa-chevron-down small text-muted ml-2"></i>';
        html += '</div>';
        html += '<div id="collapse-numeracion-fiscal-turno" class="collapse">';
        html += '<div class="card-body py-2 px-2">';
        html += '<p class="text-muted small mb-2 mb-md-1">Últimos números emitidos en este turno en la PC (para rendición de caja).</p>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 gastro-totales-tabla">';
        html += '<thead class="thead-light"><tr>';
        html += '<th>Modo</th><th>Punto de venta</th>';
        html += '<th class="text-right">Último ticket</th><th class="text-right">Tickets</th>';
        html += '<th class="text-right">Última NC</th><th class="text-right">NC</th>';
        html += '</tr></thead><tbody>';

        filas.forEach(function (f) {
            html += '<tr>';
            html += '<td><strong>' + esc(f.rol_etiqueta || '') + '</strong></td>';
            html += '<td>PV ' + esc(f.puntoventa_codigo || '—');
            if (f.puntoventa_nombre) {
                html += ' <span class="text-muted">— ' + esc(f.puntoventa_nombre) + '</span>';
            }
            html += '</td>';
            html += '<td class="text-right">' + (f.ultimo_ticket ? fmtEntero(f.ultimo_ticket) : '—') + '</td>';
            html += '<td class="text-right">' + Number(f.cantidad_tickets || 0) + '</td>';
            html += '<td class="text-right">' + (f.ultimo_nota_credito ? fmtEntero(f.ultimo_nota_credito) : '—') + '</td>';
            html += '<td class="text-right">' + Number(f.cantidad_notas_credito || 0) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div></div></div>';
        return html;
    }

    function renderTotalesResumenHtml(bloques) {
        if (!bloques || !bloques.length) {
            return '';
        }
        if (bloques.length === 1) {
            return bloques[0].html;
        }
        var html = '<div class="row gastro-totales-resumen">';
        bloques.forEach(function (b) {
            html += '<div class="col-lg-6 mb-3 mb-lg-0">' + b.html + '</div>';
        });
        html += '</div>';
        return html;
    }

    window.GastronomiaTotalesTurnoRender = {
        fmt: fmt,
        esc: esc,
        renderConciliacionHtml: renderConciliacionHtml,
        renderTotalesHtml: renderTotalesHtml,
        renderTotalesResumenHtml: renderTotalesResumenHtml,
        renderGrillaConciliacionHtml: renderGrillaConciliacionHtml,
        renderGrillaPlaceholderHtml: renderGrillaPlaceholderHtml,
        renderPaginacionGrillaHtml: renderPaginacionGrillaHtml,
        renderListaParcialesHtml: renderListaParcialesHtml,
        renderAlertasControlHtml: renderAlertasControlHtml,
        renderNumeracionFiscalHtml: renderNumeracionFiscalHtml,
        enlazarArqueoMediosCierre: enlazarArqueoMediosCierre,
        enlazarArqueoEfectivoCierre: enlazarArqueoEfectivoCierre,
        recolectarMediosContadoCierreDesdeRoot: recolectarMediosContadoCierreDesdeRoot,
        renderTotalMediosPagoFinalHtml: renderTotalMediosPagoFinalHtml,
    };
})();
