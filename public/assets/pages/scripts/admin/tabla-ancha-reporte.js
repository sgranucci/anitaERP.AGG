(function () {
    function bindTablaAncha(root) {
        var wrap = root.querySelector('.tabla-ancha-wrap');
        var topScroll = root.querySelector('.tabla-ancha-scroll-top');
        var topInner = root.querySelector('.tabla-ancha-scroll-top-inner');
        if (!wrap || !topScroll || !topInner) {
            return;
        }
        var table = wrap.querySelector('table');
        var syncing = false;
        function medir() {
            var ancho = table ? table.scrollWidth : wrap.scrollWidth;
            topInner.style.width = ancho + 'px';
            if (wrap.scrollWidth > wrap.clientWidth + 2) {
                topScroll.removeAttribute('hidden');
            } else {
                topScroll.setAttribute('hidden', 'hidden');
            }
        }
        topScroll.addEventListener('scroll', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            wrap.scrollLeft = topScroll.scrollLeft;
            syncing = false;
        });
        wrap.addEventListener('scroll', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            topScroll.scrollLeft = wrap.scrollLeft;
            syncing = false;
        });
        window.addEventListener('resize', medir);
        medir();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-tabla-ancha]').forEach(bindTablaAncha);
    });
})();
