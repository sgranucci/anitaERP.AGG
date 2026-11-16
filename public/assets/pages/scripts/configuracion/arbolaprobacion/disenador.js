/* Diseñador gráfico del árbol de aprobación */
(function () {
    var cfg = window.AnitaArbolDiseno;
    if (!cfg || !cfg.grafo) return;

    var viewport = document.getElementById('anita-diseno-viewport');
    var stage = document.getElementById('anita-diseno-stage');
    var svg = document.getElementById('anita-diseno-edges');
    var statusEl = document.getElementById('anita-diseno-status');
    var panelEmpty = document.getElementById('anita-diseno-panel-empty');
    var panelForm = document.getElementById('anita-diseno-panel-form');
    var NODE_W = (cfg.grafo.meta && cfg.grafo.meta.node_w) || 220;
    var NODE_H = (cfg.grafo.meta && cfg.grafo.meta.node_h) || 112;

    var state = {
        nodes: {},
        edges: cfg.grafo.edges || [],
        lanes: cfg.grafo.lanes || [],
        zoom: (cfg.grafo.layout && cfg.grafo.layout.zoom) || 1,
        selected: null,
        dirty: false,
    };

    (cfg.grafo.nodes || []).forEach(function (n) {
        state.nodes[n.id] = Object.assign({}, n);
    });

    function setStatus(msg) {
        if (statusEl) statusEl.textContent = msg || '';
    }

    function money(v) {
        if (v === null || v === undefined || v === '') return '—';
        return Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });
    }

    function applyTransform() {
        stage.style.transform = 'scale(' + state.zoom + ')';
    }

    function ensureMarker() {
        if (svg.querySelector('#anita-arrow')) return;
        var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        defs.innerHTML =
            '<marker id="anita-arrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto" markerUnits="strokeWidth">' +
            '<path d="M0,0 L0,6 L9,3 z" fill="#0c3b52" fill-opacity="0.7"></path></marker>';
        svg.appendChild(defs);
    }

    function renderLaneLabels() {
        stage.querySelectorAll('.anita-diseno-lane-label').forEach(function (el) { el.remove(); });
        var byLane = {};
        Object.keys(state.nodes).forEach(function (id) {
            var n = state.nodes[id];
            if (!byLane[n.lane_key]) byLane[n.lane_key] = [];
            byLane[n.lane_key].push(n);
        });
        state.lanes.forEach(function (lane) {
            var list = byLane[lane.key] || [];
            if (!list.length) return;
            var minX = Math.min.apply(null, list.map(function (n) { return n.x; }));
            var minY = Math.min.apply(null, list.map(function (n) { return n.y; }));
            var lab = document.createElement('div');
            lab.className = 'anita-diseno-lane-label';
            lab.textContent = lane.label;
            lab.style.left = minX + 'px';
            lab.style.top = Math.max(8, minY - 28) + 'px';
            stage.appendChild(lab);
        });
    }

    function renderNodes() {
        stage.querySelectorAll('.anita-diseno-node').forEach(function (el) { el.remove(); });
        Object.keys(state.nodes).forEach(function (id) {
            var n = state.nodes[id];
            var el = document.createElement('div');
            el.className = 'anita-diseno-node' + (n.auto ? ' is-auto' : '') + (state.selected === id ? ' is-selected' : '');
            el.dataset.id = id;
            el.style.left = n.x + 'px';
            el.style.top = n.y + 'px';
            var badges = '';
            if (n.auto) badges += '<span class="anita-diseno-badge is-auto">Auto</span>';
            if (n.doble_aprobacion) badges += '<span class="anita-diseno-badge is-doble">2ª</span>';
            if (n.rama) badges += '<span class="anita-diseno-badge">Rama ' + n.rama + '</span>';
            var user = n.auto
                ? 'Aprobación automática'
                : ((n.usuario_nombre || n.usuario_codigo || ('#' + n.usuario_id)));
            var monto = money(n.desdemonto) + ' → ' + money(n.hastamonto);
            if (n.moneda) monto += ' ' + n.moneda;
            el.innerHTML =
                '<div class="anita-diseno-node-head">' +
                '<span class="anita-diseno-node-nivel">N' + n.nivel + '</span>' +
                '<span>' + badges + '</span></div>' +
                '<div class="anita-diseno-node-user">' + escapeHtml(user) + '</div>' +
                '<div class="anita-diseno-node-meta">' + escapeHtml(monto) + '</div>' +
                (n.documento_estado_al_aprobar
                    ? '<div class="anita-diseno-node-meta">Doc: ' + escapeHtml(n.documento_estado_al_aprobar) + '</div>'
                    : '');
            bindDrag(el, id);
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                selectNode(id);
            });
            stage.appendChild(el);
        });
        expandStage();
        renderLaneLabels();
        drawEdges();
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function expandStage() {
        var maxX = 800, maxY = 600;
        Object.keys(state.nodes).forEach(function (id) {
            var n = state.nodes[id];
            maxX = Math.max(maxX, n.x + NODE_W + 80);
            maxY = Math.max(maxY, n.y + NODE_H + 80);
        });
        stage.style.width = maxX + 'px';
        stage.style.height = maxY + 'px';
        svg.setAttribute('width', maxX);
        svg.setAttribute('height', maxY);
        svg.style.width = maxX + 'px';
        svg.style.height = maxY + 'px';
    }

    function drawEdges() {
        ensureMarker();
        while (svg.lastChild && svg.lastChild.tagName !== 'defs') {
            svg.removeChild(svg.lastChild);
        }
        if (!svg.querySelector('defs')) ensureMarker();
        state.edges.forEach(function (e) {
            var a = state.nodes[e.from];
            var b = state.nodes[e.to];
            if (!a || !b) return;
            var x1 = a.x + NODE_W / 2;
            var y1 = a.y + NODE_H;
            var x2 = b.x + NODE_W / 2;
            var y2 = b.y;
            var mid = (y1 + y2) / 2;
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'M ' + x1 + ' ' + y1 + ' C ' + x1 + ' ' + mid + ', ' + x2 + ' ' + mid + ', ' + x2 + ' ' + y2);
            svg.appendChild(path);
        });
    }

    function bindDrag(el, id) {
        var startX, startY, origX, origY, moved;
        el.addEventListener('mousedown', function (ev) {
            if (ev.button !== 0) return;
            ev.preventDefault();
            ev.stopPropagation();
            moved = false;
            startX = ev.clientX;
            startY = ev.clientY;
            origX = state.nodes[id].x;
            origY = state.nodes[id].y;

            function onMove(e) {
                var dx = (e.clientX - startX) / state.zoom;
                var dy = (e.clientY - startY) / state.zoom;
                if (Math.abs(dx) + Math.abs(dy) > 3) moved = true;
                state.nodes[id].x = Math.max(8, origX + dx);
                state.nodes[id].y = Math.max(8, origY + dy);
                el.style.left = state.nodes[id].x + 'px';
                el.style.top = state.nodes[id].y + 'px';
                state.dirty = true;
                drawEdges();
                renderLaneLabels();
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                if (moved) {
                    expandStage();
                    setStatus('Posiciones sin guardar');
                }
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }

    function selectNode(id) {
        state.selected = id;
        renderNodes();
        var n = state.nodes[id];
        if (!n) return;
        panelEmpty.classList.add('d-none');
        panelForm.classList.remove('d-none');
        document.getElementById('panel_nivel_id').value = n.nivel_id;
        document.getElementById('panel_nivel').value = n.nivel;
        document.getElementById('panel_usuario_id').value = n.usuario_id || '';
        var cod = document.getElementById('panel_usuario_codigo');
        var nom = document.getElementById('panel_usuario_nombre');
        if (cod) cod.value = n.usuario_codigo || '';
        if (nom) nom.value = n.usuario_nombre || '';
        document.getElementById('panel_usuario_label').textContent = n.auto
            ? 'Hoy: automático'
            : ((n.usuario_codigo || '') + ' — ' + (n.usuario_nombre || ''));
        document.getElementById('panel_desde').value = n.desdemonto != null ? n.desdemonto : '';
        document.getElementById('panel_hasta').value = n.hastamonto != null ? n.hastamonto : '';
        var estado = document.getElementById('panel_estado_doc');
        if (estado) estado.value = n.documento_estado_al_aprobar || '';
        document.getElementById('panel_doble').checked = !!n.doble_aprobacion;
    }

    function collectLayoutPayload(extra) {
        var nodes = {};
        Object.keys(state.nodes).forEach(function (id) {
            nodes[id] = { x: state.nodes[id].x, y: state.nodes[id].y };
        });
        return Object.assign({
            nodes: nodes,
            zoom: state.zoom,
            pan_x: 0,
            pan_y: 0,
        }, extra || {});
    }

    function postJson(url, body, method) {
        return fetch(url, {
            method: method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.urls.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });
    }

    document.getElementById('btn-guardar-layout').addEventListener('click', function () {
        setStatus('Guardando…');
        postJson(cfg.urls.layout, collectLayoutPayload()).then(function (res) {
            if (res.ok && res.body.ok) {
                state.dirty = false;
                setStatus('Layout guardado');
            } else {
                setStatus((res.body && res.body.mensaje) || 'Error al guardar');
            }
        }).catch(function () { setStatus('Error de red'); });
    });

    document.getElementById('btn-auto-layout').addEventListener('click', function () {
        if (!confirm('¿Reordenar nodos automáticamente por carril y nivel?')) return;
        setStatus('Auto layout…');
        postJson(cfg.urls.layout, collectLayoutPayload({ auto: true })).then(function (res) {
            if (!res.ok || !res.body.ok) {
                setStatus((res.body && res.body.mensaje) || 'Error auto layout');
                return;
            }
            var nodes = res.body.nodes || {};
            Object.keys(nodes).forEach(function (id) {
                if (state.nodes[id]) {
                    state.nodes[id].x = nodes[id].x;
                    state.nodes[id].y = nodes[id].y;
                }
            });
            state.dirty = false;
            renderNodes();
            setStatus('Auto layout aplicado');
        }).catch(function () { setStatus('Error de red'); });
    });

    document.getElementById('panel_limpiar_usuario').addEventListener('click', function () {
        document.getElementById('panel_usuario_id').value = '';
        var cod = document.getElementById('panel_usuario_codigo');
        var nom = document.getElementById('panel_usuario_nombre');
        if (cod) cod.value = '';
        if (nom) nom.value = '';
        document.getElementById('panel_usuario_label').textContent = 'Quedará automático al guardar';
    });

    panelForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var nivelId = document.getElementById('panel_nivel_id').value;
        var uid = document.getElementById('panel_usuario_id').value;
        var payload = {
            nivel: parseInt(document.getElementById('panel_nivel').value, 10) || 1,
            desdemonto: document.getElementById('panel_desde').value === '' ? null : document.getElementById('panel_desde').value,
            hastamonto: document.getElementById('panel_hasta').value === '' ? null : document.getElementById('panel_hasta').value,
            doble_aprobacion: document.getElementById('panel_doble').checked,
            limpiar_usuario: !uid,
        };
        if (uid) payload.usuario_id = parseInt(uid, 10);
        var estado = document.getElementById('panel_estado_doc');
        if (estado) payload.documento_estado_al_aprobar = estado.value;
        setStatus('Actualizando nivel…');
        postJson(cfg.urls.nodo + '/' + nivelId, payload, 'PUT').then(function (res) {
            if (!res.ok || !res.body.ok) {
                setStatus((res.body && res.body.mensaje) || 'Error al actualizar');
                alert((res.body && res.body.mensaje) || 'No se pudo actualizar el nivel');
                return;
            }
            var nodo = res.body.nodo;
            var id = nodo.id;
            if (state.nodes[id]) {
                Object.keys(nodo).forEach(function (k) { state.nodes[id][k] = nodo[k]; });
            }
            // Reordenar edges por nivel dentro del carril tras cambio de número de nivel
            rebuildEdgesFromLevels();
            renderNodes();
            selectNode(id);
            setStatus('Nivel actualizado');
        }).catch(function () { setStatus('Error de red'); });
    });

    function rebuildEdgesFromLevels() {
        var byLane = {};
        Object.keys(state.nodes).forEach(function (id) {
            var n = state.nodes[id];
            byLane[n.lane_key] = byLane[n.lane_key] || [];
            byLane[n.lane_key].push(n);
        });
        var edges = [];
        Object.keys(byLane).forEach(function (lane) {
            var list = byLane[lane].slice().sort(function (a, b) {
                if (a.nivel !== b.nivel) return a.nivel - b.nivel;
                return (a.desdemonto || 0) - (b.desdemonto || 0);
            });
            for (var i = 1; i < list.length; i++) {
                edges.push({ from: list[i - 1].id, to: list[i].id });
            }
        });
        state.edges = edges;
    }

    viewport.addEventListener('wheel', function (e) {
        if (!e.ctrlKey && !e.metaKey) return;
        e.preventDefault();
        var delta = e.deltaY > 0 ? -0.08 : 0.08;
        state.zoom = Math.min(2.2, Math.max(0.45, state.zoom + delta));
        applyTransform();
        state.dirty = true;
        setStatus('Zoom ' + Math.round(state.zoom * 100) + '%');
    }, { passive: false });

    viewport.addEventListener('click', function () {
        state.selected = null;
        panelForm.classList.add('d-none');
        panelEmpty.classList.remove('d-none');
        renderNodes();
    });

    // Fix select options: estados may be {nombre: "..."}
    (function normalizeEstadoOptions() {
        var sel = document.getElementById('panel_estado_doc');
        if (!sel) return;
        Array.prototype.forEach.call(sel.options, function (opt) {
            if (!opt.value && opt.text && opt.text !== '—') {
                opt.value = opt.text;
            }
        });
    })();

    applyTransform();
    renderNodes();
    setStatus(Object.keys(state.nodes).length + ' nodos');
})();
