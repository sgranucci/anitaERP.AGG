/**
 * Cierre contable: valida fecha del asiento contra empresa.
 * URL desde data-cierre-url del input #fecha (APP_CARPETA).
 *
 * #fecha es texto libre (dd/mm/aaaa o ddmmaaaa). El valor que se graba
 * va en #fecha_iso (Y-m-d). No avisa por empresa faltante mientras se tipea:
 * ese alert cortaba la carga de la fecha.
 */
(function () {
    'use strict';

    var enCurso = false;
    var ultimoAvisoPermitido = '';

    function qs(id) {
        return document.getElementById(id);
    }

    function esFechaLibre() {
        var el = qs('fecha');
        return !!(el && el.getAttribute('data-fecha-libre') === '1');
    }

    function isoEl() {
        return qs('fecha_iso');
    }

    function pad2(n) {
        n = String(n);
        return n.length < 2 ? '0' + n : n;
    }

    function fechaDesdePartes(dia, mes, anio) {
        dia = parseInt(dia, 10);
        mes = parseInt(mes, 10);
        anio = parseInt(anio, 10);
        if (!dia || !mes || !anio) {
            return null;
        }
        if (anio < 100) {
            anio += 2000;
        }
        if (anio < 1900 || anio > 2100 || mes < 1 || mes > 12 || dia < 1 || dia > 31) {
            return null;
        }
        var dt = new Date(anio, mes - 1, dia);
        if (dt.getFullYear() !== anio || dt.getMonth() !== mes - 1 || dt.getDate() !== dia) {
            return null;
        }
        return {
            iso: anio + '-' + pad2(mes) + '-' + pad2(dia),
            texto: pad2(dia) + '/' + pad2(mes) + '/' + anio
        };
    }

    function parseFechaLibre(valor) {
        var t = String(valor || '').trim();
        if (!t) {
            return { vacia: true };
        }
        var m = t.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/);
        if (m) {
            var conSep = fechaDesdePartes(m[1], m[2], m[3]);
            return conSep ? { ok: true, iso: conSep.iso, texto: conSep.texto } : { ok: false };
        }
        if (/[^\d]/.test(t)) {
            return { ok: false };
        }
        if (t.length === 8) {
            var ocho = fechaDesdePartes(t.slice(0, 2), t.slice(2, 4), t.slice(4));
            return ocho ? { ok: true, iso: ocho.iso, texto: ocho.texto } : { ok: false };
        }
        if (t.length === 6) {
            var seis = fechaDesdePartes(t.slice(0, 2), t.slice(2, 4), t.slice(4));
            return seis ? { ok: true, iso: seis.iso, texto: seis.texto } : { ok: false };
        }
        return { ok: false };
    }

    function mostrarErrorFecha(texto) {
        var err = qs('fecha-libre-error');
        if (!err) {
            return;
        }
        if (!texto) {
            err.textContent = '';
            err.classList.add('d-none');
            return;
        }
        err.textContent = texto;
        err.classList.remove('d-none');
    }

    /**
     * Normaliza lo escrito y copia Y-m-d a #fecha_iso.
     * @param {boolean} avisar  true = alert si la fecha no se puede leer (al guardar)
     * @return {boolean}
     */
    function commitFechaLibre(avisar) {
        if (!esFechaLibre()) {
            return true;
        }
        var el = qs('fecha');
        var parsed = parseFechaLibre(el ? el.value : '');
        var hidden = isoEl();
        var cal = qs('fecha_calendario');
        if (parsed.vacia) {
            if (hidden) {
                hidden.value = '';
            }
            if (cal) {
                cal.value = '';
            }
            mostrarErrorFecha('');
            return true;
        }
        if (!parsed.ok) {
            if (hidden) {
                hidden.value = '';
            }
            mostrarErrorFecha('Fecha inválida. Escribila como dd/mm/aaaa o 03012026.');
            if (avisar) {
                window.alert('La fecha no es válida. Escribila como dd/mm/aaaa (por ejemplo 03/01/2026) o como 03012026.');
                if (el) {
                    el.focus();
                }
            }
            return false;
        }
        if (el) {
            el.value = parsed.texto;
        }
        if (hidden) {
            hidden.value = parsed.iso;
        }
        if (cal) {
            cal.value = parsed.iso;
        }
        mostrarErrorFecha('');
        return true;
    }

    function fechaIsoConsulta() {
        if (!esFechaLibre()) {
            var plano = qs('fecha');
            return plano ? String(plano.value || '').trim() : '';
        }
        var el = qs('fecha');
        var parsed = parseFechaLibre(el ? el.value : '');
        if (parsed.vacia) {
            return '';
        }
        if (parsed.ok) {
            return parsed.iso;
        }
        var hidden = isoEl();
        return hidden ? String(hidden.value || '').trim() : '';
    }

    function urlCierre() {
        var fecha = qs('fecha');
        if (fecha && fecha.getAttribute('data-cierre-url')) {
            return fecha.getAttribute('data-cierre-url');
        }
        var base = (window.carpetaBase || '').replace(/\/$/, '');
        return base + '/contable/cierre-periodo/validar-fecha';
    }

    function empresaId() {
        var el = qs('empresa_id');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    function fechaVal() {
        if (esFechaLibre()) {
            var hidden = isoEl();
            return hidden ? String(hidden.value || '').trim() : '';
        }
        var el = qs('fecha');
        return el ? String(el.value || '').trim() : '';
    }

    /** @param {string} modo  'bloqueo' (fecha limpiada) | 'guardar' (no se guardó) | 'permitido' */
    function avisar(mensaje, modo) {
        var texto = mensaje || 'Período contable cerrado.';
        var msg = qs('modal-periodo-contable-cerrado-mensaje');
        if (msg) {
            msg.textContent = texto;
        }
        var titulo = qs('modal-periodo-contable-cerrado-titulo');
        if (titulo) {
            titulo.textContent = modo === 'permitido'
                ? 'Aviso: período contable cerrado'
                : 'Período contable cerrado';
        }
        var ayudas = {
            bloqueo: qs('modal-periodo-contable-cerrado-ayuda'),
            guardar: qs('modal-periodo-contable-cerrado-ayuda-guardar'),
            permitido: qs('modal-periodo-contable-cerrado-ayuda-permitido')
        };
        Object.keys(ayudas).forEach(function (clave) {
            if (ayudas[clave]) {
                ayudas[clave].classList.toggle('d-none', clave !== modo);
            }
        });
        var modal = qs('modal-periodo-contable-cerrado');
        if (modal && window.jQuery && typeof window.jQuery(modal).modal === 'function') {
            window.jQuery(modal).modal('show');
        } else {
            window.alert(texto);
        }
    }

    /** Evita repetir el aviso informativo en cada blur de la misma fecha/empresa. */
    function avisarUnaVez(mensaje) {
        var clave = empresaId() + '|' + fechaVal();
        if (clave === ultimoAvisoPermitido) {
            return;
        }
        ultimoAvisoPermitido = clave;
        avisar(mensaje, 'permitido');
    }

    function bloquearFecha(mensaje) {
        var el = qs('fecha');
        var hidden = isoEl();
        var cal = qs('fecha_calendario');
        if (el) {
            el.value = '';
        }
        if (hidden) {
            hidden.value = '';
        }
        if (cal) {
            cal.value = '';
        }
        avisar(mensaje, 'bloqueo');
        if (el) {
            setTimeout(function () { el.focus(); }, 250);
        }
    }

    /**
     * Bloqueo al guardar: conserva la fecha y el resto del asiento cargado
     * (la apertura pudo vencer mientras se completaba el formulario).
     */
    function bloquearGuardado(mensaje) {
        avisar(mensaje, 'guardar');
        var el = qs('fecha');
        if (el) {
            setTimeout(function () { el.focus(); }, 250);
        }
    }

    /**
     * @param {boolean} avisarSinEmpresa
     * @param {boolean} sync  true = XHR síncrono (Guardar)
     * @return {boolean} false si bloqueado (solo sync)
     */
    function validar(avisarSinEmpresa, sync) {
        var fecha = fechaVal();
        var emp = empresaId();
        if (!fecha) {
            return true;
        }
        if (emp <= 0) {
            return true;
        }

        var url = urlCierre()
            + '?empresa_id=' + encodeURIComponent(emp)
            + '&fecha=' + encodeURIComponent(fecha)
            + '&alcance=contable';

        if (sync) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, false);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(null);
                if (xhr.status < 200 || xhr.status >= 300) {
                    window.alert('No se pudo validar el cierre contable (HTTP ' + xhr.status + ').\n' + url);
                    return true;
                }
                var resp = JSON.parse(xhr.responseText || '{}');
                if (resp.permitido === false) {
                    bloquearGuardado(resp.mensaje);
                    return false;
                }
                return true;
            } catch (err) {
                window.alert('Error al validar cierre contable: ' + (err && err.message ? err.message : err) + '\n' + url);
                return true;
            }
        }

        if (enCurso) {
            return true;
        }
        enCurso = true;
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status + ' — ' + url);
            }
            return res.json();
        }).then(function (resp) {
            if (resp && resp.permitido === false) {
                bloquearFecha(resp.mensaje);

                return;
            }
            if (resp && resp.advertencia) {
                avisarUnaVez(resp.advertencia);
            }
        }).catch(function (err) {
            window.alert('No se pudo validar el cierre contable.\n' + (err && err.message ? err.message : err));
        }).finally(function () {
            enCurso = false;
        });
        return true;
    }

    window.asientoCommitFechaLibre = function (avisar) {
        return commitFechaLibre(!!avisar);
    };

    window.asientoFechaIso = function () {
        return fechaIsoConsulta();
    };

    window.anitaValidarCierreAsiento = function () {
        if (esFechaLibre() && !commitFechaLibre(false)) {
            return false;
        }
        return validar(false, false);
    };

    window.asientoFechaCierrePermitidaSync = function () {
        if (esFechaLibre() && !commitFechaLibre(true)) {
            return false;
        }
        return validar(false, true);
    };

    // Alias usado por includes previos
    window.asientoValidarFechaCierreAhora = window.anitaValidarCierreAsiento;

    function bindEmpresa() {
        bindRevalidacion('empresa_id');
    }

    /** Al cambiar de empresa cambia el cierre que aplica: revalidar. */
    function bindRevalidacion(id) {
        var el = qs(id);
        if (!el || el.getAttribute('data-cierre-bind') === '1') {
            return;
        }
        el.setAttribute('data-cierre-bind', '1');
        el.addEventListener('change', function () {
            if (esFechaLibre()) {
                commitFechaLibre(false);
            }
            validar(false, false);
        });
        if (window.jQuery) {
            window.jQuery(el).on('change.anitaCierre select2:select.anitaCierre', function () {
                if (esFechaLibre()) {
                    commitFechaLibre(false);
                }
                validar(false, false);
            });
        }
    }

    function validarSiHayEmpresa() {
        if (!fechaVal() || empresaId() <= 0) {
            return;
        }
        validar(false, false);
    }

    function init() {
        if (!qs('fecha')) {
            return;
        }
        bindEmpresa();
        if (esFechaLibre()) {
            var el = qs('fecha');
            el.addEventListener('focus', function () {
                if (typeof el.select === 'function') {
                    el.select();
                }
            });
            el.addEventListener('blur', function () {
                if (!commitFechaLibre(false)) {
                    return;
                }
                validarSiHayEmpresa();
            });
            var cal = qs('fecha_calendario');
            if (cal) {
                cal.addEventListener('change', function () {
                    var iso = String(cal.value || '').trim();
                    var partes = iso.split('-');
                    if (partes.length === 3 && el) {
                        el.value = partes[2] + '/' + partes[1] + '/' + partes[0];
                    }
                    if (!commitFechaLibre(false)) {
                        return;
                    }
                    validarSiHayEmpresa();
                });
            }
        }
        // Si al abrir ya hay empresa+fecha (alta), validar.
        var edicion = document.querySelector('input[name="_method"][value="PUT"], input[name="_method"][value="put"]');
        if (!edicion && fechaVal() && empresaId() > 0) {
            validar(false, false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    // Por si el select empresa se pinta después (Select2).
    window.setTimeout(bindEmpresa, 500);
})();
