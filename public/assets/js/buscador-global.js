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
    var ultimoPayload = null;
    var ultimoConsulta = '';
    var filtroActivo = 'todos';

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
        return panel.querySelectorAll('.anita-buscador-item:not(.d-none)');
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
        panel.querySelectorAll('.anita-buscador-item').forEach(function (el) {
            el.classList.remove('is-active');
        });
        lista[activo].classList.add('is-active');
        if (lista[activo].scrollIntoView) {
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
        enlace.setAttribute('data-grupo', item._grupo || item.tipo || '');
        if (item.nueva_pestana) {
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
        }

        var icono = document.createElement('i');
        icono.className = iconoSeguro(item.icono) + ' fa-fw anita-buscador-icono';
        enlace.appendChild(icono);

        var textos = document.createElement('span');
        textos.className = 'anita-buscador-textos';

        var tituloFila = document.createElement('span');
        tituloFila.className = 'anita-buscador-titulo-fila';

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
        tituloFila.appendChild(titulo);

        if (item.etiqueta) {
            var badge = document.createElement('span');
            badge.className = 'anita-buscador-badge';
            badge.textContent = item.etiqueta;
            tituloFila.appendChild(badge);
        }
        textos.appendChild(tituloFila);

        if (item.detalle) {
            var detalle = document.createElement('span');
            detalle.className = 'anita-buscador-detalle';
            escribirResaltado(detalle, item.detalle, consulta);
            textos.appendChild(detalle);
        }

        if (item.campos && item.campos.length) {
            var grilla = document.createElement('div');
            grilla.className = 'anita-buscador-campos';
            item.campos.slice(0, 6).forEach(function (campo) {
                if (!campo || !campo.valor) {
                    return;
                }
                var celda = document.createElement('div');
                celda.className = 'anita-buscador-campo';
                var fuerte = document.createElement('strong');
                fuerte.textContent = (campo.label || '') + ':';
                celda.appendChild(fuerte);
                var valor = document.createElement('span');
                escribirResaltado(valor, String(campo.valor), consulta);
                celda.appendChild(valor);
                grilla.appendChild(celda);
            });
            if (grilla.childNodes.length) {
                textos.appendChild(grilla);
            }
        }

        if (item.adjunto && item.adjunto.nombre) {
            var adj = document.createElement('div');
            adj.className = 'anita-buscador-adjunto';
            var icoAdj = document.createElement('i');
            icoAdj.className = 'fa fa-paperclip';
            adj.appendChild(icoAdj);
            var nomAdj = document.createElement('span');
            escribirResaltado(nomAdj, item.adjunto.nombre, consulta);
            adj.appendChild(nomAdj);
            textos.appendChild(adj);
        }

        if (item.match_en) {
            var match = document.createElement('span');
            match.className = 'anita-buscador-match';
            match.textContent = 'Coincide en: ' + item.match_en;
            textos.appendChild(match);
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
        boton.setAttribute('data-grupo', 'sugerencias');
        boton.setAttribute('data-sugerencia', item.texto || '');

        var icono = document.createElement('i');
        icono.className = (item.tipo === 'correccion' ? 'fa fa-spell-check' : 'fa fa-search') + ' fa-fw anita-buscador-icono';
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

    function aplicarFiltro(clave) {
        filtroActivo = clave || 'todos';
        panel.querySelectorAll('.anita-buscador-filtro').forEach(function (btn) {
            btn.classList.toggle('is-on', btn.getAttribute('data-filtro') === filtroActivo);
        });
        panel.querySelectorAll('[data-grupo-bloque]').forEach(function (bloque) {
            var g = bloque.getAttribute('data-grupo-bloque');
            var mostrar = filtroActivo === 'todos' || filtroActivo === g;
            bloque.classList.toggle('d-none', !mostrar);
        });
        panel.querySelectorAll('.anita-buscador-item').forEach(function (el) {
            var g = el.getAttribute('data-grupo') || '';
            var mostrar = filtroActivo === 'todos' || filtroActivo === g || g === 'sugerencias' || g === 'frase';
            if (filtroActivo !== 'todos' && (g === 'sugerencias' || g === 'frase')) {
                mostrar = false;
            }
            el.classList.toggle('d-none', !mostrar);
        });
        marcar(0);
    }

    function cabeceraResultados(totales) {
        var caja = document.createElement('div');
        caja.className = 'anita-buscador-cabecera';

        var contador = document.createElement('div');
        contador.className = 'anita-buscador-contador';
        var total = (totales && totales.total) || 0;
        contador.textContent = 'Resultados: ' + total;
        caja.appendChild(contador);

        var filtros = document.createElement('div');
        filtros.className = 'anita-buscador-filtros';
        var chips = [
            { clave: 'todos', label: 'Todos', n: total },
            { clave: 'comprobante', label: 'Comprob.', n: (totales && totales.comprobantes) || 0 },
            { clave: 'adjunto', label: 'Adjuntos', n: (totales && totales.adjuntos) || 0 },
            { clave: 'registro', label: 'Registros', n: (totales && totales.registros) || 0 },
            { clave: 'pantalla', label: 'Pantallas', n: (totales && totales.pantallas) || 0 }
        ];
        chips.forEach(function (chip) {
            if (chip.clave !== 'todos' && !chip.n) {
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'anita-buscador-filtro' + (filtroActivo === chip.clave ? ' is-on' : '');
            btn.setAttribute('data-filtro', chip.clave);
            btn.textContent = chip.label + (chip.clave === 'todos' ? '' : ' ' + chip.n);
            btn.addEventListener('click', function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                aplicarFiltro(chip.clave);
            });
            filtros.appendChild(btn);
        });
        caja.appendChild(filtros);
        return caja;
    }

    function bloqueGrupo(clave, titulo, lista, consulta) {
        if (!lista.length) {
            return null;
        }
        var wrap = document.createElement('div');
        wrap.setAttribute('data-grupo-bloque', clave);
        wrap.appendChild(grupo(titulo));
        lista.forEach(function (item) {
            item._grupo = clave;
            wrap.appendChild(fila(item, consulta));
        });
        return wrap;
    }

    function pintar(data, consulta) {
        panel.innerHTML = '';
        ultimoPayload = data;
        ultimoConsulta = consulta;
        filtroActivo = 'todos';

        var sugerencias = (data && data.sugerencias) || [];
        var pantallas = (data && data.pantallas) || [];
        var ayuda = (data && data.ayuda) || [];
        var comprobantes = (data && data.comprobantes) || [];
        var adjuntos = (data && data.adjuntos) || [];
        var registros = (data && data.registros) || [];
        var acciones = (data && data.acciones) || [];
        var frase = (data && data.frase) || null;
        var totales = (data && data.totales) || {
            pantallas: pantallas.length,
            ayuda: ayuda.length,
            comprobantes: comprobantes.length,
            adjuntos: adjuntos.length,
            registros: registros.length,
            acciones: acciones.length,
            total: pantallas.length + ayuda.length + comprobantes.length + adjuntos.length + registros.length + acciones.length
        };

        if (!sugerencias.length && !acciones.length && !pantallas.length && !ayuda.length && !comprobantes.length && !adjuntos.length && !registros.length && !frase) {
            var vacio = document.createElement('div');
            vacio.className = 'anita-buscador-vacio';
            vacio.textContent = 'Sin resultados para «' + consulta + '».';
            panel.appendChild(vacio);
            abrir();
            return;
        }

        panel.appendChild(cabeceraResultados(totales));

        if (sugerencias.length) {
            var sg = document.createElement('div');
            sg.setAttribute('data-grupo-bloque', 'sugerencias');
            var esCorreccion = sugerencias[0].tipo === 'correccion';
            sg.appendChild(grupo(esCorreccion ? 'Quisiste decir' : 'Sugerencias'));
            sugerencias.forEach(function (item) {
                sg.appendChild(filaSugerencia(item));
            });
            panel.appendChild(sg);
        }
        if (acciones.length) {
            var ba = bloqueGrupo('accion', 'Acciones', acciones, consulta);
            if (ba) {
                panel.appendChild(ba);
            }
        }
        if (frase && frase.titulo) {
            var bf = document.createElement('div');
            bf.setAttribute('data-grupo-bloque', 'frase');
            bf.appendChild(grupo(frase.enviar ? 'Preguntar' : 'Consulta'));
            frase._grupo = 'frase';
            bf.appendChild(fila(frase, consulta));
            panel.appendChild(bf);
        }
        [
            ['comprobante', 'Comprobantes', comprobantes],
            ['adjunto', 'Adjuntos', adjuntos],
            ['registro', 'Registros', registros],
            ['pantalla', 'Pantallas', pantallas],
            ['ayuda', 'Ayuda', ayuda]
        ].forEach(function (def) {
            var bloque = bloqueGrupo(def[0], def[1], def[2], consulta);
            if (bloque) {
                panel.appendChild(bloque);
            }
        });

        var pie = document.createElement('div');
        pie.className = 'anita-buscador-pie';
        pie.textContent = 'Flechas · Enter abre · Esc cierra · Filtros arriba';
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
            if (ultimoPayload && ultimoConsulta === input.value.trim()) {
                pintar(ultimoPayload, ultimoConsulta);
                return;
            }
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
