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
    var huboPreview = false;
    var resumenDatos = { ok: 0, errores: 0, avisos: 0 };

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

    function marcarGrabar(habilitado) {
        if (!btnGrabar) {
            return;
        }
        btnGrabar.disabled = false;
        btnGrabar.classList.toggle('btn-primary', habilitado);
        btnGrabar.classList.toggle('btn-secondary', !habilitado);
        btnGrabar.title = habilitado
            ? 'Graba el movimiento con la vista previa actual.'
            : 'La vista previa tiene que quedar sin errores.';
    }

    function invalidarPreview() {
        previewVigente = false;
        huboPreview = false;
        marcarGrabar(false);
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

    function aviso(clase, mensaje) {
        var div = document.createElement('div');
        div.className = clase;
        div.textContent = mensaje;
        alertas.appendChild(div);
        return div;
    }

    function pintarAlertas(errores, avisos, lineas) {
        alertas.innerHTML = '';
        (errores || []).forEach(function (msg) {
            aviso('alert alert-danger py-2 mb-1', msg);
        });
        (lineas || []).forEach(function (linea) {
            if (!(linea.errores || []).length) {
                return;
            }
            var detalle = 'Fila ' + texto(linea.fila);
            if (linea.sku_excel) {
                detalle += ' · SKU ' + texto(linea.sku_excel);
            }
            if (linea.color_excel) {
                detalle += ' · ' + texto(linea.color_excel);
            }
            aviso('alert alert-danger py-2 mb-1', detalle + ': ' + (linea.errores || []).join(' '));
        });
        (avisos || []).forEach(function (msg) {
            aviso('alert alert-warning py-2 mb-1', msg);
        });
    }

    function pintarTabla(data) {
        tbody.innerHTML = '';
        var lineas = (data.lineas || []).slice().sort(function (a, b) {
            var ea = (a.errores || []).length > 0 ? 0 : 1;
            var eb = (b.errores || []).length > 0 ? 0 : 1;
            if (ea !== eb) {
                return ea - eb;
            }
            return (Number(a.fila) || 0) - (Number(b.fila) || 0);
        });
        lineas.forEach(function (linea) {
            var tr = document.createElement('tr');
            var tieneError = (linea.errores || []).length > 0;
            var tieneAviso = (linea.avisos || []).length > 0;
            if (tieneError) {
                tr.className = 'table-danger';
            } else if (tieneAviso) {
                tr.className = 'table-warning';
            }
            celda(tr, linea.hoja);
            celda(tr, linea.fila);
            celda(tr, linea.sku_excel);
            celda(tr, linea.articulo);
            celda(tr, linea.combinacion);
            celda(tr, linea.color_excel);
            celda(tr, linea.color_maestro);
            celda(tr, linea.modulos === null || linea.modulos === undefined || linea.modulos === '' ? '' : linea.modulos, 'text-right');
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
            huboPreview = true;
            resumenDatos = {
                ok: Number(r.ok) || 0,
                errores: Number(r.errores) || 0,
                avisos: Number(r.avisos) || 0
            };
            resumen.textContent = texto(res.json.formato_etiqueta || 'Excel')
                + ' · catálogo ' + texto(res.json.canal_etiqueta)
                + ' · ' + texto(r.ok) + ' líneas OK'
                + ' · ' + texto(r.errores) + ' con error'
                + ' · ' + texto(r.avisos) + ' con aviso'
                + ' · ' + texto(r.pares) + ' pares';
            resumen.className = resumenDatos.errores > 0 ? 'small text-danger font-weight-bold' : 'text-muted small';
            pintarAlertas(res.json.cabecera_errores, res.json.cabecera_avisos, res.json.lineas);
            pintarTabla(res.json);
            previewVigente = !!res.json.puede_grabar;
            marcarGrabar(previewVigente);
            if (!previewVigente && alertas.childElementCount) {
                alertas.scrollIntoView({ block: 'nearest' });
            }
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

    function explicarPorQueNoGraba() {
        if (!huboPreview) {
            pintarAlertas(['Primero genere la vista previa.'], [], []);
            alertas.scrollIntoView({ block: 'center' });
            return;
        }
        var texto = resumenDatos.errores > 0
            ? 'No se puede grabar: hay ' + resumenDatos.errores + ' líneas con error. Corrija el Excel y vuelva a generar la vista previa.'
            : 'No se puede grabar. Revise los avisos en rojo y vuelva a generar la vista previa.';
        if (!alertas.querySelector('[data-bloqueo-grabar]')) {
            var div = aviso('alert alert-danger py-2 mb-1', texto);
            div.setAttribute('data-bloqueo-grabar', '1');
            alertas.insertBefore(div, alertas.firstChild);
        }
        alertas.scrollIntoView({ block: 'center' });
    }

    form.addEventListener('submit', function (ev) {
        if (!previewVigente) {
            ev.preventDefault();
            explicarPorQueNoGraba();
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
