(function () {
    'use strict';

    var root = document.getElementById('anita-buscador');
    var input = document.getElementById('anita-buscador-q');
    var panel = document.getElementById('anita-buscador-panel');
    var lupa = document.getElementById('anita-buscador-lupa');
    if (!root || !input || !panel) {
        return;
    }

    var url = root.getAttribute('data-url') || '';
    var urlEleccion = root.getAttribute('data-eleccion') || '';
    var timer = null;
    var seq = 0;
    var controlador = null;
    var activo = -1;

    function iconoSeguro(clases) {
        var texto = String(clases || '').trim();
        if (!/^[a-z0-9\-\s]+$/i.test(texto)) {
            return 'fa fa-circle';
        }
        return texto;
    }

    function cerrar() {
        panel.classList.add('d-none');
        panel.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        activo = -1;
    }

    function abrir() {
        panel.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    }

    function items() {
        return panel.querySelectorAll('.anita-buscador-item');
    }

    function marcar(indice) {
        var lista = items();
        if (!lista.length) {
            activo = -1;
            return;
        }
        if (indice < 0) {
            indice = lista.length - 1;
        }
        if (indice >= lista.length) {
            indice = 0;
        }
        activo = indice;
        lista.forEach(function (el, i) {
            el.classList.toggle('is-active', i === activo);
        });
        if (lista[activo] && lista[activo].scrollIntoView) {
            lista[activo].scrollIntoView({ block: 'nearest' });
        }
    }

    function grupo(titulo) {
        var encabezado = document.createElement('div');
        encabezado.className = 'anita-buscador-grupo';
        encabezado.textContent = titulo;
        return encabezado;
    }

    function agujas(consulta) {
        return String(consulta || '').trim().toLocaleLowerCase('es').split(/\s+/).filter(function (parte) {
            return parte.length >= 2;
        });
    }

    function escribirResaltado(nodo, texto, consulta) {
        var fuente = String(texto || '');
        var partes = agujas(consulta);
        if (!partes.length) {
            nodo.textContent = fuente;
            return;
        }
        var bajo = fuente.toLocaleLowerCase('es');
        var i = 0;
        while (i < fuente.length) {
            var mejor = -1;
            var largo = 0;
            partes.forEach(function (parte) {
                var en = bajo.indexOf(parte, i);
                if (en !== -1 && (mejor === -1 || en < mejor)) {
                    mejor = en;
                    largo = parte.length;
                }
            });
            if (mejor === -1) {
                nodo.appendChild(document.createTextNode(fuente.slice(i)));
                break;
            }
            if (mejor > i) {
                nodo.appendChild(document.createTextNode(fuente.slice(i, mejor)));
            }
            var marca = document.createElement('mark');
            marca.className = 'anita-buscador-marca';
            marca.textContent = fuente.slice(mejor, mejor + largo);
            nodo.appendChild(marca);
            i = mejor + largo;
        }
    }

    function registrarEleccion(item) {
        if (!urlEleccion || !item || !item.clave || !item.tipo) {
            return;
        }
        var token = document.querySelector('meta[name="csrf-token"]');
        var cuerpo = new URLSearchParams();
        cuerpo.set('q', input.value.trim());
        cuerpo.set('tipo', item.tipo);
        cuerpo.set('clave', item.clave);
        fetch(urlEleccion, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') || '' : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: cuerpo.toString(),
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {});
    }

    function fila(item, consulta) {
        var enlace = document.createElement('a');
        enlace.className = 'anita-buscador-item';
        enlace.href = item.url || '#';
        enlace.setAttribute('role', 'option');
        if (item.nueva_pestana) {
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
        }

        var icono = document.createElement('i');
        icono.className = iconoSeguro(item.icono) + ' fa-fw';
        enlace.appendChild(icono);

        var textos = document.createElement('span');
        textos.className = 'anita-buscador-textos';

        var titulo = document.createElement('span');
        titulo.className = 'anita-buscador-titulo';
        escribirResaltado(titulo, item.titulo || '', consulta);
        if (item.anclado) {
            var pin = document.createElement('i');
            pin.className = 'fa fa-thumbtack anita-buscador-anclado';
            pin.title = 'Anclado en la barra';
            titulo.appendChild(document.createTextNode(' '));
            titulo.appendChild(pin);
        }
        textos.appendChild(titulo);

        if (item.detalle) {
            var detalle = document.createElement('span');
            detalle.className = 'anita-buscador-detalle';
            escribirResaltado(detalle, item.detalle, consulta);
            textos.appendChild(detalle);
        }

        enlace.appendChild(textos);
        enlace.addEventListener('mouseenter', function () {
            var lista = items();
            marcar(Array.prototype.indexOf.call(lista, enlace));
        });
        enlace.addEventListener('click', function (evento) {
            registrarEleccion(item);
            if (item.accion === 'consulta_ia') {
                evento.preventDefault();
                cerrar();
                if (item.enviar && window.AnitaAiConsultaEnviar) {
                    window.AnitaAiConsultaEnviar(item.frase || '');
                } else if (window.AnitaAiConsultaAbrir) {
                    window.AnitaAiConsultaAbrir(item.frase || '');
                }
            }
        });
        return enlace;
    }

    function filaSugerencia(item) {
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'anita-buscador-item anita-buscador-sugerencia';
        boton.setAttribute('role', 'option');
        boton.setAttribute('data-sugerencia', item.texto || '');

        var icono = document.createElement('i');
        icono.className = (item.tipo === 'correccion' ? 'fa fa-spell-check' : 'fa fa-search') + ' fa-fw';
        boton.appendChild(icono);

        var textos = document.createElement('span');
        textos.className = 'anita-buscador-textos';
        var titulo = document.createElement('span');
        titulo.className = 'anita-buscador-titulo';
        escribirResaltado(titulo, item.texto || '', input.value.trim());
        textos.appendChild(titulo);
        var detalle = document.createElement('span');
        detalle.className = 'anita-buscador-detalle';
        detalle.textContent = item.tipo === 'correccion' ? 'Reemplaza lo escrito' : 'Completar búsqueda';
        textos.appendChild(detalle);
        boton.appendChild(textos);

        boton.addEventListener('mouseenter', function () {
            var lista = items();
            marcar(Array.prototype.indexOf.call(lista, boton));
        });
        boton.addEventListener('click', function () {
            aplicarSugerencia(item.texto || '');
        });
        return boton;
    }

    function aplicarSugerencia(texto) {
        if (!texto) {
            return;
        }
        input.value = texto;
        input.focus();
        buscar();
    }

    function pintar(data, consulta) {
        panel.innerHTML = '';
        var sugerencias = (data && data.sugerencias) || [];
        var pantallas = (data && data.pantallas) || [];
        var ayuda = (data && data.ayuda) || [];
        var comprobantes = (data && data.comprobantes) || [];
        var registros = (data && data.registros) || [];
        var acciones = (data && data.acciones) || [];
        var frase = (data && data.frase) || null;

        if (!sugerencias.length && !acciones.length && !pantallas.length && !ayuda.length && !comprobantes.length && !registros.length && !frase) {
            var vacio = document.createElement('div');
            vacio.className = 'anita-buscador-vacio';
            vacio.textContent = 'Sin resultados para «' + consulta + '».';
            panel.appendChild(vacio);
            abrir();
            return;
        }

        if (sugerencias.length) {
            var esCorreccion = sugerencias[0].tipo === 'correccion';
            panel.appendChild(grupo(esCorreccion ? 'Quisiste decir' : 'Sugerencias'));
            sugerencias.forEach(function (item) {
                panel.appendChild(filaSugerencia(item));
            });
        }
        if (acciones.length) {
            panel.appendChild(grupo('Acciones'));
            acciones.forEach(function (item) {
                panel.appendChild(fila(item, consulta));
            });
        }
        if (frase && frase.titulo) {
            panel.appendChild(grupo(frase.enviar ? 'Preguntar' : 'Consulta'));
            panel.appendChild(fila(frase, consulta));
        }
        if (pantallas.length) {
            panel.appendChild(grupo('Pantallas'));
            pantallas.forEach(function (item) {
                panel.appendChild(fila(item, consulta));
            });
        }
        if (ayuda.length) {
            panel.appendChild(grupo('Ayuda'));
            ayuda.forEach(function (item) {
                panel.appendChild(fila(item, consulta));
            });
        }
        if (comprobantes.length) {
            panel.appendChild(grupo('Comprobantes'));
            comprobantes.forEach(function (item) {
                panel.appendChild(fila(item, consulta));
            });
        }
        if (registros.length) {
            panel.appendChild(grupo('Registros'));
            registros.forEach(function (item) {
                panel.appendChild(fila(item, consulta));
            });
        }

        var pie = document.createElement('div');
        pie.className = 'anita-buscador-pie';
        pie.textContent = 'Flechas para moverse · Enter abre · Esc cierra';
        panel.appendChild(pie);
        abrir();
        marcar(0);
    }

    function estado(mensaje) {
        panel.innerHTML = '';
        var caja = document.createElement('div');
        caja.className = 'anita-buscador-estado';
        caja.textContent = mensaje;
        panel.appendChild(caja);
        abrir();
    }

    function buscar() {
        var consulta = input.value.trim();
        if (consulta.length < 2) {
            cerrar();
            return;
        }

        if (controlador && controlador.abort) {
            controlador.abort();
        }
        controlador = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var actual = ++seq;
        estado('Buscando…');

        var destino = url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(consulta);
        fetch(destino, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controlador ? controlador.signal : undefined
        }).then(function (respuesta) {
            if (!respuesta.ok) {
                throw new Error('busqueda');
            }
            return respuesta.json();
        }).then(function (data) {
            if (actual !== seq) {
                return;
            }
            pintar(data, consulta);
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            if (actual !== seq) {
                return;
            }
            estado('No se pudo buscar. Reintente.');
        });
    }

    function programar() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(buscar, 180);
    }

    function abrirActivo() {
        var lista = items();
        var elegido = lista[activo] || lista[0];
        if (elegido) {
            elegido.click();
        }
    }

    input.addEventListener('input', programar);
    input.addEventListener('focus', function () {
        if (input.value.trim().length >= 2 && panel.classList.contains('d-none')) {
            programar();
        }
    });
    input.addEventListener('keydown', function (evento) {
        var lista = items();
        if (evento.key === 'ArrowDown') {
            if (!lista.length) {
                return;
            }
            evento.preventDefault();
            marcar(activo + 1);
            return;
        }
        if (evento.key === 'ArrowUp') {
            if (!lista.length) {
                return;
            }
            evento.preventDefault();
            marcar(activo - 1);
            return;
        }
        if (evento.key === 'Escape') {
            cerrar();
            return;
        }
        if (evento.key === 'Enter') {
            if (lista.length) {
                evento.preventDefault();
                abrirActivo();
            }
        }
    });

    if (lupa) {
        lupa.addEventListener('click', function () {
            if (items().length) {
                abrirActivo();
                return;
            }
            input.focus();
            buscar();
        });
    }

    document.addEventListener('click', function (evento) {
        if (!root.contains(evento.target)) {
            cerrar();
        }
    });
})();
