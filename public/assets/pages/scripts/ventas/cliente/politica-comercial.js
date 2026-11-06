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

    /**
     * Antes avisaba moroso/proforma con alert() al cargar el cliente en pedido.
     * Eso pasó al módulo de avisos del cliente (clienteAvisos / ClienteAvisoSupport).
     * Acá solo queda el banner bajo el nombre; el tope al facturar/boletar sigue con mensaje().
     */
    function mensajeAvisoCarga() {
        return null;
    }

    function avisarRestricciones() {
        return false;
    }

    function setActual(payload) {
        window.__clientePoliticaActual = payload && payload.politica ? payload : payloadVacio();
        pintarBanner();
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
