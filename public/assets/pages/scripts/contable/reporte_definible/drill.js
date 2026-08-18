/**
 * Drill de una celda del informe definible: cuentas del rubro y, dentro de cada
 * cuenta, los asientos con el comprobante que los originó.
 */
(function () {
    'use strict';

    var cfg = window.rdDrill || {};
    var $modal = null;

    function el(id) { return document.getElementById(id); }

    function fmt(valor) {
        var n = Number(valor || 0);
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = String(texto == null ? '' : texto);
        return div.innerHTML;
    }

    function abrirModal() {
        if (window.jQuery) {
            $modal = window.jQuery('#rd-modal-drill');
            $modal.modal('show');
        } else {
            var m = el('rd-modal-drill');
            if (m) { m.classList.add('show'); m.style.display = 'block'; }
        }
    }

    function estado(cargando, error) {
        var c = el('rd-drill-cargando');
        var e = el('rd-drill-error');
        if (c) c.style.display = cargando ? '' : 'none';
        if (e) {
            e.style.display = error ? '' : 'none';
            e.textContent = error || '';
        }
        if (cargando) el('rd-drill-contenido').innerHTML = '';
    }

    function pedir(params) {
        var url = cfg.url + (cfg.url.indexOf('?') >= 0 ? '&' : '?') + params;
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(function (r) { return r.json(); });
    }

    function ventanaTexto(v) {
        if (!v) return '';
        return 'Empresas ' + (v.empresa_ids || []).join(', ') + ' · ' + v.fecha_desde + ' a ' + v.fecha_hasta;
    }

    function renderCuentas(data, nombreRubro) {
        el('rd-drill-titulo').textContent = 'Cuentas de ' + (data.rubro ? (data.rubro.codigo + ' ' + data.rubro.nombre) : nombreRubro);
        el('rd-drill-migas').textContent = ventanaTexto(data.ventana) +
            (data.movimientos ? ' · ' + data.movimientos + ' movimientos leídos' : '');

        if (!data.cuentas || !data.cuentas.length) {
            estado(false, data.error || 'El rubro no tiene cuentas con movimiento en el período.');
            return;
        }

        var html = '<table class="table table-sm table-hover mb-0">' +
            '<thead style="background:#85C1E9;color:#17202A;"><tr>' +
            '<th style="width:130px">Cuenta</th><th>Nombre</th><th class="text-right" style="width:160px">Importe</th>' +
            '<th class="text-center" style="width:70px">Asientos</th></tr></thead><tbody>';
        data.cuentas.forEach(function (c) {
            html += '<tr><td>' + escapar(c.codigo_fmt) + '</td><td>' + escapar(c.nombre) + '</td>' +
                '<td class="text-right" style="font-variant-numeric:tabular-nums">' + fmt(c.valor) + '</td>' +
                '<td class="text-center"><button type="button" class="btn-accion-tabla rd-drill-ver-asientos" ' +
                'data-codigo="' + c.codigo + '" data-nombre="' + escapar(c.nombre) + '" title="Ver asientos">' +
                '<i class="fa fa-search text-primary"></i></button></td></tr>';
        });
        html += '</tbody><tfoot><tr class="font-weight-bold">' +
            '<td colspan="2" class="text-right">Total del rubro</td>' +
            '<td class="text-right" style="font-variant-numeric:tabular-nums">' + fmt(data.total) + '</td><td></td>' +
            '</tr></tfoot></table>';
        el('rd-drill-contenido').innerHTML = html;
        estado(false, null);

        el('rd-drill-contenido').querySelectorAll('.rd-drill-ver-asientos').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cargarAsientos(btn.getAttribute('data-codigo'), btn.getAttribute('data-nombre'));
            });
        });
    }

    function renderAsientos(data, nombreCuenta) {
        var cuenta = data.cuenta || {};
        el('rd-drill-titulo').textContent = 'Asientos de ' + (cuenta.codigo_fmt || '') + ' ' + (cuenta.nombre || nombreCuenta);
        el('rd-drill-migas').textContent = ventanaTexto(data.ventana) +
            (data.truncado ? ' · se muestran los primeros ' + data.limite : '');

        if (!data.asientos || !data.asientos.length) {
            estado(false, data.error || 'Sin asientos para esta cuenta en el período.');
            return;
        }

        var html = '<table class="table table-sm table-hover mb-0">' +
            '<thead style="background:#85C1E9;color:#17202A;"><tr>' +
            '<th style="width:95px">Fecha</th><th style="width:90px">Asiento</th><th style="width:60px">Tipo</th>' +
            '<th>Empresa</th><th class="text-right" style="width:150px">Importe</th>' +
            '<th>Detalle</th><th style="width:170px">Origen</th></tr></thead><tbody>';
        data.asientos.forEach(function (a) {
            var fecha = String(a.fecha || '').substring(0, 10).split('-').reverse().join('/');
            var nro = escapar(a.numeroasiento);
            if (cfg.puedeVerAsiento && a.asiento_id) {
                nro = '<a href="' + String(cfg.urlAsiento).replace('__ID__', a.asiento_id) + '" target="_blank" ' +
                    'rel="noopener" class="text-primary">' + nro + '</a>';
            }
            var origen = '<span class="text-muted">—</span>';
            if (a.origen) {
                origen = escapar(a.origen.tipo) + ' #' + a.origen.id;
                if (a.origen.url) {
                    origen = '<a href="' + escapar(a.origen.url) + '" target="_blank" rel="noopener" class="text-primary">' + origen + '</a>';
                }
            }
            html += '<tr><td>' + fecha + '</td><td>' + nro + '</td><td>' + escapar(a.tipo) + '</td>' +
                '<td>' + escapar(a.empresa) + '</td>' +
                '<td class="text-right" style="font-variant-numeric:tabular-nums">' + fmt(a.monto) + '</td>' +
                '<td class="small">' + escapar(a.observacion) + '</td><td class="small">' + origen + '</td></tr>';
        });
        html += '</tbody><tfoot><tr class="font-weight-bold">' +
            '<td colspan="4" class="text-right">Total listado</td>' +
            '<td class="text-right" style="font-variant-numeric:tabular-nums">' + fmt(data.total) + '</td>' +
            '<td colspan="2"></td></tr></tfoot></table>';
        el('rd-drill-contenido').innerHTML = html;
        estado(false, null);
    }

    function cargarCuentas(rubroId, nombre) {
        abrirModal();
        estado(true, null);
        pedir('rubro_id=' + encodeURIComponent(rubroId))
            .then(function (j) { renderCuentas(j, nombre); })
            .catch(function () { estado(false, 'No se pudo leer el detalle del rubro.'); });
    }

    function cargarAsientos(codigo, nombre) {
        abrirModal();
        estado(true, null);
        pedir('codigo=' + encodeURIComponent(codigo))
            .then(function (j) { renderAsientos(j, nombre); })
            .catch(function () { estado(false, 'No se pudieron leer los asientos de la cuenta.'); });
    }

    function bind() {
        if (!cfg.url) return;
        document.querySelectorAll('.rd-drill-rubro').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cargarCuentas(btn.getAttribute('data-rubro'), btn.getAttribute('data-nombre'));
            });
        });
        document.querySelectorAll('.rd-drill-cuenta').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cargarAsientos(btn.getAttribute('data-codigo'), btn.getAttribute('data-nombre'));
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
})();
