$(function () {
    var $tbody = $('#conta-lineas');
    var $objetivo = $('#conta-objetivo');
    var monto = parseFloat($objetivo.data('monto')) || 0;
    var tolerancia = 0.02;

    function numero($input) {
        var valor = parseFloat(String($input.val() || '').replace(',', '.'));
        return isNaN(valor) ? 0 : Math.round(valor * 100) / 100;
    }

    function formato(valor) {
        return valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcular() {
        var debe = 0;
        var haber = 0;
        var completa = true;
        var renglones = 0;
        $tbody.find('tr.conta-linea').each(function () {
            var $tr = $(this);
            var d = numero($tr.find('.debe-linea'));
            var h = numero($tr.find('.haber-linea'));
            var id = parseInt($tr.find('.cuentacontable_id').val(), 10) || 0;
            if (d <= 0 && h <= 0 && id <= 0) {
                return;
            }
            renglones += 1;
            debe += d;
            haber += h;
            if (id <= 0 || (d > 0 && h > 0) || (d <= 0 && h <= 0)) {
                completa = false;
            }
        });
        debe = Math.round(debe * 100) / 100;
        haber = Math.round(haber * 100) / 100;
        var diferencia = Math.round((debe - haber) * 100) / 100;
        var cuadra = completa && renglones >= 2
            && Math.abs(debe - haber) <= tolerancia
            && Math.abs(debe - monto) <= tolerancia;

        $('#conta-debe').text(formato(debe));
        $('#conta-haber').text(formato(haber));
        $('#conta-dif').text(formato(Math.abs(diferencia)));
        $('#conta-generar').prop('disabled', !cuadra);
        $('.conta-pie').toggleClass('conta-ok', cuadra);
        var aviso = 'El debe y el haber tienen que sumar ' + formato(monto) + ', el total del movimiento.';
        if (cuadra) {
            aviso = 'El asiento cuadra con el total del movimiento.';
        } else if (Math.abs(debe - haber) <= tolerancia && Math.abs(debe - monto) <= tolerancia) {
            aviso = 'El total ya cuadra. Completá la cuenta contable de cada renglón.';
        }
        $('#conta-aviso').text(aviso);
    }

    $('#conta-agregar').on('click', function () {
        var html = $('#conta-template-linea').html();
        $tbody.append(html);
        $tbody.find('tr.conta-linea').last().find('.codigocuentacontable').trigger('focus');
        recalcular();
    });

    $tbody.on('click', '.conta-quitar', function () {
        var $filas = $tbody.find('tr.conta-linea');
        if ($filas.length <= 2) {
            return;
        }
        $(this).closest('tr').remove();
        recalcular();
    });

    $tbody.on('input', '.debe-linea', function () {
        if (numero($(this)) > 0) {
            $(this).closest('tr').find('.haber-linea').val('0.00');
        }
        recalcular();
    });

    $tbody.on('input', '.haber-linea', function () {
        if (numero($(this)) > 0) {
            $(this).closest('tr').find('.debe-linea').val('0.00');
        }
        recalcular();
    });

    $tbody.on('change', '.cuentacontable_id', recalcular);

    function focoPrimeraCuentaACargar() {
        var $fila = $tbody.find('tr.conta-linea').filter(function () {
            return (parseInt($(this).find('.cuentacontable_id').val(), 10) || 0) <= 0;
        }).first();
        var $campo = $fila.find('.codigocuentacontable');
        if (!$campo.length) {
            $campo = $tbody.find('.codigocuentacontable').first();
        }
        if ($campo.length) {
            $campo.trigger('focus');
        }
    }

    recalcular();
    window.setTimeout(focoPrimeraCuentaACargar, 0);
});
