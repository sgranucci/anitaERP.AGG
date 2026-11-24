(function () {
    function celdasAcciones(table) {
        if (!table) {
            return [];
        }
        return table.querySelectorAll('th.lw-col-acciones, td.lw-col-acciones, th.col-acciones-tabla, td.col-acciones-tabla');
    }

    function setStickyAcciones(root, table, activo) {
        if (root.dataset.accionesStickyAuto === '0') {
            return;
        }
        var celdas = celdasAcciones(table);
        if (!celdas.length) {
            return;
        }
        var i;
        for (i = 0; i < celdas.length; i++) {
            if (activo) {
                celdas[i].classList.add('col-fija-der-1');
            } else {
                celdas[i].classList.remove('col-fija-der-1');
            }
        }
        if (activo) {
            root.classList.add('tabla-ancha--acciones-sticky');
        } else {
            root.classList.remove('tabla-ancha--acciones-sticky');
        }
    }

    function bindTablaAncha(root) {
        if (root.dataset.tablaAnchaBound === '1') {
            if (typeof root._tablaAnchaMedir === 'function') {
                root._tablaAnchaMedir();
            }
            return;
        }
        var wrap = root.querySelector('.tabla-ancha-wrap');
        var topScroll = root.querySelector('.tabla-ancha-scroll-top');
        var topInner = root.querySelector('.tabla-ancha-scroll-top-inner');
        if (!wrap || !topScroll || !topInner) {
            return;
        }
        root.dataset.tablaAnchaBound = '1';
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
            // Medir overflow sin sticky: si no, el ancho fijo de col-fija-der puede falsear.
            setStickyAcciones(root, table, false);
            var ancho = table ? table.scrollWidth : wrap.scrollWidth;
            topInner.style.width = ancho + 'px';
            var hayScroll = wrap.scrollWidth > wrap.clientWidth + 2;
            if (hayScroll) {
                topScroll.removeAttribute('hidden');
                setStickyAcciones(root, table, true);
            } else {
                topScroll.setAttribute('hidden', 'hidden');
            }
        }
        root._tablaAnchaMedir = medir;
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
        window.setTimeout(medir, 50);
        window.setTimeout(medir, 250);
    }

    function iniciar() {
        document.querySelectorAll('[data-tabla-ancha]').forEach(bindTablaAncha);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
    window.addEventListener('load', iniciar);
})();
