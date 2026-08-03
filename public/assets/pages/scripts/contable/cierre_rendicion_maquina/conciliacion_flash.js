(function () {
    'use strict';

    var cfg = window.CIERRE_REND_MAQ_CONC || {};

    function tokenCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function reloadConsulta() {
        var params = new URLSearchParams(window.location.search);
        if (! params.has('consultar')) {
            params.set('consultar', '1');
        }
        window.location.search = params.toString();
    }

    function mostrarErroresResultado(resultado) {
        var errores = (resultado && resultado.errores) ? resultado.errores : [];
        if (! errores.length) {
            return '';
        }
        return '\n\nErrores:\n' + errores.map(function (e) {
            return (e.grupo_clave || '?') + ': ' + e.mensaje;
        }).join('\n');
    }

    function ejecutarCierreJornada(btn) {
        var empresaId = parseInt(btn.getAttribute('data-empresa-id') || '0', 10);
        var fechaDia = btn.getAttribute('data-fecha-dia') || '';
        var pendientes = parseInt(btn.getAttribute('data-pendientes') || '0', 10);
        var fechaFmt = btn.getAttribute('data-fecha-fmt') || fechaDia;

        if (empresaId <= 0 || ! fechaDia) {
            return;
        }

        var msg = '¿Confirmar cierre contable de la jornada ' + fechaFmt + '?\n'
            + pendientes + ' rendición(es) pendiente(s).';
        if (! confirm(msg)) {
            return;
        }

        btn.disabled = true;

        fetch(cfg.urlEjecutarJornada, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                empresa_id: empresaId,
                fecha_dia: fechaDia,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (! data.ok) {
                    alert(data.mensaje || 'No se pudo ejecutar el cierre de la jornada.');
                    btn.disabled = false;
                    return;
                }
                alert(data.mensaje || 'Cierre completado.');
                reloadConsulta();
            })
            .catch(function () {
                alert('Error de comunicación al ejecutar el cierre de la jornada.');
                btn.disabled = false;
            });
    }

    function ejecutarCierrePeriodo(btn) {
        var empresaId = parseInt(btn.getAttribute('data-empresa-id') || '0', 10);
        var fechaDesde = btn.getAttribute('data-fecha-desde') || '';
        var fechaHasta = btn.getAttribute('data-fecha-hasta') || '';
        var grupos = parseInt(btn.getAttribute('data-grupos') || '0', 10);
        var pendientes = parseInt(btn.getAttribute('data-pendientes') || '0', 10);

        if (empresaId <= 0 || ! fechaDesde || ! fechaHasta) {
            return;
        }

        var msg = '¿Confirmar cierre contable del periodo completo?\n'
            + pendientes + ' rendición(es) pendiente(s), '
            + grupos + ' jornada(s) a cerrar.';
        if (! confirm(msg)) {
            return;
        }

        btn.disabled = true;

        fetch(cfg.urlEjecutarPeriodo, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                empresa_id: empresaId,
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
                confirmar: true,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (! data.ok) {
                    alert(data.mensaje || 'No se pudo ejecutar el cierre del periodo.');
                    btn.disabled = false;
                    return;
                }
                alert((data.mensaje || 'Cierre completado.') + mostrarErroresResultado(data.resultado));
                reloadConsulta();
            })
            .catch(function () {
                alert('Error de comunicación al ejecutar el cierre del periodo.');
                btn.disabled = false;
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-cerrar-jornada-conc').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ejecutarCierreJornada(btn);
            });
        });

        var btnPeriodo = document.getElementById('btn-cerrar-periodo-conc');
        if (btnPeriodo) {
            btnPeriodo.addEventListener('click', function () {
                ejecutarCierrePeriodo(btnPeriodo);
            });
        }
    });
})();
