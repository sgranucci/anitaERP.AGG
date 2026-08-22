/**
 * Cierre contable: valida fecha del asiento contra empresa.
 * URL desde data-cierre-url del input #fecha (APP_CARPETA).
 */
(function () {
    'use strict';

    var enCurso = false;
    var ultimoAvisoPermitido = '';

    function qs(id) {
        return document.getElementById(id);
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
        if (el) {
            el.value = '';
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
            if (avisarSinEmpresa) {
                window.alert('Seleccione la empresa para validar si la fecha está dentro de un período cerrado.');
                var e = qs('empresa_id');
                if (e) {
                    e.focus();
                }
            }
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

    window.anitaValidarCierreAsiento = function (avisarSinEmpresa) {
        return validar(!!avisarSinEmpresa, false);
    };

    window.asientoFechaCierrePermitidaSync = function () {
        return validar(true, true);
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
            validar(false, false);
        });
        if (window.jQuery) {
            window.jQuery(el).on('change.anitaCierre select2:select.anitaCierre', function () {
                validar(false, false);
            });
        }
    }

    function init() {
        if (!qs('fecha')) {
            return;
        }
        bindEmpresa();
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
