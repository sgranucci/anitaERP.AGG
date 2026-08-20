(function ($) {
    'use strict';

    var $root = $('#acc-workbench');
    if (!$root.length) {
        return;
    }

    var TOL = 0.01;
    var state = {
        creditos: [],
        deudas: [],
        recientes: [],
        kpis: { creditos: 0, deudas: 0, nc: 0, pagos: 0, vencida: 0 },
        lineas: [],
        creditoActivo: 0,
        autoActivo: true,
        omitidosDeuda: {},
        omitidosCredito: {},
        verOtrasEmpresas: false,
        verOtrasMonedas: false
    };

    var csrf = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();
    var urlPendientes = $root.data('url-pendientes');
    var urlAplicar = $root.data('url-aplicar');
    var urlDesaplicar = $root.data('url-desaplicar');
    var urlCc = $root.data('url-cc');
    var urlCotizacion = $root.data('url-cotizacion');
    var monedaLocalId = parseInt($root.data('moneda-local') || '1', 10) || 1;
    var cotDiaCache = {};

    function round4(n) {
        return Math.round((Number(n) || 0) * 10000) / 10000;
    }

    function fmt(n) {
        return (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cotizLabel(item) {
        var mon = item.moneda || '';
        var cot = Number(item.cotizacion) || 1;
        if (cot > 1.0001) {
            return mon + ' · cot ' + cot.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }
        return mon;
    }

    function esLocal(monedaId) {
        return Number(monedaId) <= monedaLocalId;
    }

    function cotNorm(v) {
        var n = Number(v) || 0;
        return n > 0 ? n : 1;
    }

    function esCruzada(c, d) {
        return !!(c && d && Number(c.moneda_id) !== Number(d.moneda_id));
    }

    function cotLiqDePar(c, d, override) {
        if (override != null && Number(override) > 0) {
            return Number(override);
        }
        var toolbar = parseFloat($('#acc-cot-liq').val() || '0') || 0;
        if (toolbar > 0) {
            return toolbar;
        }
        var me = null;
        if (d && !esLocal(d.moneda_id)) {
            me = d;
        } else if (c && !esLocal(c.moneda_id)) {
            me = c;
        }
        if (!me) {
            return 1;
        }
        var key = String(me.moneda_id) + '|' + ($('#acc-fecha').val() || '');
        if (cotDiaCache[key] > 0) {
            return cotDiaCache[key];
        }
        return cotNorm(me.cotizacion);
    }

    function valorLocal(monto, cot, monedaId) {
        monto = Math.abs(Number(monto) || 0);
        return esLocal(monedaId) ? round4(monto) : round4(monto * cotNorm(cot));
    }

    function convertirDeudaACredito(montoDeuda, monedaDeuda, monedaCredito, cotLiq, cotCredito) {
        montoDeuda = Math.abs(Number(montoDeuda) || 0);
        cotLiq = cotNorm(cotLiq);
        cotCredito = cotNorm(cotCredito);
        if (esLocal(monedaDeuda) && !esLocal(monedaCredito)) {
            return round4(montoDeuda / cotLiq);
        }
        if (!esLocal(monedaDeuda) && esLocal(monedaCredito)) {
            return round4(montoDeuda * cotLiq);
        }
        return round4((montoDeuda * cotLiq) / cotCredito);
    }

    function liquidar(c, d, montoDeuda, cotLiq) {
        montoDeuda = round4(Math.abs(Number(montoDeuda) || 0));
        if (!c || !d) {
            return { cruzada: false, monto_deuda: montoDeuda, monto_credito: montoDeuda, dc: 0, cotizacion_liquidacion: 1 };
        }
        if (!esCruzada(c, d)) {
            var dcMisma = round4(montoDeuda * (cotNorm(d.cotizacion) - cotNorm(c.cotizacion)));
            return {
                cruzada: false,
                monto_deuda: montoDeuda,
                monto_credito: montoDeuda,
                dc: Math.abs(dcMisma) < TOL ? 0 : dcMisma,
                cotizacion_liquidacion: cotNorm(d.cotizacion)
            };
        }
        cotLiq = cotLiqDePar(c, d, cotLiq);
        var montoCredito = convertirDeudaACredito(montoDeuda, d.moneda_id, c.moneda_id, cotLiq, c.cotizacion);
        var valorD = valorLocal(montoDeuda, d.cotizacion, d.moneda_id);
        var valorC = valorLocal(montoCredito, c.cotizacion, c.moneda_id);
        var dcCruz = round4(valorC - valorD);
        return {
            cruzada: true,
            monto_deuda: montoDeuda,
            monto_credito: montoCredito,
            dc: Math.abs(dcCruz) < TOL ? 0 : dcCruz,
            cotizacion_liquidacion: cotLiq
        };
    }

    function enriquecerLinea(l) {
        var c = creditoById(l.credito_id);
        var d = deudaById(l.deuda_id);
        var liq = liquidar(c, d, l.monto, l.cotizacion_liquidacion);
        l.monto_credito = liq.monto_credito;
        l.dc = liq.dc;
        if (liq.cruzada) {
            l.cotizacion_liquidacion = liq.cotizacion_liquidacion;
        }
        return l;
    }

    function dcDeLinea(l) {
        return enriquecerLinea(l).dc || 0;
    }

    function dcLabel(dc) {
        if (Math.abs(dc) < TOL) {
            return '';
        }
        return fmt(Math.abs(dc)) + (dc > 0 ? ' pérdida' : ' ganancia');
    }

    function fechaComprobante(item, conVencimiento) {
        var fecha = fechaUi(item && item.fecha);
        if (!fecha) {
            return '';
        }
        if (conVencimiento && item.vencimiento && item.vencimiento !== item.fecha) {
            return fecha + ' · vto ' + fechaUi(item.vencimiento);
        }
        return fecha;
    }

    function fechaUi(ymd) {
        if (!ymd) {
            return '';
        }
        var p = String(ymd).split('-');
        return p.length === 3 ? (p[2] + '/' + p[1] + '/' + p[0]) : ymd;
    }

    function proveedorId() {
        return parseInt($('#proveedor_id').val() || '0', 10);
    }

    function empresaId() {
        return parseInt($('#empresa_id').val() || '0', 10);
    }

    function creditoById(id) {
        return state.creditos.find(function (c) { return Number(c.id) === Number(id); }) || null;
    }

    function deudaById(id) {
        return state.deudas.find(function (d) { return Number(d.id) === Number(id); }) || null;
    }

    function asignadoCredito(id) {
        return state.lineas.reduce(function (s, l) {
            if (Number(l.credito_id) !== Number(id)) {
                return s;
            }
            enriquecerLinea(l);
            return s + (Number(l.monto_credito) || l.monto);
        }, 0);
    }

    function asignadoDeuda(id) {
        return state.lineas.reduce(function (s, l) {
            return Number(l.deuda_id) === Number(id) ? s + l.monto : s;
        }, 0);
    }

    function lineasDeDeuda(id) {
        return state.lineas.filter(function (l) { return Number(l.deuda_id) === Number(id); });
    }

    function lineasDeCredito(id) {
        return state.lineas.filter(function (l) { return Number(l.credito_id) === Number(id); });
    }

    function pairOrigen(creditoId, deudaId) {
        var l = state.lineas.find(function (x) {
            return Number(x.credito_id) === Number(creditoId) && Number(x.deuda_id) === Number(deudaId);
        });
        return l ? l.origen : '';
    }

    function mismaEmpresa(c, d) {
        if (!c || !d) {
            return false;
        }
        if (Number(c.empresa_id) > 0 && Number(d.empresa_id) > 0 && Number(c.empresa_id) !== Number(d.empresa_id)) {
            return false;
        }
        return true;
    }

    function empresaContexto() {
        var filtro = empresaId();
        if (filtro > 0) {
            return filtro;
        }
        var c = creditoById(state.creditoActivo);
        return c && Number(c.empresa_id) > 0 ? Number(c.empresa_id) : 0;
    }

    function esOtraEmpresa(item) {
        var emp = empresaContexto();
        if (!emp || !item) {
            return false;
        }
        return Number(item.empresa_id) > 0 && Number(item.empresa_id) !== emp;
    }

    function monedaContexto() {
        var c = creditoById(state.creditoActivo);
        return c && Number(c.moneda_id) > 0 ? Number(c.moneda_id) : 0;
    }

    function esOtraMoneda(item) {
        var mon = monedaContexto();
        if (!mon || !item) {
            return false;
        }
        return Number(item.moneda_id) > 0 && Number(item.moneda_id) !== mon;
    }

    function estaAsignado(item, lado) {
        if (!item) {
            return false;
        }
        return (lado === 'credito' ? asignadoCredito(item.id) : asignadoDeuda(item.id)) >= TOL;
    }

    function visiblePorEmpresa(item, lado) {
        if (!esOtraEmpresa(item)) {
            return true;
        }
        if (estaAsignado(item, lado)) {
            return true;
        }
        return !!state.verOtrasEmpresas;
    }

    function visiblePorMoneda(item, lado) {
        if (!esOtraMoneda(item)) {
            return true;
        }
        if (estaAsignado(item, lado)) {
            return true;
        }
        return !!state.verOtrasMonedas;
    }

    function visiblePorVista(item, lado) {
        return visiblePorEmpresa(item, lado) && visiblePorMoneda(item, lado);
    }

    function contarOtros(predicado) {
        var n = 0;
        state.creditos.forEach(function (c) {
            if (predicado(c) && !estaAsignado(c, 'credito')) {
                n += 1;
            }
        });
        state.deudas.forEach(function (d) {
            if (predicado(d) && !estaAsignado(d, 'deuda')) {
                n += 1;
            }
        });
        return n;
    }

    function itemsOtrasEmpresas() {
        return contarOtros(esOtraEmpresa);
    }

    function itemsOtrasMonedas() {
        return contarOtros(function (item) {
            return esOtraMoneda(item) && !esOtraEmpresa(item);
        });
    }

    function hintConversion(d, c) {
        if (!c || !d || !esCruzada(c, d)) {
            return '';
        }
        var cot = cotLiqDePar(c, d, null);
        var equiv = convertirDeudaACredito(d.saldo, d.moneda_id, c.moneda_id, cot, c.cotizacion);
        return '≈ ' + fmt(equiv) + ' ' + (c.moneda || '') + ' @ ' + cot.toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 4
        });
    }

    function esCompatibleFifo(c, d) {
        return mismaEmpresa(c, d) && Number(c.moneda_id) === Number(d.moneda_id);
    }

    function esCompatible(c, d) {
        return mismaEmpresa(c, d);
    }

    function toast(msg, tipo) {
        if (window.toastr) {
            window.toastr[tipo || 'success'](msg);
            return;
        }
        window.alert(msg);
    }

    function ordenarCreditos(filas) {
        return filas.slice().sort(function (a, b) {
            var fa = String(a.fecha || '');
            var fb = String(b.fecha || '');
            if (fa !== fb) {
                return fa < fb ? -1 : 1;
            }
            return Number(a.id) - Number(b.id);
        });
    }

    function ordenarDeudas(filas) {
        return filas.slice().sort(function (a, b) {
            var va = String(a.vencimiento || a.fecha || '9999-12-31');
            var vb = String(b.vencimiento || b.fecha || '9999-12-31');
            if (va !== vb) {
                return va < vb ? -1 : 1;
            }
            var fa = String(a.fecha || '');
            var fb = String(b.fecha || '');
            if (fa !== fb) {
                return fa < fb ? -1 : 1;
            }
            return Number(a.id) - Number(b.id);
        });
    }

    function sugerirFifoLocal(creditos, deudas) {
        var creditosOrd = ordenarCreditos(creditos);
        var deudasOrd = ordenarDeudas(deudas);
        var restC = {};
        var restD = {};
        creditosOrd.forEach(function (c) { restC[c.id] = round4(Math.abs(c.saldo)); });
        deudasOrd.forEach(function (d) { restD[d.id] = round4(Math.abs(d.saldo)); });
        var out = [];
        creditosOrd.forEach(function (c) {
            deudasOrd.forEach(function (d) {
                if (!esCompatibleFifo(c, d)) {
                    return;
                }
                var dispC = restC[c.id] || 0;
                var dispD = restD[d.id] || 0;
                if (dispC < TOL || dispD < TOL) {
                    return;
                }
                var monto = round4(Math.min(dispC, dispD));
                if (monto < TOL) {
                    return;
                }
                out.push({ credito_id: Number(c.id), deuda_id: Number(d.id), monto: monto });
                restC[c.id] = round4(dispC - monto);
                restD[d.id] = round4(dispD - monto);
            });
        });
        return out;
    }

    function sugerirParearLocal(creditos, deudas) {
        var creditosOrd = ordenarCreditos(creditos);
        var deudasOrd = ordenarDeudas(deudas);
        var usados = {};
        var out = [];
        creditosOrd.forEach(function (c) {
            var saldoC = round4(Math.abs(c.saldo));
            if (saldoC < TOL) {
                return;
            }
            deudasOrd.some(function (d) {
                if (usados[d.id] || !esCompatibleFifo(c, d)) {
                    return false;
                }
                var saldoD = round4(Math.abs(d.saldo));
                if (Math.abs(saldoC - saldoD) >= TOL) {
                    return false;
                }
                out.push({ credito_id: Number(c.id), deuda_id: Number(d.id), monto: saldoC });
                usados[d.id] = true;
                return true;
            });
        });
        return out;
    }

    function sugerirFifoRestanteLocal(reservas, omitirD, omitirC) {
        var consumoC = {};
        var consumoD = {};
        reservas.forEach(function (r) {
            consumoC[r.credito_id] = round4((consumoC[r.credito_id] || 0) + r.monto);
            consumoD[r.deuda_id] = round4((consumoD[r.deuda_id] || 0) + r.monto);
        });
        var creditosAdj = state.creditos.filter(function (c) {
            return !omitirC[c.id];
        }).map(function (c) {
            return $.extend({}, c, { saldo: round4(Math.abs(c.saldo) - (consumoC[c.id] || 0)) });
        }).filter(function (c) { return c.saldo >= TOL; });
        var deudasAdj = state.deudas.filter(function (d) {
            return !omitirD[d.id];
        }).map(function (d) {
            return $.extend({}, d, { saldo: round4(Math.abs(d.saldo) - (consumoD[d.id] || 0)) });
        }).filter(function (d) { return d.saldo >= TOL; });
        return sugerirFifoLocal(creditosAdj, deudasAdj);
    }

    function recomponerAuto() {
        var manuals = state.lineas.filter(function (l) { return l.origen === 'manual'; });
        if (!state.autoActivo) {
            state.lineas = manuals;
            return;
        }
        var autos = sugerirFifoRestanteLocal(manuals, state.omitidosDeuda, state.omitidosCredito).map(function (l) {
            return enriquecerLinea({ credito_id: l.credito_id, deuda_id: l.deuda_id, monto: l.monto, origen: 'auto' });
        });
        state.lineas = manuals.concat(autos);
    }

    function upsertManual(creditoId, deudaId, monto, cotLiq) {
        monto = round4(monto);
        state.lineas = state.lineas.filter(function (l) {
            return !(Number(l.credito_id) === Number(creditoId) && Number(l.deuda_id) === Number(deudaId));
        });
        if (monto >= TOL) {
            state.lineas.push(enriquecerLinea({
                credito_id: Number(creditoId),
                deuda_id: Number(deudaId),
                monto: monto,
                cotizacion_liquidacion: cotLiq != null ? cotLiq : null,
                origen: 'manual'
            }));
        }
        delete state.omitidosDeuda[deudaId];
        recomponerAuto();
    }

    function quitarDeuda(deudaId) {
        state.lineas = state.lineas.filter(function (l) { return Number(l.deuda_id) !== Number(deudaId); });
        state.omitidosDeuda[deudaId] = true;
        recomponerAuto();
    }

    function incluirDeuda(deudaId) {
        delete state.omitidosDeuda[deudaId];
        recomponerAuto();
    }

    function textoBusqueda(item) {
        return [
            item.etiqueta, item.tipo, item.tipo_label, item.empresa, item.moneda,
            item.fecha, item.vencimiento, item.aging_label
        ].join(' ').toLowerCase();
    }

    function filaVisibleCredito(c) {
        if (!visiblePorVista(c, 'credito')) {
            return false;
        }
        var q = String($('#acc-buscar-credito').val() || '').trim().toLowerCase();
        return !q || textoBusqueda(c).indexOf(q) !== -1;
    }

    function filaVisibleDeuda(d) {
        if (!visiblePorVista(d, 'deuda')) {
            return false;
        }
        var q = String($('#acc-buscar-deuda').val() || '').trim().toLowerCase();
        if (q && textoBusqueda(d).indexOf(q) === -1) {
            return false;
        }
        var filtro = String($('#acc-filtro-deuda').val() || 'todas');
        var c = creditoById(state.creditoActivo);
        if (filtro === 'compatibles') {
            return !c || esCompatible(c, d);
        }
        if (filtro === 'sugeridas') {
            return asignadoDeuda(d.id) >= TOL;
        }
        if (filtro === 'vencidas') {
            return ['vencida', '30', '60'].indexOf(d.aging) !== -1;
        }
        if (filtro === 'excluidas') {
            return !!state.omitidosDeuda[d.id];
        }
        return true;
    }

    function hintCredito(c) {
        return lineasDeCredito(c.id).map(function (l) {
            var d = deudaById(l.deuda_id);
            return (d ? d.etiqueta : ('#' + l.deuda_id)) + ' ' + fmt(l.monto);
        }).join(' · ');
    }

    function hintDeuda(d) {
        return lineasDeDeuda(d.id).map(function (l) {
            var c = creditoById(l.credito_id);
            return (c ? c.tipo + ' ' + c.etiqueta : ('#' + l.credito_id)) + ' ' + fmt(l.monto);
        }).join(' · ');
    }

    function esExactoPar(l) {
        var c = creditoById(l.credito_id);
        var d = deudaById(l.deuda_id);
        if (!c || !d) {
            return false;
        }
        return Math.abs(l.monto - c.saldo) < TOL && Math.abs(l.monto - d.saldo) < TOL;
    }

    function pintarClasesCredito($row, c) {
        var usado = asignadoCredito(c.id);
        var hasManual = lineasDeCredito(c.id).some(function (l) { return l.origen === 'manual'; });
        $row.toggleClass('is-on', Number(state.creditoActivo) === Number(c.id));
        $row.toggleClass('is-matched', usado >= TOL && !hasManual);
        $row.toggleClass('is-manual', hasManual);
        $row.toggleClass('is-exact', lineasDeCredito(c.id).some(esExactoPar));
        $row.toggleClass('is-incompatible', esOtraEmpresa(c));
        $row.toggleClass('is-otra-moneda', !esOtraEmpresa(c) && esOtraMoneda(c));
        $row.toggleClass('acc-hidden', !filaVisibleCredito(c));
        $row.find('.acc-sel-credito').prop('checked', Number(state.creditoActivo) === Number(c.id));
        var resto = round4(c.saldo - usado);
        $row.find('.acc-saldo').text(fmt(resto));
        $row.find('.acc-saldo-sub').text(usado >= TOL ? ('de ' + fmt(c.saldo)) : cotizLabel(c));
        var pct = c.saldo > 0 ? Math.min(100, (usado / c.saldo) * 100) : 0;
        $row.find('.acc-progress').toggleClass('is-manual', hasManual).find('span').css('width', pct + '%');
        var $chips = $row.find('.acc-chips').empty();
        lineasDeCredito(c.id).forEach(function (l) {
            var d = deudaById(l.deuda_id);
            $chips.append($('<span class="acc-chip"/>').toggleClass('is-manual', l.origen === 'manual')
                .text((d ? d.etiqueta : '#' + l.deuda_id) + ' ' + fmt(l.monto)));
        });
        $row.find('.acc-fecha').text(fechaComprobante(c, false));
        $row.find('.acc-meta-hint').text(hintCredito(c) || ((c.empresa || '') + (c.tipo_label ? ' · ' + c.tipo_label : '')));
    }

    function pintarClasesDeuda($row, d) {
        var usado = asignadoDeuda(d.id);
        var cAct = creditoById(state.creditoActivo);
        var hasManual = lineasDeDeuda(d.id).some(function (l) { return l.origen === 'manual'; });
        var omitida = !!state.omitidosDeuda[d.id];
        var compatible = !cAct || esCompatible(cAct, d);
        $row.toggleClass('is-matched', usado >= TOL && !hasManual && !omitida);
        $row.toggleClass('is-manual', hasManual);
        $row.toggleClass('is-omitida', omitida);
        $row.toggleClass('is-compatible', !!cAct && compatible && usado < TOL && !omitida && !esOtraMoneda(d));
        $row.toggleClass('is-incompatible', esOtraEmpresa(d) || (!!cAct && !compatible));
        $row.toggleClass('is-otra-moneda', !!cAct && compatible && esOtraMoneda(d) && !omitida);
        $row.toggleClass('is-exact', lineasDeDeuda(d.id).some(esExactoPar));
        $row.toggleClass('acc-hidden', !filaVisibleDeuda(d));
        $row.find('.acc-sel-deuda').prop('checked', usado >= TOL);
        var $monto = $row.find('.acc-monto-deuda');
        var montoActivo = 0;
        if (cAct) {
            state.lineas.forEach(function (l) {
                if (Number(l.credito_id) === Number(cAct.id) && Number(l.deuda_id) === Number(d.id)) {
                    montoActivo = l.monto;
                }
            });
        } else {
            montoActivo = usado;
        }
        if (!$monto.is(':focus')) {
            $monto.val(montoActivo >= TOL ? montoActivo.toFixed(2) : '');
            $monto.attr('placeholder', fmt(d.saldo));
        }
        $row.find('.acc-saldo').text(fmt(round4(d.saldo - usado)));
        $row.find('.acc-saldo-sub').text(cotizLabel(d));
        $row.find('.acc-fecha').text(fechaComprobante(d, true));
        var $chips = $row.find('.acc-chips').empty();
        if (omitida) {
            $chips.append($('<span class="acc-chip is-manual"/>').text('excluida · clic para incluir'));
        } else {
            lineasDeDeuda(d.id).forEach(function (l) {
                var c = creditoById(l.credito_id);
                $chips.append($('<span class="acc-chip"/>').toggleClass('is-manual', l.origen === 'manual')
                    .text((c ? c.tipo : 'CC') + ' ' + fmt(l.monto)));
            });
            if (cAct && esOtraMoneda(d) && esCompatible(cAct, d)) {
                var conv = hintConversion(d, cAct);
                if (conv) {
                    $chips.append($('<span class="acc-chip is-cruzada"/>').text(conv));
                }
            }
        }
        $row.find('.acc-meta-hint').text(omitida ? 'Fuera del matching automático' : (hintDeuda(d) || (d.empresa || '')));
    }

    function htmlCredito(c) {
        return '<div class="acc-row" data-id="' + c.id + '" data-lado="credito">'
            + '<input type="radio" name="acc-credito" class="acc-sel-credito" value="' + c.id + '">'
            + '<span class="acc-badge ' + String(c.tipo).toLowerCase() + '">' + $('<div>').text(c.tipo).html() + '</span>'
            + '<div><div class="acc-etiqueta"></div><div class="acc-fecha"></div><div class="acc-meta acc-meta-hint"></div><div class="acc-chips"></div><div class="acc-progress"><span></span></div></div>'
            + '<div class="acc-side"><div class="acc-saldo"></div><div class="acc-saldo-sub"></div></div>'
            + '</div>';
    }

    function htmlDeuda(d) {
        return '<div class="acc-row" data-id="' + d.id + '" data-lado="deuda">'
            + '<input type="checkbox" class="acc-sel-deuda">'
            + '<span class="acc-badge ' + String(d.tipo).toLowerCase() + '">' + $('<div>').text(d.tipo).html() + '</span>'
            + '<div><div class="acc-etiqueta"></div><div class="acc-fecha"></div>'
            + '<div><span class="acc-badge aging-' + d.aging + '">' + $('<div>').text(d.aging_label || '').html() + '</span></div>'
            + '<div class="acc-meta acc-meta-hint"></div><div class="acc-chips"></div></div>'
            + '<div class="acc-side"><div class="acc-saldo"></div><div class="acc-saldo-sub"></div>'
            + '<input type="number" min="0" step="0.01" class="form-control form-control-sm acc-monto-deuda"></div>'
            + '</div>';
    }

    function syncLista($body, items, lado) {
        var ids = items.map(function (i) { return String(i.id); });
        var actuales = [];
        $body.children('[data-id]').each(function () { actuales.push(String($(this).data('id'))); });
        var misma = ids.length === actuales.length && ids.every(function (id, i) { return id === actuales[i]; });
        if (!items.length) {
            var vacio = lado === 'credito'
                ? 'Sin notas de crédito ni pagos a cuenta pendientes.'
                : 'Sin facturas adeudadas.';
            if (!$body.children('.acc-empty').length || $body.children('[data-id]').length) {
                $body.html('<div class="acc-empty">' + vacio + '</div>');
            }
            return;
        }
        if (!misma) {
            var html = '';
            items.forEach(function (item) {
                html += lado === 'credito' ? htmlCredito(item) : htmlDeuda(item);
            });
            $body.html(html);
            $body.children('[data-id]').each(function (i) {
                $(this).find('.acc-etiqueta').text(items[i].etiqueta);
            });
        }
        $body.children('[data-id]').each(function () {
            var id = parseInt($(this).data('id'), 10);
            var item = lado === 'credito' ? creditoById(id) : deudaById(id);
            if (!item) {
                return;
            }
            if (lado === 'credito') {
                pintarClasesCredito($(this), item);
            } else {
                pintarClasesDeuda($(this), item);
            }
        });
    }

    function renderBoard() {
        var $body = $('#acc-board-body');
        if (!state.lineas.length) {
            $body.html('<div class="acc-empty">El matching aparece acá apenas hay crédito y deuda compatibles. Podés cambiar cualquier monto.</div>');
            $('#acc-board-resumen').text('Sin líneas');
            return;
        }
        var html = '';
        state.lineas.forEach(function (l, idx) {
            var c = creditoById(l.credito_id);
            var d = deudaById(l.deuda_id);
            var dc = dcDeLinea(l);
            var fechaC = c ? fechaComprobante(c, false) : '';
            var fechaD = d ? fechaComprobante(d, true) : '';
            enriquecerLinea(l);
            var cruz = c && d && esCruzada(c, d);
            var cotInp = cruz
                ? '<input type="number" min="0" step="0.0001" class="form-control form-control-sm acc-board-cot" value="' + cotLiqDePar(c, d, l.cotizacion_liquidacion).toFixed(4) + '" title="Cotización de liquidación">'
                : '<span class="acc-pair-equiv">' + (c && d ? fmt(l.monto_credito || l.monto) + ' ' + (c.moneda || '') : '') + '</span>';
            var equiv = cruz
                ? fmt(l.monto_credito) + ' ' + (c ? (c.moneda || '') : '')
                : '';
            html += '<div class="acc-pair is-' + l.origen + (cruz ? ' is-cruzada' : '') + '" data-idx="' + idx + '" data-credito="' + l.credito_id + '" data-deuda="' + l.deuda_id + '">'
                + '<span class="acc-badge ' + l.origen + '">' + (l.origen === 'manual' ? 'Fijada' : 'Sugerida') + '</span>'
                + '<div><div class="acc-pair-nom acc-pair-c"></div><div class="acc-pair-meta">' + (c ? ((fechaC ? fechaC + ' · ' : '') + cotizLabel(c) + ' · resto ' + fmt(round4(c.saldo - asignadoCredito(c.id)))) : '') + '</div></div>'
                + '<div class="acc-pair-arrow">→</div>'
                + '<div><div class="acc-pair-nom acc-pair-d"></div><div class="acc-pair-meta">' + (d ? ((fechaD ? fechaD + ' · ' : '') + cotizLabel(d) + ' · saldo ' + fmt(d.saldo)) : '') + '</div></div>'
                + '<input type="number" min="0" step="0.01" class="form-control form-control-sm acc-board-monto" value="' + l.monto.toFixed(2) + '" title="Monto en moneda de la deuda">'
                + cotInp
                + '<div class="acc-pair-dc' + (dc > 0 ? ' is-loss' : (dc < 0 ? ' is-gain' : '')) + '">'
                + (equiv ? '<div class="acc-pair-equiv">' + equiv + '</div>' : '')
                + (dcLabel(dc) || '—') + '</div>'
                + '<button type="button" class="btn btn-sm btn-outline-danger acc-board-x" title="Sacar este par">×</button>'
                + '</div>';
        });
        $body.html(html);
        $body.children('.acc-pair').each(function (i) {
            var l = state.lineas[i];
            var c = creditoById(l.credito_id);
            var d = deudaById(l.deuda_id);
            $(this).find('.acc-pair-c').text(c ? c.etiqueta : ('Crédito #' + l.credito_id));
            $(this).find('.acc-pair-d').text(d ? d.etiqueta : ('Deuda #' + l.deuda_id));
        });
        var autos = state.lineas.filter(function (l) { return l.origen === 'auto'; }).length;
        var mans = state.lineas.length - autos;
        $('#acc-board-resumen').text(state.lineas.length + ' par(es) · ' + autos + ' sugerida(s) · ' + mans + ' fijada(s)');
    }

    function renderRecientes() {
        var $tb = $('#acc-recientes-body');
        if (!state.recientes.length) {
            $tb.html('<tr><td colspan="6" class="text-muted text-center">Sin aplicaciones manuales recientes</td></tr>');
            return;
        }
        var html = '';
        state.recientes.forEach(function (r) {
            var dc = Number(r.diferencia_cambio) || 0;
            html += '<tr>'
                + '<td>' + fechaUi(r.fecha) + '</td>'
                + '<td></td><td></td>'
                + '<td class="text-right">' + fmt(r.monto) + ' ' + (r.moneda || '')
                + (r.cotizacion_liquidacion && r.moneda_contraparte && r.moneda_contraparte !== r.moneda
                    ? ' → ' + (r.moneda_contraparte || '') + ' · liq ' + Number(r.cotizacion_liquidacion).toLocaleString('es-AR')
                    : '')
                + '</td>'
                + '<td class="text-right">' + (Math.abs(dc) >= TOL ? dcLabel(dc) : '—') + '</td>'
                + '<td><button type="button" class="btn btn-outline-danger btn-xs btn-sm acc-desaplicar" data-id="' + r.id + '">Desaplicar</button></td>'
                + '</tr>';
        });
        $tb.html(html);
        $tb.find('tr').each(function (i) {
            $(this).find('td').eq(1).text(state.recientes[i].credito);
            $(this).find('td').eq(2).text(state.recientes[i].deuda);
        });
    }

    function renderKpisDock() {
        var visiblesCredito = state.creditos.filter(function (c) { return visiblePorVista(c, 'credito'); });
        var visiblesDeuda = state.deudas.filter(function (d) { return visiblePorVista(d, 'deuda'); });
        var ocultosCredito = state.creditos.length - visiblesCredito.length;
        var ocultosDeuda = state.deudas.length - visiblesDeuda.length;
        var k = kpisDeVista(visiblesCredito, visiblesDeuda);
        var total = state.lineas.reduce(function (s, l) { return s + l.monto; }, 0);
        var libreC = round4(k.creditos - total);
        $('#acc-kpi-creditos').text(fmt(k.creditos));
        $('#acc-kpi-deudas').text(fmt(k.deudas));
        $('#acc-kpi-match').text(fmt(total));
        $('#acc-kpi-libre').text(fmt(Math.max(0, libreC)));
        $('#acc-kpi-creditos-hint').text(visiblesCredito.length + ' comprobantes · NC ' + fmt(k.nc) + ' · pagos ' + fmt(k.pagos));
        $('#acc-kpi-deudas-hint').text(visiblesDeuda.length + ' comprobantes · vencida ' + fmt(k.vencida));
        $('#acc-kpi-match-hint').text(state.lineas.filter(function (l) { return l.origen === 'auto'; }).length + ' sugeridas · '
            + state.lineas.filter(function (l) { return l.origen === 'manual'; }).length + ' fijadas');
        $('#acc-kpi-libre-hint').text(libreC >= TOL ? 'Todavía hay crédito sin pegar' : 'Crédito cubierto');
        $('#acc-count-creditos').text(visiblesCredito.length + (ocultosCredito ? ' · ' + ocultosCredito + ' ocultos' : ''));
        $('#acc-count-deudas').text(visiblesDeuda.length + (ocultosDeuda ? ' · ' + ocultosDeuda + ' ocultos' : ''));

        var credito = creditoById(state.creditoActivo);
        var restoActivo = credito ? round4(credito.saldo - asignadoCredito(credito.id)) : libreC;
        var dcTotal = state.lineas.reduce(function (s, l) { return s + dcDeLinea(l); }, 0);
        $('#acc-dock-aplicar').text(fmt(total));
        $('#acc-dock-resto').text(credito ? fmt(restoActivo) : fmt(Math.max(0, libreC)));
        $('#acc-dock-dc').text(Math.abs(dcTotal) >= TOL ? dcLabel(dcTotal) : '—');
        $('#acc-dock-dc').toggleClass('is-loss', dcTotal > TOL).toggleClass('is-gain', dcTotal < -TOL);
        $('#acc-dock-lineas').text(state.lineas.length);
        var denom = credito ? credito.saldo : (k.creditos || 1);
        var pct = denom > 0 ? Math.min(100, (total / denom) * 100) : 0;
        if (!credito && k.creditos > 0) {
            pct = Math.min(100, (total / k.creditos) * 100);
        }
        $('#acc-dock-bar').toggleClass('is-over', restoActivo < -TOL).find('span').css('width', pct + '%');
        $('#btn-acc-aplicar').prop('disabled', state.lineas.length === 0);
        $('#acc-dock-error').text(restoActivo < -TOL ? 'Hay un crédito sobreaplicado. Bajá el monto.' : '');
        actualizarBotonOtrasEmpresas();
        actualizarBotonOtrasMonedas();
        var pid = proveedorId();
        var $link = $('#acc-link-cc');
        if (pid > 0 && urlCc) {
            $link.attr('href', String(urlCc).replace('__ID__', pid)).removeClass('d-none');
        } else {
            $link.addClass('d-none');
        }
    }

    function kpisDeVista(creditos, deudas) {
        var k = { creditos: 0, deudas: 0, nc: 0, pagos: 0, vencida: 0 };
        creditos.forEach(function (c) {
            k.creditos += Number(c.saldo) || 0;
            if (String(c.tipo || '').toUpperCase() === 'NC') {
                k.nc += Number(c.saldo) || 0;
            } else {
                k.pagos += Number(c.saldo) || 0;
            }
        });
        deudas.forEach(function (d) {
            k.deudas += Number(d.saldo) || 0;
            if (['vencida', '30', '60'].indexOf(d.aging) !== -1) {
                k.vencida += Number(d.saldo) || 0;
            }
        });
        Object.keys(k).forEach(function (key) {
            k[key] = round4(k[key]);
        });
        return k;
    }

    function actualizarBotonOtrasEmpresas() {
        var n = itemsOtrasEmpresas();
        var $btn = $('#btn-acc-otras-empresas');
        $btn.toggleClass('acc-hidden', n === 0 && !state.verOtrasEmpresas)
            .toggleClass('is-on', !!state.verOtrasEmpresas);
        $('#acc-otras-count').text(n);
        $btn.find('.acc-otras-label').text(state.verOtrasEmpresas ? 'Ocultar otras empresas' : 'Ver otras empresas');
        $btn.attr('title', state.verOtrasEmpresas
            ? 'Volver a la vista de la empresa en uso'
            : 'Mostrar comprobantes de otras empresas (aparecen grisados)');
    }

    function actualizarBotonOtrasMonedas() {
        var n = itemsOtrasMonedas();
        var $btn = $('#btn-acc-otras-monedas');
        $btn.toggleClass('acc-hidden', n === 0 && !state.verOtrasMonedas)
            .toggleClass('is-on', !!state.verOtrasMonedas);
        $('#acc-otras-mon-count').text(n);
        $btn.find('.acc-otras-mon-label').text(state.verOtrasMonedas ? 'Ocultar otras monedas' : 'Ver otras monedas');
        $btn.attr('title', state.verOtrasMonedas
            ? 'Volver a la misma moneda del crédito'
            : 'Mostrar facturas en otra moneda para aplicarlas con cotización');
    }

    function asegurarVacioFiltro($body, lado) {
        $body.children('.acc-empty-filtro').remove();
        if (!$body.children('[data-id]').length) {
            return;
        }
        if ($body.children('[data-id]:not(.acc-hidden)').length) {
            return;
        }
        var nEmp = itemsOtrasEmpresas();
        var nMon = itemsOtrasMonedas();
        var msg = lado === 'credito'
            ? 'Sin créditos de esta empresa y moneda.'
            : 'Sin facturas de esta empresa y moneda.';
        if (nMon > 0) {
            msg += ' Hay ' + nMon + ' de otra moneda.';
        }
        if (nEmp > 0) {
            msg += ' Hay ' + nEmp + ' de otras empresas.';
        }
        $body.append('<div class="acc-empty acc-empty-filtro">' + msg + '</div>');
    }

    function pintar() {
        syncLista($('#acc-creditos-body'), state.creditos, 'credito');
        syncLista($('#acc-deudas-body'), state.deudas, 'deuda');
        asegurarVacioFiltro($('#acc-creditos-body'), 'credito');
        asegurarVacioFiltro($('#acc-deudas-body'), 'deuda');
        renderBoard();
        renderRecientes();
        renderKpisDock();
    }

    function aplicarWorkbench(wb, opts) {
        opts = opts || {};
        state.creditos = wb.creditos || [];
        state.deudas = wb.deudas || [];
        state.recientes = wb.recientes || [];
        state.kpis = wb.kpis || state.kpis;
        if (!opts.conservar) {
            state.lineas = [];
            state.omitidosDeuda = {};
            state.omitidosCredito = {};
        }
        if (state.creditoActivo && !creditoById(state.creditoActivo)) {
            state.creditoActivo = 0;
        }
        if (!state.creditoActivo && state.creditos.length) {
            state.creditoActivo = Number(state.creditos[0].id);
        }
        recomponerAuto();
        refrescarCotDia();
        sincronizarCotLiq(creditoById(state.creditoActivo));
        pintar();
    }

    function cargar() {
        var pid = proveedorId();
        if (!pid) {
            aplicarWorkbench({ creditos: [], deudas: [], recientes: [], kpis: { creditos: 0, deudas: 0, nc: 0, pagos: 0, vencida: 0 } });
            return;
        }
        $.getJSON(urlPendientes, { proveedor_id: pid })
            .done(function (res) { aplicarWorkbench(res); })
            .fail(function () { toast('No se pudieron cargar los pendientes', 'error'); });
    }

    function seleccionarCredito(id) {
        state.creditoActivo = Number(id) || 0;
        state.verOtrasMonedas = false;
        sincronizarCotLiq(creditoById(state.creditoActivo));
        pintar();
    }

    function sincronizarCotLiq(c) {
        var $inp = $('#acc-cot-liq');
        var $hint = $('#acc-cot-liq-hint');
        if (!c || esLocal(c.moneda_id)) {
            $hint.text('Para cruzar pesos ↔ dólares');
            return;
        }
        var cot = cotNorm(c.cotizacion);
        if (!$inp.val() && cot > 1) {
            $inp.attr('placeholder', String(cot));
        }
        $hint.text('Pesos por 1 ' + (c.moneda || 'ME') + ' · tildá la factura en pesos para aplicarla');
    }

    function asignarDeuda(deudaId, checked, montoExplicit) {
        var d = deudaById(deudaId);
        if (!d) {
            return;
        }
        if (!checked) {
            quitarDeuda(deudaId);
            pintar();
            return;
        }
        if (state.omitidosDeuda[deudaId] && montoExplicit == null) {
            incluirDeuda(deudaId);
            pintar();
            return;
        }
        if (!state.creditoActivo) {
            toast('Elegí un crédito a la izquierda', 'warning');
            pintar();
            return;
        }
        var c = creditoById(state.creditoActivo);
        if (!c) {
            return;
        }
        if (!esCompatible(c, d)) {
            toast('Ese crédito y esa factura no son de la misma empresa', 'warning');
            pintar();
            return;
        }
        if (esCruzada(c, d) && cotLiqDePar(c, d, null) <= 0) {
            toast('Indicá la cotización de liquidación para cruzar monedas', 'warning');
            pintar();
            return;
        }
        var ya = 0;
        state.lineas.forEach(function (l) {
            if (Number(l.credito_id) === Number(c.id) && Number(l.deuda_id) === Number(d.id)) {
                ya = l.monto;
            }
        });
        var restoC = round4(c.saldo - asignadoCredito(c.id) + (esCruzada(c, d) ? (ya ? liquidar(c, d, ya, null).monto_credito : 0) : ya));
        var restoD = round4(d.saldo - asignadoDeuda(d.id) + ya);
        var maxDeuda = restoD;
        if (esCruzada(c, d)) {
            var cot = cotLiqDePar(c, d, null);
            var equiv = convertirDeudaACredito(1, d.moneda_id, c.moneda_id, cot, c.cotizacion);
            maxDeuda = equiv > 0 ? round4(Math.min(restoD, restoC / equiv)) : restoD;
        } else {
            maxDeuda = Math.min(restoC, restoD);
        }
        var monto = montoExplicit != null ? round4(montoExplicit) : maxDeuda;
        monto = Math.min(monto, maxDeuda);
        upsertManual(c.id, d.id, monto, esCruzada(c, d) ? cotLiqDePar(c, d, null) : null);
        pintar();
    }

    function aplicar() {
        var pid = proveedorId();
        if (!pid || !state.lineas.length) {
            return;
        }
        $('#btn-acc-aplicar').prop('disabled', true);
        var payload = state.lineas.map(function (l) {
            enriquecerLinea(l);
            var row = { credito_id: l.credito_id, deuda_id: l.deuda_id, monto: l.monto };
            var c = creditoById(l.credito_id);
            var d = deudaById(l.deuda_id);
            if (c && d && esCruzada(c, d)) {
                row.cotizacion_liquidacion = cotLiqDePar(c, d, l.cotizacion_liquidacion);
            } else if (l.cotizacion_liquidacion) {
                row.cotizacion_liquidacion = l.cotizacion_liquidacion;
            }
            return row;
        });
        $.ajax({
            url: urlAplicar,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: {
                proveedor_id: pid,
                empresa_id: empresaId(),
                fecha: $('#acc-fecha').val(),
                lineas: payload
            }
        }).done(function (res) {
            toast(res.mensaje || 'Aplicado');
            aplicarWorkbench(res.workbench || {});
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo aplicar';
            toast(msg, 'error');
            $('#acc-dock-error').text(msg);
            $('#btn-acc-aplicar').prop('disabled', false);
        });
    }

    $root.on('click', '.acc-row[data-lado="credito"]', function (e) {
        if ($(e.target).is('input')) {
            return;
        }
        seleccionarCredito(parseInt($(this).data('id'), 10));
    });
    $root.on('change', '.acc-sel-credito', function () {
        seleccionarCredito(parseInt($(this).val(), 10));
    });
    $root.on('change', '.acc-sel-deuda', function () {
        asignarDeuda(parseInt($(this).closest('.acc-row').data('id'), 10), this.checked, null);
    });
    $root.on('click', '.acc-row[data-lado="deuda"]', function (e) {
        if ($(e.target).is('input,button,select')) {
            return;
        }
        var id = parseInt($(this).data('id'), 10);
        if (state.omitidosDeuda[id]) {
            incluirDeuda(id);
            pintar();
            return;
        }
        var $chk = $(this).find('.acc-sel-deuda');
        $chk.prop('checked', !$chk.prop('checked')).trigger('change');
    });
    $root.on('change', '.acc-monto-deuda', function () {
        var id = parseInt($(this).closest('.acc-row').data('id'), 10);
        var monto = parseFloat($(this).val() || '0') || 0;
        asignarDeuda(id, monto > 0, monto);
    });

    $root.on('change', '.acc-board-cot', function () {
        var $pair = $(this).closest('.acc-pair');
        var cid = parseInt($pair.data('credito'), 10);
        var did = parseInt($pair.data('deuda'), 10);
        var cot = parseFloat($(this).val() || '0') || 0;
        var linea = state.lineas.find(function (l) {
            return Number(l.credito_id) === cid && Number(l.deuda_id) === did;
        });
        if (!linea) {
            return;
        }
        upsertManual(cid, did, linea.monto, cot);
        pintar();
    });
    $root.on('change', '.acc-board-monto', function () {
        var $pair = $(this).closest('.acc-pair');
        var cid = parseInt($pair.data('credito'), 10);
        var did = parseInt($pair.data('deuda'), 10);
        var monto = parseFloat($(this).val() || '0') || 0;
        state.creditoActivo = cid;
        if (monto < TOL) {
            state.lineas = state.lineas.filter(function (l) {
                return !(Number(l.credito_id) === cid && Number(l.deuda_id) === did);
            });
            state.omitidosDeuda[did] = true;
            recomponerAuto();
        } else {
            upsertManual(cid, did, monto);
        }
        pintar();
    });
    $root.on('click', '.acc-board-x', function () {
        var $pair = $(this).closest('.acc-pair');
        var did = parseInt($pair.data('deuda'), 10);
        var cid = parseInt($pair.data('credito'), 10);
        state.lineas = state.lineas.filter(function (l) {
            return !(Number(l.credito_id) === cid && Number(l.deuda_id) === did);
        });
        if (!lineasDeDeuda(did).length) {
            state.omitidosDeuda[did] = true;
        }
        recomponerAuto();
        pintar();
    });
    $root.on('click', '.acc-pair', function (e) {
        if ($(e.target).is('input,button')) {
            return;
        }
        seleccionarCredito(parseInt($(this).data('credito'), 10));
    });

    $('#acc-auto').on('change', function () {
        state.autoActivo = this.checked;
        recomponerAuto();
        pintar();
    });
    $('#btn-acc-fifo').on('click', function () {
        state.omitidosDeuda = {};
        state.omitidosCredito = {};
        state.lineas = [];
        state.autoActivo = true;
        $('#acc-auto').prop('checked', true);
        recomponerAuto();
        pintar();
        toast(state.lineas.length ? (state.lineas.length + ' par(es) sugeridos') : 'No hay matching posible', state.lineas.length ? 'success' : 'info');
    });
    $('#btn-acc-parear').on('click', function () {
        var pareos = sugerirParearLocal(state.creditos, state.deudas);
        if (!pareos.length) {
            toast('No hay importes iguales', 'info');
            return;
        }
        pareos.forEach(function (p) {
            upsertManual(p.credito_id, p.deuda_id, p.monto);
        });
        pintar();
        toast(pareos.length + ' par(es) de importe igual fijados');
    });
    $('#btn-acc-limpiar').on('click', function () {
        state.lineas = [];
        state.omitidosDeuda = {};
        state.omitidosCredito = {};
        state.deudas.forEach(function (d) { state.omitidosDeuda[d.id] = true; });
        pintar();
    });
    $('#btn-acc-aplicar').on('click', aplicar);
    $('#acc-buscar-credito, #acc-buscar-deuda, #acc-filtro-deuda').on('input change', function () {
        pintar();
    });
    $('#btn-acc-otras-empresas').on('click', function () {
        state.verOtrasEmpresas = !state.verOtrasEmpresas;
        pintar();
    });
    $('#btn-acc-otras-monedas').on('click', function () {
        state.verOtrasMonedas = !state.verOtrasMonedas;
        if (state.verOtrasMonedas) {
            sincronizarCotLiq(creditoById(state.creditoActivo));
        }
        pintar();
    });

    $('#acc-recientes-body').on('click', '.acc-desaplicar', function () {
        var id = parseInt($(this).data('id'), 10);
        if (!id || !window.confirm('¿Revertir esta aplicación?')) {
            return;
        }
        $.ajax({
            url: String(urlDesaplicar).replace('__ID__', id),
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: { proveedor_id: proveedorId(), empresa_id: empresaId() }
        }).done(function (res) {
            toast(res.mensaje || 'Revertida');
            aplicarWorkbench(res.workbench || {});
        }).fail(function (xhr) {
            toast((xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo desaplicar', 'error');
        });
    });

    function refrescarCotDia() {
        var fecha = $('#acc-fecha').val();
        var monedas = {};
        state.creditos.concat(state.deudas).forEach(function (item) {
            if (item && !esLocal(item.moneda_id)) {
                monedas[item.moneda_id] = true;
            }
        });
        Object.keys(monedas).forEach(function (mid) {
            if (!urlCotizacion || !fecha) {
                return;
            }
            $.getJSON(urlCotizacion, { fecha: fecha, moneda_id: mid })
                .done(function (res) {
                    var cot = parseFloat(res && res.cotizacion);
                    if (cot > 0) {
                        cotDiaCache[String(mid) + '|' + fecha] = cot;
                        if (!$('#acc-cot-liq').val()) {
                            $('#acc-cot-liq-hint').text('Día: ' + cot.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }));
                        }
                    }
                });
        });
    }

    $('#acc-fecha, #acc-cot-liq').on('change', function () {
        if (this.id === 'acc-fecha') {
            refrescarCotDia();
        }
        state.lineas.forEach(function (l) {
            if (l.origen === 'manual') {
                enriquecerLinea(l);
            }
        });
        pintar();
    });

    $('#empresa_id').on('change', function () {
        state.verOtrasEmpresas = false;
        var emp = empresaId();
        if (emp > 0) {
            var actual = creditoById(state.creditoActivo);
            if (!actual || Number(actual.empresa_id) !== emp) {
                var primero = state.creditos.find(function (c) { return Number(c.empresa_id) === emp; });
                state.creditoActivo = primero ? Number(primero.id) : 0;
            }
        }
        recomponerAuto();
        pintar();
    });
    $(document).on('change.cpProveedorCargado', '#proveedor_id', cargar);
    $('#proveedor_id').on('change', cargar);

    if (window.APLICACION_CC_INICIAL) {
        aplicarWorkbench(window.APLICACION_CC_INICIAL);
    } else if (proveedorId()) {
        cargar();
    } else {
        pintar();
    }
}(jQuery));
