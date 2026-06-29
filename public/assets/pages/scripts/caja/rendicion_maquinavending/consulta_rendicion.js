(function () {
    'use strict';

    const CFG = window.RENDICION_MV_CAJA || {};

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('#form-rendicion-mv-caja input[name="_token"]')?.value
            || '';
    }

    function empresaId() {
        const el = document.getElementById('empresa_id');
        return el ? parseInt(el.value, 10) || 0 : 0;
    }

    function fmtMoney(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ocultarAvisoSinRendicion() {
        document.getElementById('aviso-sin-rendicion-cargada')?.classList.add('d-none');
    }

    async function cargarConsulta(consulta) {
        const fd = new FormData();
        fd.append('_token', csrf());
        fd.append('empresa_id', String(empresaId()));
        fd.append('consulta', consulta || '');
        const resp = await fetch(CFG.urlConsulta, { method: 'POST', body: fd });
        const data = await resp.json();
        document.getElementById('tbody-consulta-rendicion-ventas').innerHTML = data.data || '';
    }

    async function cargarDatos(rendicionId) {
        const fd = new FormData();
        fd.append('_token', csrf());
        fd.append('maquinavending_rendicion_id', String(rendicionId));
        const resp = await fetch(CFG.urlDatos, { method: 'POST', body: fd });
        const data = await resp.json();
        if (!data.ok) {
            alert(data.mensaje || 'No se pudieron cargar los datos.');
            return;
        }
        aplicarDatos(data.datos);
        $('#modal-consulta-rendicion-ventas').modal('hide');
    }

    function aplicarDatos(d) {
        document.getElementById('maquinavending_rendicion_id').value = String(d.maquinavending_rendicion_id);
        document.getElementById('maquinavending_id').value = String(d.maquinavending_id);
        document.getElementById('puntoventa_cae_id').value = String(d.puntoventa_cae_id);
        document.getElementById('puntoventa_caea_id').value = String(d.puntoventa_caea_id);
        document.getElementById('empresa_id').value = String(d.empresa_id);
        document.getElementById('codigo').value = d.codigo_sugerido || '';
        document.getElementById('etiqueta_rendicion_ventas').value = '#'.concat(d.numero_cierre, ' — ', d.maquina_nombre);
        document.getElementById('lbl-fecha-jornada').textContent = d.fecha_jornada_fmt || '—';
        document.getElementById('lbl-pv').textContent = (d.puntoventa_codigo || '') + ' — ' + (d.puntoventa_nombre || '');
        document.getElementById('totalfactura').value = Number(d.totalfactura || 0).toFixed(2);
        document.getElementById('totalcobrado').value = Number(d.totalcobrado || 0).toFixed(2);
        document.getElementById('sobrantefaltante').value = Number(d.sobrantefaltante || 0).toFixed(2);

        const lblVentas = document.getElementById('lbl-total-ventas');
        const lblCobrado = document.getElementById('lbl-total-cobrado');
        if (lblVentas) {
            lblVentas.textContent = '$'.concat(fmtMoney(d.totalfactura));
        }
        if (lblCobrado) {
            lblCobrado.textContent = '$'.concat(fmtMoney(d.totalcobrado));
        }

        const link = document.getElementById('link-comprobante-ventas');
        if (link && d.comprobante_url) {
            link.href = d.comprobante_url;
            link.classList.remove('d-none');
        }

        const tbody = document.getElementById('tbody-movimientos-mv-caja');
        tbody.innerHTML = '';
        (d.movimientos || []).forEach(function (m, idx) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (m.cuentacaja_nombre || '') +
                '<input type="hidden" name="movimientos[' + idx + '][cuentacaja_id]" value="' + m.cuentacaja_id + '"></td>' +
                '<td><input type="number" step="0.01" class="form-control form-control-sm text-right" name="movimientos[' + idx + '][monto]" value="' + Number(m.monto).toFixed(2) + '" readonly></td>' +
                '<td><input type="number" step="0.0001" class="form-control form-control-sm text-right" name="movimientos[' + idx + '][cotizacion]" value="' + Number(m.cotizacion).toFixed(4) + '" readonly></td>';
            tbody.appendChild(tr);
        });

        document.getElementById('panel-datos-rendicion')?.classList.remove('d-none');
        ocultarAvisoSinRendicion();

        const chk = document.getElementById('chk_verificacion_mv');
        if (chk) {
            chk.disabled = false;
            chk.checked = false;
        }
    }

    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('.consultarendicionventas');
        if (btn) {
            ev.preventDefault();
            if (empresaId() <= 0) {
                alert('Seleccione la empresa.');
                return;
            }
            cargarConsulta('').then(function () {
                $('#modal-consulta-rendicion-ventas').modal('show');
            });
        }
        const elegir = ev.target.closest('.elegir-rendicion-ventas');
        if (elegir) {
            ev.preventDefault();
            let rendicionId = parseInt(elegir.getAttribute('data-id'), 10);
            if (!rendicionId) {
                const fila = elegir.closest('tr');
                rendicionId = parseInt(fila?.querySelector('td.id')?.textContent?.trim() || '', 10);
            }
            if (rendicionId > 0) {
                cargarDatos(rendicionId);
            }
        }
    });

    document.getElementById('modal-consulta-rendicion-ventas')?.addEventListener('shown.bs.modal', function () {
        document.getElementById('consulta_rendicion_ventas_texto')?.focus();
    });

    document.getElementById('btn-buscar-rendicion-ventas')?.addEventListener('click', function () {
        cargarConsulta(document.getElementById('consulta_rendicion_ventas_texto')?.value || '');
    });

    document.getElementById('consulta_rendicion_ventas_texto')?.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            cargarConsulta(ev.target.value || '');
        }
    });
})();
