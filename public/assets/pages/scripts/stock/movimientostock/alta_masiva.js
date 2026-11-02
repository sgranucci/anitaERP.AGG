(function () {
    var form = document.getElementById('form-alta-masiva-stock');
    if (!form) {
        return;
    }

    var btnPreview = document.getElementById('btn-preview-alta-masiva');
    var btnGrabar = document.getElementById('btn-grabar-alta-masiva');
    var resumen = document.getElementById('alta-masiva-resumen');
    var alertas = document.getElementById('alta-masiva-alertas');
    var wrap = document.getElementById('alta-masiva-tabla-wrap');
    var tbody = document.querySelector('#alta-masiva-tabla tbody');
    var overlay = document.getElementById('alta-masiva-overlay');
    var overlayTitulo = document.getElementById('alta-masiva-overlay-titulo');
    var previewVigente = false;

    function mostrarOverlay(titulo) {
        if (!overlay) {
            return;
        }
        if (overlayTitulo && titulo) {
            overlayTitulo.textContent = titulo;
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

    function invalidarPreview() {
        previewVigente = false;
        if (btnGrabar) {
            btnGrabar.disabled = true;
        }
    }

    function texto(valor) {
        return valor === null || valor === undefined ? '' : String(valor);
    }

    function celda(tr, valor, clase) {
        var td = document.createElement('td');
        if (clase) {
            td.className = clase;
        }
        td.textContent = texto(valor);
        tr.appendChild(td);
        return td;
    }

    function pintarAlertas(errores, avisos) {
        alertas.innerHTML = '';
        (errores || []).forEach(function (msg) {
            var div = document.createElement('div');
            div.className = 'alert alert-danger py-2';
            div.textContent = msg;
            alertas.appendChild(div);
        });
        (avisos || []).forEach(function (msg) {
            var div = document.createElement('div');
            div.className = 'alert alert-warning py-2';
            div.textContent = msg;
            alertas.appendChild(div);
        });
    }

    function pintarTabla(data) {
        tbody.innerHTML = '';
        (data.lineas || []).forEach(function (linea) {
            var tr = document.createElement('tr');
            var tieneError = (linea.errores || []).length > 0;
            var tieneAviso = (linea.avisos || []).length > 0;
            if (tieneError) {
                tr.className = 'table-danger';
            } else if (tieneAviso) {
                tr.className = 'table-warning';
            }
            celda(tr, linea.fila);
            celda(tr, linea.sku_excel);
            celda(tr, linea.articulo);
            celda(tr, linea.combinacion);
            celda(tr, linea.color_excel);
            celda(tr, linea.color_maestro);
            celda(tr, linea.talles_txt);
            celda(tr, linea.pares, 'text-right');
            var estado = tieneError
                ? (linea.errores || []).join(' ')
                : (tieneAviso ? (linea.avisos || []).join(' ') : 'OK');
            celda(tr, estado);
            tbody.appendChild(tr);
        });
        wrap.classList.remove('d-none');
    }

    function previsualizar() {
        var archivo = document.getElementById('archivo');
        if (!archivo || !archivo.files || !archivo.files.length) {
            alert('Seleccione el Excel.');
            return;
        }

        var data = new FormData(form);
        mostrarOverlay('Leyendo el Excel…');
        fetch(form.getAttribute('data-preview-url'), {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (resp) {
            return resp.json().then(function (json) {
                return { status: resp.status, json: json };
            });
        }).then(function (res) {
            ocultarOverlay();
            if (!res.json || res.json.ok === false) {
                invalidarPreview();
                pintarAlertas([ (res.json && res.json.mensaje) || 'No se pudo leer el Excel.' ], []);
                return;
            }
            var r = res.json.resumen || {};
            resumen.textContent = 'Catálogo ' + texto(res.json.canal_etiqueta)
                + ' · ' + texto(r.ok) + ' líneas OK'
                + ' · ' + texto(r.errores) + ' con error'
                + ' · ' + texto(r.avisos) + ' con aviso'
                + ' · ' + texto(r.pares) + ' pares';
            pintarAlertas(res.json.cabecera_errores, res.json.cabecera_avisos);
            pintarTabla(res.json);
            previewVigente = !!res.json.puede_grabar;
            btnGrabar.disabled = !previewVigente;
        }).catch(function () {
            ocultarOverlay();
            invalidarPreview();
            pintarAlertas(['No se pudo leer el Excel.'], []);
        });
    }

    btnPreview.addEventListener('click', previsualizar);

    ['archivo', 'deposito_id', 'empresa_id', 'tipotransaccion_stock_id', 'fecha'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.addEventListener('change', invalidarPreview);
        el.addEventListener('input', invalidarPreview);
    });

    form.addEventListener('submit', function (ev) {
        if (!previewVigente || btnGrabar.disabled) {
            ev.preventDefault();
            alert('Primero genere la vista previa sin errores.');
            return;
        }
        var r = resumen.textContent || 'el comprobante';
        if (!window.confirm('Se va a grabar el movimiento con la vista previa actual (' + r + '). ¿Continuar?')) {
            ev.preventDefault();
            return;
        }
        mostrarOverlay('Grabando el comprobante…');
    });

    window.addEventListener('pageshow', ocultarOverlay);
})();
