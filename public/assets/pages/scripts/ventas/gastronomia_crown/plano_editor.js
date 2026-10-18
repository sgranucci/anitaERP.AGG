/**
 * Editor gráfico de planos de mesas (Konva 9) — ABM Crown.
 * Mantiene sincronizado el hidden #layout_json del formulario y, en edición,
 * permite guardar solo el plano por AJAX.
 */
(function ($) {
    'use strict';

    var cfg = window.GastroPlanoEditorCfg || null;
    if (!cfg || typeof window.Konva === 'undefined') {
        return;
    }

    var COLOR_RELLENO = '#AED6F1';
    var COLOR_BORDE = '#2471A3';
    var COLOR_SELECCION = '#F5B041';
    var COLOR_TEXTO = '#17202A';
    var MIN_LADO = 40;

    var estado = {
        layout: normalizarLayout(cfg.layout),
        mesas: cfg.mesas || [],
        mesaPorId: {},
        forma: 'rect',
        nodoSeleccionado: null,
        zoom: 1
    };

    var stage = null;
    var capaGrid = null;
    var capaNodos = null;
    var transformer = null;

    function normalizarLayout(layout) {
        layout = layout || {};
        return {
            version: 1,
            width: parseInt(layout.width, 10) || 1200,
            height: parseInt(layout.height, 10) || 800,
            grid: parseInt(layout.grid, 10) || 20,
            nodes: Array.isArray(layout.nodes) ? layout.nodes.slice() : []
        };
    }

    function grid() {
        return estado.layout.grid || 20;
    }

    function snap(valor) {
        var g = grid();
        return Math.round(valor / g) * g;
    }

    function indexarMesas() {
        estado.mesaPorId = {};
        (estado.mesas || []).forEach(function (m) {
            estado.mesaPorId[String(m.id)] = m;
        });
    }

    function mesaDe(mesaId) {
        return estado.mesaPorId[String(mesaId)] || null;
    }

    function etiquetaMesa(mesaId) {
        var m = mesaDe(mesaId);
        if (!m) {
            return '#' + mesaId;
        }
        return m.codigo || m.nombre || ('#' + mesaId);
    }

    function nodoPorId(id) {
        var encontrado = null;
        estado.layout.nodes.forEach(function (n) {
            if (String(n.id) === String(id)) {
                encontrado = n;
            }
        });
        return encontrado;
    }

    function sincronizarHidden() {
        var $hidden = $('#layout_json');
        if ($hidden.length) {
            $hidden.val(JSON.stringify(estado.layout));
        }
        pintarEstadoTexto();
    }

    function pintarEstadoTexto() {
        var total = estado.layout.nodes.length;
        var sinUbicar = mesasSinUbicar().length;
        var txt = total + ' mesa(s) en el plano · ' + sinUbicar + ' sin ubicar';
        if (estado.nodoSeleccionado) {
            var n = nodoPorId(estado.nodoSeleccionado);
            if (n) {
                txt += ' · Seleccionada: ' + etiquetaMesa(n.mesa_id)
                    + ' (' + n.w + '×' + n.h + ', ' + n.rotation + '°)';
            }
        }
        $('#gcpl-status-texto').text(txt);
    }

    function mesasSinUbicar() {
        var usados = {};
        estado.layout.nodes.forEach(function (n) {
            usados[String(n.mesa_id)] = true;
        });
        return (estado.mesas || []).filter(function (m) {
            return !usados[String(m.id)];
        });
    }

    /* ---------------- Paleta ---------------- */

    function pintarPaleta() {
        var filtro = ($('#gcpl-palette-buscar').val() || '').toLowerCase().trim();
        var disponibles = mesasSinUbicar();
        var visibles = disponibles.filter(function (m) {
            if (!filtro) {
                return true;
            }
            return String(m.codigo || '').toLowerCase().indexOf(filtro) >= 0
                || String(m.nombre || '').toLowerCase().indexOf(filtro) >= 0;
        });

        $('#gcpl-palette-count').text(disponibles.length);

        var $lista = $('#gcpl-palette-list');
        if (!estado.mesas.length) {
            $lista.html('<div class="text-muted small p-2">No hay mesas para la empresa y sala elegidas.</div>');
            return;
        }
        if (!visibles.length) {
            $lista.html('<div class="text-muted small p-2">Todas las mesas ya están en el plano.</div>');
            return;
        }

        var html = '';
        visibles.forEach(function (m) {
            html += '<button type="button" class="gcpl-mesa-chip" data-mesa-id="' + m.id + '">'
                + '<strong>' + $('<div>').text(m.codigo || ('#' + m.id)).html() + '</strong>'
                + '<small>' + $('<div>').text(m.nombre || '').html() + '</small>'
                + '</button>';
        });
        $lista.html(html);
    }

    /* ---------------- Stage ---------------- */

    function anchoContenedor() {
        var el = document.getElementById('gcpl-stage');
        return el ? Math.max(320, el.clientWidth) : 900;
    }

    function altoContenedor() {
        var el = document.getElementById('gcpl-stage');
        return el ? Math.max(260, el.clientHeight) : 520;
    }

    function crearStage() {
        stage = new Konva.Stage({
            container: 'gcpl-stage',
            width: anchoContenedor(),
            height: altoContenedor()
        });

        capaGrid = new Konva.Layer({ listening: false });
        capaNodos = new Konva.Layer();
        stage.add(capaGrid);
        stage.add(capaNodos);

        transformer = new Konva.Transformer({
            rotateEnabled: true,
            rotationSnaps: [0, 15, 30, 45, 60, 75, 90, 135, 180, 225, 270, 315],
            enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
            borderStroke: COLOR_SELECCION,
            anchorStroke: COLOR_SELECCION,
            anchorFill: '#fff',
            boundBoxFunc: function (oldBox, newBox) {
                if (newBox.width < MIN_LADO || newBox.height < MIN_LADO) {
                    return oldBox;
                }
                return newBox;
            }
        });
        capaNodos.add(transformer);

        stage.on('click tap', function (e) {
            if (e.target === stage) {
                seleccionar(null);
            }
        });

        dibujarGrid();
    }

    function dibujarGrid() {
        capaGrid.destroyChildren();
        var g = grid();
        var w = estado.layout.width;
        var h = estado.layout.height;

        capaGrid.add(new Konva.Rect({
            x: 0, y: 0, width: w, height: h,
            fill: '#ffffff', stroke: '#b3bcc4', strokeWidth: 1
        }));

        var i;
        for (i = g; i < w; i += g) {
            capaGrid.add(new Konva.Line({
                points: [i, 0, i, h],
                stroke: (i % (g * 5) === 0) ? '#cfd8dd' : '#e8edf0',
                strokeWidth: 1
            }));
        }
        for (i = g; i < h; i += g) {
            capaGrid.add(new Konva.Line({
                points: [0, i, w, i],
                stroke: (i % (g * 5) === 0) ? '#cfd8dd' : '#e8edf0',
                strokeWidth: 1
            }));
        }
        capaGrid.batchDraw();
    }

    function formaDeNodo(nodo, seleccionado) {
        var comun = {
            name: 'gcpl-forma',
            fill: seleccionado ? '#FAD7A0' : COLOR_RELLENO,
            stroke: seleccionado ? COLOR_SELECCION : COLOR_BORDE,
            strokeWidth: 2
        };
        if (nodo.shape === 'round') {
            var radio = Math.min(nodo.w, nodo.h) / 2;
            return new Konva.Circle($.extend({}, comun, {
                x: nodo.w / 2, y: nodo.h / 2, radius: radio
            }));
        }
        if (nodo.shape === 'oval') {
            return new Konva.Ellipse($.extend({}, comun, {
                x: nodo.w / 2, y: nodo.h / 2, radiusX: nodo.w / 2, radiusY: nodo.h / 2
            }));
        }
        return new Konva.Rect($.extend({}, comun, {
            x: 0, y: 0, width: nodo.w, height: nodo.h, cornerRadius: 6
        }));
    }

    function textoDeNodo(nodo) {
        return new Konva.Text({
            name: 'gcpl-texto',
            x: 0,
            y: nodo.h / 2 - 9,
            width: nodo.w,
            align: 'center',
            text: etiquetaMesa(nodo.mesa_id),
            fontSize: 13,
            fontStyle: 'bold',
            fill: COLOR_TEXTO,
            listening: false
        });
    }

    function pintarNodos() {
        // Soltar el transformer antes de destruir los grupos que referencia.
        transformer.nodes([]);
        capaNodos.find('.gcpl-nodo').forEach(function (g) {
            g.destroy();
        });

        estado.layout.nodes.forEach(function (nodo) {
            var seleccionado = String(nodo.id) === String(estado.nodoSeleccionado);
            var grupo = new Konva.Group({
                name: 'gcpl-nodo',
                x: nodo.x,
                y: nodo.y,
                rotation: nodo.rotation || 0,
                draggable: true
            });
            grupo.setAttr('nodoId', nodo.id);
            grupo.add(formaDeNodo(nodo, seleccionado));
            grupo.add(textoDeNodo(nodo));

            grupo.on('dragend', function () {
                nodo.x = snap(grupo.x());
                nodo.y = snap(grupo.y());
                grupo.position({ x: nodo.x, y: nodo.y });
                capaNodos.batchDraw();
                sincronizarHidden();
            });

            grupo.on('transformend', function () {
                nodo.w = Math.max(MIN_LADO, Math.round(nodo.w * grupo.scaleX()));
                nodo.h = Math.max(MIN_LADO, Math.round(nodo.h * grupo.scaleY()));
                nodo.rotation = Math.round(grupo.rotation());
                nodo.x = snap(grupo.x());
                nodo.y = snap(grupo.y());
                grupo.scale({ x: 1, y: 1 });
                sincronizarHidden();
                // Konva todavía está procesando el evento sobre este grupo: redibujamos en el próximo tick.
                setTimeout(pintarNodos, 0);
            });

            grupo.on('click tap', function (e) {
                e.cancelBubble = true;
                seleccionar(nodo.id);
            });

            capaNodos.add(grupo);
        });

        aplicarTransformer();
        transformer.moveToTop();
        capaNodos.batchDraw();
    }

    function aplicarTransformer() {
        if (!estado.nodoSeleccionado) {
            transformer.nodes([]);
            return;
        }
        var grupo = null;
        capaNodos.find('.gcpl-nodo').forEach(function (g) {
            if (String(g.getAttr('nodoId')) === String(estado.nodoSeleccionado)) {
                grupo = g;
            }
        });
        transformer.nodes(grupo ? [grupo] : []);
    }

    function seleccionar(nodoId) {
        estado.nodoSeleccionado = nodoId;
        pintarNodos();
        pintarEstadoTexto();
    }

    /* ---------------- Acciones ---------------- */

    function agregarMesa(mesaId) {
        mesaId = parseInt(mesaId, 10);
        if (!mesaId || nodoDeMesa(mesaId)) {
            return;
        }
        var w = estado.forma === 'oval' ? 120 : 80;
        var h = 80;
        var desplazamiento = (estado.layout.nodes.length % 8) * grid();
        var nodo = {
            id: 'n' + mesaId + '_' + Date.now().toString(36),
            mesa_id: mesaId,
            x: snap(estado.layout.width / 2 - w / 2 + desplazamiento),
            y: snap(estado.layout.height / 2 - h / 2 + desplazamiento),
            w: w,
            h: h,
            rotation: 0,
            shape: estado.forma
        };
        estado.layout.nodes.push(nodo);
        estado.nodoSeleccionado = nodo.id;
        pintarNodos();
        pintarPaleta();
        sincronizarHidden();
    }

    function nodoDeMesa(mesaId) {
        var encontrado = null;
        estado.layout.nodes.forEach(function (n) {
            if (parseInt(n.mesa_id, 10) === parseInt(mesaId, 10)) {
                encontrado = n;
            }
        });
        return encontrado;
    }

    function borrarSeleccionado() {
        if (!estado.nodoSeleccionado) {
            return;
        }
        estado.layout.nodes = estado.layout.nodes.filter(function (n) {
            return String(n.id) !== String(estado.nodoSeleccionado);
        });
        estado.nodoSeleccionado = null;
        pintarNodos();
        pintarPaleta();
        sincronizarHidden();
    }

    function rotarSeleccionado() {
        var nodo = nodoPorId(estado.nodoSeleccionado);
        if (!nodo) {
            return;
        }
        nodo.rotation = (parseInt(nodo.rotation, 10) + 15) % 360;
        pintarNodos();
        sincronizarHidden();
    }

    function cambiarFormaSeleccionada(forma) {
        estado.forma = forma;
        $('.gcpl-shape').removeClass('active');
        $('.gcpl-shape[data-shape="' + forma + '"]').addClass('active');
        var nodo = nodoPorId(estado.nodoSeleccionado);
        if (nodo) {
            nodo.shape = forma;
            pintarNodos();
            sincronizarHidden();
        }
    }

    function aplicarZoom(z) {
        estado.zoom = Math.max(0.2, Math.min(3, z));
        stage.scale({ x: estado.zoom, y: estado.zoom });
        stage.batchDraw();
    }

    function ajustarVista() {
        stage.width(anchoContenedor());
        stage.height(altoContenedor());
        var z = Math.min(
            stage.width() / (estado.layout.width + 40),
            stage.height() / (estado.layout.height + 40)
        );
        stage.position({ x: 10, y: 10 });
        aplicarZoom(z);
    }

    /* ---------------- Mesas por empresa / sala ---------------- */

    function recargarMesas() {
        var empresaId = parseInt($('#empresa_id').val(), 10) || 0;
        var salaId = parseInt($('#sala_id').val(), 10) || 0;
        if (!empresaId) {
            estado.mesas = [];
            indexarMesas();
            pintarPaleta();
            return;
        }
        $.getJSON(cfg.urls.mesasSala, { empresa_id: empresaId, sala_id: salaId || '' })
            .done(function (resp) {
                if (!resp || !resp.ok) {
                    return;
                }
                estado.mesas = resp.mesas || [];
                indexarMesas();
                var permitidas = {};
                estado.mesas.forEach(function (m) {
                    permitidas[String(m.id)] = true;
                });
                var antes = estado.layout.nodes.length;
                estado.layout.nodes = estado.layout.nodes.filter(function (n) {
                    return permitidas[String(n.mesa_id)];
                });
                if (estado.layout.nodes.length !== antes) {
                    avisar('Se quitaron ' + (antes - estado.layout.nodes.length)
                        + ' mesa(s) del plano por no pertenecer a la sala elegida.', 'warning');
                }
                estado.nodoSeleccionado = null;
                pintarNodos();
                pintarPaleta();
                sincronizarHidden();
            });
    }

    function avisar(msg, tipo) {
        if (window.toastr) {
            window.toastr[tipo || 'info'](msg);
            return;
        }
        window.alert(msg);
    }

    function guardarLayoutAjax() {
        if (!cfg.esEdicion || !cfg.planoId) {
            return;
        }
        var token = $('#form-general input[name="_token"]').val()
            || $('meta[name="csrf-token"]').attr('content') || '';
        $.ajax({
            url: cfg.urls.guardarLayout + '/' + cfg.planoId + '/layout',
            method: 'POST',
            data: { layout_json: JSON.stringify(estado.layout) },
            headers: { 'X-CSRF-TOKEN': token }
        }).done(function (resp) {
            if (resp && resp.ok) {
                if (resp.layout) {
                    estado.layout = normalizarLayout(resp.layout);
                    estado.nodoSeleccionado = null;
                    pintarNodos();
                    pintarPaleta();
                    sincronizarHidden();
                }
                avisar(resp.mensaje || 'Plano guardado.', 'success');
            }
        }).fail(function (xhr) {
            avisar((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo guardar el plano.', 'error');
        });
    }

    /* ---------------- Init ---------------- */

    $(function () {
        if (!document.getElementById('gcpl-stage')) {
            return;
        }

        indexarMesas();
        crearStage();
        pintarNodos();
        pintarPaleta();
        sincronizarHidden();
        ajustarVista();

        // Alta con empresa fija en hidden: el change nunca dispara, cargamos las mesas acá.
        if (!estado.mesas.length && parseInt($('#empresa_id').val(), 10) > 0) {
            recargarMesas();
        }

        $('#gcpl-palette-list').on('click', '.gcpl-mesa-chip', function () {
            agregarMesa($(this).data('mesa-id'));
        });

        $('#gcpl-palette-buscar').on('input', pintarPaleta);

        $('.gcpl-shape').on('click', function () {
            cambiarFormaSeleccionada($(this).data('shape'));
        });

        $('#gcpl-borrar').on('click', borrarSeleccionado);
        $('#gcpl-rotar').on('click', rotarSeleccionado);
        $('#gcpl-zoom-in').on('click', function () { aplicarZoom(estado.zoom * 1.2); });
        $('#gcpl-zoom-out').on('click', function () { aplicarZoom(estado.zoom / 1.2); });
        $('#gcpl-zoom-fit').on('click', ajustarVista);
        $('#gcpl-guardar-layout').on('click', guardarLayoutAjax);

        $('#empresa_id, #sala_id').on('change', recargarMesas);

        $(document).on('keydown.gcplEditor', function (e) {
            var tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                return;
            }
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (estado.nodoSeleccionado) {
                    e.preventDefault();
                    borrarSeleccionado();
                }
            }
        });

        $(window).on('resize.gcplEditor', function () {
            stage.width(anchoContenedor());
            stage.height(altoContenedor());
            stage.batchDraw();
        });

        // El layout viaja en el hidden junto al resto del formulario.
        $('#form-general').on('submit', sincronizarHidden);
    });
})(jQuery);
