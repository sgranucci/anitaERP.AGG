/**
 * Consulta de recepciones de proveedor filtradas por artículo (ABM artículos).
 */
(function () {
    'use strict';

    function urlConsultaRecepcionesArticulo() {
        var el = document.getElementById('recepcion-proveedor-consulta-articulo-url');
        if (el && el.value) {
            return el.value;
        }
        if (typeof carpetaBase !== 'undefined' && carpetaBase) {
            return String(carpetaBase).replace(/\/$/, '') + '/stock/recepcion-proveedor/consulta-por-articulo';
        }
        return '/stock/recepcion-proveedor/consulta-por-articulo';
    }

    function notificar(titulo, mensaje, tipo) {
        if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(mensaje, titulo, tipo || 'warning');
        } else {
            alert(mensaje);
        }
    }

    function abrirConsultaRecepcionesArticulo(articuloId, volverUrl) {
        articuloId = parseInt(articuloId, 10) || 0;
        if (articuloId <= 0) {
            notificar('Recepciones', 'Seleccione un artículo válido.');
            return;
        }

        var params = new URLSearchParams({
            articulo_id: String(articuloId),
            vista: 'consulta',
            volver: volverUrl || (window.location.pathname + window.location.search),
        });

        window.open(urlConsultaRecepcionesArticulo() + '?' + params.toString(), '_blank', 'noopener');
    }

    function datosDesdeBoton(btn) {
        return {
            articuloId: parseInt(btn.getAttribute('data-articulo-id'), 10) || 0,
            volverUrl: window.location.pathname + window.location.search,
        };
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-recepciones-articulo');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var datos = datosDesdeBoton(btn);
        abrirConsultaRecepcionesArticulo(datos.articuloId, datos.volverUrl);
    });

    window.abrirConsultaRecepcionesArticulo = abrirConsultaRecepcionesArticulo;
})();
