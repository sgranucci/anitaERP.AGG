(function () {
    'use strict';

    const progress = document.getElementById('mc-progress');
    const search = document.getElementById('mc-search');
    const chapters = document.querySelectorAll('.mc-chapter');
    const navLinks = document.querySelectorAll('.mc-nav a');
    const themeBtn = document.getElementById('mc-theme-toggle');

    // Tema claro por defecto (legibilidad en tablas y hero)
    if (!localStorage.getItem('mc-gastronomia-theme')) {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('mc-gastronomia-theme', 'light');
    } else {
        document.documentElement.setAttribute(
            'data-theme',
            localStorage.getItem('mc-gastronomia-theme') === 'dark' ? 'dark' : 'light'
        );
    }

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
        themeBtn.addEventListener('click', function () {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const next = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('mc-gastronomia-theme', next);
        });
    }
})();
