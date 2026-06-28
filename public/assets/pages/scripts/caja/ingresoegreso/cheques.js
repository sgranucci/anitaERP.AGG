var cuentacajaxcodigoEmitido;
var cuentacajaxcodigoReemplazo;

function activaEventosChequesIngresoEgreso() {
    $('#agrega_renglon_cheque_emitido').on('click', agregaRenglonChequeEmitido);
    $('#agrega_renglon_cheque_recibido').on('click', agregaRenglonChequeRecibido);
    $('#agrega_renglon_cheque_reemplazo').on('click', agregaRenglonChequeReemplazo);

    $(document).on('click', '.eliminar_cheque_emitido', borraRenglonChequeEmitido);
    $(document).on('click', '.eliminar_cheque_recibido', borraRenglonChequeRecibido);
    $(document).on('click', '.eliminar_cheque_reemplazo', borraRenglonChequeReemplazo);

    $(document).on('change', '.montocheque_emitido, .cotizacioncheque_emitido, .moneda_emitido_id', function () {
        if (typeof sumaMonto === 'function') sumaMonto();
        flModificaAsiento = true;
    });
    $(document).on('change', '.montocheque_recibido, .cotizacioncheque_recibido, .monedacheque_recibido_id', function () {
        if (typeof sumaMonto === 'function') sumaMonto();
        flModificaAsiento = true;
    });
    $(document).on('change', '.montocheque_reemplazo, .origen_reemplazo', function () {
        toggleBloqueReemplazo($(this).closest('tr'));
        if (typeof sumaMonto === 'function') sumaMonto();
        flModificaAsiento = true;
    });

    $(document).on('click', '.consultacuentacaja_emitido', function () {
        cuentacajaxcodigoEmitido = $(this).closest('tr');
        if (!$('#empresa_id').val()) {
            alert('Debe ingresar empresa');
            return;
        }
        $('#consultacuentacajaModal').modal('show');
    });

    $(document).on('click', '.consultacuentacaja_reemplazo', function () {
        cuentacajaxcodigoReemplazo = $(this).closest('tr');
        if (!$('#empresa_id').val()) {
            alert('Debe ingresar empresa');
            return;
        }
        $('#consultacuentacajaModal').modal('show');
    });

    $(document).on('click', '.consultabanco_recibido', function () {
        ptrbanco_id = $(this).closest('tr').find('.banco_recibido_id');
        ptrcodigobanco = $(this).closest('tr').find('.codigobanco_recibido');
        ptrnombrebanco = $(this).closest('tr').find('.nombrebanco_recibido');
        $('#consultabancoModal').modal('show');
    });

    $(document).on('click', '.consultabanco_reemplazo', function () {
        ptrbanco_id = $(this).closest('tr').find('.banco_reemplazo_id');
        ptrnombrebanco = $(this).closest('tr').find('.nombrebanco_reemplazo');
        $('#consultabancoModal').modal('show');
    });

    $(document).on('click', '.buscar_cheque_anulado', function () {
        var row = $(this).closest('tr');
        var numero = row.find('.numerocheque_anulado_buscar').val();
        var empresa_id = $('#empresa_id').val();
        if (!numero || !empresa_id) {
            alert('Indique empresa y n\u00famero de cheque a anular');
            return;
        }
        $.post(carpetaBase + '/caja/ingresoegreso/buscar-cheque', {
            _token: $('input[name=_token]').val(),
            empresa_id: empresa_id,
            numerocheque: numero,
            banco_id: row.find('.banco_reemplazo_id').val() || 0
        }, function (data) {
            if (data.mensaje !== 'ok') {
                alert('Cheque no encontrado');
                return;
            }
            row.find('.cheque_anulado_id').val(data.cheque.id);
            row.find('.numerocheque_anulado').val(data.cheque.numerocheque + ' (' + data.cheque.banco + ')');
            row.find('.montocheque_reemplazo').val(data.cheque.monto);
            row.find('.moneda_reemplazo_id').val(data.cheque.moneda_id);
            row.find('.cotizacioncheque_reemplazo').val(data.cheque.cotizacion);
            row.find('.origen_reemplazo').val(data.cheque.origen === 'E' ? 'E' : 'R');
            toggleBloqueReemplazo(row);
            flModificaAsiento = true;
        });
    });
}

function toggleBloqueReemplazo(row) {
    var tipo = row.find('.origen_reemplazo').val();
    if (tipo === 'R') {
        row.find('.bloque-reemplazo-emitido').hide();
        row.find('.bloque-reemplazo-recibido').show();
    } else {
        row.find('.bloque-reemplazo-recibido').hide();
        row.find('.bloque-reemplazo-emitido').show();
    }
}

function agregaRenglonChequeEmitido(e) {
    e.preventDefault();
    var html = $('#template-renglon-cheque-emitido').html();
    $('#tbody-cheque-emitido-table').append(html);
    var row = $('#tbody-cheque-emitido-table tr:last');
    row.find('.fechapago_emitido').val($('#fecha').val());
    flModificaAsiento = true;
}

function agregaRenglonChequeRecibido(e) {
    e.preventDefault();
    var html = $('#template-renglon-cheque-recibido').html();
    $('#tbody-cheque-recibido-table').append(html);
    $('#tbody-cheque-recibido-table tr:last').find('.fechapago_recibido').val($('#fecha').val());
    flModificaAsiento = true;
}

function agregaRenglonChequeReemplazo(e) {
    e.preventDefault();
    var html = $('#template-renglon-cheque-reemplazo').html();
    $('#tbody-cheque-reemplazo-table').append(html);
    var row = $('#tbody-cheque-reemplazo-table tr:last');
    row.find('.fechapago_reemplazo').val($('#fecha').val());
    toggleBloqueReemplazo(row);
    flModificaAsiento = true;
}

function borraRenglonChequeEmitido(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
    sumaMontosChequesIngresoEgreso();
    flModificaAsiento = true;
}

function borraRenglonChequeRecibido(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
    sumaMontosChequesIngresoEgreso();
    flModificaAsiento = true;
}

function borraRenglonChequeReemplazo(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
    sumaMontosChequesIngresoEgreso();
    flModificaAsiento = true;
}

function sumaMontosChequesIngresoEgreso() {
    var extraDebe = 0;
    var extraHaber = 0;
    var monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val() || 1;

    $("#tbody-cheque-emitido-table .montocheque_emitido").each(function () {
        var monto = parseFloat($(this).val()) || 0;
        if (monto <= 0) return;
        var moneda = $(this).closest('tr').find('.moneda_emitido_id').val();
        var cot = $(this).closest('tr').find('.cotizacioncheque_emitido').val();
        var coef = calculaCoeficienteMoneda(monedaDefault, moneda, cot);
        extraHaber += monto * coef;
    });

    $("#tbody-cheque-recibido-table .montocheque_recibido").each(function () {
        var monto = parseFloat($(this).val()) || 0;
        if (monto <= 0) return;
        var moneda = $(this).closest('tr').find('.monedacheque_recibido_id').val();
        var cot = $(this).closest('tr').find('.cotizacioncheque_recibido').val();
        var coef = calculaCoeficienteMoneda(monedaDefault, moneda, cot);
        extraDebe += monto * coef;
    });

    return { extraDebe: extraDebe, extraHaber: extraHaber };
}

function serializarChequesEmitidos() {
    var datos = [];
    $('#tbody-cheque-emitido-table tr').each(function () {
        var monto = parseFloat($(this).find('.montocheque_emitido').val()) || 0;
        if (monto <= 0) return;
        datos.push({
            cuentacaja_ids: $(this).find('.cuentacaja_emitido_id').val(),
            moneda_ids: $(this).find('.moneda_emitido_id').val(),
            montos: monto,
            cotizaciones: $(this).find('.cotizacioncheque_emitido').val(),
            fechapagos: $(this).find('.fechapago_emitido').val()
        });
    });
    return JSON.stringify(datos);
}

function serializarChequesRecibidos() {
    var datos = [];
    $('#tbody-cheque-recibido-table tr').each(function () {
        var monto = parseFloat($(this).find('.montocheque_recibido').val()) || 0;
        if (monto <= 0) return;
        datos.push({
            moneda_ids: $(this).find('.monedacheque_recibido_id').val(),
            montos: monto,
            cotizaciones: $(this).find('.cotizacioncheque_recibido').val()
        });
    });
    return JSON.stringify(datos);
}

function serializarChequesReemplazo() {
    var datos = [];
    $('#tbody-cheque-reemplazo-table tr').each(function () {
        var idAnulado = $(this).find('.cheque_anulado_id').val();
        if (!idAnulado) return;
        datos.push({
            cheque_anulado_id: idAnulado,
            origen_anulado: 'R',
            origen_reemplazo: $(this).find('.origen_reemplazo').val(),
            monto_anulado: parseFloat($(this).find('.montocheque_reemplazo').val()) || 0,
            monto_reemplazo: parseFloat($(this).find('.montocheque_reemplazo').val()) || 0,
            moneda_ids: $(this).find('.moneda_reemplazo_id').val(),
            cotizaciones: $(this).find('.cotizacioncheque_reemplazo').val(),
            cuentacaja_reemplazo_ids: $(this).find('.cuentacaja_reemplazo_id').val(),
            fechapago_reemplazo: $(this).find('.fechapago_reemplazo').val()
        });
    });
    return JSON.stringify(datos);
}
