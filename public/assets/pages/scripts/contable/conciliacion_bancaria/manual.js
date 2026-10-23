(function () {
    var cfg = window.conciliacionManual || {};
    var estado = { contables: [], banco: [], pares: [] };
    var selC = null;
    var selB = null;
    var tope = 250;

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

    function difImporte(mov, imp) {
        return Math.abs(Math.abs(Number(mov.importe) || 0) - imp);
    }

    function pasa(mov, lado, ignorarConciliados) {
        var q = texto(document.getElementById('filtro-texto').value);
        var blob = lado === 'c'
            ? texto(mov.tipo_comp + ' ' + mov.descripcion + ' ' + mov.nro_asiento_fmt + ' ' + mov.nro)
            : texto(mov.code_description_ib + ' ' + mov.code_description_bank + ' ' + mov.voucher_number);
        if (q && blob.indexOf(q) === -1) return false;
        if (!ignorarConciliados && document.getElementById('solo-pendientes').checked) {
            var usados = lado === 'c' ? hashesContables() : hashesBanco();
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

    function hashesContables() {
        var map = {};
        estado.pares.forEach(function (p) { map[p.hash_contable] = p; });
        return map;
    }

    function hashesBanco() {
        var map = {};
        estado.pares.forEach(function (p) { map[p.hash_banco] = p; });
        return map;
    }

    function filaHtml(mov, lado, imp) {
        var activo = (lado === 'c' ? selC : selB) && (lado === 'c' ? selC.hash : selB.hash) === mov.hash;
        var sugerido = mov._sugerido ? ' background:#FDEBD0;' : '';
        var marca = mov.anterior ? ' <span class="badge badge-secondary">anterior</span>' : '';
        var dif = (imp != null && difImporte(mov, imp) > 0.02)
            ? ' <span class="small text-muted">dif. ' + dinero(difImporte(mov, imp)) + '</span>'
            : '';
        var det = lado === 'c'
            ? escapar((mov.tipo_comp || '') + ' ' + (mov.nro_asiento_fmt || '') + ' ' + (mov.descripcion || ''))
            : escapar((mov.code_description_ib || '') + ' ' + (mov.voucher_number || '') + ' ' + (mov.code_description_bank || ''));
        var fecha = escapar(lado === 'c' ? (mov.fecha_fmt || mov.fecha || '') : String(mov.process_date || '').substring(0, 10));
        return '<div class="px-2 py-1 border-bottom js-fila" data-lado="' + lado + '" data-hash="' + mov.hash + '" style="cursor:pointer;' + (activo ? 'background:#D6EAF8;' : sugerido) + '">'
            + '<div class="d-flex justify-content-between"><span>' + fecha + marca + '</span><span><strong>' + dinero(mov.importe) + '</strong>' + dif + '</span></div>'
            + '<div class="small text-muted text-truncate">' + det + '</div></div>';
    }

    function pintar() {
        var imp = importeFiltro();
        var cont = porImporte(estado.contables.filter(function (m) { return pasa(m, 'c'); }), imp);
        var ban = porImporte(estado.banco.filter(function (m) { return pasa(m, 'b'); }), imp);
        document.getElementById('cuenta-contable').textContent = cont.length + ' / ' + estado.contables.length;
        document.getElementById('cuenta-banco').textContent = ban.length + ' / ' + estado.banco.length;
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
                ? 'Ese importe ya está conciliado. Destildá Solo pendientes para verlo.'
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
            return '<div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center">'
                + '<span class="small">' + escapar((p.fecha_contable || '') + ' ' + (p.detalle_contable || '') + ' ↔ ' + (p.fecha_banco || '') + ' ' + (p.detalle_banco || ''))
                + ' <span class="badge badge-light">' + escapar(p.modo || '') + '</span></span>'
                + '<span><strong class="mr-2">' + dinero(p.importe) + '</strong>'
                + '<button type="button" class="btn btn-outline-danger btn-sm js-desconciliar" data-id="' + p.id + '">Desconciliar</button></span></div>';
        }).join('');
        document.getElementById('lista-pares').innerHTML = pares || '<div class="p-2 text-muted">Todavía no hay cruces en el mes.</div>';
    }

    function buscar(lista, hash) {
        for (var i = 0; i < lista.length; i++) {
            if (lista[i].hash === hash) return lista[i];
        }
        return null;
    }

    function seleccionar(lado, hash) {
        if (lado === 'c') selC = buscar(estado.contables, hash);
        else selB = buscar(estado.banco, hash);
        estado.contables.forEach(function (m) { m._sugerido = false; });
        estado.banco.forEach(function (m) { m._sugerido = false; });
        var base = lado === 'c' ? selC : selB;
        var otra = lado === 'c' ? estado.banco : estado.contables;
        var usados = lado === 'c' ? hashesBanco() : hashesContables();
        if (base) {
            otra.forEach(function (m) {
                if (!usados[m.hash] && Math.abs(Math.abs(m.importe) - Math.abs(base.importe)) <= 0.05) m._sugerido = true;
            });
        }
        actualizarEstado();
        pintar();
    }

    function actualizarEstado() {
        var el = document.getElementById('estado-cruce');
        if (!selC || !selB) {
            el.textContent = 'Elegí uno de cada lado. Enter concilia. Los importes iguales quedan marcados.';
            return;
        }
        var diff = Math.round(((Number(selC.importe) || 0) - (Number(selB.importe) || 0)) * 100) / 100;
        el.textContent = 'Diferencia ' + dinero(diff);
    }

    function cargar() {
        overlay(true, 'Cargando movimientos…');
        var url = cfg.tableroUrl + '?empresa_id=' + cfg.empresaId + '&cuentacaja_id=' + cfg.cuentacajaId
            + '&mes=' + cfg.mes + '&anio=' + cfg.anio
            + (document.getElementById('incluir-anteriores').checked ? '&incluir_anteriores=1' : '');
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'No se pudo cargar');
                estado = data;
                selC = null;
                selB = null;
                pintar();
            })
            .catch(function (e) { alert(e.message || 'Error'); })
            .finally(function () { overlay(false); });
    }

    function conciliar(forzar) {
        if (!selC || !selB) {
            alert('Elegí un movimiento contable y uno de Interbanking.');
            return;
        }
        var diff = Math.abs((Number(selC.importe) || 0) - (Number(selB.importe) || 0));
        if (!forzar && diff > 0.05) {
            if (!confirm('Los importes difieren en ' + dinero(diff) + '. ¿Cruzarlos igual?')) return;
            forzar = true;
        }
        overlay(true, 'Guardando el cruce…');
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('cuentacaja_id', cfg.cuentacajaId);
        body.set('forzar', forzar ? '1' : '0');
        Object.keys(selC).forEach(function (k) { if (k.charAt(0) !== '_') body.set('contable[' + k + ']', selC[k] == null ? '' : selC[k]); });
        Object.keys(selB).forEach(function (k) { if (k.charAt(0) !== '_') body.set('banco[' + k + ']', selB[k] == null ? '' : selB[k]); });
        fetch(cfg.conciliarUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'No se pudo conciliar');
            cargar();
        }).catch(function (e) {
            overlay(false);
            alert(e.message || 'Error');
        });
    }

    function sugerir() {
        var base = selC || selB;
        if (!base) {
            alert('Elegí un movimiento para buscar el par.');
            return;
        }
        var otra = selC ? estado.banco : estado.contables;
        var usados = selC ? hashesBanco() : hashesContables();
        var mejor = null;
        var mejorDiff = 1e12;
        otra.forEach(function (m) {
            if (usados[m.hash]) return;
            var diff = Math.abs(Math.abs(m.importe) - Math.abs(base.importe));
            if (diff < mejorDiff) {
                mejorDiff = diff;
                mejor = m;
            }
        });
        if (!mejor || mejorDiff > 1) {
            alert('No hay un importe cercano entre los pendientes.');
            return;
        }
        if (selC) selB = mejor;
        else selC = mejor;
        actualizarEstado();
        pintar();
    }

    document.addEventListener('click', function (ev) {
        var fila = ev.target.closest('.js-fila');
        if (fila) {
            seleccionar(fila.getAttribute('data-lado'), fila.getAttribute('data-hash'));
            return;
        }
        var btn = ev.target.closest('.js-desconciliar');
        if (!btn) return;
        if (!confirm('¿Desconciliar este cruce?')) return;
        overlay(true, 'Desconciliando…');
        var body = new URLSearchParams();
        body.set('empresa_id', cfg.empresaId);
        body.set('cuentacaja_id', cfg.cuentacajaId);
        body.set('par_id', btn.getAttribute('data-id'));
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
    ['filtro-texto', 'filtro-importe'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', pintar);
    });
    document.getElementById('solo-pendientes').addEventListener('change', pintar);
    document.getElementById('incluir-anteriores').addEventListener('change', cargar);
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && (ev.target.id === 'filtro-texto' || ev.target.id === 'filtro-importe' || ev.target.tagName === 'BODY')) {
            ev.preventDefault();
            conciliar(false);
        }
    });

    cargar();
})();
