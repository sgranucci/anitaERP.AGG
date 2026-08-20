/* global carpetaBase */
window.clienteConsultaModoFiltro = true;

function aplicarClienteFiltroCertsan(data) {
    if (!data || !data.id) {
        $('#cliente_id').val('');
        $('#codigocliente').val('');
        $('#nombrecliente').val('');
        return;
    }
    $('#cliente_id').val(data.id);
    $('#codigocliente').val(data.codigo != null ? data.codigo : '');
    $('#nombrecliente').val(data.nombre || '');
}

function limpiarClienteFiltroCertsan(mantenerCodigo) {
    $('#cliente_id').val('');
    if (!mantenerCodigo) {
        $('#codigocliente').val('');
    }
    $('#nombrecliente').val('');
}

function resolverClienteFiltroCertsan(codigo, opciones) {
    opciones = opciones || {};
    var alertar = !!opciones.alertar;
    var $input = $('#codigocliente');
    var cod = $.trim(codigo || '');

    if (cod === '') {
        limpiarClienteFiltroCertsan(false);
        return;
    }

    $.get(carpetaBase + '/ventas/leerunclienteporcodigo/' + encodeURIComponent(cod))
        .done(function (data) {
            if (data && data.id) {
                aplicarClienteFiltroCertsan(data);
                return;
            }
            limpiarClienteFiltroCertsan(true);
            $input.val(cod);
            if (alertar) {
                setTimeout(function () {
                    alert('No se encontr\u00f3 el cliente indicado.');
                    $input.trigger('focus').select();
                }, 0);
            }
        })
        .fail(function () {
            limpiarClienteFiltroCertsan(true);
            $input.val(cod);
            if (alertar) {
                setTimeout(function () {
                    alert('No se encontr\u00f3 el cliente indicado.');
                    $input.trigger('focus').select();
                }, 0);
            }
        });
}

window.onClienteElegidoEnConsulta = function (fila) {
    aplicarClienteFiltroCertsan(fila);
    return true;
};

function esTeclaF1ClienteCertsan(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalConsultaClienteAbiertoCertsan() {
    var m = document.getElementById('consultaclienteModal');
    return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
}

function manejarF1ClienteCertsan(e) {
    if (!esTeclaF1ClienteCertsan(e)) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigocliente')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    if (modalConsultaClienteAbiertoCertsan()) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    $(target).closest('.tm-cliente-campo, .form-group').find('.consultacliente').first().trigger('click');
}

function manejarEnterClienteCertsan(e) {
    if (!(e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13))) {
        return;
    }
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigocliente')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    resolverClienteFiltroCertsan($(target).val(), { alertar: true });
}

if (!window.__certsanClienteCaptureActivo) {
    document.addEventListener('keydown', manejarF1ClienteCertsan, true);
    document.addEventListener('keydown', manejarEnterClienteCertsan, true);
    window.__certsanClienteCaptureActivo = true;
}

$(function () {
    $('#codigocliente')
        .off('change.certsanCliente')
        .on('blur.certsanCliente', function () {
            if (modalConsultaClienteAbiertoCertsan()) {
                return;
            }
            resolverClienteFiltroCertsan($(this).val(), { alertar: false });
        });
});
