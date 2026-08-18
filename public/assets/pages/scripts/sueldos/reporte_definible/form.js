(function () {
    'use strict';

    var state = {
        columnas: [],
        config: { siguienteNro: 1, columnaInicial: 0, contenidosNumericos: ['importe', 'cantidad', 'valor', 'concepto_ganancias'] },
        seleccionId: null,
        conceptosLocales: [],
        dirty: false,
        snapshot: ''
    };

    function parseJsonScript(id, fallback) {
        var el = document.getElementById(id);
        if (!el) return fallback;
        try {
            return JSON.parse(el.textContent || 'null') || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function esNumerico(contenido) {
        return state.config.contenidosNumericos.indexOf(contenido) !== -1;
    }

    function snapshotActual() {
        return JSON.stringify({
            columna_id: document.getElementById('rsd_columna_id') ? document.getElementById('rsd_columna_id').value : '',
            nro_columna: document.getElementById('rsd_nro_columna') ? document.getElementById('rsd_nro_columna').value : '',
            descripcion: document.getElementById('rsd_descripcion') ? document.getElementById('rsd_descripcion').value : '',
            contenido: document.getElementById('rsd_contenido') ? document.getElementById('rsd_contenido').value : '',
            campo_empleado: document.getElementById('rsd_campo_empleado') ? document.getElementById('rsd_campo_empleado').value : '',
            largo: document.getElementById('rsd_largo') ? document.getElementById('rsd_largo').value : '',
            formula: document.getElementById('rsd_formula') ? document.getElementById('rsd_formula').value : '',
            conceptos: state.conceptosLocales
        });
    }

    function marcarDirty() {
        state.dirty = snapshotActual() !== state.snapshot;
        actualizarEstado();
    }

    function confirmarSiDirty() {
        if (!state.dirty) return true;
        return window.confirm('Hay cambios sin guardar en la columna. ¿Descartarlos?');
    }

    function toggleCampos() {
        var c = document.getElementById('rsd_contenido');
        if (!c) return;
        var v = c.value;
        document.querySelectorAll('.rsd-campo-empleado, .rsd-largo').forEach(function (el) {
            el.classList.toggle('d-none', v !== 'campo_empleado');
        });
        document.querySelectorAll('.rsd-formula').forEach(function (el) {
            el.classList.toggle('d-none', v !== 'formula');
        });
        var panelConceptos = document.getElementById('form-concepto-rsd');
        var scroll = document.getElementById('rsd-conceptos-scroll');
        var vacio = document.getElementById('rsd-conceptos-vacio');
        var mostrarConceptos = esNumerico(v);
        if (panelConceptos) panelConceptos.classList.toggle('d-none', !mostrarConceptos);
        if (scroll) scroll.classList.toggle('d-none', !mostrarConceptos);
        if (vacio && !mostrarConceptos) {
            vacio.classList.remove('d-none');
            vacio.textContent = 'Esta columna no usa conceptos (campo empleado o fórmula).';
        }
    }

    function actualizarEstado() {
        var el = document.getElementById('rsd-estado-seleccion');
        var pendientes = document.getElementById('rsd-conceptos-pendientes');
        if (el) {
            if (state.seleccionId) {
                el.textContent = (state.dirty ? '● Cambios sin guardar — ' : '') + 'Columna seleccionada #' + state.seleccionId;
            } else {
                el.textContent = state.dirty ? '● Nueva columna con cambios sin guardar' : 'Modo alta de columna';
            }
        }
        if (pendientes) {
            pendientes.textContent = state.dirty
                ? 'Los conceptos se guardan junto con la columna (botón Grabar columna).'
                : '';
        }
    }

    function actualizarTextoGrabarColumna(texto) {
        document.querySelectorAll('.rsd-texto-guardar-columna').forEach(function (el) {
            el.textContent = texto;
        });
    }

    function findColumna(id) {
        id = parseInt(id || '0', 10);
        for (var i = 0; i < state.columnas.length; i++) {
            if (parseInt(state.columnas[i].id, 10) === id) return state.columnas[i];
        }
        return null;
    }

    function resaltarFila(id) {
        document.querySelectorAll('#rsd-columnas-tbody tr').forEach(function (tr) {
            var match = parseInt(tr.getAttribute('data-columna-id') || '0', 10) === parseInt(id || '0', 10);
            tr.classList.toggle('table-active', match);
            var btn = tr.querySelector('.rsd-seleccionar-columna');
            if (btn) btn.setAttribute('aria-pressed', match ? 'true' : 'false');
        });
    }

    function renderConceptos() {
        var tbody = document.getElementById('rsd-conceptos-tbody');
        var vacio = document.getElementById('rsd-conceptos-vacio');
        var hidden = document.getElementById('rsd-conceptos-hidden');
        if (!tbody || !hidden) return;

        tbody.innerHTML = '';
        hidden.innerHTML = '';

        var contenido = document.getElementById('rsd_contenido')
            ? document.getElementById('rsd_contenido').value
            : 'importe';
        var numerico = esNumerico(contenido);

        if (!numerico) {
            if (vacio) {
                vacio.classList.remove('d-none');
                vacio.textContent = 'Esta columna no usa conceptos (campo empleado o fórmula).';
            }
            return;
        }

        if (!state.conceptosLocales.length) {
            if (vacio) {
                vacio.classList.remove('d-none');
                vacio.textContent = 'Sin conceptos. Use el formulario de arriba (F1 / Enter / lupa) y Agregar.';
            }
        } else if (vacio) {
            vacio.classList.add('d-none');
        }

        state.conceptosLocales.forEach(function (c, i) {
            var codigo = String(c.concepto_codigo || '');
            var codigoFmt = codigo ? ('0000' + codigo).slice(-4) : '';
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + (c.signo === '-' ? '−' : '+') + '</td>' +
                '<td>' + codigoFmt + '</td>' +
                '<td>' + (c.descripcion || '') + '</td>' +
                '<td class="text-nowrap">' +
                '<button type="button" class="btn-accion-tabla rsd-editar-concepto" data-idx="' + i + '" title="Editar">' +
                '<i class="fa fa-edit"></i><span class="sr-only">Editar concepto</span></button> ' +
                '<button type="button" class="btn-accion-tabla rsd-quitar-concepto" data-idx="' + i + '" title="Quitar">' +
                '<i class="fa fa-times-circle text-danger"></i><span class="sr-only">Quitar concepto</span></button>' +
                '</td>';
            tbody.appendChild(tr);

            var hCodigo = document.createElement('input');
            hCodigo.type = 'hidden';
            hCodigo.name = 'conceptos[' + i + '][concepto_codigo]';
            hCodigo.value = codigo;
            hidden.appendChild(hCodigo);

            var hSigno = document.createElement('input');
            hSigno.type = 'hidden';
            hSigno.name = 'conceptos[' + i + '][signo]';
            hSigno.value = c.signo === '-' ? '-' : '+';
            hidden.appendChild(hSigno);

            var hOrden = document.createElement('input');
            hOrden.type = 'hidden';
            hOrden.name = 'conceptos[' + i + '][orden]';
            hOrden.value = String(c.orden != null ? c.orden : (i + 1));
            hidden.appendChild(hOrden);
        });
    }

    function limpiarEditorConcepto() {
        var campo = document.querySelector('#form-concepto-rsd .tm-concepto-sueldos-campo');
        if (campo && window.limpiarConceptoSueldosEnCampo) {
            window.limpiarConceptoSueldosEnCampo(window.jQuery(campo), false);
        }
        var signo = document.getElementById('rsd_concepto_signo');
        if (signo) signo.value = '+';
        var idx = document.getElementById('rsd_concepto_indice');
        if (idx) idx.value = '';
        var btn = document.getElementById('rsd-agregar-concepto');
        if (btn) btn.textContent = 'Agregar';
    }

    function cargarFormularioColumna(col) {
        var form = document.getElementById('form-columna-rsd');
        if (!form) return;

        if (col) {
            document.getElementById('rsd_columna_id').value = col.id;
            document.getElementById('rsd_nro_columna').value = col.nro_columna;
            document.getElementById('rsd_descripcion').value = col.descripcion || '';
            document.getElementById('rsd_contenido').value = col.contenido || 'importe';
            document.getElementById('rsd_campo_empleado').value = col.campo_empleado || '';
            document.getElementById('rsd_largo').value = col.largo || '';
            document.getElementById('rsd_formula').value = col.formula || '';
            state.seleccionId = col.id;
            state.conceptosLocales = (col.conceptos || []).map(function (c, i) {
                return {
                    concepto_codigo: parseInt(c.concepto_codigo, 10) || 0,
                    signo: c.signo === '-' ? '-' : '+',
                    orden: c.orden != null ? parseInt(c.orden, 10) : (i + 1),
                    descripcion: c.descripcion || '',
                    concepto_id: c.concepto_id || 0
                };
            });
            document.getElementById('rsd-titulo-form-columna').textContent = 'Editar columna ' + col.nro_columna;
            actualizarTextoGrabarColumna('Actualizar columna');
            document.getElementById('rsd-cancelar-edicion-columna').classList.remove('d-none');
            document.getElementById('rsd-titulo-panel-conceptos').textContent =
                'Conceptos — columna ' + col.nro_columna + ' (' + (col.descripcion || '') + ')';
        } else {
            document.getElementById('rsd_columna_id').value = '';
            document.getElementById('rsd_nro_columna').value = state.config.siguienteNro;
            document.getElementById('rsd_descripcion').value = '';
            document.getElementById('rsd_contenido').value = 'importe';
            document.getElementById('rsd_campo_empleado').value = '';
            document.getElementById('rsd_largo').value = '';
            document.getElementById('rsd_formula').value = '';
            state.seleccionId = null;
            state.conceptosLocales = [];
            document.getElementById('rsd-titulo-form-columna').textContent = 'Agregar columna';
            actualizarTextoGrabarColumna('Grabar columna');
            document.getElementById('rsd-cancelar-edicion-columna').classList.add('d-none');
            document.getElementById('rsd-titulo-panel-conceptos').textContent = 'Conceptos de la columna';
        }

        limpiarEditorConcepto();
        toggleCampos();
        renderConceptos();
        resaltarFila(state.seleccionId);
        resaltarPreviewColumna(state.seleccionId);
        state.snapshot = snapshotActual();
        state.dirty = false;
        actualizarEstado();
    }

    function seleccionarColumna(id, forzar) {
        id = parseInt(id || '0', 10);
        if (!forzar && !confirmarSiDirty()) return;
        var col = findColumna(id);
        if (!col) return;
        cargarFormularioColumna(col);
    }

    function modoNuevaColumna(forzar) {
        if (!forzar && !confirmarSiDirty()) return;
        cargarFormularioColumna(null);
    }

    function agregarOActualizarConcepto() {
        var campo = document.querySelector('#form-concepto-rsd .tm-concepto-sueldos-campo');
        if (!campo) return;
        var codigoVisible = campo.querySelector('.codigoconcepto_sueldos');
        var nombre = campo.querySelector('.nombreconcepto_sueldos');
        var idHidden = campo.querySelector('.concepto_sueldos_id');
        var codigoRaw = String(codigoVisible ? codigoVisible.value : '').replace(/\D+/g, '');
        var codigo = parseInt(codigoRaw, 10) || 0;
        if (codigo <= 0) {
            window.alert('Indique un concepto válido (F1 / Enter / lupa).');
            if (codigoVisible) codigoVisible.focus();
            return;
        }
        var signo = document.getElementById('rsd_concepto_signo');
        var idxEl = document.getElementById('rsd_concepto_indice');
        var idx = idxEl && idxEl.value !== '' ? parseInt(idxEl.value, 10) : -1;
        var item = {
            concepto_codigo: codigo,
            signo: signo && signo.value === '-' ? '-' : '+',
            orden: idx >= 0 && state.conceptosLocales[idx] ? state.conceptosLocales[idx].orden : (state.conceptosLocales.length + 1),
            descripcion: nombre ? String(nombre.value || '') : '',
            concepto_id: idHidden ? (parseInt(idHidden.value, 10) || 0) : 0
        };

        var dup = state.conceptosLocales.some(function (c, i) {
            return i !== idx && parseInt(c.concepto_codigo, 10) === codigo;
        });
        if (dup) {
            window.alert('Ese concepto ya está en la columna.');
            return;
        }

        if (idx >= 0 && state.conceptosLocales[idx]) {
            state.conceptosLocales[idx] = item;
        } else {
            state.conceptosLocales.push(item);
        }
        limpiarEditorConcepto();
        renderConceptos();
        marcarDirty();
    }

    function editarConceptoLocal(idx) {
        idx = parseInt(idx, 10);
        var c = state.conceptosLocales[idx];
        if (!c) return;
        var campo = document.querySelector('#form-concepto-rsd .tm-concepto-sueldos-campo');
        if (!campo || !window.jQuery) return;
        var data = {
            id: c.concepto_id || 0,
            codigo: c.concepto_codigo,
            descripcion: c.descripcion || ''
        };
        if (data.id > 0 && window.aplicarConceptoSueldosEnCampo) {
            window.aplicarConceptoSueldosEnCampo(window.jQuery(campo), data);
        } else {
            var codigoInput = campo.querySelector('.codigoconcepto_sueldos');
            var nombreInput = campo.querySelector('.nombreconcepto_sueldos');
            if (codigoInput) codigoInput.value = ('0000' + String(c.concepto_codigo)).slice(-4);
            if (nombreInput) nombreInput.value = c.descripcion || '';
        }
        document.getElementById('rsd_concepto_signo').value = c.signo === '-' ? '-' : '+';
        document.getElementById('rsd_concepto_indice').value = String(idx);
        document.getElementById('rsd-agregar-concepto').textContent = 'Actualizar';
    }

    function quitarConceptoLocal(idx) {
        idx = parseInt(idx, 10);
        if (!state.conceptosLocales[idx]) return;
        state.conceptosLocales.splice(idx, 1);
        state.conceptosLocales.forEach(function (c, i) { c.orden = i + 1; });
        limpiarEditorConcepto();
        renderConceptos();
        marcarDirty();
    }

    function activarTabColumnasSiPedido() {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab');
        if (!window.jQuery) return;
        if (tab === 'columnas' || tab === 'diseno') {
            window.jQuery('a[href="#tab-diseno"]').tab('show');
        } else if (tab === 'gobierno' || tab === 'operacion') {
            window.jQuery('a[href="#tab-' + tab + '"]').tab('show');
        }
    }

    function renderPreview(data) {
        var thead = document.getElementById('rsd-preview-thead');
        var tbody = document.getElementById('rsd-preview-tbody');
        var chips = document.getElementById('rsd-preview-chips');
        var fuente = document.getElementById('rsd-preview-fuente');
        if (!thead || !tbody) return;

        var columnas = (data && data.columnas) || state.columnas || [];
        thead.innerHTML = '';
        columnas.forEach(function (col) {
            var th = document.createElement('th');
            th.setAttribute('data-nro', String(col.nro_columna));
            th.textContent = 'C' + col.nro_columna + ' ' + (col.descripcion || '');
            thead.appendChild(th);
        });

        tbody.innerHTML = '';
        var filas = (data && data.filas) || [];
        if (!filas.length) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = Math.max(columnas.length, 1);
            td.className = 'text-muted text-center';
            td.textContent = 'Sin muestra de ejecución. Ejecute el listado para ver filas aquí.';
            tr.appendChild(td);
            tbody.appendChild(tr);
        } else {
            filas.slice(0, 12).forEach(function (fila) {
                var tr = document.createElement('tr');
                columnas.forEach(function (col) {
                    var td = document.createElement('td');
                    td.setAttribute('data-nro', String(col.nro_columna));
                    var key = 'c' + col.nro_columna;
                    var val = fila[key];
                    if (val === undefined || val === null) val = fila['C' + col.nro_columna];
                    td.textContent = val === undefined || val === null ? '' : String(val);
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }

        if (chips) {
            chips.innerHTML = '';
            columnas.filter(function (c) { return c.contenido === 'formula' && c.formula; }).forEach(function (c) {
                var b = document.createElement('span');
                b.className = 'badge badge-info';
                b.textContent = 'C' + c.nro_columna + ': ' + c.formula;
                chips.appendChild(b);
            });
        }
        if (fuente) {
            fuente.textContent = (data && data.fuente) ? data.fuente : 'Estructura viva';
        }
        resaltarPreviewColumna(state.seleccionId);
    }

    function resaltarPreviewColumna(columnaId) {
        var col = findColumna(columnaId);
        var nro = col ? String(col.nro_columna) : '';
        document.querySelectorAll('#rsd-preview-papel [data-nro]').forEach(function (el) {
            el.classList.toggle('rsd-col-activa', nro !== '' && el.getAttribute('data-nro') === nro);
        });
    }

    function cargarPreview() {
        var urlEl = document.getElementById('rsd-preview-url');
        if (!urlEl || !window.jQuery) {
            renderPreview({ columnas: state.columnas, filas: [], fuente: 'Estructura viva' });
            return;
        }
        var url = '';
        try { url = JSON.parse(urlEl.textContent || '""'); } catch (e) { url = ''; }
        if (!url) {
            renderPreview({ columnas: state.columnas, filas: [], fuente: 'Estructura viva' });
            return;
        }
        window.jQuery.getJSON(url)
            .done(function (data) { renderPreview(data); })
            .fail(function () {
                renderPreview({ columnas: state.columnas, filas: [], fuente: 'Estructura viva' });
            });
    }

    function initSuscripcionEditor() {
        var form = document.getElementById('form-suscripcion-rsd');
        if (!form) return;

        function setVal(id, value) {
            var el = document.getElementById(id);
            if (el) el.value = value == null ? '' : String(value);
        }

        function limpiar() {
            setVal('suscripcion_id', '');
            setVal('sus_nombre', '');
            setVal('sus_email', '');
            setVal('sus_destinatarios', '');
            setVal('sus_formato', 'PDF');
            setVal('sus_burst_dimension', 'ninguna');
            setVal('sus_periodicidad', 'mensual');
            setVal('sus_dia_mes', '5');
            setVal('sus_dia_semana', '1');
            setVal('sus_hora', '07:00');
            setVal('sus_periodo_relativo', 'ultima_liquidacion');
            setVal('sus_mensaje', '');
            var pub = document.getElementById('sus-publicar');
            var solo = document.getElementById('sus-solo-alertas');
            if (pub) pub.checked = true;
            if (solo) solo.checked = false;
            var hid = form.querySelector('[name="liquidacion_id"]');
            var num = form.querySelector('.codigoliquidacion_sueldos');
            var desc = form.querySelector('.descripcionliquidacion_sueldos');
            if (hid) hid.value = '';
            if (num) num.value = '';
            if (desc) desc.value = '';
            var btn = document.getElementById('sus-submit');
            if (btn) btn.textContent = 'Guardar distribución';
            if (window.jQuery) window.jQuery('#sus_periodo_relativo').trigger('change');
        }

        document.querySelectorAll('.rsd-editar-suscripcion').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var payload = {};
                try { payload = JSON.parse(btn.getAttribute('data-suscripcion') || '{}'); } catch (e) { payload = {}; }
                setVal('suscripcion_id', payload.id || '');
                setVal('sus_nombre', payload.nombre || '');
                setVal('sus_email', payload.email || '');
                setVal('sus_destinatarios', payload.destinatarios || '');
                setVal('sus_formato', payload.formato || 'PDF');
                setVal('sus_burst_dimension', payload.burst_dimension || 'ninguna');
                setVal('sus_periodicidad', payload.periodicidad || 'mensual');
                setVal('sus_dia_mes', payload.dia_mes || 5);
                setVal('sus_dia_semana', payload.dia_semana || 1);
                setVal('sus_hora', payload.hora || '07:00');
                setVal('sus_periodo_relativo', payload.periodo_relativo || 'ultima_liquidacion');
                setVal('sus_mensaje', payload.mensaje || '');
                var pub = document.getElementById('sus-publicar');
                var solo = document.getElementById('sus-solo-alertas');
                if (pub) pub.checked = !!payload.publicar;
                if (solo) solo.checked = !!payload.solo_si_alertas;
                var hid = form.querySelector('[name="liquidacion_id"]');
                var num = form.querySelector('.codigoliquidacion_sueldos');
                var desc = form.querySelector('.descripcionliquidacion_sueldos');
                if (hid) hid.value = payload.liquidacion_id || '';
                if (num) num.value = payload.liquidacion_numero || '';
                if (desc) desc.value = payload.liquidacion_descripcion || '';
                var submit = document.getElementById('sus-submit');
                if (submit) submit.textContent = 'Actualizar distribución';
                if (window.jQuery) window.jQuery('#sus_periodo_relativo').trigger('change');
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        var cancelar = document.getElementById('sus-cancelar-edicion');
        if (cancelar) cancelar.addEventListener('click', limpiar);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSuscripcionEditor();
        if (!document.getElementById('form-columna-rsd')) return;

        state.columnas = parseJsonScript('rsd-columnas-payload', []);
        state.config = Object.assign(state.config, parseJsonScript('rsd-editor-config', {}));

        activarTabColumnasSiPedido();
        cargarPreview();

        var togglePapel = document.getElementById('rsd-toggle-papel');
        if (togglePapel) {
            togglePapel.addEventListener('click', function () {
                var papel = document.getElementById('rsd-preview-papel');
                if (!papel) return;
                papel.classList.toggle('compacto');
                togglePapel.textContent = papel.classList.contains('compacto') ? 'Compacto' : 'Papel';
            });
        }

        var inicial = parseInt(state.config.columnaInicial || '0', 10);
        if (inicial > 0 && findColumna(inicial)) {
            seleccionarColumna(inicial, true);
        } else {
            modoNuevaColumna(true);
        }

        var contenido = document.getElementById('rsd_contenido');
        if (contenido) {
            contenido.addEventListener('change', function () {
                toggleCampos();
                renderConceptos();
                marcarDirty();
            });
        }

        ['rsd_nro_columna', 'rsd_descripcion', 'rsd_campo_empleado', 'rsd_largo', 'rsd_formula'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', marcarDirty);
            if (el) el.addEventListener('change', marcarDirty);
        });

        var nueva = document.getElementById('rsd-nueva-columna');
        if (nueva) nueva.addEventListener('click', function () { modoNuevaColumna(false); });

        var cancelar = document.getElementById('rsd-cancelar-edicion-columna');
        if (cancelar) cancelar.addEventListener('click', function () { modoNuevaColumna(false); });

        document.getElementById('form-concepto-rsd').addEventListener('submit', function (e) {
            e.preventDefault();
            agregarOActualizarConcepto();
        });

        document.addEventListener('click', function (e) {
            var sel = e.target.closest('.rsd-seleccionar-columna');
            if (sel) {
                var tr = sel.closest('tr');
                if (tr) seleccionarColumna(tr.getAttribute('data-columna-id'), false);
                return;
            }
            var ed = e.target.closest('.rsd-editar-concepto');
            if (ed) {
                editarConceptoLocal(ed.getAttribute('data-idx'));
                return;
            }
            var qu = e.target.closest('.rsd-quitar-concepto');
            if (qu) {
                quitarConceptoLocal(qu.getAttribute('data-idx'));
            }
        });

        var formCol = document.getElementById('form-columna-rsd');
        if (formCol) {
            formCol.addEventListener('submit', function () {
                renderConceptos();
            });
        }
    });
})();
