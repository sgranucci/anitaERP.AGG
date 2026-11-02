(function () {
    var abriendo = false;

    function salaId() {
        var campo = document.querySelector('input[name="sala_id"]');
        return campo ? campo.value : '';
    }

    function aplicarTipo(tipo) {
        var hidden = document.getElementById('boveda_tipo_id');
        var codigo = document.getElementById('boveda_tipo_codigo');
        var nombre = document.getElementById('boveda_tipo_nombre');
        var efecto = document.getElementById('boveda_tipo_efecto');
        if (!hidden || !tipo) {
            return;
        }
        hidden.value = tipo.id;
        hidden.setAttribute('data-monedas', (tipo.monedas || []).join(','));
        hidden.setAttribute('data-referencia', tipo.referencia || '');
        hidden.setAttribute('data-efecto', tipo.efecto || '');
        if (codigo) {
            codigo.value = tipo.codigo;
            codigo.setAttribute('data-resuelto', String(tipo.codigo));
            codigo.removeAttribute('data-invalido');
        }
        if (nombre) {
            nombre.value = tipo.nombre || '';
        }
        if (efecto) {
            efecto.textContent = tipo.efecto || '';
        }
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function limpiarTipo(codigo) {
        var hidden = document.getElementById('boveda_tipo_id');
        var nombre = document.getElementById('boveda_tipo_nombre');
        var efecto = document.getElementById('boveda_tipo_efecto');
        if (hidden) {
            hidden.value = '';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (nombre) {
            nombre.value = '';
        }
        if (efecto) {
            efecto.textContent = '';
        }
        if (codigo) {
            codigo.setAttribute('data-resuelto', '');
        }
    }

    function resolver(campo) {
        if (abriendo || !campo || campo.readOnly) {
            return;
        }
        var texto = campo.value.trim();
        var hidden = document.getElementById('boveda_tipo_id');
        if (texto === '') {
            limpiarTipo(campo);
            campo.removeAttribute('data-invalido');
            return;
        }
        if (campo.getAttribute('data-resuelto') === texto && hidden && hidden.value) {
            return;
        }
        window.jQuery.get(window.carpetaBase + '/caja/tesoreria-crown/boveda/resolver-tipo', {
            sala_id: salaId(),
            codigo: texto
        }).done(function (respuesta) {
            if (!respuesta || !respuesta.ok) {
                limpiarTipo(campo);
                campo.setAttribute('data-invalido', '1');
                return;
            }
            aplicarTipo(respuesta.tipo);
        });
    }

    function enfocarSiguiente(desde) {
        var form = desde && desde.closest ? desde.closest('form') : null;
        if (!form) {
            return;
        }
        var campos = Array.prototype.filter.call(form.querySelectorAll('input, select, textarea'), function (campo) {
            if (campo.disabled || campo.readOnly || (campo.closest && campo.closest('.is-off'))) {
                return false;
            }
            var tipo = (campo.type || '').toLowerCase();
            if (tipo === 'hidden' || tipo === 'submit' || tipo === 'button' || tipo === 'reset' || tipo === 'image') {
                return false;
            }
            return campo.tabIndex >= 0;
        });
        var indice = campos.indexOf(desde);
        var siguiente = indice >= 0 ? campos[indice + 1] : null;
        if (!siguiente) {
            var boton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (boton) {
                boton.focus();
            }
            return;
        }
        siguiente.focus();
        if (siguiente.tagName !== 'SELECT' && typeof siguiente.select === 'function') {
            siguiente.select();
        }
    }

    function avisarCodigo(campo, mensaje) {
        campo.setCustomValidity(mensaje);
        campo.reportValidity();
        campo.setCustomValidity('');
        campo.focus();
    }

    function fila(tipo) {
        var tr = document.createElement('tr');
        [tipo.codigo, tipo.nombre, tipo.efecto].forEach(function (texto) {
            var td = document.createElement('td');
            td.textContent = texto;
            tr.appendChild(td);
        });
        var acciones = document.createElement('td');
        acciones.className = 'text-center';
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-outline-primary js-elegir-boveda-tipo';
        boton.textContent = 'Elegir';
        boton.addEventListener('click', function () {
            aplicarTipo(tipo);
            window.jQuery('#consultabovedatipoModal').modal('hide');
            var codigo = document.getElementById('boveda_tipo_codigo');
            if (codigo) {
                enfocarSiguiente(codigo);
            }
        });
        acciones.appendChild(boton);
        tr.appendChild(acciones);
        return tr;
    }

    function buscar(consulta) {
        var cuerpo = document.getElementById('datosbovedatipo');
        if (!cuerpo) {
            return;
        }
        cuerpo.textContent = '';
        var aviso = document.createElement('tr');
        var celda = document.createElement('td');
        celda.colSpan = 4;
        celda.className = 'text-muted';
        celda.textContent = 'Buscando…';
        aviso.appendChild(celda);
        cuerpo.appendChild(aviso);
        window.jQuery.ajax({
            url: window.carpetaBase + '/caja/tesoreria-crown/boveda/consulta-tipo',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': window.jQuery('meta[name="csrf-token"]').attr('content') },
            data: { sala_id: salaId(), consulta: consulta || '' }
        }).done(function (respuesta) {
            cuerpo.textContent = '';
            var lista = (respuesta && respuesta.data) || [];
            if (!lista.length) {
                var vacio = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = 4;
                td.className = 'text-muted';
                td.textContent = 'Sin resultados';
                vacio.appendChild(td);
                cuerpo.appendChild(vacio);
                return;
            }
            lista.forEach(function (tipo) {
                cuerpo.appendChild(fila(tipo));
            });
        }).fail(function () {
            cuerpo.textContent = '';
            var error = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'text-danger';
            td.textContent = 'No se pudo consultar';
            error.appendChild(td);
            cuerpo.appendChild(error);
        });
    }

    function abrir() {
        abriendo = true;
        var buscador = document.getElementById('consultabovedatipo');
        if (buscador) {
            buscador.value = '';
        }
        buscar('');
        window.jQuery('#consultabovedatipoModal').modal('show');
        window.setTimeout(function () {
            if (buscador) {
                buscador.focus();
            }
        }, 200);
    }

    document.addEventListener('mousedown', function (evento) {
        if (evento.target.closest && evento.target.closest('.consultabovedatipo')) {
            abriendo = true;
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (!evento.target.classList || !evento.target.classList.contains('codigobovedatipo')) {
            return;
        }
        if (evento.target.readOnly || evento.target.disabled) {
            return;
        }
        if (evento.key === 'F1') {
            evento.preventDefault();
            abrir();
            return;
        }
        if (evento.key !== 'Enter') {
            return;
        }
        evento.preventDefault();
        evento.stopPropagation();
        var campo = evento.target;
        var texto = campo.value.trim();
        var hidden = document.getElementById('boveda_tipo_id');
        if (texto === '') {
            limpiarTipo(campo);
            avisarCodigo(campo, 'Elegí el código de transacción.');
            return;
        }
        if (campo.getAttribute('data-resuelto') === texto && hidden && hidden.value) {
            enfocarSiguiente(campo);
            return;
        }
        window.jQuery.get(window.carpetaBase + '/caja/tesoreria-crown/boveda/resolver-tipo', {
            sala_id: salaId(),
            codigo: texto
        }).done(function (respuesta) {
            if (!respuesta || !respuesta.ok) {
                limpiarTipo(campo);
                campo.setAttribute('data-invalido', '1');
                avisarCodigo(campo, 'Código de transacción inexistente.');
                return;
            }
            aplicarTipo(respuesta.tipo);
            enfocarSiguiente(campo);
        }).fail(function () {
            avisarCodigo(campo, 'No se pudo validar el código de transacción.');
        });
    }, true);

    document.addEventListener('click', function (evento) {
        if (evento.target.closest && evento.target.closest('.consultabovedatipo')) {
            evento.preventDefault();
            abrir();
        }
    });

    document.addEventListener('focusout', function (evento) {
        if (!evento.target.classList || !evento.target.classList.contains('codigobovedatipo')) {
            return;
        }
        if (abriendo) {
            return;
        }
        resolver(evento.target);
    });

    document.addEventListener('input', function (evento) {
        if (!evento.target.classList || !evento.target.classList.contains('codigobovedatipo')) {
            return;
        }
        evento.target.removeAttribute('data-invalido');
        if (evento.target.getAttribute('data-resuelto') !== evento.target.value.trim()) {
            var hidden = document.getElementById('boveda_tipo_id');
            if (hidden) {
                hidden.value = '';
            }
        }
    });

    if (window.jQuery) {
        window.jQuery('#consultabovedatipoModal').on('hidden.bs.modal', function () {
            abriendo = false;
        });
        window.jQuery(document).on('keydown', '#consultabovedatipo', function (evento) {
            if (evento.key !== 'Enter') {
                return;
            }
            evento.preventDefault();
            var primero = document.querySelector('#datosbovedatipo .js-elegir-boveda-tipo');
            if (primero) {
                primero.click();
            }
        });
        window.jQuery(document).on('input', '#consultabovedatipo', function () {
            buscar(this.value);
        });
    }
})();
