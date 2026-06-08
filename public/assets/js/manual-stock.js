(function () {
    'use strict';

    const progress = document.getElementById('mc-progress');
    const search = document.getElementById('mc-search');
    const chapters = document.querySelectorAll('.mc-chapter');
    const navLinks = document.querySelectorAll('.mc-nav a');
    const themeBtn = document.getElementById('mc-theme-toggle');

    function onScroll() {
        const h = document.documentElement;
        const pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
        if (progress) progress.style.width = pct + '%';

        let current = '';
        chapters.forEach(function (ch) {
            if (ch.getBoundingClientRect().top <= 120) {
                current = ch.id;
            }
        });
        navLinks.forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('href') === '#' + current);
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (search) {
        search.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            chapters.forEach(function (ch) {
                const text = ch.textContent.toLowerCase();
                ch.classList.toggle('mc-hidden', q.length > 1 && text.indexOf(q) === -1);
            });
            navLinks.forEach(function (a) {
                const id = (a.getAttribute('href') || '').replace('#', '');
                const el = document.getElementById(id);
                if (el) {
                    a.parentElement.classList.toggle('mc-hidden', el.classList.contains('mc-hidden'));
                }
            });
        });
    }

    if (themeBtn) {
        const stored = localStorage.getItem('mc-theme');
        if (stored) document.documentElement.setAttribute('data-theme', stored);
        themeBtn.addEventListener('click', function () {
            const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next === 'dark' ? '' : 'light');
            if (next === 'dark') {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('mc-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('mc-theme', 'light');
            }
        });
    }
})();
