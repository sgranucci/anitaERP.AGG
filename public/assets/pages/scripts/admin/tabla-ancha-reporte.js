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
        function medirDobleCabecera() {
            if (!root.classList.contains('tabla-ancha--doble-cabecera') || !table) {
                return;
            }
            var thead = table.querySelector('thead');
            var segunda = table.querySelector('thead tr:nth-child(2)');
            if (!thead || !segunda) {
                return;
            }
            var altoSegunda = segunda.offsetHeight;
            var altoPrimera = Math.max(0, thead.offsetHeight - altoSegunda);
            if (altoPrimera > 0) {
                root.style.setProperty('--tabla-ancha-h1', altoPrimera + 'px');
            }
        }
        function medir() {
            medirDobleCabecera();
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

    function iniciar() {
        document.querySelectorAll('[data-tabla-ancha]').forEach(bindTablaAncha);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
