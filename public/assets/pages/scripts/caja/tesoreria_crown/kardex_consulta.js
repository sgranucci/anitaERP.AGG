(function () {
    var abriendo = {};

    function salaId() {
        var campo = document.querySelector('#form-filtros-tesoreria-crown-kardex input[name="sala_id"]');
        return campo ? campo.value : '';
    }

    function tipoId() {
        var campo = document.getElementById('kardex_tipo_id');
        return campo ? campo.value : '';
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

    function crearFila(celdas, onElegir) {
        var tr = document.createElement('tr');
        celdas.forEach(function (texto) {
            var td = document.createElement('td');
            td.textContent = texto;
            tr.appendChild(td);
        });
        var acciones = document.createElement('td');
        acciones.className = 'text-center';
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-outline-primary';
        boton.textContent = 'Elegir';
        boton.addEventListener('click', onElegir);
        acciones.appendChild(boton);
        tr.appendChild(acciones);
        return tr;
    }

    function mensajeTabla(cuerpo, cols, texto, clase) {
        cuerpo.textContent = '';
        var tr = document.createElement('tr');
        var td = document.createElement('td');
        td.colSpan = cols;
        td.className = clase || 'text-muted';
        td.textContent = texto;
        tr.appendChild(td);
        cuerpo.appendChild(tr);
    }

    function recurso(cfg) {
        var estado = { abriendo: false };

        function aplicar(item) {
            var hidden = document.getElementById(cfg.hiddenId);
            var codigo = document.getElementById(cfg.codigoId);
            var nombre = document.getElementById(cfg.nombreId);
            if (!hidden || !item) {
                return;
            }
            hidden.value = item.id;
            if (codigo) {
                var codigoVisible = item.abreviatura || item.codigo;
                codigo.value = codigoVisible;
                codigo.setAttribute('data-resuelto', String(codigoVisible));
                codigo.removeAttribute('data-invalido');
            }
            if (nombre) {
                nombre.value = item.nombre || '';
            }
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function limpiar(campo) {
            var hidden = document.getElementById(cfg.hiddenId);
            var nombre = document.getElementById(cfg.nombreId);
            if (hidden) {
                hidden.value = '';
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (nombre) {
                nombre.value = '';
            }
            if (campo) {
                campo.setAttribute('data-resuelto', '');
            }
        }

        function resolver(campo) {
            if (estado.abriendo || !campo || campo.readOnly) {
                return;
            }
            var texto = campo.value.trim();
            var hidden = document.getElementById(cfg.hiddenId);
            if (texto === '') {
                limpiar(campo);
                campo.removeAttribute('data-invalido');
                return;
            }
            if (campo.getAttribute('data-resuelto') === texto && hidden && hidden.value) {
                return;
            }
            var data = { sala_id: salaId(), codigo: texto };
            if (cfg.extraResolver) {
                Object.assign(data, cfg.extraResolver());
            }
            window.jQuery.get(window.carpetaBase + cfg.resolverUrl, data).done(function (respuesta) {
                if (!respuesta || !respuesta.ok || !respuesta[cfg.responseKey]) {
                    limpiar(campo);
                    campo.setAttribute('data-invalido', '1');
                    return;
                }
                aplicar(respuesta[cfg.responseKey]);
            });
        }

        function buscar(consulta) {
            var cuerpo = document.getElementById(cfg.tbodyId);
            if (!cuerpo) {
                return;
            }
            mensajeTabla(cuerpo, cfg.cols, 'Buscando…');
            var data = { sala_id: salaId(), consulta: consulta || '' };
            if (cfg.extraConsulta) {
                Object.assign(data, cfg.extraConsulta());
            }
            window.jQuery.ajax({
                url: window.carpetaBase + cfg.consultaUrl,
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': window.jQuery('meta[name="csrf-token"]').attr('content') },
                data: data
            }).done(function (respuesta) {
                cuerpo.textContent = '';
                var lista = (respuesta && respuesta.data) || [];
                if (!lista.length) {
                    mensajeTabla(cuerpo, cfg.cols, 'Sin resultados');
                    return;
                }
                lista.forEach(function (item) {
                    cuerpo.appendChild(crearFila(cfg.celdas(item), function () {
                        aplicar(item);
                        window.jQuery('#' + cfg.modalId).modal('hide');
                        var codigo = document.getElementById(cfg.codigoId);
                        if (codigo) {
                            enfocarSiguiente(codigo);
                        }
                    }));
                });
            }).fail(function () {
                mensajeTabla(cuerpo, cfg.cols, 'No se pudo consultar', 'text-danger');
            });
        }

        function abrir() {
            estado.abriendo = true;
            abriendo[cfg.key] = true;
            var buscador = document.getElementById(cfg.buscadorId);
            if (buscador) {
                buscador.value = '';
            }
            buscar('');
            window.jQuery('#' + cfg.modalId).modal('show');
            window.setTimeout(function () {
                if (buscador) {
                    buscador.focus();
                }
            }, 200);
        }

        document.addEventListener('mousedown', function (evento) {
            if (evento.target.closest && evento.target.closest('.' + cfg.lupaClass)) {
                estado.abriendo = true;
                abriendo[cfg.key] = true;
            }
        });

        document.addEventListener('click', function (evento) {
            if (evento.target.closest && evento.target.closest('.' + cfg.lupaClass)) {
                evento.preventDefault();
                abrir();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (!evento.target.classList || !evento.target.classList.contains(cfg.codigoClass)) {
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
            var hidden = document.getElementById(cfg.hiddenId);
            if (texto === '') {
                limpiar(campo);
                enfocarSiguiente(campo);
                return;
            }
            if (campo.getAttribute('data-resuelto') === texto && hidden && hidden.value) {
                enfocarSiguiente(campo);
                return;
            }
            var data = { sala_id: salaId(), codigo: texto };
            if (cfg.extraResolver) {
                Object.assign(data, cfg.extraResolver());
            }
            window.jQuery.get(window.carpetaBase + cfg.resolverUrl, data).done(function (respuesta) {
                if (!respuesta || !respuesta.ok || !respuesta[cfg.responseKey]) {
                    limpiar(campo);
                    campo.setAttribute('data-invalido', '1');
                    return;
                }
                aplicar(respuesta[cfg.responseKey]);
                enfocarSiguiente(campo);
            });
        }, true);

        document.addEventListener('focusout', function (evento) {
            if (!evento.target.classList || !evento.target.classList.contains(cfg.codigoClass)) {
                return;
            }
            if (estado.abriendo || abriendo[cfg.key]) {
                return;
            }
            resolver(evento.target);
        });

        document.addEventListener('input', function (evento) {
            if (!evento.target.classList || !evento.target.classList.contains(cfg.codigoClass)) {
                return;
            }
            evento.target.removeAttribute('data-invalido');
            if (evento.target.getAttribute('data-resuelto') !== evento.target.value.trim()) {
                var hidden = document.getElementById(cfg.hiddenId);
                if (hidden) {
                    hidden.value = '';
                }
            }
        });

        if (window.jQuery) {
            window.jQuery('#' + cfg.modalId).on('hidden.bs.modal', function () {
                estado.abriendo = false;
                abriendo[cfg.key] = false;
            });
            window.jQuery(document).on('keydown', '#' + cfg.buscadorId, function (evento) {
                if (evento.key !== 'Enter') {
                    return;
                }
                evento.preventDefault();
                var primero = document.querySelector('#' + cfg.tbodyId + ' button');
                if (primero) {
                    primero.click();
                }
            });
            window.jQuery(document).on('input', '#' + cfg.buscadorId, function () {
                buscar(this.value);
            });
        }

        return { limpiar: limpiar, aplicar: aplicar };
    }

    var deposito = recurso({
        key: 'deposito',
        hiddenId: 'kardex_deposito_id',
        codigoId: 'kardex_deposito_codigo',
        nombreId: 'kardex_deposito_nombre',
        codigoClass: 'codigokardeposito',
        lupaClass: 'consultakardeposito',
        modalId: 'consultakardepositoModal',
        buscadorId: 'consultakardeposito',
        tbodyId: 'datoskardeposito',
        cols: 3,
        consultaUrl: '/caja/tesoreria-crown/kardex-valores/consulta-deposito',
        resolverUrl: '/caja/tesoreria-crown/kardex-valores/resolver-deposito',
        responseKey: 'deposito',
        celdas: function (item) {
            return [item.codigo, item.nombre];
        }
    });

    recurso({
        key: 'tipo',
        hiddenId: 'kardex_tipo_id',
        codigoId: 'kardex_tipo_codigo',
        nombreId: 'kardex_tipo_nombre',
        codigoClass: 'codigokartipo',
        lupaClass: 'consultakartipo',
        modalId: 'consultakartipoModal',
        buscadorId: 'consultakartipo',
        tbodyId: 'datoskartipo',
        cols: 4,
        consultaUrl: '/caja/tesoreria-crown/boveda/consulta-tipo',
        resolverUrl: '/caja/tesoreria-crown/boveda/resolver-tipo',
        responseKey: 'tipo',
        celdas: function (item) {
            return [item.codigo, item.nombre, item.efecto || ''];
        }
    });

    recurso({
        key: 'subtipo',
        hiddenId: 'kardex_subtipo_id',
        codigoId: 'kardex_subtipo_codigo',
        nombreId: 'kardex_subtipo_nombre',
        codigoClass: 'codigokarsubtipo',
        lupaClass: 'consultakarsubtipo',
        modalId: 'consultakarsubtipoModal',
        buscadorId: 'consultakarsubtipo',
        tbodyId: 'datoskarsubtipo',
        cols: 3,
        consultaUrl: '/caja/tesoreria-crown/kardex-valores/consulta-subtipo',
        resolverUrl: '/caja/tesoreria-crown/kardex-valores/resolver-subtipo',
        responseKey: 'subtipo',
        extraConsulta: function () {
            return { tipo_id: tipoId() };
        },
        extraResolver: function () {
            return { tipo_id: tipoId() };
        },
        celdas: function (item) {
            return [item.codigo, item.nombre];
        }
    });

    recurso({
        key: 'valor',
        hiddenId: 'kardex_valor_id',
        codigoId: 'kardex_valor_codigo',
        nombreId: 'kardex_valor_nombre',
        codigoClass: 'codigokarvalor',
        lupaClass: 'consultakarvalor',
        modalId: 'consultakarvalorModal',
        buscadorId: 'consultakarvalor',
        tbodyId: 'datoskarvalor',
        cols: 3,
        consultaUrl: '/caja/tesoreria-crown/kardex-valores/consulta-valor',
        resolverUrl: '/caja/tesoreria-crown/kardex-valores/resolver-valor',
        responseKey: 'valor',
        celdas: function (item) {
            return [item.codigo, item.nombre];
        }
    });

    document.addEventListener('change', function (evento) {
        if (evento.target && evento.target.id === 'kardex_tipo_id') {
            var hidden = document.getElementById('kardex_subtipo_id');
            var codigo = document.getElementById('kardex_subtipo_codigo');
            var nombre = document.getElementById('kardex_subtipo_nombre');
            if (hidden) {
                hidden.value = '';
            }
            if (codigo) {
                codigo.value = '';
                codigo.setAttribute('data-resuelto', '');
            }
            if (nombre) {
                nombre.value = '';
            }
        }
    });

    void deposito;
})();
