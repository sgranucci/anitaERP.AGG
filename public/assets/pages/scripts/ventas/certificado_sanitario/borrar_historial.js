(function () {
    var modal = document.getElementById('modal-borrar-historial-certsan');
    if (!modal) {
        return;
    }

    var fechaDesde = document.getElementById('certsan-borrar-fecha-desde');
    var fechaHasta = document.getElementById('certsan-borrar-fecha-hasta');
    var btnPreview = document.getElementById('certsan-borrar-preview');
    var btnBorrar = document.getElementById('certsan-borrar-confirmar');
    var errorBox = document.getElementById('certsan-borrar-error');
    var resumenBox = document.getElementById('certsan-borrar-resumen');
    var resumenTexto = document.getElementById('certsan-borrar-resumen-texto');
    var muestraWrap = document.getElementById('certsan-borrar-muestra-wrap');
    var muestraBody = document.getElementById('certsan-borrar-muestra');
    var overlay = document.getElementById('certsan-borrar-overlay');
    var overlayTitulo = document.getElementById('certsan-borrar-overlay-titulo');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
    var cantidadLista = 0;

    function mostrarError(texto) {
        errorBox.textContent = texto || 'No se pudo completar la operación.';
        errorBox.classList.remove('d-none');
    }

    function ocultarError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }

    function limpiarResumen() {
        cantidadLista = 0;
        btnBorrar.disabled = true;
        resumenBox.classList.add('d-none');
        muestraWrap.classList.add('d-none');
        muestraBody.innerHTML = '';
        resumenTexto.textContent = '';
    }

    function mostrarOverlay(titulo) {
        if (overlayTitulo && titulo) {
            overlayTitulo.textContent = titulo;
        }
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function fechas() {
        return {
            fecha_desde: fechaDesde.value,
            fecha_hasta: fechaHasta.value
        };
    }

    function fechasCompletas() {
        return fechaDesde.value !== '' && fechaHasta.value !== '';
    }

    fechaDesde.addEventListener('input', limpiarResumen);
    fechaHasta.addEventListener('input', limpiarResumen);
    window.addEventListener('pageshow', ocultarOverlay);

    btnPreview.addEventListener('click', function () {
        ocultarError();
        limpiarResumen();
        if (!fechasCompletas()) {
            mostrarError('Indique fecha desde y fecha hasta.');
            return;
        }
        var params = new URLSearchParams(fechas());
        btnPreview.disabled = true;
        fetch(modal.getAttribute('data-url-preview') + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        }).then(function (pack) {
            if (!pack.ok || !pack.data.ok) {
                mostrarError((pack.data && pack.data.mensaje) || 'No se pudo consultar el rango.');
                return;
            }
            var resumen = pack.data.resumen || {};
            var cantidad = parseInt(resumen.cantidad, 10) || 0;
            cantidadLista = cantidad;
            resumenBox.classList.remove('d-none');
            if (cantidad === 0) {
                resumenTexto.textContent = 'No hay certificados entre el ' + pack.data.desde + ' y el ' + pack.data.hasta + '.';
                return;
            }
            var texto = cantidad === 1
                ? 'Se va a borrar 1 certificado'
                : 'Se van a borrar ' + cantidad + ' certificados';
            texto += ' entre el ' + pack.data.desde + ' y el ' + pack.data.hasta + '.';
            if (cantidad > (resumen.muestra || []).length) {
                texto += ' La tabla muestra los primeros ' + (resumen.muestra || []).length + '.';
            }
            resumenTexto.textContent = texto;
            (resumen.muestra || []).forEach(function (fila) {
                var tr = document.createElement('tr');
                var tdFecha = document.createElement('td');
                var tdNro = document.createElement('td');
                tdFecha.textContent = fila.fecha || '';
                tdNro.textContent = fila.etiqueta || '';
                tr.appendChild(tdFecha);
                tr.appendChild(tdNro);
                muestraBody.appendChild(tr);
            });
            if ((resumen.muestra || []).length > 0) {
                muestraWrap.classList.remove('d-none');
            }
            btnBorrar.disabled = false;
        }).catch(function () {
            mostrarError('No se pudo consultar el rango.');
        }).then(function () {
            btnPreview.disabled = false;
        });
    });

    btnBorrar.addEventListener('click', function () {
        if (cantidadLista < 1 || !fechasCompletas()) {
            return;
        }
        var texto = cantidadLista === 1
            ? 'Se borrará 1 certificado. Esta acción no se puede deshacer.'
            : 'Se borrarán ' + cantidadLista + ' certificados. Esta acción no se puede deshacer.';
        var confirmar = function (acepta) {
            if (!acepta) {
                return;
            }
            ejecutarBorrado();
        };
        if (typeof swal === 'function') {
            swal({
                title: '¿Borrar el historial del rango?',
                text: texto,
                icon: 'warning',
                dangerMode: true,
                buttons: {
                    cancel: 'Cancelar',
                    confirm: 'Borrar'
                }
            }).then(confirmar);
            return;
        }
        confirmar(window.confirm(texto));
    });

    function ejecutarBorrado() {
        ocultarError();
        mostrarOverlay('Borrando historial…');
        btnBorrar.disabled = true;
        var body = new URLSearchParams(fechas());
        body.set('confirmar', '1');
        body.set('_token', token);
        fetch(modal.getAttribute('data-url-borrar'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        }).then(function (pack) {
            if (!pack.ok || !pack.data.ok) {
                ocultarOverlay();
                btnBorrar.disabled = false;
                mostrarError((pack.data && pack.data.mensaje) || 'No se pudo borrar el historial.');
                return;
            }
            window.location.reload();
        }).catch(function () {
            ocultarOverlay();
            btnBorrar.disabled = false;
            mostrarError('No se pudo borrar el historial.');
        });
    }
})();
