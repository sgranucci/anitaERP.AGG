(function () {
    var link = document.getElementById('anita-nav-aprobaciones');
    if (!link) return;

    var url = link.getAttribute('data-contador-url');
    if (!url) return;

    var badge = document.getElementById('anita-aprob-badge');
    var last = parseInt(link.getAttribute('data-count') || '0', 10) || 0;
    var toastTimer = null;

    function label(n) {
        return n > 99 ? '99+' : String(n);
    }

    function showToast(count) {
        var prev = document.getElementById('anita-inbox-live-toast');
        if (prev) prev.remove();
        var el = document.createElement('div');
        el.id = 'anita-inbox-live-toast';
        el.className = 'anita-inbox-toast';
        el.innerHTML = 'Tenés pendientes nuevos en aprobaciones (' + label(count) + '). '
            + '<a href="' + (link.getAttribute('href') || '#') + '">Abrir bandeja</a>';
        document.body.appendChild(el);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 8000);
    }

    function applyCount(count) {
        count = Math.max(0, parseInt(count, 10) || 0);
        if (count > last) {
            showToast(count);
        }
        last = count;
        link.setAttribute('data-count', String(count));
        if (!badge) return;
        badge.textContent = label(count);
        if (count > 0) {
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    }

    function poll() {
        if (document.hidden) return;
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (j && j.ok) applyCount(j.count);
            })
            .catch(function () {});
    }

    setInterval(poll, 30000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });
})();
