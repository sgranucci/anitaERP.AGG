(function () {
    var trigger = document.getElementById('anita-nav-notif');
    if (!trigger) return;

    var badge = document.getElementById('anita-notif-badge');
    var list = document.getElementById('anita-notif-list');
    var markAll = document.getElementById('anita-notif-mark-all');
    var feedUrl = trigger.getAttribute('data-feed-url');
    var contadorUrl = trigger.getAttribute('data-contador-url');
    var leerTodasUrl = trigger.getAttribute('data-leer-todas-url');
    var leerBase = trigger.getAttribute('data-leer-url-base');
    var lastUnread = parseInt((badge && badge.textContent) || '0', 10) || 0;
    var loadedOnce = false;
    var loading = false;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function setBadge(n) {
        n = Math.max(0, parseInt(n, 10) || 0);
        if (n > lastUnread && loadedOnce) {
            var prev = document.getElementById('anita-inbox-live-toast');
            if (prev) prev.remove();
            var el = document.createElement('div');
            el.id = 'anita-inbox-live-toast';
            el.className = 'anita-inbox-toast';
            el.textContent = n === 1 ? 'Tenés 1 aviso nuevo' : 'Tenés ' + n + ' avisos nuevos';
            document.body.appendChild(el);
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 6000);
        }
        lastUnread = n;
        if (!badge) return;
        badge.textContent = n > 99 ? '99+' : String(n);
        if (n > 0) badge.classList.remove('d-none');
        else badge.classList.add('d-none');
    }

    function showMessage(msg) {
        if (list) {
            list.innerHTML = '<span class="dropdown-item text-muted text-sm">' + esc(msg) + '</span>';
        }
    }

    function renderItems(items) {
        if (!list) return;
        if (!items || !items.length) {
            showMessage('Sin avisos por ahora');
            return;
        }
        list.innerHTML = items.map(function (it) {
            return '<a href="' + esc(it.url) + '" class="dropdown-item anita-notif-item' + (it.leida ? '' : ' is-unread') + '" data-id="' + esc(it.id) + '">'
                + '<div class="anita-notif-item-title">' + esc(it.titulo) + '</div>'
                + (it.cuerpo ? '<div class="anita-notif-item-body">' + esc(it.cuerpo) + '</div>' : '')
                + '<span class="anita-notif-item-when">' + esc(it.cuando) + '</span>'
                + '</a>';
        }).join('<div class="dropdown-divider"></div>');

        list.querySelectorAll('.anita-notif-item').forEach(function (a) {
            a.addEventListener('click', function () {
                var id = a.getAttribute('data-id');
                if (!id || !leerBase) return;
                fetch(leerBase.replace(/\/$/, '') + '/' + id + '/leer', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j && j.ok) setBadge(j.unread);
                }).catch(function () {});
            });
        });
    }

    function loadFeed() {
        if (!feedUrl) {
            showMessage('URL de avisos no configurada');
            return;
        }
        if (loading) return;
        loading = true;
        showMessage('Cargando…');
        fetch(feedUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { okHttp: r.ok, j: j };
                }).catch(function () {
                    return { okHttp: false, j: null };
                });
            })
            .then(function (res) {
                loading = false;
                if (!res.okHttp || !res.j || !res.j.ok) {
                    showMessage('No se pudieron cargar los avisos');
                    return;
                }
                loadedOnce = true;
                setBadge(res.j.unread);
                renderItems(res.j.items || []);
            })
            .catch(function () {
                loading = false;
                showMessage('No se pudieron cargar los avisos');
            });
    }

    function pollCount() {
        if (document.hidden || !contadorUrl) return;
        fetch(contadorUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (j && j.ok) {
                    loadedOnce = true;
                    setBadge(j.unread);
                }
            })
            .catch(function () {});
    }

    // Bootstrap 4 event + click fallback
    if (window.jQuery) {
        window.jQuery(trigger).on('show.bs.dropdown', loadFeed);
    }
    trigger.addEventListener('click', function () {
        setTimeout(loadFeed, 0);
    });

    if (markAll && leerTodasUrl) {
        markAll.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            fetch(leerTodasUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) {
                        setBadge(0);
                        loadFeed();
                    }
                })
                .catch(function () {});
        });
    }

    setInterval(pollCount, 45000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollCount();
    });
})();
