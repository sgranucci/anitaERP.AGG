/**
 * Visor de planos de mesas (solo lectura) para el POS Crown.
 * Pinta cada mesa según su estado y avisa el clic hacia pos.js.
 *
 * window.GastroCrownPlanoViewer.montar(contenedorId, plano, opciones)
 * window.GastroCrownPlanoViewer.pintar(contenedorId, mesasPorId, mesaActivaId)
 */
(function (window) {
    'use strict';

    var COLORES = {
        libre: { fill: '#D5F5E3', stroke: '#27AE60', texto: '#145A32' },
        ocupada: { fill: '#FADBD8', stroke: '#CB4335', texto: '#78281F' },
        activa: { fill: '#AED6F1', stroke: '#2471A3', texto: '#154360' }
    };

    var instancias = {};

    function estadoDeMesa(mesa, mesaActivaId) {
        if (mesa && mesaActivaId && parseInt(mesa.id, 10) === parseInt(mesaActivaId, 10)) {
            return 'activa';
        }
        if (mesa && mesa.ocupada) {
            return 'ocupada';
        }
        return 'libre';
    }

    function construirForma(nodo, colores) {
        var comun = {
            fill: colores.fill,
            stroke: colores.stroke,
            strokeWidth: 2
        };
        if (nodo.shape === 'round') {
            return new window.Konva.Circle(Object.assign({}, comun, {
                x: nodo.w / 2,
                y: nodo.h / 2,
                radius: Math.min(nodo.w, nodo.h) / 2
            }));
        }
        if (nodo.shape === 'oval') {
            return new window.Konva.Ellipse(Object.assign({}, comun, {
                x: nodo.w / 2,
                y: nodo.h / 2,
                radiusX: nodo.w / 2,
                radiusY: nodo.h / 2
            }));
        }
        return new window.Konva.Rect(Object.assign({}, comun, {
            x: 0,
            y: 0,
            width: nodo.w,
            height: nodo.h,
            cornerRadius: 6
        }));
    }

    function montar(contenedorId, plano, opciones) {
        if (typeof window.Konva === 'undefined') {
            return null;
        }
        var el = document.getElementById(contenedorId);
        if (!el) {
            return null;
        }
        opciones = opciones || {};

        if (instancias[contenedorId]) {
            instancias[contenedorId].stage.destroy();
            delete instancias[contenedorId];
        }

        var layout = plano.layout || { width: 1200, height: 800, nodes: [] };
        var ancho = Math.max(240, el.clientWidth || 320);
        var alto = Math.max(200, el.clientHeight || 320);

        var stage = new window.Konva.Stage({
            container: contenedorId,
            width: ancho,
            height: alto
        });
        var capa = new window.Konva.Layer();
        stage.add(capa);

        var inst = {
            stage: stage,
            capa: capa,
            layout: layout,
            grupos: {},
            onMesaClick: opciones.onMesaClick || function () {}
        };

        (layout.nodes || []).forEach(function (nodo) {
            var grupo = new window.Konva.Group({
                x: nodo.x,
                y: nodo.y,
                rotation: nodo.rotation || 0
            });
            var forma = construirForma(nodo, COLORES.libre);
            var texto = new window.Konva.Text({
                x: 0,
                y: nodo.h / 2 - 16,
                width: nodo.w,
                align: 'center',
                text: '',
                fontSize: 13,
                fontStyle: 'bold',
                fill: COLORES.libre.texto,
                listening: false
            });
            var sub = new window.Konva.Text({
                x: 0,
                y: nodo.h / 2 + 1,
                width: nodo.w,
                align: 'center',
                text: '',
                fontSize: 10,
                fill: COLORES.libre.texto,
                listening: false
            });

            grupo.add(forma);
            grupo.add(texto);
            grupo.add(sub);
            grupo.on('click tap', function () {
                inst.onMesaClick(parseInt(nodo.mesa_id, 10));
            });
            grupo.on('mouseenter', function () {
                stage.container().style.cursor = 'pointer';
            });
            grupo.on('mouseleave', function () {
                stage.container().style.cursor = 'default';
            });

            capa.add(grupo);
            inst.grupos[String(nodo.mesa_id)] = {
                grupo: grupo,
                forma: forma,
                texto: texto,
                sub: sub,
                nodo: nodo
            };
        });

        instancias[contenedorId] = inst;
        ajustar(contenedorId);

        return inst;
    }

    function ajustar(contenedorId) {
        var inst = instancias[contenedorId];
        if (!inst) {
            return;
        }
        var el = inst.stage.container();
        var ancho = Math.max(240, el.clientWidth || 320);
        var alto = Math.max(200, el.clientHeight || 320);
        inst.stage.width(ancho);
        inst.stage.height(alto);

        var z = Math.min(
            ancho / ((inst.layout.width || 1200) + 20),
            alto / ((inst.layout.height || 800) + 20)
        );
        z = Math.max(0.1, Math.min(2, z));
        inst.stage.scale({ x: z, y: z });
        inst.stage.position({ x: 8, y: 8 });
        inst.stage.batchDraw();
    }

    function pintar(contenedorId, mesasPorId, mesaActivaId) {
        var inst = instancias[contenedorId];
        if (!inst) {
            return;
        }
        mesasPorId = mesasPorId || {};

        Object.keys(inst.grupos).forEach(function (mesaId) {
            var ref = inst.grupos[mesaId];
            var mesa = mesasPorId[mesaId] || null;
            var colores = COLORES[estadoDeMesa(mesa, mesaActivaId)];

            ref.forma.fill(colores.fill);
            ref.forma.stroke(colores.stroke);
            ref.texto.fill(colores.texto);
            ref.sub.fill(colores.texto);
            ref.texto.text(mesa ? (mesa.codigo || ('#' + mesaId)) : ('#' + mesaId));
            ref.sub.text(mesa && mesa.ocupada ? 'ocupada' : '');
        });

        inst.capa.batchDraw();
    }

    function destruir(contenedorId) {
        if (instancias[contenedorId]) {
            instancias[contenedorId].stage.destroy();
            delete instancias[contenedorId];
        }
    }

    function existe(contenedorId) {
        return !!instancias[contenedorId];
    }

    window.GastroCrownPlanoViewer = {
        montar: montar,
        pintar: pintar,
        ajustar: ajustar,
        destruir: destruir,
        existe: existe
    };
})(window);
