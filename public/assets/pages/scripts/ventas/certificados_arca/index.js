(function () {
    'use strict';

    function overlay() {
        return document.getElementById('cert-arca-overlay');
    }

    function mostrarOverlay(titulo, subtitulo) {
        var el = overlay();
        if (!el) {
            return;
        }
        var t = document.getElementById('cert-arca-overlay-titulo');
        var s = document.getElementById('cert-arca-overlay-subtitulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
        el.classList.remove('d-none');
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var el = overlay();
        if (!el) {
            return;
        }
        el.classList.add('d-none');
        el.style.display = '';
        el.setAttribute('aria-hidden', 'true');
    }

    function filasInventario() {
        return Array.isArray(window.certificadosArcaFilas) ? window.certificadosArcaFilas : [];
    }

    function extraerTexto(el) {
        return el && el.textContent ? String(el.textContent).replace(/\s+/g, ' ').trim() : '';
    }

    function armarReplicarExtras(origenId) {
        var wrap = document.getElementById('replicar-extras');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = '';
        var hay = false;
        filasInventario().forEach(function (fila) {
            if (!fila || String(fila.id) === String(origenId)) {
                return;
            }
            hay = true;
            var idChk = 'replicar-id-' + String(fila.id).replace(/[^a-zA-Z0-9_-]/g, '_');
            var div = document.createElement('div');
            div.className = 'custom-control custom-checkbox mb-1';
            var input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'custom-control-input replicar-id-extra';
            input.id = idChk;
            input.name = 'replicar_ids[]';
            input.value = String(fila.id);
            var label = document.createElement('label');
            label.className = 'custom-control-label';
            label.setAttribute('for', idChk);
            var detalle = fila.etiqueta || fila.id;
            if (fila.alias) {
                detalle += ' — ' + fila.alias;
            }
            if (fila.cuit) {
                detalle += ' (CUIT ' + fila.cuit + ')';
            }
            label.textContent = detalle;
            div.appendChild(input);
            div.appendChild(label);
            wrap.appendChild(div);
        });
        if (!hay) {
            wrap.innerHTML = '<span class="text-muted small">No hay otros webservices en este entorno.</span>';
        }
    }

    function etiquetasAInstalar(origenEtiqueta) {
        var nombres = [origenEtiqueta || 'este webservice'];
        document.querySelectorAll('#replicar-extras input.replicar-id-extra:checked').forEach(function (chk) {
            var lab = wrapLabel(chk);
            if (lab) {
                nombres.push(lab);
            }
        });
        return nombres;
    }

    function wrapLabel(chk) {
        var lab = chk.id ? document.querySelector('label[for="' + chk.id + '"]') : null;
        return extraerTexto(lab) || chk.value;
    }

    function mostrarModalPrueba(prueba) {
        if (!prueba || typeof prueba !== 'object') {
            return;
        }
        var ok = !!prueba.ok;
        var titulo = document.getElementById('modal-prueba-cert-arca-titulo');
        var resumen = document.getElementById('modal-prueba-cert-arca-resumen');
        var tbody = document.getElementById('modal-prueba-cert-arca-pasos');
        var header = document.getElementById('modal-prueba-cert-arca-header');
        if (!titulo || !resumen || !tbody) {
            return;
        }
        titulo.textContent = ok ? 'Prueba OK' : 'Prueba falló';
        if (header) {
            header.classList.remove('bg-success', 'bg-danger', 'text-white');
            header.classList.add(ok ? 'bg-success' : 'bg-danger', 'text-white');
        }
        var alias = prueba.alias ? ' — alias «' + prueba.alias + '»' : '';
        resumen.textContent = (prueba.etiqueta || 'Certificado') + alias +
            '. Se pidió ticket WSAA' + (ok ? ' y respondió el endpoint Dummy.' : '. Revise el detalle de cada paso.');
        tbody.innerHTML = '';
        (prueba.pasos || []).forEach(function (paso) {
            var tr = document.createElement('tr');
            var tdEstado = document.createElement('td');
            tdEstado.className = paso.ok ? 'text-success font-weight-bold' : 'text-danger font-weight-bold';
            tdEstado.textContent = paso.ok ? 'OK' : 'ERROR';
            var tdPaso = document.createElement('td');
            tdPaso.textContent = paso.nombre || '';
            var tdDetalle = document.createElement('td');
            tdDetalle.style.whiteSpace = 'pre-wrap';
            tdDetalle.style.wordBreak = 'break-word';
            tdDetalle.textContent = paso.detalle || '';
            tr.appendChild(tdEstado);
            tr.appendChild(tdPaso);
            tr.appendChild(tdDetalle);
            tbody.appendChild(tr);
        });
        if (window.jQuery) {
            window.jQuery('#modal-prueba-cert-arca').modal('show');
        }
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (form.classList.contains('form-generar-csr-arca')) {
            if (!window.confirm('¿Generar un CSR nuevo? Se crea una clave nueva; el certificado vigente sigue activo hasta que instale el .crt de ARCA.')) {
                ev.preventDefault();
                return;
            }
            mostrarOverlay('Generando CSR…', 'No cierre la página.');
            return;
        }
        if (form.classList.contains('form-probar-cert-arca')) {
            if (!window.confirm('¿Probar conexión con ARCA? Se pide un ticket WSAA y se llama al endpoint Dummy (no emite comprobantes).')) {
                ev.preventDefault();
                return;
            }
            mostrarOverlay('Probando conexión ARCA…', 'WSAA + dummy. No cierre la página.');
            return;
        }
        if (form.id === 'form-instalar-crt-arca') {
            var zip = document.getElementById('par_zip');
            var crt = document.getElementById('certificado');
            var clave = document.getElementById('clave');
            var hayZip = zip && zip.files && zip.files.length > 0;
            var hayCrt = crt && crt.files && crt.files.length > 0;
            var hayClave = clave && clave.files && clave.files.length > 0;
            if (!hayZip && !hayCrt) {
                ev.preventDefault();
                window.alert('Seleccione el .crt de ARCA, o el ZIP exportado desde el otro servidor.');
                return;
            }
            if (hayClave && !hayCrt && !hayZip) {
                ev.preventDefault();
                window.alert('La clave privada va junto con el .crt, o use el ZIP que ya trae los dos archivos.');
                return;
            }
            var origen = extraerTexto(document.getElementById('instalar_alias_label')) || 'este webservice';
            var destinos = etiquetasAInstalar(origen);
            var como = hayZip || hayClave
                ? 'Se importa el par de otro servidor (certificado + clave privada).'
                : 'Se valida el .crt contra el CSR de este servidor.';
            var msg = '¿Instalar este certificado?\n\n' + como +
                '\n\nSe reemplazarán cert.crt y privada.key de:\n- ' +
                destinos.join('\n- ') +
                '\n\nQueda backup. Los webservices no tildados no se tocan.';
            if (!window.confirm(msg)) {
                ev.preventDefault();
                return;
            }
            mostrarOverlay('Validando e instalando…', 'No cierre la página.');
        }
    });

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.btn-subir-crt-arca') : null;
        if (!btn) {
            return;
        }
        var id = btn.getAttribute('data-id') || '';
        var alias = btn.getAttribute('data-alias') || '';
        var etiqueta = btn.getAttribute('data-etiqueta') || '';
        var inputId = document.getElementById('instalar_certificado_id');
        var label = document.getElementById('instalar_alias_label');
        if (inputId) {
            inputId.value = id;
        }
        if (label) {
            label.textContent = (etiqueta ? etiqueta + ' — ' : '') + alias;
        }
        ['certificado', 'par_zip', 'clave'].forEach(function (idInput) {
            var file = document.getElementById(idInput);
            if (file) {
                file.value = '';
            }
        });
        var ayuda = document.getElementById('instalar_ayuda_csr');
        if (ayuda) {
            var tieneCsr = btn.getAttribute('data-tiene-csr') === '1';
            ayuda.innerHTML = tieneCsr
                ? 'Hay un CSR en este servidor: puede subir solo el <code>.crt</code> de ARCA. Si el certificado se renovó en otro servidor, use el ZIP (<code>cert.crt</code> + <code>privada.key</code>).'
                : 'No hay CSR en este servidor. Suba el ZIP exportado desde el otro ERP (<code>cert.crt</code> + <code>privada.key</code>), o el .crt junto con la clave privada.';
        }
        armarReplicarExtras(id);
        if (window.jQuery) {
            window.jQuery('#modal-subir-crt-arca').modal('show');
        }
    });

    window.addEventListener('pageshow', ocultarOverlay);

    if (window.certificadosArcaPrueba) {
        document.addEventListener('DOMContentLoaded', function () {
            mostrarModalPrueba(window.certificadosArcaPrueba);
        });
    }
})();
