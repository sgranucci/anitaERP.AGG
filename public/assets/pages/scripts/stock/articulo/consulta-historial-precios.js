/**
 * Abre el informe de historial de precios de compra filtrado por artículo (ABM artículos).
 */
(function () {
    'use strict';

    function urlHistorialPreciosArticulo() {
        var el = document.getElementById('historial-precios-articulo-url');
        if (el && el.value) {
            return el.value;
        }
        if (typeof carpetaBase !== 'undefined' && carpetaBase) {
            return String(carpetaBase).replace(/\/$/, '') + '/compras/historial-precios-articulo';
        }
        return '/compras/historial-precios-articulo';
    }

    function notificar(titulo, mensaje, tipo) {
        if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(mensaje, titulo, tipo || 'warning');
        } else {
            alert(mensaje);
        }
    }

    function abrirHistorialPreciosArticulo(articuloId) {
        articuloId = parseInt(articuloId, 10) || 0;
        if (articuloId <= 0) {
            notificar('Historial precios', 'Seleccione un artículo válido.');
            return;
        }

        var params = new URLSearchParams({
            articulo_id: String(articuloId),
            modo: 'detalle',
            consultar: '1',
        });

        window.open(urlHistorialPreciosArticulo() + '?' + params.toString(), '_blank', 'noopener');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-historial-precios-articulo');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirHistorialPreciosArticulo(btn.getAttribute('data-articulo-id'));
    });

    window.abrirHistorialPreciosArticulo = abrirHistorialPreciosArticulo;
})();
