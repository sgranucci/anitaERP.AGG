(function () {
    'use strict';

    document.getElementById('empresa_id')?.addEventListener('change', function () {
        document.getElementById('maquinavending_rendicion_id').value = '';
        const etiqueta = document.getElementById('etiqueta_rendicion_ventas');
        if (etiqueta) {
            etiqueta.value = '';
        }
        const fechaCabecera = document.getElementById('fecha_jornada_cabecera');
        if (fechaCabecera) {
            fechaCabecera.value = '—';
        }
        document.getElementById('panel-datos-rendicion')?.classList.add('d-none');
        document.getElementById('aviso-sin-rendicion-cargada')?.classList.remove('d-none');
        const link = document.getElementById('link-comprobante-ventas');
        if (link) {
            link.classList.add('d-none');
            link.href = '#';
        }
        const chk = document.getElementById('chk_verificacion_mv');
        if (chk) {
            chk.checked = false;
            chk.disabled = true;
        }
        const submit = document.querySelector('#form-rendicion-mv-caja button[type="submit"]');
        if (submit) {
            submit.disabled = true;
        }
    });

    const chk = document.getElementById('chk_verificacion_mv');
    const form = document.getElementById('form-rendicion-mv-caja');
    const submit = form?.querySelector('button[type="submit"]');

    function syncSubmit() {
        if (!submit || !chk) {
            return;
        }
        if (chk.disabled) {
            submit.disabled = true;
            return;
        }
        submit.disabled = !chk.checked;
    }

    chk?.addEventListener('change', syncSubmit);
    syncSubmit();
})();
