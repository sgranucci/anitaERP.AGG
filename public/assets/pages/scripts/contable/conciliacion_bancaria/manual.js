(function () {
    var cfg = window.conciliacionManual || {};
    var prefs = cfg.preferencias || {};
    var tolerancia = Number(cfg.tolerancia) || 0.05;
    var estado = { contables: [], banco: [], pares: [], hashes_contables: [], hashes_banco: [] };
    var mapHashesC = {};
    var mapHashesB = {};
    /** @type {Object.<string, boolean>} */
    var selC = {};
    /** @type {Object.<string, boolean>} */
    var selB = {};
    /**
     * Parejas armadas automáticamente al tildar (clic o Ctrl+clic): cada cheque con su contrapartida.
     * @type {Array<{c: string, b: string}>}
     */
    var parejas = [];
    var focoLado = 'c';
    var focoHash = null;
    var tipoChip = '';
    var tope = 250;
    var periodoCierre = { estado: 'sin_ejecucion' };
    /** @type {File[]} */
    var evidenciaPendiente = [];
    /** @type {Array<{id:number,nombre:string,etapa:string,usuario:?string,fecha:?string}>} */
    var evidencias = [];

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function overlay(mostrar, titulo) {
        var el = document.getElementById('conciliacion-manual-overlay');
        if (!el) return;
        if (titulo) {
            var t = document.getElementById('conciliacion-manual-titulo');
            if (t) t.textContent = titulo;
        }
        el.classList.toggle('d-none', !mostrar);
        el.style.display = mostrar ? 'flex' : '';
        el.setAttribute('aria-hidden', mostrar ? 'false' : 'true');
    }

    function dinero(n) {
        var v = Number(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapar(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function texto(s) {
        return String(s || '').toLowerCase();
    }

    function parseImporteEscrito(raw) {
        var s = String(raw || '').trim().replace(/\s/g, '').replace(/\$/g, '');
        if (s === '') return null;
        var negativo = s.charAt(0) === '-';
        if (s.charAt(0) === '+' || s.charAt(0) === '-') s = s.slice(1);
        if (s === '') return null;
        var coma = s.lastIndexOf(',');
        var punto = s.lastIndexOf('.');
        if (coma !== -1 && punto !== -1) {
            s = coma > punto ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
        } else if (coma !== -1) {
            s = s.replace(',', '.');
        } else if (punto !== -1) {
            var partes = s.split('.');
            var decimales = partes[partes.length - 1];
            if (partes.length > 2 || decimales.length === 3) {
                s = partes.join('');
            }
        }
        var n = Number(s);
        if (!Number.isFinite(n)) return null;
        return negativo ? -n : n;
    }

    function importeFiltro() {
        var n = parseImporteEscrito(document.getElementById('filtro-importe').value);
        return n === null ? null : Math.abs(n);
    }

    function fechaFiltro() {
        var el = document.getElementById('filtro-fecha');
        return el && el.value ? el.value : '';
    }

    function fechaMov(mov, lado) {
        if (lado === 'c') {
            var f = String(mov.fecha || '');
            if (/^\d{4}-\d{2}-\d{2}/.test(f)) return f.substring(0, 10);
            var fmt = String(mov.fecha_fmt || '');
            var m = fmt.match(/^(\d{2})\/(\d{2})\/(\d{2,4})$/);
            if (m) {
                var yy = m[3].length === 2 ? ('20' + m[3]) : m[3];
                return yy + '-' + m[2] + '-' + m[1];
            }
            return '';
        }
        return String(mov.process_date || '').substring(0, 10);
    }

    function difImporte(mov, imp) {
        return Math.abs(Math.abs(Number(mov.importe) || 0) - imp);
    }

    function blobTipo(mov, lado) {
        if (lado === 'c') {
            var b = texto(mov.tipo_comp + ' ' + mov.descripcion + ' ' + mov.nro_asiento_fmt + ' ' + mov.nro);
            // El mayor graba la anulación como OPP "ANULA OPP". En pantalla se busca como AOP.
            if (/\banula\s+opp\b/.test(b)) b += ' aop';
            return b;
        }
        return texto(mov.code_description_ib + ' ' + mov.code_description_bank + ' ' + mov.voucher_number);
    }

    function pasaTipo(mov, lado) {
        if (!tipoChip) return true;
        var b = blobTipo(mov, lado);
        if (tipoChip === 'cheque') {
            return /\bch\b|cheque|chp|chd|ch dep|ch\/pag/.test(b) || texto(mov.tipo_comp || '').indexOf('ch') !== -1;
        }
        if (tipoChip === 'trf') {
            return /trf|transf|transfer/.test(b) || ['OPP', 'OPA', 'TRF'].indexOf(String(mov.tipo_comp || '').toUpperCase()) !== -1;
        }
        if (tipoChip === 'dep') {
            return /\bdep\b|idép|idep|acred|depósito|deposito/.test(b) || String(mov.tipo_comp || '').toUpperCase() === 'ING';
        }
        if (tipoChip === 'gasto') {
            return /deb|gasto|comision|comisión|sell|iva|int\.|manten|egr/.test(b)
                || String(mov.tipo_comp || '').toUpperCase() === 'EGR'
                || String(mov.debit_credit_type || '').toUpperCase() === 'D';
        }
        return true;
    }

    function rebuildHashMaps() {
        mapHashesC = {};
        mapHashesB = {};
        (estado.hashes_contables || []).forEach(function (h) { mapHashesC[h] = true; });
        (estado.hashes_banco || []).forEach(function (h) { mapHashesB[h] = true; });
        (estado.pares || []).forEach(function (p) {
            if (p.hash_contable) mapHashesC[p.hash_contable] = true;
            if (p.hash_banco) mapHashesB[p.hash_banco] = true;
        });
    }

    var chequesAopCache = null;

    function esBusquedaAop(q) {
        return q === 'aop';
    }

    /** Cheques que tienen una ANULA OPP en el tablero. Al buscar "aop" se muestra también el original. */
    function chequesConAnulacion() {
        if (chequesAopCache) return chequesAopCache;
        var set = {};
        var soloPend = document.getElementById('solo-pendientes').checked;
        (estado.contables || []).forEach(function (m) {
            if (!/\banula\s+opp\b/i.test(String(m.descripcion || ''))) return;
            if (soloPend && mapHashesC[m.hash]) return;
            var ref = refCheque(m, 'c');
            if (!ref || /^0+$/.test(ref)) return;
            set[ref] = true;
        });
        chequesAopCache = set;
        return set;
    }

    function esOriginalDeAnulacion(mov) {
        if (/\banula\s+opp\b/i.test(String(mov.descripcion || ''))) return false;
        var ref = refCheque(mov, 'c');
        return !!(ref && chequesConAnulacion()[ref]);
    }

    function pasa(mov, lado, ignorarConciliados) {
        var q = texto(document.getElementById('filtro-texto').value);
        var blob = blobTipo(mov, lado);
        var parejaAop = lado === 'c' && esBusquedaAop(q) && esOriginalDeAnulacion(mov);
        if (q && blob.indexOf(q) === -1 && !parejaAop) return false;
        var fFiltro = fechaFiltro();
        // Si el texto encuentra el movimiento (número de cheque, ANULA, AOP) o es el cheque
        // original de esa anulación, se muestra aunque caiga en otro día.
        var textoManda = (q !== '' && lado === 'c' && blob.indexOf(q) !== -1) || parejaAop;
        if (fFiltro && !textoManda) {
            var fMov = fechaMov(mov, lado);
            // Con "Incluir anteriores": el banco queda en el día elegido;
            // anitaERP muestra ese día y asientos previos (cheques, etc.).
            if (lado === 'c' && document.getElementById('incluir-anteriores').checked) {
                if (fMov && fMov > fFiltro) return false;
            } else if (fMov !== fFiltro) {
                return false;
            }
        }
        if (!pasaTipo(mov, lado)) return false;
        if (!ignorarConciliados && document.getElementById('solo-pendientes').checked) {
            var usados = lado === 'c' ? mapHashesC : mapHashesB;
            if (usados[mov.hash]) return false;
        }
        return true;
    }

    function porImporte(lista, imp) {
        if (imp === null) return lista;
        var exactos = lista.filter(function (m) { return difImporte(m, imp) <= 0.02; });
        if (exactos.length) return exactos;
        var margen = Math.min(100, Math.max(1, imp * 0.0002));
        return lista.filter(function (m) { return difImporte(m, imp) <= margen; })
            .sort(function (a, b) { return difImporte(a, imp) - difImporte(b, imp); });
    }

    function listaVisible(lado) {
        var fuente = lado === 'c' ? estado.contables : estado.banco;
        var mapa = lado === 'c' ? selC : selB;
        var imp = importeFiltro();
        var lista = porImporte(fuente.filter(function (m) { return pasa(m, lado); }), imp);
        // Seleccionados / sugeridos arriba y visibles aunque el filtro de importe los oculte.
        var pinned = [];
        var rest = [];
        var enLista = {};
        lista.forEach(function (m) {
            enLista[m.hash] = true;
            if (mapa[m.hash] || m._sugerido) pinned.push(m);
            else rest.push(m);
        });
        Object.keys(mapa).forEach(function (h) {
            if (!mapa[h] || enLista[h]) return;
            var m = buscar(fuente, h);
            // El importe no lo esconde; el texto sí. Si no, queda tildado un cheque que ya no se está buscando.
            if (m && pasa(m, lado)) {
                m._sugerido = true;
                pinned.unshift(m);
            }
        });
        var salida = pinned.concat(rest);
        if (lado === 'c' && esBusquedaAop(texto(document.getElementById('filtro-texto').value))) {
            salida = salida.slice().sort(function (a, b) {
                var ra = refCheque(a, 'c') || '\uffff';
                var rb = refCheque(b, 'c') || '\uffff';
                if (ra !== rb) return ra < rb ? -1 : 1;
                var aa = /\banula\s+opp\b/i.test(String(a.descripcion || '')) ? 1 : 0;
                var bb = /\banula\s+opp\b/i.test(String(b.descripcion || '')) ? 1 : 0;
                return aa - bb;
            });
        }
        return salida;
    }

    function buscar(lista, hash) {
        for (var i = 0; i < lista.length; i++) {
            if (lista[i].hash === hash) return lista[i];
        }
        return null;
    }

    function seleccionados(lado) {
        var mapa = lado === 'c' ? selC : selB;
        var fuente = lado === 'c' ? estado.contables : estado.banco;
        var out = [];
        Object.keys(mapa).forEach(function (h) {
            if (!mapa[h]) return;
            var m = buscar(fuente, h);
            if (m) out.push(m);
        });
        return out;
    }

    function sumaSel(lado) {
        return seleccionados(lado).reduce(function (acc, m) {
            return acc + (Number(m.importe) || 0);
        }, 0);
    }

    function countSel(mapa) {
        var n = 0;
        Object.keys(mapa).forEach(function (k) { if (mapa[k]) n++; });
        return n;
    }

    function limpiarSugeridos() {
        estado.contables.forEach(function (m) { m._sugerido = false; });
        estado.banco.forEach(function (m) { m._sugerido = false; });
    }

    function digitosRef(s) {
        return String(s == null ? '' : s).replace(/\D+/g, '');
    }

    function refCheque(mov, lado) {
        if (lado === 'b') {
            return digitosRef(mov.voucher_number);
        }
        var m = String(mov.descripcion || '').match(/\bCh:\s*(\d{5,12})\b/i);
        return m ? digitosRef(m[1]) : '';
    }

    function marcarSugeridosDesdeBase() {
        limpiarSugeridos();
        var basesC = seleccionados('c');
        var basesB = seleccionados('b');
        var base = null;
        var otra = null;
        var usados = null;
        var ladoOtra = null;
        if (basesC.length === 1 && basesB.length === 0) {
            base = basesC[0];
            otra = estado.banco;
            usados = mapHashesB;
            ladoOtra = 'b';
        } else if (basesB.length === 1 && basesC.length === 0) {
            base = basesB[0];
            otra = estado.contables;
            usados = mapHashesC;
            ladoOtra = 'c';
        } else {
            return;
        }
        var refBase = refCheque(base, ladoOtra === 'b' ? 'c' : 'b');
        otra.forEach(function (m) {
            if (usados[m.hash]) return;
            var mismoImp = Math.abs(Math.abs(m.importe) - Math.abs(base.importe)) <= tolerancia;
            var mismaRef = refBase && refCheque(m, ladoOtra) === refBase;
            if (mismoImp || mismaRef) m._sugerido = true;
        });
    }

    function distanciaDias(a, b) {
        var ta = Date.parse(a);
        var tb = Date.parse(b);
        if (isNaN(ta) || isNaN(tb)) return 1e6;
        return Math.abs(ta - tb) / 86400000;
    }

    /**
     * Busca el mejor candidato del otro lado (prioridad: misma ref de cheque, luego importe).
     * @return {{mejor: object, diff: number, porRef: boolean}|null}
     */
    function buscarMejorPar(base, ladoBase, opciones) {
        opciones = opciones || {};
        var excluir = opciones.excluir || {};
        var estricto = !!opciones.estricto;
        var otra = ladoBase === 'c' ? estado.banco : estado.contables;
        var usados = ladoBase === 'c' ? mapHashesB : mapHashesC;
        var ladoOtra = ladoBase === 'c' ? 'b' : 'c';
        var refBase = refCheque(base, ladoBase);
        if (/^0*$/.test(refBase)) refBase = '';
        var mejor = null;
        var mejorDiff = 1e12;
        var mejorPorRef = null;
        otra.forEach(function (m) {
            if (usados[m.hash] || excluir[m.hash]) return;
            if (estricto) {
                // Con varios cheques a la vez no se cruzan dos cheques con número distinto aunque el importe coincida.
                var refM = refCheque(m, ladoOtra);
                if (/^0*$/.test(refM)) refM = '';
                if (refBase && refM && refBase !== refM) return;
            }
            var d = Math.abs(Math.abs(m.importe) - Math.abs(base.importe));
            if (refBase && refCheque(m, ladoOtra) === refBase) {
                if (!mejorPorRef || d < Math.abs(Math.abs(mejorPorRef.importe) - Math.abs(base.importe))) {
                    mejorPorRef = m;
                }
            }
            // Mismo importe (transferencias iguales): gana la de fecha más cercana.
            var empate = mejor && Math.abs(d - mejorDiff) <= 0.005
                && distanciaDias(fechaMov(m, ladoOtra), fechaMov(base, ladoBase))
                    < distanciaDias(fechaMov(mejor, ladoOtra), fechaMov(base, ladoBase));
            if (d < mejorDiff - 0.005 || empate) {
                mejorDiff = d;
                mejor = m;
            }
        });
        if (mejorPorRef) {
            mejor = mejorPorRef;
            mejorDiff = Math.abs(Math.abs(mejor.importe) - Math.abs(base.importe));
        }
        if (!mejor || (mejorDiff > 1 && !mejorPorRef)) {
            return null;
        }
        return { mejor: mejor, diff: mejorDiff, porRef: !!mejorPorRef };
    }

    /** Tilda el par 1:1 sin conciliar; Enter / Conciliar confirma. */
    function aplicarParSugerido(base, ladoBase, mejor) {
        limpiarSugeridos();
        base._sugerido = true;
        mejor._sugerido = true;
        parejas = [ladoBase === 'c' ? { c: base.hash, b: mejor.hash } : { c: mejor.hash, b: base.hash }];
        if (ladoBase === 'c') {
            selC = {};
            selC[base.hash] = true;
            selB = {};
            selB[mejor.hash] = true;
            focoLado = 'b';
            focoHash = mejor.hash;
        } else {
            selB = {};
            selB[base.hash] = true;
            selC = {};
            selC[mejor.hash] = true;
            focoLado = 'c';
            focoHash = mejor.hash;
        }
        pintar();
        resaltarParEnListas();
        document.getElementById('estado-cruce').textContent =
            'Par listo (' + dinero(mejor.importe) + '). Enter o Conciliar para confirmar · Esc limpia.';
    }

    /**
     * @param {{silencio?: boolean}} [opciones]
     * @return {boolean}
     */
    function sugerir(opciones) {
        opciones = opciones || {};
        var silencio = !!opciones.silencio;
        var c = seleccionados('c');
        var b = seleccionados('b');
        if (c.length === 1 && b.length === 1) {
            limpiarSugeridos();
            c[0]._sugerido = true;
            b[0]._sugerido = true;
            focoLado = 'c';
            focoHash = c[0].hash;
            pintar();
            resaltarParEnListas();
            document.getElementById('estado-cruce').textContent =
                'Ya hay un par 1:1. Enter o Conciliar para confirmar · Esc limpia.';
            return true;
        }
        var base = null;
        var ladoBase = null;
        if (c.length === 1 && b.length === 0) {
            base = c[0];
            ladoBase = 'c';
        } else if (b.length === 1 && c.length === 0) {
            base = b[0];
            ladoBase = 'b';
        } else if (c.length === 0 && b.length === 0 && focoHash) {
            base = buscar(focoLado === 'c' ? estado.contables : estado.banco, focoHash);
            ladoBase = focoLado;
        }
        if (!base) {
            if (!silencio) alert('Elegí un solo movimiento (de un lado) para buscar el par.');
            return false;
        }
        var hallado = buscarMejorPar(base, ladoBase);
        if (!hallado) {
            if (!silencio) alert('No hay un importe cercano entre los pendientes.');
            return false;
        }
        aplicarParSugerido(base, ladoBase, hallado.mejor);
        return true;
    }

    function filaHtml(mov, lado, imp) {
        var mapa = lado === 'c' ? selC : selB;
        var activo = !!mapa[mov.hash];
        var sugerido = mov._sugerido ? ' cm-sugerido' : '';
        var marca = '';
        if (lado === 'c' && /\banula\s+opp\b/i.test(String(mov.descripcion || ''))) {
            marca += ' <span class="badge badge-danger">AOP</span>';
        }
        if (mov.anterior) marca += ' <span class="badge badge-secondary">anterior</span>';
        if (mov.fecha_extra) marca += ' <span class="badge badge-info">día extra</span>';
        if (mov._sugerido) marca += ' <span class="badge badge-warning">sugerido</span>';
        var dif = (imp != null && difImporte(mov, imp) > 0.02)
            ? ' <span class="small text-muted">dif. ' + dinero(difImporte(mov, imp)) + '</span>'
            : '';
        var det = lado === 'c'
            ? escapar((mov.tipo_comp || '') + ' ' + (mov.nro_asiento_fmt || '') + ' ' + (mov.descripcion || ''))
            : escapar((mov.code_description_ib || '') + ' ' + (mov.voucher_number || '') + ' ' + (mov.code_description_bank || ''));
        var fecha = escapar(lado === 'c' ? (mov.fecha_fmt || mov.fecha || '') : String(mov.process_date || '').substring(0, 10));
        var cls = 'px-2 py-1 cm-fila js-fila' + (activo ? ' cm-activo' : '') + sugerido;
        return '<div class="' + cls + '" data-lado="' + lado + '" data-hash="' + mov.hash + '" tabindex="-1">'
            + '<div class="d-flex align-items-start">'
            + '<input type="checkbox" class="cm-check js-check" ' + (activo ? 'checked' : '') + ' tabindex="-1">'
            + '<div class="flex-grow-1" style="min-width:0;">'
            + '<div class="d-flex justify-content-between"><span>' + fecha + marca + '</span><span><strong>' + dinero(mov.importe) + '</strong>' + dif + '</span></div>'
            + '<div class="small text-muted text-truncate">' + det + '</div>'
            + '</div></div></div>';
    }

    function labelModo(modo) {
        if (modo === 'cierre_contaduria') return 'cierre Contaduría';
        if (modo === 'manual_multi') return 'multi';
        if (modo === 'manual') return 'manual';
        return modo || 'auto';
    }

    function filasDetalleMov(mov, lado) {
        if (lado === 'c') {
            var rows = [
                ['Fecha', escapar(mov.fecha_fmt || mov.fecha || '')],
                ['Tipo', escapar(mov.tipo_comp || '')],
                ['Asiento', escapar(mov.nro_asiento_fmt || '')],
                ['Nro', escapar(mov.nro || '')],
                ['Importe', dinero(mov.importe)],
                ['Debe', dinero(mov.debe)],
                ['Haber', dinero(mov.haber)],
                ['Descripción', escapar(mov.descripcion || '')],
            ];
            if (mov.anterior) rows.push(['Origen', 'Pendiente anterior']);
            return rows;
        }
        return [
            ['Fecha', escapar(String(mov.process_date || '').substring(0, 10))],
            ['Concepto IB', escapar(mov.code_description_ib || '')],
            ['Concepto banco', escapar(mov.code_description_bank || '')],
            ['Comprobante', escapar(mov.voucher_number || '')],
            ['D/C', escapar(mov.debit_credit_type || '')],
            ['Importe', dinero(mov.importe)],
            ['Monto extracto', dinero(mov.amount)],
        ];
    }

    function htmlDetalleKv(rows) {
        return '<dl class="cm-detalle-kv mb-0">'
            + rows.map(function (r) {
                return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>';
            }).join('')
            + '</dl>';
    }

    function pintarDetalle() {
        var panel = document.getElementById('panel-detalle');
        var ladoLbl = document.getElementById('detalle-lado');
        var cSel = seleccionados('c');
        var bSel = seleccionados('b');

        if (cSel.length === 1 && bSel.length === 1) {
            var diff = Math.round((cSel[0].importe - bSel[0].importe) * 100) / 100;
            ladoLbl.textContent = 'Par sugerido';
            panel.innerHTML = '<p class="small mb-2 '
                + (Math.abs(diff) <= tolerancia ? 'text-success' : 'text-warning') + '">'
                + (Math.abs(diff) <= tolerancia
                    ? 'Par listo. Tocá el botón verde <strong>Conciliar</strong> o apretá <kbd>Enter</kbd>.'
                    : 'Importes distintos (dif. ' + dinero(diff) + '). Conciliar / Enter pide confirmación.')
                + '</p>'
                + '<div class="cm-par-bloque mb-2"><div class="small font-weight-bold text-muted mb-1">anitaERP</div>'
                + htmlDetalleKv(filasDetalleMov(cSel[0], 'c')) + '</div>'
                + '<div class="cm-par-bloque"><div class="small font-weight-bold text-muted mb-1">Banco</div>'
                + htmlDetalleKv(filasDetalleMov(bSel[0], 'b')) + '</div>';
            return;
        }

        var mov = null;
        var lado = focoLado;
        if (focoHash) {
            mov = buscar(lado === 'c' ? estado.contables : estado.banco, focoHash);
        }
        if (!mov) {
            var sels = cSel.concat(bSel);
            if (sels.length === 1) {
                mov = sels[0];
                lado = cSel.length ? 'c' : 'b';
            }
        }
        if (!mov) {
            ladoLbl.textContent = '';
            panel.innerHTML = '<p class="text-muted small mb-0">Elegí un movimiento para ver el detalle. Clic = selección exclusiva · Ctrl+clic = multi-selección.</p>';
            return;
        }
        ladoLbl.textContent = lado === 'c' ? 'anitaERP' : 'Banco';
        panel.innerHTML = htmlDetalleKv(filasDetalleMov(mov, lado));
    }

    function filaPorHash(lado, hash) {
        return document.querySelector('.js-fila[data-lado="' + lado + '"][data-hash="' + hash + '"]');
    }

    function resaltarParEnListas() {
        ['c', 'b'].forEach(function (lado) {
            var mapa = lado === 'c' ? selC : selB;
            Object.keys(mapa).forEach(function (h) {
                if (!mapa[h]) return;
                var el = filaPorHash(lado, h);
                if (!el) return;
                el.classList.add('cm-flash');
                if (el.scrollIntoView) {
                    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
                setTimeout(function () { el.classList.remove('cm-flash'); }, 1800);
            });
        });
        if (focoHash) {
            var focoEl = filaPorHash(focoLado, focoHash);
            if (focoEl && typeof focoEl.focus === 'function') focoEl.focus();
        }
    }

    function actualizarTotales() {
        var sumC = sumaSel('c');
        var sumB = sumaSel('b');
        var nC = countSel(selC);
        var nB = countSel(selB);
        var diff = Math.round((sumC - sumB) * 100) / 100;
        var elDiff = document.getElementById('tot-diff');
        document.getElementById('tot-sel-c').textContent = dinero(sumC);
        document.getElementById('tot-sel-b').textContent = dinero(sumB);
        document.getElementById('tot-sel-c-n').textContent = nC ? '(' + nC + ')' : '';
        document.getElementById('tot-sel-b-n').textContent = nB ? '(' + nB + ')' : '';
        elDiff.textContent = dinero(diff);
        elDiff.classList.remove('cm-diff-ok', 'cm-diff-mal');
        if (nC + nB > 0) {
            elDiff.classList.add(Math.abs(diff) <= tolerancia ? 'cm-diff-ok' : 'cm-diff-mal');
        }

        var totalConc = (estado.pares || []).reduce(function (acc, p) {
            return acc + (Number(p.importe) || 0);
        }, 0);
        document.getElementById('tot-conciliado').textContent = dinero(totalConc);
        var paresN = document.getElementById('tot-pares-n');
        if (paresN) paresN.textContent = String((estado.pares || []).length);

        var aviso = document.getElementById('aviso-multimatch');
        var btnConc = document.getElementById('btn-conciliar');
        var listo = nC > 0 && nB > 0 && Math.abs(diff) <= tolerancia;
        // Orden de pago + su "ANULA OPP": dos o más contables que suman cero, sin banco.
        var anulacionOp = nB === 0 && nC >= 2 && Math.abs(sumC) <= tolerancia;
        if (anulacionOp) listo = true;
        // Varios cheques con su pareja automática: cada pareja se evalúa sola (no la suma total).
        var lotePares = parejas.length > 1 && nC === parejas.length && nB === parejas.length && seleccionSoloParejas();
        if (lotePares) {
            var difMax = 0;
            parejas.forEach(function (p) {
                var mc = buscar(estado.contables, p.c);
                var mb = buscar(estado.banco, p.b);
                if (mc && mb) {
                    difMax = Math.max(difMax, Math.abs(Math.abs(Number(mc.importe) || 0) - Math.abs(Number(mb.importe) || 0)));
                }
            });
            listo = difMax <= tolerancia;
        }
        if (btnConc) {
            btnConc.classList.toggle('cm-conciliar-listo', listo);
            btnConc.innerHTML = listo
                ? 'Conciliar <span class="cm-enter-hint">Enter</span>'
                : 'Conciliar';
        }
        if (lotePares) {
            if (listo) {
                aviso.innerHTML = parejas.length + ' pares listos (cada cheque con su contrapartida) — <strong>Conciliar</strong> o <kbd>Enter</kbd> los guarda todos';
                aviso.className = 'small text-success font-weight-bold';
            } else {
                aviso.innerHTML = parejas.length + ' pares con diferencia de importe — <strong>Conciliar</strong> / <kbd>Enter</kbd> pide confirmación.';
                aviso.className = 'small text-warning';
            }
        } else if (nC === 1 && nB === 1) {
            if (Math.abs(diff) <= tolerancia) {
                aviso.innerHTML = 'Par listo — <strong>Conciliar</strong> o <kbd>Enter</kbd>';
                aviso.className = 'small text-success font-weight-bold';
            } else {
                aviso.innerHTML = 'Importes distintos — <strong>Conciliar</strong> / <kbd>Enter</kbd> pide confirmación.';
                aviso.className = 'small text-warning';
            }
        } else if (nC > 0 && nB > 0 && (nC > 1 || nB > 1)) {
            if (Math.abs(diff) <= tolerancia) {
                aviso.innerHTML = 'Sumas iguales (' + nC + ':' + nB + ') — <strong>Conciliar</strong> o <kbd>Enter</kbd>';
                aviso.className = 'small text-success font-weight-bold';
            } else {
                aviso.textContent = 'Multi-selección ' + nC + ':' + nB + ' — diferencia ' + dinero(diff) + '.';
                aviso.className = 'small text-muted';
            }
        } else if (anulacionOp) {
            aviso.innerHTML = 'Orden de pago y su anulación se compensan (' + nC + ' contables, sin banco) — <strong>Conciliar</strong> o <kbd>Enter</kbd>';
            aviso.className = 'small text-success font-weight-bold';
        } else if (nC + nB > 0) {
            aviso.textContent = 'Elegí al menos uno del otro lado.';
            aviso.className = 'small text-muted';
        } else {
            aviso.textContent = '';
            aviso.className = 'small';
        }
        pintarCierre();
    }

    function pintarCierre() {
        var badge = document.getElementById('badge-cierre');
        var btnCerrar = document.getElementById('btn-cerrar-periodo');
        var btnReabrir = document.getElementById('btn-reabrir-periodo');
        var btnAprobar = document.getElementById('btn-aprobar-periodo');
        if (!badge) return;
        var estado = (periodoCierre && periodoCierre.estado) || 'sin_ejecucion';
        if (btnAprobar) btnAprobar.classList.add('d-none');
        if (estado === 'aprobado') {
            badge.textContent = 'Aprobado' + (periodoCierre.aprobado_at ? ' ' + periodoCierre.aprobado_at : '');
            badge.className = 'badge badge-primary ml-1';
            if (btnCerrar) btnCerrar.classList.add('d-none');
            if (btnReabrir) btnReabrir.classList.add('d-none');
        } else if (estado === 'cerrado') {
            badge.textContent = 'Cerrado' + (periodoCierre.cerrado_at ? ' ' + periodoCierre.cerrado_at : '');
            badge.className = 'badge badge-success ml-1';
            if (btnCerrar) btnCerrar.classList.add('d-none');
            if (btnReabrir) btnReabrir.classList.remove('d-none');
            if (btnAprobar && cfg.puedeAprobar) btnAprobar.classList.remove('d-none');
        } else {
            badge.textContent = estado === 'sin_ejecucion' ? 'Sin corrida automática' : 'Período abierto';
            badge.className = 'badge badge-light ml-1';
            if (btnCerrar) btnCerrar.classList.remove('d-none');
            if (btnReabrir) btnReabrir.classList.add('d-none');
        }
        pintarEvidencias();
    }

    function pintarEvidencias() {
        var ul = document.getElementById('lista-evidencias');
        var ayuda = document.getElementById('evidencia-ayuda');
        if (!ul) return;
        var html = '';
        evidencias.forEach(function (a) {
            var href = (cfg.descargarEvidenciaUrl || '') + '/' + a.id;
            html += '<li class="mb-1">'
                + '<a href="' + escapar(href) + '" target="_blank" rel="noopener" class="text-primary">'
                + '<i class="fa fa-file"></i> ' + escapar(a.nombre) + '</a>'
                + ' <span class="badge badge-secondary">' + escapar(a.etapa) + '</span>'
                + (a.fecha ? ' <span class="text-muted">' + escapar(a.fecha) + '</span>' : '')
                + (a.usuario ? ' · ' + escapar(a.usuario) : '')
                + '</li>';
        });
        if (evidenciaPendiente.length) {
            evidenciaPendiente.forEach(function (f) {
                html += '<li class="mb-1 text-info"><i class="fa fa-clock"></i> Pendiente: '
                    + escapar(f.name) + ' <span class="text-muted">(se sube al cerrar/aprobar)</span></li>';
            });
        }
        if (!html) {
            html = '<li class="text-muted">Sin adjuntos todavía.</li>';
        }
        ul.innerHTML = html;
        if (ayuda) {
            var eid = periodoCierre && periodoCierre.ejecucion_id;
            ayuda.textContent = eid
                ? 'Ejecución #' + eid + ' — Adjuntar guarda ya; o sumá archivos y cerrá/aprobá.'
                : 'Elegí archivos y cerrá el período (o Adjuntar tras la primera corrida).';
        }
    }

    function cargarEvidencias() {
        var eid = periodoCierre && periodoCierre.ejecucion_id;
        if (!eid || !cfg.listarEvidenciaUrl) {
            evidencias = [];
            pintarEvidencias();
            return;
        }
        fetch(cfg.listarEvidenciaUrl + '?ejecucion_id=' + eid, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.ok) {
                evidencias = data.evidencias || [];
            }
            pintarEvidencias();
        }).catch(function () {
            pintarEvidencias();
        });
    }

    function appendEvidenciasAFormData(fd) {
        evidenciaPendiente.forEach(function (f) {
            fd.append('evidencias[]', f);
        });
    }

    function etapaEvidenciaActual() {
        var est = (periodoCierre && periodoCierre.estado) || '';
        if (est === 'cerrado' || est === 'aprobado') return 'aprobacion';
        return 'cierre';
    }

    function adjuntarEvidenciaAhora(files) {
        var eid = periodoCierre && periodoCierre.ejecucion_id;
        if (!eid) {
            Array.prototype.forEach.call(files || [], function (f) {
                evidenciaPendiente.push(f);
            });
            pintarEvidencias();
            return;
        }
        if (!cfg.adjuntarEvidenciaUrl) return;
        overlay(true, 'Adjuntando evidencia…');
        var fd = new FormData();
        fd.append('ejecucion_id', eid);
        fd.append('etapa', etapaEvidenciaActual());
        Array.prototype.forEach.call(files || [], function (f) {
            fd.append('evidencias[]', f);
        });
        fetch(cfg.adjuntarEvidenciaUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo adjuntar');
            evidencias = data.evidencias || evidencias;
            pintarEvidencias();
            overlay(false);
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function actualizarEstado() {
        var el = document.getElementById('estado-cruce');
        var nC = countSel(selC);
        var nB = countSel(selB);
        if (!nC && !nB) {
            el.textContent = 'Tildá un movimiento: se sugiere el par. Enter confirma · Esc limpia · Ctrl+clic multi.';
            return;
        }
        el.textContent = 'Selección ' + nC + ' contable / ' + nB + ' banco · dif. ' + dinero(sumaSel('c') - sumaSel('b'));
    }

    function soltarSeleccionFueraDeTexto() {
        var q = texto(document.getElementById('filtro-texto').value);
        if (!q) return;
        function fuera(mapa, lado) {
            var fuente = lado === 'c' ? estado.contables : estado.banco;
            Object.keys(mapa).forEach(function (h) {
                if (!mapa[h]) return;
                var m = buscar(fuente, h);
                if (m && (blobTipo(m, lado).indexOf(q) !== -1 || (lado === 'c' && esBusquedaAop(q) && esOriginalDeAnulacion(m)))) return;
                delete mapa[h];
                parejas.forEach(function (p) {
                    if ((lado === 'c' ? p.c : p.b) !== h) return;
                    if (lado === 'c') delete selB[p.b];
                    else delete selC[p.c];
                });
                parejas = parejas.filter(function (p) { return (lado === 'c' ? p.c : p.b) !== h; });
            });
        }
        fuera(selC, 'c');
        fuera(selB, 'b');
    }

    function pintar() {
        chequesAopCache = null;
        soltarSeleccionFueraDeTexto();
        var imp = importeFiltro();
        var cont = listaVisible('c');
        var ban = listaVisible('b');
        var pendC = estado.contables.filter(function (m) { return !mapHashesC[m.hash]; }).length;
        var pendB = estado.banco.filter(function (m) { return !mapHashesB[m.hash]; }).length;
        document.getElementById('cuenta-contable').textContent = cont.length + ' visibles · ' + pendC + ' pend. / ' + estado.contables.length;
        document.getElementById('cuenta-banco').textContent = ban.length + ' visibles · ' + pendB + ' pend. / ' + estado.banco.length;
        document.getElementById('lista-contable').innerHTML = cont.slice(0, tope).map(function (m) { return filaHtml(m, 'c', imp); }).join('')
            || '<div class="p-3 text-muted">Sin movimientos con este filtro.</div>';
        document.getElementById('lista-banco').innerHTML = ban.slice(0, tope).map(function (m) { return filaHtml(m, 'b', imp); }).join('')
            || '<div class="p-3 text-muted">Sin movimientos con este filtro.</div>';

        var aviso = document.getElementById('estado-cruce');
        var aprox = imp !== null && (cont.concat(ban)).some(function (m) { return difImporte(m, imp) > 0.02; });
        if (imp !== null && cont.length === 0 && ban.length === 0) {
            var ocultos = porImporte(estado.contables.filter(function (m) { return pasa(m, 'c', true); }), imp).length
                || porImporte(estado.banco.filter(function (m) { return pasa(m, 'b', true); }), imp).length;
            aviso.textContent = ocultos
                ? 'Ese importe ya está conciliado (puede ser de otro mes). Destildá Solo pendientes para verlo.'
                : 'No hay movimientos por ' + dinero(imp) + '.';
        } else if (aprox) {
            var mejor = cont.concat(ban).slice().sort(function (a, b) { return difImporte(a, imp) - difImporte(b, imp); })[0];
            aviso.textContent = 'No hay ' + dinero(imp) + ' exacto. El más cercano es ' + dinero(mejor.importe) + ' (diferencia ' + dinero(difImporte(mejor, imp)) + ').';
        } else if (cont.length > tope || ban.length > tope) {
            aviso.textContent = 'Mostrando ' + tope + '. Afiná el filtro para ver el resto.';
        } else {
            actualizarEstado();
        }

        var pares = estado.pares.map(function (p) {
            var esGrupo = p.tipo === 'grupo';
            var badgeMatch = esGrupo
                ? ' <span class="badge badge-info">' + (p.n_contable || '?') + ':' + (p.n_banco || '?') + '</span>'
                : '';
            var dataAttr = esGrupo
                ? 'data-grupo-id="' + p.grupo_id + '"'
                : 'data-par-id="' + (p.par_id || p.id) + '"';
            return '<div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center">'
                + '<span class="small">' + escapar((p.fecha_contable || '') + ' ' + (p.detalle_contable || '') + ' ↔ ' + (p.fecha_banco || '') + ' ' + (p.detalle_banco || ''))
                + ' <span class="badge badge-light">' + escapar(labelModo(p.modo)) + '</span>' + badgeMatch + '</span>'
                + '<span><strong class="mr-2">' + dinero(p.importe) + '</strong>'
                + '<button type="button" class="btn btn-outline-danger btn-sm js-desconciliar" ' + dataAttr + '>Desconciliar</button></span></div>';
        }).join('');
        document.getElementById('lista-pares').innerHTML = pares || '<div class="p-2 text-muted">Todavía no hay cruces en el mes.</div>';

        actualizarTotales();
        pintarDetalle();
    }

    /** true si todo lo tildado hasta ahora (ambos lados) pertenece a parejas armadas automáticamente. */
    function seleccionSoloParejas() {
        var enC = {};
        var enB = {};
        parejas.forEach(function (p) { enC[p.c] = true; enB[p.b] = true; });
        var ok = true;
        Object.keys(selC).forEach(function (h) { if (selC[h] && !enC[h]) ok = false; });
        Object.keys(selB).forEach(function (h) { if (selB[h] && !enB[h]) ok = false; });
        return ok;
    }

    /** Busca y tilda la contrapartida del movimiento recién tildado (misma ref de cheque o mismo importe). */
    function autoParear(lado, hash) {
        var base = buscar(lado === 'c' ? estado.contables : estado.banco, hash);
        if (!base) return;
        var mapaOtra = lado === 'c' ? selB : selC;
        var hallado = buscarMejorPar(base, lado, { excluir: mapaOtra, estricto: true });
        if (!hallado) return;
        mapaOtra[hallado.mejor.hash] = true;
        parejas.push(lado === 'c' ? { c: hash, b: hallado.mejor.hash } : { c: hallado.mejor.hash, b: hash });
        base._sugerido = true;
        hallado.mejor._sugerido = true;
    }

    function seleccionar(lado, hash, multi) {
        var mapa = lado === 'c' ? selC : selB;
        var activoAntes = !!mapa[hash];
        if (!multi) {
            // Clic en el tildado → limpia el par entero (no re-sugerir).
            if (activoAntes) {
                selC = {};
                selB = {};
                parejas = [];
                focoLado = lado;
                focoHash = null;
                limpiarSugeridos();
                pintar();
                return;
            }
            // Clic en otra fila: base exclusiva y rearmar par del otro lado.
            selC = {};
            selB = {};
            parejas = [];
            mapa = lado === 'c' ? selC : selB;
            mapa[hash] = true;
        } else if (activoAntes) {
            // Ctrl+clic sobre algo ya tildado: se destilda; si venía de una pareja automática, esa pareja se deshace.
            delete mapa[hash];
            // Si venía de una pareja automática, se destilda también su contrapartida.
            parejas.forEach(function (p) {
                if ((lado === 'c' ? p.c : p.b) === hash) {
                    if (lado === 'c') delete selB[p.b]; else delete selC[p.c];
                }
            });
            parejas = parejas.filter(function (p) { return (lado === 'c' ? p.c : p.b) !== hash; });
            limpiarSugeridos();
        } else {
            // Ctrl+clic: se suma el movimiento y, si todo lo tildado son parejas automáticas, se trae su contrapartida.
            var autoOk = seleccionSoloParejas();
            mapa[hash] = true;
            if (autoOk) autoParear(lado, hash);
        }
        focoLado = lado;
        focoHash = hash;
        // Solo al tildar (no al destildar): sugiere y tilda el mejor del otro lado. Enter confirma.
        if (!multi && !activoAntes
            && ((countSel(selC) === 1 && countSel(selB) === 0) || (countSel(selB) === 1 && countSel(selC) === 0))) {
            if (sugerir({ silencio: true })) return;
        }
        marcarSugeridosDesdeBase();
        marcarSugeridosParejas();
        pintar();
    }

    function marcarSugeridosParejas() {
        parejas.forEach(function (p) {
            var mc = buscar(estado.contables, p.c);
            var mb = buscar(estado.banco, p.b);
            if (mc) mc._sugerido = true;
            if (mb) mb._sugerido = true;
        });
    }

    function limpiarSeleccion() {
        parejas = [];
        selC = {};
        selB = {};
        focoHash = null;
        limpiarSugeridos();
        pintar();
    }

    function queryTablero() {
        return '?empresa_id=' + cfg.empresaId + '&cuentacaja_id=' + cfg.cuentacajaId
            + '&mes=' + cfg.mes + '&anio=' + cfg.anio
            + (document.getElementById('incluir-anteriores').checked ? '&incluir_anteriores=1' : '')
            + (fechaFiltro() ? '&fecha=' + encodeURIComponent(fechaFiltro()) : '');
    }

    function cargar() {
        overlay(true, 'Cargando movimientos…');
        fetch(cfg.tableroUrl + queryTablero(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'No se pudo cargar');
                estado = data;
                estado.hashes_contables = data.hashes_contables || [];
                estado.hashes_banco = data.hashes_banco || [];
                periodoCierre = data.periodo_cierre || { estado: 'sin_ejecucion' };
                rebuildHashMaps();
                selC = {};
                selB = {};
                parejas = [];
                focoHash = null;
                limpiarSugeridos();
                pintar();
                cargarEvidencias();
            })
            .catch(function (e) { alert(e.message || 'Error'); })
            .finally(function () { overlay(false); });
    }

    function traerBanco() {
        var fecha = fechaFiltro();
        if (!fecha) {
            alert('Indicá una fecha para traer movimientos del banco (ej. cheques debitados ese día).');
            return;
        }
        overlay(true, 'Consultando Interbanking…');
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('cuentacaja_id', cfg.cuentacajaId);
        body.set('fecha', fecha);
        fetch(cfg.sincronizarUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo sincronizar');
            overlay(true, 'Actualizando tablero…');
            return fetch(cfg.tableroUrl + queryTablero(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (tablero) {
                    if (!tablero.ok) throw new Error(tablero.error || 'No se pudo cargar');
                    estado = tablero;
                    estado.hashes_contables = tablero.hashes_contables || [];
                    estado.hashes_banco = tablero.hashes_banco || [];
                    rebuildHashMaps();
                    selC = {};
                    selB = {};
                    parejas = [];
                    focoHash = null;
                    pintar();
                    document.getElementById('estado-cruce').textContent = 'IB ' + fecha + ': ' + (data.filas || 0) + ' fila(s) sincronizada(s).';
                });
        }).catch(function (e) {
            alert(e.message || 'Error');
        }).finally(function () { overlay(false); });
    }

    function appendMovimiento(body, prefijo, mov, idx) {
        Object.keys(mov).forEach(function (k) {
            if (k.charAt(0) === '_') return;
            body.set(prefijo + '[' + idx + '][' + k + ']', mov[k] == null ? '' : mov[k]);
        });
    }

    function conciliar(forzar) {
        if (periodoCierre && (periodoCierre.estado === 'cerrado' || periodoCierre.estado === 'aprobado')) {
            alert('El período está ' + periodoCierre.estado + '. Reabrilo para conciliar.');
            return;
        }
        var c = seleccionados('c');
        var b = seleccionados('b');
        var anulacionOp = b.length === 0 && c.length >= 2 && Math.abs(sumaSel('c')) <= tolerancia;
        if (anulacionOp) {
            overlay(true, 'Guardando la anulación…');
            postConciliar(c, [], false).then(function () {
                cargar();
            }).catch(function (e) {
                overlay(false);
                alert(e.message || 'Error');
            });
            return;
        }
        if (c.length === 0 || b.length === 0) {
            alert('Elegí al menos un movimiento contable y uno de banco.');
            return;
        }

        var lote = armarLoteParejas(c, b);
        if (lote === false) return;
        if (lote) {
            conciliarLote(lote, forzar);
            return;
        }

        var diff = Math.abs(sumaSel('c') - sumaSel('b'));
        if (!forzar && diff > tolerancia) {
            if (!confirm('Los importes/sumas difieren en ' + dinero(diff) + ' (' + c.length + ':' + b.length + '). ¿Cruzarlos igual?')) return;
            forzar = true;
        }
        overlay(true, c.length > 1 || b.length > 1 ? 'Guardando el grupo…' : 'Guardando el cruce…');
        postConciliar(c, b, forzar).then(function () {
            cargar();
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function postConciliar(c, b, forzar) {
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('cuentacaja_id', cfg.cuentacajaId);
        body.set('forzar', forzar ? '1' : '0');
        c.forEach(function (mov, i) { appendMovimiento(body, 'contables', mov, i); });
        b.forEach(function (mov, i) { appendMovimiento(body, 'bancos', mov, i); });
        return fetch(cfg.conciliarUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo conciliar');
            return data;
        });
    }

    /**
     * Si hay varias parejas armadas al tildar (cada cheque con su contrapartida), se guardan como
     * cruces 1:1 independientes con un solo Enter. Lo tildado a mano fuera de las parejas se cruza aparte.
     * @return {Array<{c: object[], b: object[]}>|null|false} null = no aplica (flujo normal); false = hay que corregir la selección.
     */
    function armarLoteParejas(c, b) {
        var porHashC = {};
        var porHashB = {};
        c.forEach(function (m) { porHashC[m.hash] = m; });
        b.forEach(function (m) { porHashB[m.hash] = m; });
        var usadasC = {};
        var usadasB = {};
        var tareas = [];
        parejas.forEach(function (p) {
            if (porHashC[p.c] && porHashB[p.b] && !usadasC[p.c] && !usadasB[p.b]) {
                usadasC[p.c] = true;
                usadasB[p.b] = true;
                tareas.push({ c: [porHashC[p.c]], b: [porHashB[p.b]] });
            }
        });
        if (tareas.length === 0) return null;
        var restoC = c.filter(function (m) { return !usadasC[m.hash]; });
        var restoB = b.filter(function (m) { return !usadasB[m.hash]; });
        if (restoC.length === 0 && restoB.length === 0 && tareas.length === 1) return null;
        if ((restoC.length > 0) !== (restoB.length > 0)) {
            alert('Quedan ' + (restoC.length + restoB.length) + ' movimiento(s) tildados sin par del otro lado. '
                + 'Destildalos o elegí su contrapartida antes de conciliar.');
            return false;
        }
        if (restoC.length > 0) tareas.push({ c: restoC, b: restoB });
        return tareas;
    }

    function conciliarLote(tareas, forzar) {
        var conDiferencia = tareas.filter(function (t) {
            var sc = t.c.reduce(function (a, m) { return a + (Number(m.importe) || 0); }, 0);
            var sb = t.b.reduce(function (a, m) { return a + (Number(m.importe) || 0); }, 0);
            return Math.abs(sc - sb) > tolerancia;
        }).length;
        if (!forzar && conDiferencia > 0) {
            if (!confirm(conDiferencia + ' de ' + tareas.length + ' cruces tienen diferencia de importe. ¿Cruzarlos igual?')) return;
            forzar = true;
        }
        var hechos = 0;
        var paso = function () {
            if (hechos >= tareas.length) {
                cargar();
                return null;
            }
            overlay(true, 'Guardando cruce ' + (hechos + 1) + ' de ' + tareas.length + '…');
            var t = tareas[hechos];
            return postConciliar(t.c, t.b, forzar).then(function () {
                hechos++;
                return paso();
            });
        };
        var p = paso();
        if (p) {
            p.catch(function (e) {
                var msg = e.message || 'Error';
                cargar();
                setTimeout(function () {
                    alert('Se guardaron ' + hechos + ' de ' + tareas.length + ' cruces. El siguiente falló: ' + msg);
                }, 600);
            });
        }
    }

    function importarExtracto(file) {
        if (!file || !cfg.importarExtractoUrl) return;
        overlay(true, 'Importando extracto…');
        var fd = new FormData();
        fd.append('empresa_id', cfg.empresaId);
        fd.append('cuentacaja_id', cfg.cuentacajaId);
        fd.append('archivo', file);
        fetch(cfg.importarExtractoUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo importar');
            document.getElementById('estado-cruce').textContent =
                'Import ' + (data.formato || '') + ': ' + (data.filas || 0) + ' fila(s).';
            cargar();
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function cerrarPeriodo() {
        if (!confirm('¿Cerrar el período ' + String(cfg.mes).padStart(2, '0') + '/' + cfg.anio + ' para esta cuenta?')) return;
        overlay(true, 'Cerrando período…');
        var fd = new FormData();
        fd.append('empresa_id', cfg.empresaId);
        fd.append('cuentacaja_id', cfg.cuentacajaId);
        fd.append('mes', cfg.mes);
        fd.append('anio', cfg.anio);
        appendEvidenciasAFormData(fd);
        fetch(cfg.cerrarPeriodoUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo cerrar');
            evidenciaPendiente = [];
            periodoCierre = {
                estado: data.estado || 'cerrado',
                cerrado_at: data.cerrado_at || null,
                ejecucion_id: data.ejecucion_id || (periodoCierre && periodoCierre.ejecucion_id) || null
            };
            pintarCierre();
            cargarEvidencias();
            overlay(false);
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function reabrirPeriodo() {
        if (!confirm('¿Reabrir el período?')) return;
        overlay(true, 'Reabriendo…');
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('cuentacaja_id', cfg.cuentacajaId);
        body.set('mes', cfg.mes);
        body.set('anio', cfg.anio);
        fetch(cfg.reabrirPeriodoUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo reabrir');
            periodoCierre = {
                estado: data.estado || 'borrador',
                ejecucion_id: data.ejecucion_id || (periodoCierre && periodoCierre.ejecucion_id) || null
            };
            pintarCierre();
            overlay(false);
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function aprobarPeriodo() {
        if (!cfg.aprobarPeriodoUrl) return;
        if (!confirm('¿Aprobar el cierre del período? Queda listo para auditoría.')) return;
        overlay(true, 'Aprobando…');
        var fd = new FormData();
        fd.append('empresa_id', cfg.empresaId);
        fd.append('cuentacaja_id', cfg.cuentacajaId);
        fd.append('mes', cfg.mes);
        fd.append('anio', cfg.anio);
        appendEvidenciasAFormData(fd);
        fetch(cfg.aprobarPeriodoUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo aprobar');
            evidenciaPendiente = [];
            periodoCierre = {
                estado: data.estado || 'aprobado',
                aprobado_at: data.aprobado_at || null,
                ejecucion_id: data.ejecucion_id || (periodoCierre && periodoCierre.ejecucion_id) || null
            };
            pintarCierre();
            cargarEvidencias();
            overlay(false);
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function exportar() {
        window.location.href = cfg.exportarUrl + queryTablero();
    }

    function guardarModo() {
        var sel = document.getElementById('select-modo-workspace');
        if (!sel || !cfg.guardarModoUrl) return;
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('modo', sel.value);
        fetch(cfg.guardarModoUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo guardar el modo');
            prefs = data.preferencias || prefs;
            cfg.preferencias = prefs;
            var ayuda = document.getElementById('ayuda-modo');
            if (ayuda) ayuda.textContent = prefs.ayuda || '';
            var btnTraer = document.getElementById('btn-traer-banco');
            if (btnTraer) {
                btnTraer.className = 'btn btn-sm ' + (prefs.traer_banco_destacado ? 'btn-primary' : 'btn-outline-primary');
            }
            if (prefs.incluir_anteriores_default) {
                document.getElementById('incluir-anteriores').checked = true;
            }
            if (prefs.fecha_default_hoy && !fechaFiltro()) {
                var hoy = new Date();
                var yyyy = hoy.getFullYear();
                var mm = String(hoy.getMonth() + 1).padStart(2, '0');
                var dd = String(hoy.getDate()).padStart(2, '0');
                document.getElementById('filtro-fecha').value = yyyy + '-' + mm + '-' + dd;
            }
            if (!data.persistido) {
                alert('Modo aplicado en pantalla. Para guardarlo por empresa hace falta correr la migración de config.');
            }
            cargar();
        }).catch(function (e) {
            alert(e.message || 'Error');
        });
    }

    function esCampoFiltro(el) {
        if (!el) return false;
        var id = el.id || '';
        return id === 'filtro-texto' || id === 'filtro-importe' || id === 'filtro-fecha'
            || id === 'select-modo-workspace' || el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA';
    }

    function navegarLista(delta) {
        var visibles = listaVisible(focoLado);
        if (!visibles.length) {
            focoLado = focoLado === 'c' ? 'b' : 'c';
            visibles = listaVisible(focoLado);
        }
        if (!visibles.length) return;
        var idx = -1;
        for (var i = 0; i < visibles.length; i++) {
            if (visibles[i].hash === focoHash) {
                idx = i;
                break;
            }
        }
        idx = Math.max(0, Math.min(visibles.length - 1, idx + delta));
        if (idx < 0) idx = 0;
        focoHash = visibles[idx].hash;
        pintarDetalle();
        var el = document.querySelector('.js-fila[data-lado="' + focoLado + '"][data-hash="' + focoHash + '"]');
        if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    }

    function toggleFoco() {
        if (!focoHash) {
            var visibles = listaVisible(focoLado);
            if (!visibles.length) return;
            focoHash = visibles[0].hash;
        }
        seleccionar(focoLado, focoHash, true);
    }

    document.addEventListener('click', function (ev) {
        var chip = ev.target.closest('.cm-chip');
        if (chip) {
            tipoChip = chip.getAttribute('data-tipo') || '';
            document.querySelectorAll('.cm-chip').forEach(function (b) {
                var on = (b.getAttribute('data-tipo') || '') === tipoChip;
                b.classList.toggle('btn-primary', on);
                b.classList.toggle('active', on);
                b.classList.toggle('btn-outline-secondary', !on);
            });
            pintar();
            return;
        }
        var fila = ev.target.closest('.js-fila');
        if (fila) {
            // El tilde (checkbox) suma sin Ctrl: sigue trayendo la contrapartida de cada uno (cheques y transferencias).
            var esTilde = !!ev.target.closest('.js-check');
            seleccionar(fila.getAttribute('data-lado'), fila.getAttribute('data-hash'), !!(ev.ctrlKey || ev.metaKey || esTilde));
            return;
        }
        var btn = ev.target.closest('.js-desconciliar');
        if (!btn) return;
        if (!confirm('¿Desconciliar este cruce?')) return;
        overlay(true, 'Desconciliando…');
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('cuentacaja_id', cfg.cuentacajaId);
        if (btn.getAttribute('data-grupo-id')) {
            body.set('grupo_id', btn.getAttribute('data-grupo-id'));
        } else {
            body.set('par_id', btn.getAttribute('data-par-id') || btn.getAttribute('data-id'));
        }
        fetch(cfg.desconciliarUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo desconciliar');
            cargar();
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    });

    document.getElementById('btn-conciliar').addEventListener('click', function () { conciliar(false); });
    document.getElementById('btn-sugerir').addEventListener('click', sugerir);
    document.getElementById('btn-traer-banco').addEventListener('click', traerBanco);
    document.getElementById('btn-limpiar-sel').addEventListener('click', limpiarSeleccion);
    document.getElementById('btn-cerrar-periodo').addEventListener('click', cerrarPeriodo);
    document.getElementById('btn-reabrir-periodo').addEventListener('click', reabrirPeriodo);
    var btnAprobar = document.getElementById('btn-aprobar-periodo');
    if (btnAprobar) btnAprobar.addEventListener('click', aprobarPeriodo);
    document.getElementById('input-extracto').addEventListener('change', function (ev) {
        var file = ev.target.files && ev.target.files[0];
        ev.target.value = '';
        if (file) importarExtracto(file);
    });
    var inputEvidencia = document.getElementById('input-evidencia');
    if (inputEvidencia) {
        inputEvidencia.addEventListener('change', function (ev) {
            var files = ev.target.files;
            ev.target.value = '';
            if (files && files.length) adjuntarEvidenciaAhora(files);
        });
    }
    document.getElementById('btn-exportar').addEventListener('click', function (ev) {
        ev.preventDefault();
        exportar();
    });
    document.getElementById('select-modo-workspace').addEventListener('change', guardarModo);

    ['filtro-texto', 'filtro-importe'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', pintar);
    });
    document.getElementById('filtro-fecha').addEventListener('change', function () {
        cargar();
    });
    document.getElementById('solo-pendientes').addEventListener('change', pintar);
    document.getElementById('incluir-anteriores').addEventListener('change', cargar);

    document.addEventListener('keydown', function (ev) {
        if (esCampoFiltro(ev.target) && ev.key !== 'Escape' && ev.key !== 'Enter') {
            return;
        }
        if (ev.key === 'Escape') {
            limpiarSeleccion();
            return;
        }
        if (ev.key === 'Enter' && !esCampoFiltro(ev.target)) {
            ev.preventDefault();
            conciliar(false);
            return;
        }
        if (ev.key === 'Enter' && (ev.target.id === 'filtro-texto' || ev.target.id === 'filtro-importe')) {
            ev.preventDefault();
            conciliar(false);
            return;
        }
        if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            navegarLista(1);
            return;
        }
        if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            navegarLista(-1);
            return;
        }
        if (ev.key === ' ' && !esCampoFiltro(ev.target)) {
            ev.preventDefault();
            toggleFoco();
            return;
        }
        if ((ev.key === 's' || ev.key === 'S') && !esCampoFiltro(ev.target) && !ev.ctrlKey && !ev.metaKey) {
            ev.preventDefault();
            sugerir();
            return;
        }
        if ((ev.key === 'c' || ev.key === 'C') && !esCampoFiltro(ev.target) && !ev.ctrlKey && !ev.metaKey) {
            focoLado = 'c';
            navegarLista(0);
            return;
        }
        if ((ev.key === 'b' || ev.key === 'B') && !esCampoFiltro(ev.target) && !ev.ctrlKey && !ev.metaKey) {
            focoLado = 'b';
            navegarLista(0);
        }
    });

    // Defaults de modo al abrir
    if (prefs.fecha_default_hoy) {
        var hoy = new Date();
        document.getElementById('filtro-fecha').value = hoy.getFullYear()
            + '-' + String(hoy.getMonth() + 1).padStart(2, '0')
            + '-' + String(hoy.getDate()).padStart(2, '0');
    }

    cargar();
})();
