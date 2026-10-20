/**
 * Tope de circuito comercial del cliente (pedido → boleta → factura).
 * El backend es la fuente de verdad; esto solo pinta banner y corta en UI.
 */
(function (window) {
    var POLITICAS = {
        NORMAL: 'NORMAL',
        MOROSO: 'MOROSO',
        PROFORMA: 'PROFORMA',
        BLOQUEADO: 'BLOQUEADO'
    };

    var DEFAULT_PERMITE = {
        consultar: true,
        cobranza: true,
        pedido: true,
        boleta: true,
        factura: true
    };

    function normalizarOperacion(valor) {
        var op = String(valor || '').toLowerCase().trim();
        if (op === 'pedido' || op === 'carga_pedido' || op === 'ordenventa') {
            return 'pedido';
        }
        if (op === 'boleta' || op === 'ot' || op === 'ordentrabajo' || op === 'remito' || op === 'despacho') {
            return 'boleta';
        }
        if (op === 'factura' || op === 'facturacion' || op === 'comprobante') {
            return 'factura';
        }
        if (op === 'cobranza' || op === 'cobro' || op === 'recibo') {
            return 'cobranza';
        }
        return 'consultar';
    }

    function contextoActual() {
        return normalizarOperacion(window.CLIENTE_CONSULTA_CONTEXTO || 'consultar');
    }

    function payloadVacio() {
        return {
            politica: POLITICAS.NORMAL,
            etiqueta: 'Normal',
            motivo: '',
            leyenda: '',
            banner: '',
            permite: DEFAULT_PERMITE,
            mensajes: {}
        };
    }

    function payloadActual() {
        return window.__clientePoliticaActual || payloadVacio();
    }

    function permiteOperacion(operacion, payload) {
        var op = normalizarOperacion(operacion);
        if (op === 'consultar' || op === 'cobranza') {
            return true;
        }
        var p = payload || payloadActual();
        if (!p || !p.permite) {
            return true;
        }
        return p.permite[op] !== false;
    }

    function mensaje(operacion, payload) {
        var op = normalizarOperacion(operacion);
        var p = payload || payloadActual();
        if (p && p.mensajes && p.mensajes[op]) {
            return p.mensajes[op];
        }
        var etiqueta = (p && p.etiqueta) ? p.etiqueta : 'restringido';
        return 'El cliente no puede realizar esta operación (' + etiqueta + ').';
    }

    /** Evita repetir el mismo cartel si setActual corre dos veces (init + leercliente). */
    var ultimoMensajeAvisado = null;

    /**
     * Aviso informativo al cargar el cliente en pedido (no corta la carga).
     * Solo topes posteriores al pedido: moroso (boleta) / proforma (factura).
     * Si no permite pedido, el rechazo lo maneja consulta.js (sin duplicar cartel).
     */
    function mensajeAvisoCarga(payload) {
        var p = payload || payloadActual();
        if (!p || !p.mensajes || p.mensajes.pedido) {
            return null;
        }
        return p.mensajes.boleta || p.mensajes.factura || null;
    }

    function avisarRestricciones(payload) {
        var msg = mensajeAvisoCarga(payload);
        if (!msg) {
            ultimoMensajeAvisado = null;
            return false;
        }
        if (msg === ultimoMensajeAvisado) {
            return false;
        }
        ultimoMensajeAvisado = msg;
        // Diferir: no bloquear pintura del banner ni el cierre del modal de consulta.
        setTimeout(function () {
            alert(msg);
        }, 0);
        return true;
    }

    function setActual(payload, opciones) {
        window.__clientePoliticaActual = payload && payload.politica ? payload : payloadVacio();
        pintarBanner();
        var avisar = !opciones || opciones.avisar !== false;
        if (avisar && contextoActual() === 'pedido') {
            avisarRestricciones(window.__clientePoliticaActual);
        }
        return window.__clientePoliticaActual;
    }

    function claseBanner(politica) {
        if (politica === POLITICAS.MOROSO) {
            return 'text-warning';
        }
        if (politica === POLITICAS.PROFORMA) {
            return 'text-success';
        }
        if (politica === POLITICAS.BLOQUEADO) {
            return 'text-danger';
        }
        return 'text-danger';
    }

    function pintarBanner(payload) {
        var p = payload || payloadActual();
        var $label = $('#nombretiposuspension');
        if (!$label.length) {
            return;
        }
        $label.removeClass('text-warning text-success text-danger');
        if (!p || p.politica === POLITICAS.NORMAL || !p.banner) {
            $label.text('');
            $label.attr('title', '');
            return;
        }
        $label.addClass(claseBanner(p.politica));
        $label.text(p.banner);
        $label.attr('title', p.leyenda || p.banner);
    }

    window.clientePoliticaComercial = {
        POLITICAS: POLITICAS,
        contexto: contextoActual,
        normalizarOperacion: normalizarOperacion,
        permiteOperacion: permiteOperacion,
        mensaje: mensaje,
        mensajeAvisoCarga: mensajeAvisoCarga,
        avisarRestricciones: avisarRestricciones,
        setActual: setActual,
        payloadActual: payloadActual,
        pintarBanner: pintarBanner
    };
})(window);
