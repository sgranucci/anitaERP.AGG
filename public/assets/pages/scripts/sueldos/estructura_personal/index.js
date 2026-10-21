(function () {
    var root = document.getElementById('ep-app');
    if (!root) {
        return;
    }

    var guardado = localStorage.getItem('ep-theme');
    if (guardado === 'dark' || guardado === 'light') {
        root.dataset.theme = guardado;
    }

    var themeBtn = document.getElementById('ep-theme');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var siguiente = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = siguiente;
            localStorage.setItem('ep-theme', siguiente);
        });
    }

    document.querySelectorAll('.ep-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.ep-tab').forEach(function (otro) { otro.classList.remove('on'); });
            document.querySelectorAll('.ep-pg').forEach(function (pg) { pg.classList.remove('on'); });
            tab.classList.add('on');
            var panel = document.getElementById(tab.getAttribute('data-ep-tab'));
            if (panel) {
                panel.classList.add('on');
            }
        });
    });

    var q = document.getElementById('ep-q');
    var base = document.getElementById('ep-base');
    var baseCount = document.getElementById('ep-base-count');
    function filtrarBase() {
        if (!base) {
            return;
        }
        var texto = (q && q.value || '').toLowerCase().trim();
        var cc = (document.querySelector('.ep-cc.on') || {}).getAttribute ? document.querySelector('.ep-cc.on').getAttribute('data-cc') : '';
        var visibles = 0;
        base.querySelectorAll('tbody tr').forEach(function (tr) {
            var okTexto = texto === '' || (tr.getAttribute('data-txt') || '').indexOf(texto) !== -1;
            var okCc = !cc || tr.getAttribute('data-cc') === cc;
            var mostrar = okTexto && okCc;
            tr.hidden = !mostrar;
            if (mostrar) {
                visibles++;
            }
        });
        if (baseCount) {
            baseCount.textContent = String(visibles);
        }
    }
    if (q) {
        q.addEventListener('input', filtrarBase);
    }
    document.querySelectorAll('.ep-cc').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var ya = chip.classList.contains('on');
            document.querySelectorAll('.ep-cc').forEach(function (otro) { otro.classList.remove('on'); });
            if (!ya && chip.getAttribute('data-cc') !== '') {
                chip.classList.add('on');
            }
            filtrarBase();
        });
    });

    document.querySelectorAll('.ep-mot').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var ya = chip.classList.contains('on');
            document.querySelectorAll('.ep-mot').forEach(function (otro) { otro.classList.remove('on'); });
            var motivo = '';
            if (!ya) {
                chip.classList.add('on');
                motivo = chip.getAttribute('data-motivo') || '';
            }
            var n = 0;
            document.querySelectorAll('#ep-bajas tbody tr').forEach(function (tr) {
                var mostrar = motivo === '' || tr.getAttribute('data-motivo') === motivo;
                tr.hidden = !mostrar;
                if (mostrar) {
                    n++;
                }
            });
            var badge = document.getElementById('ep-bajas-count');
            if (badge) {
                badge.textContent = String(n);
            }
        });
    });

    var excel = document.getElementById('ep-excel');
    var overlay = document.getElementById('ep-overlay');
    function mostrarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    window.addEventListener('pageshow', ocultarOverlay);
    if (excel) {
        excel.addEventListener('click', function (e) {
            e.preventDefault();
            mostrarOverlay();
            var ctrl = new AbortController();
            function alEscape(ev) {
                if (ev.key === 'Escape') {
                    ctrl.abort();
                    ocultarOverlay();
                }
            }
            document.addEventListener('keydown', alEscape);
            fetch(excel.href, { credentials: 'same-origin', signal: ctrl.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('No se pudo generar el Excel');
                    }
                    var nombre = 'estructura-personal.xlsx';
                    var disp = res.headers.get('Content-Disposition') || '';
                    var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disp);
                    if (match) {
                        nombre = decodeURIComponent((match[1] || match[2] || match[3] || nombre).trim());
                    }
                    return res.blob().then(function (blob) { return { blob: blob, nombre: nombre }; });
                })
                .then(function (pack) {
                    var url = URL.createObjectURL(pack.blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = pack.nombre;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    ocultarOverlay();
                })
                .catch(function (err) {
                    ocultarOverlay();
                    if (err.name !== 'AbortError') {
                        alert(err.message || 'No se pudo exportar');
                    }
                })
                .finally(function () {
                    document.removeEventListener('keydown', alEscape);
                });
        });
    }
})();
