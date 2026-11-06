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

    function normalizarDisparador(valor) {
        var codigo = String(valor || '').toLowerCase().trim();
        if (!codigo) {
            return null;
        }
        var normal = MAPA[codigo] || codigo;
        return DISPARADOR[normal] || normal || null;
    }

    function filtrar(lista, disparadorOverride) {
        var disparador = normalizarDisparador(disparadorOverride) || disparadorPantalla();
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
     * Decide si se puede seguir (cargar cliente o facturar).
     * Bloquear corta. Confirmar pregunta. El aviso de texto deja seguir.
     * disparadorOverride: p. ej. 'factura' al facturar desde un pedido.
     */
    function evaluar(lista, disparadorOverride) {
        var avisos = filtrar(lista, disparadorOverride);
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

    function mostrar(avisos, clienteId, disparadorOverride) {
        var disparador = normalizarDisparador(disparadorOverride) || disparadorPantalla();
        var lista = filtrar(avisos, disparador);
        pintar(lista);
        if (!lista.length) {
            return;
        }
        var clave = String(disparador || '') + ':' + claveDe(clienteId, lista);
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

    /**
     * Avisos de factura al emitir desde pedido (la pantalla sigue en contexto pedido).
     * @return {boolean} false si hay que abortar la facturación
     */
    function antesDeFacturar(clienteId) {
        var lista = window.__clienteAvisosIniciales || [];
        var evaluacion = evaluar(lista, 'factura');
        if (!evaluacion.seguir) {
            window.alert(evaluacion.mensaje || 'No se puede facturar con este cliente.');
            return false;
        }
        if (evaluacion.avisos && evaluacion.avisos.length) {
            mostrar(lista, clienteId, 'factura');
        }
        return true;
    }

    window.clienteAvisos = {
        disparadorPantalla: disparadorPantalla,
        filtrar: filtrar,
        evaluar: evaluar,
        mostrar: mostrar,
        antesDeFacturar: antesDeFacturar,
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
