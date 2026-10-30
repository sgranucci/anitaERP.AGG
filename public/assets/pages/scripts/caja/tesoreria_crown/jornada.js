(function () {
    var form = document.getElementById('form-asignar-jornada');
    if (!form) {
        return;
    }
    var salaId = form.getAttribute('data-sala-id');
    var modal = $('#modal-consulta-jornada');
    var modo = 'deposito';

    function url(ruta) {
        return ruta + (ruta.indexOf('?') >= 0 ? '&' : '?') + 'sala_id=' + encodeURIComponent(salaId);
    }

    function aplicar(prefijo, fila) {
        document.getElementById(prefijo === 'deposito' ? 'deposito_id' : 'cajero_crown_id').value = fila.id;
        document.getElementById(prefijo + '_codigo').value = fila.codigo;
        document.getElementById(prefijo + '_nombre').value = fila.nombre;
    }

    function limpiar(prefijo) {
        document.getElementById(prefijo === 'deposito' ? 'deposito_id' : 'cajero_crown_id').value = '';
        document.getElementById(prefijo + '_nombre').value = '';
    }

    function enfocar(id) {
        var campo = document.getElementById(id);
        if (campo) {
            campo.focus();
        }
    }

    function siguienteDe(prefijo) {
        if (prefijo === 'deposito') {
            enfocar('cajero_codigo');
            return;
        }
        enfocar('turno');
    }

    function resolver(prefijo, avisar) {
        var codigo = document.getElementById(prefijo + '_codigo').value.trim();
        if (codigo === '') {
            limpiar(prefijo);
            return Promise.resolve(false);
        }
        var ruta = prefijo === 'deposito'
            ? url('/caja/tesoreria-crown/jornada/resolver-deposito') + '&codigo=' + encodeURIComponent(codigo)
            : url('/caja/tesoreria-crown/jornada/resolver-cajero') + '&codigo=' + encodeURIComponent(codigo);
        return fetch(ruta, { headers: { 'Accept': 'application/json' } })
            .then(function (respuesta) {
                return respuesta.json().then(function (fila) {
                    return { ok: respuesta.ok, fila: fila };
                });
            })
            .then(function (resultado) {
                if (!resultado || !resultado.ok) {
                    limpiar(prefijo);
                    if (avisar && resultado && resultado.fila && resultado.fila.mensaje) {
                        window.alert(resultado.fila.mensaje);
                        enfocar(prefijo + '_codigo');
                    }
                    return false;
                }
                aplicar(prefijo, resultado.fila);
                return true;
            })
            .catch(function () {
                return false;
            });
    }

    function cabecera() {
        var tr = document.getElementById('modal-consulta-jornada-cabecera');
        tr.innerHTML = modo === 'cajero'
            ? '<th>Código</th><th>Legajo</th><th>Nombre</th><th></th>'
            : '<th>Código</th><th>Nombre</th><th></th>';
    }

    function pintar(filas, total) {
        var cuerpo = document.getElementById('modal-consulta-jornada-filas');
        var aviso = document.getElementById('modal-consulta-jornada-total');
        cuerpo.innerHTML = '';
        if (aviso) {
            aviso.textContent = modo === 'cajero'
                ? (total || filas.length) + ' cajeros'
                : '';
        }
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var boton = '<td class="text-right"><button type="button" class="btn btn-sm btn-outline-info">Elegir</button></td>';
            tr.innerHTML = modo === 'cajero'
                ? '<td></td><td></td><td></td>' + boton
                : '<td></td><td></td>' + boton;
            tr.children[0].textContent = fila.codigo;
            if (modo === 'cajero') {
                tr.children[1].textContent = fila.legajo || '';
                tr.children[2].textContent = fila.nombre;
            } else {
                tr.children[1].textContent = fila.nombre;
            }
            tr.querySelector('button').addEventListener('click', function () {
                var elegido = modo;
                aplicar(elegido, fila);
                modal.one('hidden.bs.modal', function () {
                    siguienteDe(elegido);
                });
                modal.modal('hide');
            });
            cuerpo.appendChild(tr);
        });
    }

    function buscar() {
        var q = document.getElementById('modal-consulta-jornada-q').value.trim();
        var base = modo === 'deposito'
            ? '/caja/tesoreria-crown/jornada/consulta-deposito'
            : '/caja/tesoreria-crown/jornada/consulta-cajero';
        fetch(url(base) + '&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (data) { pintar(data.filas || [], data.total); });
    }

    function abrir(nuevoModo, titulo) {
        modo = nuevoModo;
        document.getElementById('modal-consulta-jornada-titulo').textContent = titulo;
        document.getElementById('modal-consulta-jornada-q').value = '';
        document.getElementById('modal-consulta-jornada-filas').innerHTML = '';
        cabecera();
        modal.modal('show');
        buscar();
    }

    var enterLoLlevaElFormulario = form.hasAttribute('data-enter-campos');
    document.getElementById('deposito_codigo').addEventListener('change', function () { resolver('deposito', false); });
    document.getElementById('cajero_codigo').addEventListener('change', function () { resolver('cajero', false); });
    document.getElementById('deposito_codigo').addEventListener('keydown', function (evento) {
        if (evento.key === 'F1') { evento.preventDefault(); abrir('deposito', 'Cajas'); }
        if (evento.key === 'Enter' && !enterLoLlevaElFormulario) {
            evento.preventDefault();
            resolver('deposito', true).then(function (ok) { if (ok) { siguienteDe('deposito'); } });
        }
    });
    document.getElementById('cajero_codigo').addEventListener('keydown', function (evento) {
        if (evento.key === 'F1') { evento.preventDefault(); abrir('cajero', 'Cajeros'); }
        if (evento.key === 'Enter' && !enterLoLlevaElFormulario) {
            evento.preventDefault();
            resolver('cajero', true).then(function (ok) { if (ok) { siguienteDe('cajero'); } });
        }
    });
    document.getElementById('turno').addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter' && !enterLoLlevaElFormulario) {
            evento.preventDefault();
            enfocar('tipo_tarea');
        }
    });
    document.getElementById('btn-consulta-deposito').addEventListener('click', function () { abrir('deposito', 'Cajas'); });
    document.getElementById('btn-consulta-cajero').addEventListener('click', function () { abrir('cajero', 'Cajeros'); });
    document.getElementById('modal-consulta-jornada-buscar').addEventListener('click', buscar);
    document.getElementById('modal-consulta-jornada-q').addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') { evento.preventDefault(); buscar(); }
    });
    var panel = document.getElementById('panel-abrir-caja');
    if (panel && panel.classList.contains('show')) {
        enfocar('deposito_codigo');
    }
    if (window.jQuery && panel) {
        window.jQuery(panel).on('shown.bs.collapse', function () {
            enfocar('deposito_codigo');
        });
    }
    form.addEventListener('submit', function (evento) {
        if (!document.getElementById('deposito_id').value || !document.getElementById('cajero_crown_id').value) {
            evento.preventDefault();
            window.alert('Elegí una caja y un cajero de esta sala.');
        }
    });
})();
