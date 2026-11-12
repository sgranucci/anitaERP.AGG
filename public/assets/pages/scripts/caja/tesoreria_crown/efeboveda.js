(function () {
    function texto(numero) {
        return numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function hiddenTipo() {
        return document.getElementById('boveda_tipo_id');
    }

    function monedasActivas() {
        var campo = hiddenTipo();
        if (!campo || !campo.value) {
            return ['ARS', 'USD', 'EUR'];
        }
        var lista = (campo.getAttribute('data-monedas') || 'ARS,USD,EUR').split(',').filter(Boolean);
        return lista.length ? lista : ['ARS', 'USD', 'EUR'];
    }

    function aplicarTipo() {
        var campo = hiddenTipo();
        var monedas = monedasActivas();
        var referencia = campo && campo.value ? (campo.getAttribute('data-referencia') || '') : '';
        var efecto = document.getElementById('boveda_tipo_efecto');
        if (efecto && campo) {
            efecto.textContent = campo.value ? (campo.getAttribute('data-efecto') || '') : '';
        }
        document.querySelectorAll('.tc-efeb-moneda').forEach(function (bloque) {
            var visible = monedas.indexOf(bloque.getAttribute('data-mon')) !== -1;
            bloque.classList.toggle('is-off', !visible);
            bloque.querySelectorAll('input').forEach(function (input) {
                input.disabled = !visible;
            });
        });
        document.querySelectorAll('.tc-efeb-ref').forEach(function (bloque) {
            var visible = bloque.getAttribute('data-ref') === referencia;
            bloque.classList.toggle('is-off', !visible);
            bloque.querySelectorAll('input, select, textarea, button').forEach(function (input) {
                input.disabled = !visible;
            });
        });
        recalcularTodo();
    }

    function cargarSubtipos(tipoId) {
        var wrap = document.getElementById('tc-boveda-subtipo-wrap');
        var select = document.getElementById('subtipo_id');
        var texto = document.getElementById('subtipo_texto');
        if (!wrap || !select) {
            return;
        }
        var url = wrap.getAttribute('data-url') || '';
        select.innerHTML = '<option value="">Elegí…</option>';
        if (texto) {
            texto.value = '';
            texto.classList.add('d-none');
        }
        actualizarAvisoSubtipo();
        if (!tipoId || !url) {
            wrap.classList.add('d-none');
            select.removeAttribute('required');
            return;
        }
        fetch(url + '?tipo_id=' + encodeURIComponent(tipoId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            var filas = data.data || [];
            if (!filas.length) {
                wrap.classList.add('d-none');
                select.removeAttribute('required');
                actualizarAvisoSubtipo();
                return;
            }
            filas.forEach(function (fila) {
                var opt = document.createElement('option');
                opt.value = fila.id;
                opt.textContent = fila.etiqueta;
                opt.setAttribute('data-codigo', fila.codigo || '');
                opt.setAttribute('data-mueve-billetaje', fila.mueve_billetaje === false || fila.mueve_billetaje === 0 ? '0' : '1');
                select.appendChild(opt);
            });
            wrap.classList.remove('d-none');
            select.setAttribute('required', 'required');
            actualizarAvisoSubtipo();
        }).catch(function () {
            wrap.classList.add('d-none');
            actualizarAvisoSubtipo();
        });
    }

    function actualizarAvisoSubtipo() {
        var select = document.getElementById('subtipo_id');
        var aviso = document.getElementById('tc-boveda-subtipo-aviso');
        if (!aviso) {
            return;
        }
        if (!select || !select.value) {
            aviso.classList.add('d-none');
            return;
        }
        var opt = select.options[select.selectedIndex];
        var mueve = opt ? (opt.getAttribute('data-mueve-billetaje') || '1') : '1';
        aviso.classList.toggle('d-none', mueve !== '0');
    }

    function totalPanel(panel) {
        var total = 0;
        panel.querySelectorAll('input[data-denom]').forEach(function (input) {
            if (input.disabled) {
                return;
            }
            var cantidad = parseFloat(String(input.value).replace(',', '.'));
            var denominacion = parseFloat(input.getAttribute('data-denom'));
            if (!isNaN(cantidad) && !isNaN(denominacion)) {
                total += cantidad * denominacion;
            }
        });
        return total;
    }

    function actualizarTotalPesos() {
        var total = 0;
        document.querySelectorAll('.js-panel-valor[data-en-pesos="1"]').forEach(function (panel) {
            var bloque = panel.closest('.tc-efeb-moneda');
            var oculto = bloque && bloque.classList.contains('is-off');
            var subtotal = oculto ? 0 : totalPanel(panel);
            var tasa = parseFloat(panel.getAttribute('data-tasa'));
            var pesos = !oculto && tasa > 0 ? subtotal * tasa : 0;
            if (!oculto && tasa > 0) {
                total += pesos;
            }
            var codigo = panel.getAttribute('data-grupo');
            if (!codigo) {
                return;
            }
            document.querySelectorAll('.js-pesos-origen[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = texto(subtotal);
            });
            document.querySelectorAll('.js-pesos-linea[data-grupo="' + codigo + '"], .js-total-en-pesos[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = texto(pesos);
            });
        });
        document.querySelectorAll('.js-total-pesos, .js-total-final').forEach(function (nodo) {
            nodo.textContent = texto(total);
        });
    }

    function recalcular(panel) {
        if (!panel.querySelector('input[data-denom]')) {
            return;
        }
        var escrito = texto(totalPanel(panel));
        panel.querySelectorAll('.js-total-valor').forEach(function (nodo) {
            nodo.textContent = escrito;
        });
        var codigo = panel.getAttribute('data-grupo');
        if (codigo) {
            document.querySelectorAll('.js-total-barra[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = escrito;
            });
        }
        actualizarTotalPesos();
    }

    function recalcularTodo() {
        document.querySelectorAll('.js-panel-valor').forEach(recalcular);
    }

    document.querySelectorAll('.js-panel-valor').forEach(function (panel) {
        panel.addEventListener('input', function () { recalcular(panel); });
    });
    var campo = hiddenTipo();
    if (campo) {
        campo.addEventListener('change', function () {
            aplicarTipo();
            cargarSubtipos(campo.value || '');
        });
    }
    var selectSubtipo = document.getElementById('subtipo_id');
    if (selectSubtipo) {
        selectSubtipo.addEventListener('change', function () {
            var texto = document.getElementById('subtipo_texto');
            var opt = selectSubtipo.options[selectSubtipo.selectedIndex];
            var codigo = opt ? (opt.getAttribute('data-codigo') || '') : '';
            if (texto) {
                texto.classList.toggle('d-none', String(codigo).toUpperCase() !== 'OTR');
                if (String(codigo).toUpperCase() !== 'OTR') {
                    texto.value = '';
                }
            }
            actualizarAvisoSubtipo();
        });
        actualizarAvisoSubtipo();
    }
    aplicarTipo();

    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (!form || !form.querySelector || !form.querySelector('#boveda_tipo_id')) {
            return;
        }
        var tilde = form.querySelector('#emitir_comprobante');
        if (tilde && tilde.checked) {
            window.open('about:blank', 'bovedaEmitir');
        }
    }, true);

    function cantidadesVisibles(form) {
        return Array.prototype.filter.call(form.querySelectorAll('input[data-denom]'), function (input) {
            return !input.disabled && !(input.closest && input.closest('.is-off'));
        });
    }

    function enfocarCantidad(input) {
        window.setTimeout(function () {
            input.focus();
        }, 0);
    }

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Enter' && evento.keyCode !== 13) {
            return;
        }
        var actual = evento.target;
        if (!actual || !actual.closest) {
            return;
        }
        var form = actual.closest('form');
        if (!form || !form.querySelector('#boveda_tipo_id')) {
            return;
        }
        var esComentario = actual.id === 'comentario';
        var esCantidad = actual.matches && actual.matches('input[data-denom]');
        if (!esComentario && !esCantidad) {
            return;
        }
        evento.preventDefault();
        evento.stopPropagation();
        if (esComentario && typeof actual.reportValidity === 'function' && !actual.reportValidity()) {
            return;
        }
        var cantidades = cantidadesVisibles(form);
        var destino = null;
        if (esComentario) {
            destino = cantidades[0] || null;
        } else {
            var indice = cantidades.indexOf(actual);
            destino = indice >= 0 ? cantidades[indice + 1] : null;
        }
        if (destino) {
            enfocarCantidad(destino);
            return;
        }
        var firma = form.querySelector('#firma_usuario');
        if (firma && !firma.disabled) {
            firma.focus();
            return;
        }
        var boton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (boton) {
            boton.focus();
        }
    }, true);
})();
