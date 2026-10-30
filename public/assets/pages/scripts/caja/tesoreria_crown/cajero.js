(function () {
    var campos = Array.prototype.slice.call(document.querySelectorAll('.tc-cajero-campo'));
    var modalEl = document.getElementById('modal-consulta-cajero-crown');
    if (!campos.length || !modalEl || !window.jQuery) {
        return;
    }
    var modal = window.jQuery(modalEl);
    var activo = null;
    var abriendo = false;
    var consultaHecha = '';
    var busquedaLista = false;
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    function codigoDe(campo) {
        return campo.querySelector('.tc-cajero-codigo');
    }

    function nombreDe(campo) {
        return campo.querySelector('.tc-cajero-nombre');
    }

    function aplicar(campo, fila) {
        var codigo = codigoDe(campo);
        codigo.value = fila.codigo;
        codigo.removeAttribute('data-invalido');
        nombreDe(campo).value = fila.nombre;
    }

    function limpiarNombre(campo) {
        nombreDe(campo).value = '';
    }

    function url(campo, base, extra) {
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return base + sep + 'sala_id=' + encodeURIComponent(campo.getAttribute('data-sala-id') || '') + extra;
    }

    function avisar(mensaje) {
        window.setTimeout(function () {
            window.alert(mensaje);
        }, 0);
    }

    function resolver(campo, avisarSiFalla) {
        var codigo = codigoDe(campo);
        var numero = codigo.value.trim();
        if (numero === '') {
            limpiarNombre(campo);
            codigo.removeAttribute('data-invalido');
            return Promise.resolve(true);
        }
        if (abriendo || modalEl.classList.contains('show')) {
            return Promise.resolve(false);
        }
        return fetch(url(campo, campo.getAttribute('data-resolver'), '&codigo=' + encodeURIComponent(numero)), {
            headers: headers,
            credentials: 'same-origin'
        }).then(function (respuesta) {
            return respuesta.json().then(function (fila) {
                return { ok: respuesta.ok, fila: fila };
            });
        }).then(function (resultado) {
            if (!resultado || !resultado.ok) {
                limpiarNombre(campo);
                var mensaje = (resultado && resultado.fila && resultado.fila.mensaje) || 'No hay un cajero activo con ese código.';
                if (codigo.getAttribute('data-invalido') === numero) {
                    if (avisarSiFalla) {
                        avisar(mensaje);
                    }
                    return false;
                }
                codigo.setAttribute('data-invalido', numero);
                if (avisarSiFalla) {
                    avisar(mensaje);
                    codigo.focus();
                }
                return false;
            }
            aplicar(campo, resultado.fila);
            return true;
        }).catch(function () {
            return false;
        });
    }

    function pintar(filas, total) {
        var cuerpo = document.getElementById('modal-consulta-cajero-crown-filas');
        var aviso = document.getElementById('modal-consulta-cajero-crown-total');
        cuerpo.innerHTML = '';
        if (aviso) {
            aviso.textContent = (total || filas.length) + ' cajeros';
        }
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td></td><td></td><td></td><td class="text-right"><button type="button" class="btn btn-sm btn-warning">Elegir</button></td>';
            tr.children[0].textContent = fila.codigo;
            tr.children[1].textContent = fila.legajo || '';
            tr.children[2].textContent = fila.nombre;
            tr.querySelector('button').addEventListener('click', function () {
                if (!activo) {
                    return;
                }
                var campo = activo;
                aplicar(campo, fila);
                modal.one('hidden.bs.modal', function () {
                    var comentario = campo.parentElement.querySelector('input[name="comentario"]');
                    if (comentario) {
                        comentario.focus();
                    }
                });
                modal.modal('hide');
            });
            cuerpo.appendChild(tr);
        });
    }

    function buscar() {
        if (!activo) {
            return;
        }
        var q = document.getElementById('modal-consulta-cajero-crown-q').value.trim();
        consultaHecha = q;
        busquedaLista = false;
        document.getElementById('modal-consulta-cajero-crown-filas').innerHTML = '';
        fetch(url(activo, activo.getAttribute('data-consulta'), '&q=' + encodeURIComponent(q)), {
            headers: headers,
            credentials: 'same-origin'
        }).then(function (respuesta) {
            return respuesta.json();
        }).then(function (data) {
            if (q !== consultaHecha) {
                return;
            }
            pintar(data.filas || [], data.total);
            busquedaLista = true;
        }).catch(function () {
            busquedaLista = false;
        });
    }

    function elegirPrimera() {
        var boton = modalEl.querySelector('#modal-consulta-cajero-crown-filas button');
        if (boton) {
            boton.click();
        }
    }

    function abrir(campo) {
        activo = campo;
        abriendo = true;
        consultaHecha = '';
        document.getElementById('modal-consulta-cajero-crown-q').value = '';
        document.getElementById('modal-consulta-cajero-crown-filas').innerHTML = '';
        modal.one('shown.bs.modal', function () {
            abriendo = false;
            document.getElementById('modal-consulta-cajero-crown-q').focus();
        });
        modal.one('hidden.bs.modal', function () {
            abriendo = false;
        });
        modal.modal('show');
        buscar();
    }

    campos.forEach(function (campo) {
        var codigo = codigoDe(campo);
        codigo.addEventListener('input', function () {
            codigo.removeAttribute('data-invalido');
        });
        codigo.addEventListener('blur', function () {
            window.setTimeout(function () {
                resolver(campo, false);
            }, 0);
        });
        codigo.addEventListener('keydown', function (evento) {
            if (evento.key === 'F1') {
                evento.preventDefault();
                abrir(campo);
            }
            if (evento.key === 'Enter') {
                if (campo.closest('[data-enter-campos]')) {
                    return;
                }
                evento.preventDefault();
                resolver(campo, true).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    var comentario = campo.parentElement.querySelector('input[name="comentario"]');
                    if (comentario) {
                        comentario.focus();
                    }
                });
            }
        });
        campo.querySelector('.tc-cajero-lupa').addEventListener('click', function () {
            abrir(campo);
        });
        var formulario = campo.closest('form');
        var comentario = formulario ? formulario.querySelector('input[name="comentario"]') : null;
        if (comentario && formulario.querySelector('.tc-mesa-ops')) {
            comentario.addEventListener('keydown', function (evento) {
                if (evento.key === 'Enter') {
                    evento.preventDefault();
                }
            });
        }
        if (codigo.value.trim() !== '' && nombreDe(campo).value.trim() === '') {
            resolver(campo, false);
        }
    });

    document.getElementById('modal-consulta-cajero-crown-buscar').addEventListener('click', buscar);
    document.getElementById('modal-consulta-cajero-crown-q').addEventListener('keydown', function (evento) {
        if (evento.key !== 'Enter') {
            return;
        }
        evento.preventDefault();
        var q = evento.target.value.trim();
        if (busquedaLista && q === consultaHecha && modalEl.querySelector('#modal-consulta-cajero-crown-filas button')) {
            elegirPrimera();
            return;
        }
        buscar();
    });
})();
