(function () {
    var links = document.querySelectorAll('[data-bandeja-ticket-contador]');
    if (!links.length) return;

    var url = null;
    for (var i = 0; i < links.length; i++) {
        url = links[i].getAttribute('data-contador-url');
        if (url) break;
    }
    if (!url) return;

    var badges = document.querySelectorAll('.js-bandeja-ticket-badge');

    function label(n) {
        return n > 99 ? '99+' : String(n);
    }

    function applyCount(count) {
        count = Math.max(0, parseInt(count, 10) || 0);
        for (var i = 0; i < links.length; i++) {
            links[i].setAttribute('data-count', String(count));
        }
        for (var j = 0; j < badges.length; j++) {
            badges[j].textContent = label(count);
            if (count > 0) {
                badges[j].classList.remove('d-none');
            } else {
                badges[j].classList.add('d-none');
            }
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

    poll();
    setInterval(poll, 30000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });
})();
