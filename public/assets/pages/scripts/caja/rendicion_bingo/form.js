(function () {
    'use strict';

    function fmtMoney(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function habilitarVerificacion(habilitar) {
        var chk = document.getElementById('chk_verificacion_bingo');
        var submit = document.querySelector('#form-rendicion-bingo-caja button[type="submit"]');
        if (chk) {
            chk.disabled = !habilitar;
            if (!habilitar) {
                chk.checked = false;
            }
        }
        if (submit) {
            submit.disabled = !habilitar || !(chk && chk.checked);
        }
    }

    window.rendicionBingoAplicarDatos = function (d) {
        document.getElementById('turno_operativo_bingo_id').value = String(d.turno_operativo_bingo_id || '');
        document.getElementById('empresa_id').value = String(d.empresa_id || '');
        if (d.codigo_sugerido) {
            document.getElementById('codigo').value = d.codigo_sugerido;
        }
        document.getElementById('etiqueta_cierre_turno').value = d.etiqueta_turno || '';

        document.getElementById('lbl-fecha-jornada').textContent = d.fecha_jornada_fmt || '—';
        var fechaCabecera = document.getElementById('fecha_jornada_cabecera');
        if (fechaCabecera) {
            fechaCabecera.value = d.fecha_jornada_fmt || '—';
        }
        document.getElementById('lbl-terminal').textContent = d.identificador_pc || '—';
        document.getElementById('lbl-operador').textContent = d.usuario_habilitado || '—';
        document.getElementById('lbl-deposito').textContent = '$' + fmtMoney(d.deposito);

        var calculo = d.calculo || {};
        document.getElementById('lbl-recaudacion').textContent = '$' + fmtMoney(calculo.total_cartones);
        document.getElementById('lbl-saldo-final').textContent = '$' + fmtMoney(calculo.saldo_final);

        var tbodyCartones = document.getElementById('tbody-cartones-presentacion');
        tbodyCartones.innerHTML = '';
        (d.cartones || []).forEach(function (c) {
            var cant = Number(c.cantidad || 0);
            var precio = Number(c.precio_unitario || 0);
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (c.codigo || '') + '</td>'
                + '<td>' + (c.nombre || '') + '</td>'
                + '<td class="text-right">' + cant + '</td>'
                + '<td class="text-right">$' + fmtMoney(precio) + '</td>'
                + '<td class="text-right">$' + fmtMoney(cant * precio) + '</td>';
            tbodyCartones.appendChild(tr);
        });

        var tbodyConceptos = document.getElementById('tbody-conceptos-presentacion');
        tbodyConceptos.innerHTML = '';
        (calculo.lineas_concepto || []).forEach(function (linea) {
            var signo = linea.signo === '+' ? '+' : '−';
            var pct = linea.porcentaje != null ? fmtMoney(linea.porcentaje) : '';
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + signo + ' ' + (linea.detalle || '') + '</td>'
                + '<td class="text-right">' + pct + '</td>'
                + '<td class="text-right">$' + fmtMoney(linea.monto) + '</td>';
            tbodyConceptos.appendChild(tr);
        });

        var link = document.getElementById('link-comprobante-cierre');
        if (link && d.comprobante_url) {
            link.href = d.comprobante_url;
            link.classList.remove('d-none');
        }

        document.getElementById('panel-datos-rendicion').classList.remove('d-none');
        document.getElementById('aviso-sin-cierre-cargado').classList.add('d-none');
        habilitarVerificacion(true);
    };

    document.getElementById('empresa_id')?.addEventListener('change', function () {
        document.getElementById('turno_operativo_bingo_id').value = '';
        document.getElementById('etiqueta_cierre_turno').value = '';
        var fechaCabecera = document.getElementById('fecha_jornada_cabecera');
        if (fechaCabecera) {
            fechaCabecera.value = '—';
        }
        document.getElementById('panel-datos-rendicion').classList.add('d-none');
        document.getElementById('aviso-sin-cierre-cargado').classList.remove('d-none');
        var link = document.getElementById('link-comprobante-cierre');
        if (link) {
            link.classList.add('d-none');
            link.href = '#';
        }
        habilitarVerificacion(false);
    });

    var chk = document.getElementById('chk_verificacion_bingo');
    chk?.addEventListener('change', function () {
        var submit = document.querySelector('#form-rendicion-bingo-caja button[type="submit"]');
        if (submit) {
            submit.disabled = chk.disabled || !chk.checked;
        }
    });

    habilitarVerificacion(false);
})();
