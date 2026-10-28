(function () {
    var abierto = false;

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function base() {
        return (typeof carpetaBase === 'string' ? carpetaBase : '') || '';
    }

    function lista() {
        return document.getElementById('rd-grupo-ccosto-lista');
    }

    function yaEsta(codigo) {
        var body = lista();
        if (!body) return false;
        return !!body.querySelector('tr[data-codigo="' + codigo + '"]');
    }

    function agregarFila(codigo, nombre) {
        var body = lista();
        if (!body || !codigo || yaEsta(codigo)) {
            return;
        }
        var tr = document.createElement('tr');
        tr.setAttribute('data-codigo', codigo);
        tr.innerHTML = '<td style="width:5rem"></td><td></td>'
            + '<td class="text-center" style="width:3rem">'
            + '<button type="button" class="btn btn-outline-danger btn-xs rd-grupo-ccosto-quitar" title="Quitar">&times;</button>'
            + '</td>';
        tr.children[0].textContent = codigo;
        tr.children[1].textContent = nombre || '';
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'grupo_ccosto[]';
        hidden.value = codigo;
        tr.children[1].appendChild(hidden);
        body.appendChild(tr);
    }

    function limpiarCodigo() {
        var codigo = document.getElementById('rd_grupo_ccosto_codigo');
        var nombre = document.getElementById('rd_grupo_ccosto_nombre');
        if (codigo) codigo.value = '';
        if (nombre) nombre.value = '';
    }

    function resolver(valor, alElegir) {
        var url = base() + '/contable/reporte-definible/resolver-grupo-ccosto?valor=' + encodeURIComponent(valor || '');
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    if (alElegir) {
                        window.alert((data && data.mensaje) || 'Grupo de centros de costo no encontrado');
                        var codigo = document.getElementById('rd_grupo_ccosto_codigo');
                        if (codigo) codigo.focus();
                    }
                    return null;
                }
                var nombre = document.getElementById('rd_grupo_ccosto_nombre');
                if (nombre) nombre.value = data.nombre || '';
                if (alElegir) {
                    agregarFila(data.codigo, data.nombre);
                    limpiarCodigo();
                }
                return data;
            });
    }

    function buscar(texto) {
        var datos = document.getElementById('rd-grupo-ccosto-datos');
        if (!datos) return;
        var cuerpo = new URLSearchParams();
        cuerpo.set('consulta', texto || '');
        cuerpo.set('_token', token());
        fetch(base() + '/contable/reporte-definible/consulta-grupo-ccosto', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': token(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: cuerpo.toString()
        }).then(function (res) { return res.json(); }).then(function (data) {
            datos.innerHTML = (data && data.data) || '<tr><td colspan="4">Sin resultados</td></tr>';
        }).catch(function () {
            datos.innerHTML = '<tr><td colspan="4">No se pudo consultar</td></tr>';
        });
    }

    function abrirModal() {
        abierto = true;
        buscar('');
        if (window.jQuery) {
            window.jQuery('#rdGrupoCcostoModal').modal('show');
        }
    }

    window.rdCargarGruposCcosto = function (codigos) {
        var body = lista();
        if (!body) return;
        body.innerHTML = '';
        (codigos || []).forEach(function (codigo) {
            resolver(String(codigo), false).then(function (data) {
                if (data) agregarFila(data.codigo, data.nombre);
            });
        });
    };

    document.addEventListener('click', function (ev) {
        var lupa = ev.target.closest('#rd-grupo-ccosto-lupa');
        if (lupa) {
            ev.preventDefault();
            abrirModal();
            return;
        }
        var agregar = ev.target.closest('#rd-grupo-ccosto-agregar');
        if (agregar) {
            ev.preventDefault();
            var codigo = document.getElementById('rd_grupo_ccosto_codigo');
            resolver(codigo ? codigo.value : '', true);
            return;
        }
        var quitar = ev.target.closest('.rd-grupo-ccosto-quitar');
        if (quitar) {
            var tr = quitar.closest('tr');
            if (tr) tr.remove();
            return;
        }
        var elegir = ev.target.closest('.eligeconsultagrupocc');
        if (elegir) {
            ev.preventDefault();
            var fila = elegir.closest('tr');
            if (!fila) return;
            var cod = (fila.querySelector('.codigo') || {}).textContent || '';
            var nom = (fila.querySelector('.nombre') || {}).textContent || '';
            agregarFila(cod.trim(), nom.trim());
            limpiarCodigo();
            if (window.jQuery) window.jQuery('#rdGrupoCcostoModal').modal('hide');
        }
    });

    document.addEventListener('keydown', function (ev) {
        var codigo = document.getElementById('rd_grupo_ccosto_codigo');
        if (!codigo || document.activeElement !== codigo) return;
        if (ev.key === 'F1') {
            ev.preventDefault();
            abrirModal();
            return;
        }
        if (ev.key === 'Enter') {
            ev.preventDefault();
            if (abierto) return;
            resolver(codigo.value, true);
        }
    });

    var buscarInput = document.getElementById('rd-grupo-ccosto-buscar');
    if (buscarInput) {
        buscarInput.addEventListener('input', function () {
            buscar(buscarInput.value);
        });
        buscarInput.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter') return;
            ev.preventDefault();
            var primera = document.querySelector('#rd-grupo-ccosto-datos .eligeconsultagrupocc');
            if (primera) primera.click();
        });
    }

    if (window.jQuery) {
        window.jQuery('#rdGrupoCcostoModal').on('hidden.bs.modal', function () {
            abierto = false;
        });
    }
})();
