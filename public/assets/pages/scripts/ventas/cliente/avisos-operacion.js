/**
 * Avisos del cliente al cargar un comprobante (pedido, factura, recibo/cobranza, etc.).
 * El backend manda los vigentes en data.avisos. Acá se filtran por la pantalla.
 */
(function (window) {
    var MAPA = {
        pedido: 'pedido',
        factura: 'factura',
        facturacion: 'factura',
        recibo: 'recibo',
        cobranza: 'cobranza',
        cobro: 'cobranza',
        boleta: 'boleta',
        ot: 'boleta',
        ordentrabajo: 'boleta',
        remito: 'remito'
    };

    var DISPARADOR = {
        pedido: 'pedido',
        factura: 'factura',
        cobranza: 'recibo',
        recibo: 'recibo',
        boleta: 'boleta',
        remito: 'remito'
    };

    var ultimaClave = '';

    function disparadorPantalla() {
        var contexto = String(window.CLIENTE_CONSULTA_CONTEXTO || '').toLowerCase().trim();
        var normal = MAPA[contexto] || contexto;
        return DISPARADOR[normal] || null;
    }

    function filtrar(lista) {
        var disparador = disparadorPantalla();
        if (!disparador) {
            return [];
        }
        return (lista || []).filter(function (aviso) {
            return aviso && aviso.disparador === disparador && aviso.mensaje;
        });
    }

    function asegurarBanner() {
        var $banner = $('#cliente-avisos-banner');
        if ($banner.length) {
            return $banner;
        }
        var $ancla = $('#nombretiposuspension');
        if (!$ancla.length) {
            return $();
        }
        $banner = $('<div id="cliente-avisos-banner" class="alert alert-warning py-1 px-2 mt-1 mb-0" role="status" style="display:none;"></div>');
        $ancla.after($banner);
        return $banner;
    }

    function pintar(avisos) {
        var $banner = asegurarBanner();
        if (!$banner.length) {
            return;
        }
        $banner.empty();
        if (!avisos || !avisos.length) {
            $banner.hide();
            return;
        }
        avisos.forEach(function (aviso, indice) {
            if (indice > 0) {
                $banner.append(document.createElement('br'));
            }
            $banner.append(document.createTextNode(aviso.mensaje));
        });
        $banner.show();
    }

    function limpiar() {
        ultimaClave = '';
        pintar([]);
    }

    function claveDe(clienteId, avisos) {
        var ids = (avisos || []).map(function (aviso) {
            return aviso.id;
        }).join(',');
        return String(clienteId || '') + ':' + ids;
    }

    /**
     * Decide si se puede seguir cargando el cliente.
     * Bloquear corta. Confirmar pregunta. El aviso de texto deja seguir.
     */
    function evaluar(lista) {
        var avisos = filtrar(lista);
        var bloqueos = [];
        var visibles = [];
        var i;
        var aviso;
        var pregunta;

        for (i = 0; i < avisos.length; i++) {
            aviso = avisos[i];
            if (aviso.modo === 'accion' && aviso.accion === 'bloquear') {
                bloqueos.push(aviso.mensaje);
                continue;
            }
            if (aviso.modo === 'accion' && aviso.accion === 'confirmar') {
                pregunta = aviso.mensaje || '¿Continuar con este cliente?';
                if (!window.confirm(pregunta)) {
                    return { seguir: false, mensaje: 'Se canceló la carga del cliente.', avisos: [] };
                }
                visibles.push(aviso);
                continue;
            }
            visibles.push(aviso);
        }

        if (bloqueos.length) {
            return { seguir: false, mensaje: bloqueos.join('\n'), avisos: [] };
        }

        return { seguir: true, mensaje: '', avisos: visibles };
    }

    function mostrar(avisos, clienteId) {
        var lista = filtrar(avisos);
        pintar(lista);
        if (!lista.length) {
            return;
        }
        var clave = claveDe(clienteId, lista);
        if (clave === ultimaClave) {
            return;
        }
        ultimaClave = clave;
        var texto = lista.map(function (aviso) {
            return aviso.mensaje;
        }).join('\n');
        setTimeout(function () {
            window.alert(texto);
        }, 0);
    }

    window.clienteAvisos = {
        disparadorPantalla: disparadorPantalla,
        filtrar: filtrar,
        evaluar: evaluar,
        mostrar: mostrar,
        pintar: pintar,
        limpiar: limpiar
    };

    $(function () {
        var iniciales = window.__clienteAvisosIniciales || [];
        var clienteId = $('#cliente_id').val();
        if (iniciales.length && clienteId) {
            mostrar(iniciales, clienteId);
        }
    });
})(window);
