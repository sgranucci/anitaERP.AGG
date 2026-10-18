$(function () {
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
    if (typeof activa_eventos_consultacentrocosto === 'function') {
        activa_eventos_consultacentrocosto();
    }
    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }
    if (typeof activa_eventos_consultabienusorubro === 'function') {
        activa_eventos_consultabienusorubro();
    }
    if (typeof activa_eventos_consultabienusosubrubro === 'function') {
        activa_eventos_consultabienusosubrubro();
    }
    if (typeof activa_eventos_consultabienuso === 'function') {
        activa_eventos_consultabienuso();
    }

    actualizarCamposBienUsoTipo();
    $('#tipo_bien').on('change', actualizarCamposBienUsoTipo);

    var $foco = $('#codigo_bien');
    if (!$foco.length || !$foco.is(':visible')) {
        $foco = $('#descripcion');
    }
    if ($foco.length) {
        $foco.trigger('focus');
    }

    // Enter avanza al siguiente campo editable del formulario (sin enviar).
    $('#form-general').on('keydown', 'input, select, textarea', function (e) {
        if (!(e.key === 'Enter' || e.keyCode === 13)) {
            return;
        }
        var tag = (this.tagName || '').toLowerCase();
        if (tag === 'textarea') {
            return;
        }
        // Campos de consulta con resolver propio (código + Enter).
        if ($(this).is('.codigocuentacontable, .codigocentrocosto, .codigoproveedor, .codigobienusorubro, .codigobienusosubrubro, .codigobienuso, .codigobienusoindice')) {
            return;
        }
        e.preventDefault();
        var $campos = $('#form-general')
            .find('input:visible:not([type=hidden]):not([readonly]):not([disabled]), select:visible:not([disabled]), textarea:visible:not([readonly]):not([disabled])')
            .filter(function () {
                return $(this).closest('.d-none').length === 0;
            });
        var idx = $campos.index(this);
        if (idx >= 0 && idx < $campos.length - 1) {
            $campos.eq(idx + 1).trigger('focus').select();
        }
    });
});

function actualizarCamposBienUsoTipo() {
    var tipo = $('#tipo_bien').val() || '';
    var esMaquina = tipo === 'M' || tipo === 'I';
    var esPc = tipo === 'P';

    $('.campos-bien-maquina').toggle(esMaquina);
    $('.campos-bien-pc').toggle(esPc);

    var $hostname = $('#hostname');
    var $uid = $('#uid');
    if ($hostname.length) {
        $hostname.prop('required', esPc);
    }
    if ($uid.length) {
        $uid.prop('required', tipo === 'M');
    }
}
