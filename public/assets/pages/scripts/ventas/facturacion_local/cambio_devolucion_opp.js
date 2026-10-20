(function ($) {
    'use strict';

    var $campoActivo = null;

    function campoDe(el) {
        return $(el).closest('.tm-cuentacaja-campo');
    }

    function aplicarCuenta($campo, data) {
        $campo.find('.cuentacaja_id').val(data.id || '');
        $campo.find('.codigocuentacaja').val(data.codigo || '');
        $campo.find('.descripcioncuentacaja').val(data.nombre || '');
    }

    function avisar(mensaje) {
        if ($('#consultacuentacajaModal').hasClass('show')) {
            $('#consultacuentacajaModal').modal('hide');
        }
        if ($('#modal-cdm-cerrar-opp').hasClass('show')) {
            // No cerrar el modal de OPP: solo el de consulta.
        }
        window.setTimeout(function () {
            window.alert(mensaje);
        }, 0);
    }

    function resolverCodigo($campo, avisarError) {
        var codigo = $.trim($campo.find('.codigocuentacaja').val() || '');
        var input = $campo.find('.codigocuentacaja').get(0);
        if (codigo === '') {
            aplicarCuenta($campo, { id: '', codigo: '', nombre: '' });
            if (avisarError) {
                avisar('Ingresá el código del medio de pago.');
                if (input) {
                    input.focus();
                }
            }
            return false;
        }
        if (parseInt($campo.find('.cuentacaja_id').val() || '0', 10) > 0) {
            return true;
        }
        var empresa = $('#empresa_id').val() || '';
        var base = (window.carpetaBase || '') + '/caja/cuentacaja/leercuentacajaporcodigo';
        var ok = false;
        $.ajax({
            url: base + '/' + encodeURIComponent(codigo),
            data: { empresa_id: empresa },
            dataType: 'json',
            async: false,
        }).done(function (cuenta) {
            if (!cuenta || !cuenta.id) {
                if (avisarError) {
                    avisar('No se encontró la cuenta de caja.');
                    if (input) {
                        input.focus();
                    }
                }
                aplicarCuenta($campo, { id: '', codigo: codigo, nombre: '' });
                return;
            }
            aplicarCuenta($campo, {
                id: cuenta.id,
                codigo: cuenta.codigo || codigo,
                nombre: cuenta.nombre || '',
            });
            ok = true;
        }).fail(function (xhr) {
            aplicarCuenta($campo, { id: '', codigo: codigo, nombre: '' });
            if (avisarError) {
                var msg = 'No se encontró la cuenta de caja.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                avisar(msg);
                if (input) {
                    input.focus();
                }
            }
        });
        return ok;
    }

    function abrirModal($campo) {
        $campoActivo = $campo;
        if (!(parseInt($('#empresa_id').val() || '0', 10) > 0)) {
            avisar('El legajo no tiene empresa para filtrar medios de pago.');
            return;
        }
        $('#consultacuentacaja').val('');
        $('#consultacuentacajaModal').modal('show');
    }

    $(document).on('click', '#modal-cdm-cerrar-opp .consultacuentacaja', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModal(campoDe(this));
    });

    $(document).on('keydown', '#modal-cdm-cerrar-opp .codigocuentacaja', function (e) {
        if (e.which === 112 || e.key === 'F1') {
            e.preventDefault();
            abrirModal(campoDe(this));
            return;
        }
        if (e.which === 13 || e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            resolverCodigo(campoDe(this), true);
        }
    });

    $(document).on('input', '#modal-cdm-cerrar-opp .codigocuentacaja', function () {
        var $campo = campoDe(this);
        $campo.find('.cuentacaja_id').val('');
        $campo.find('.descripcioncuentacaja').val('');
    });

    $(document).on('blur', '#modal-cdm-cerrar-opp .codigocuentacaja', function () {
        if ($('#consultacuentacajaModal').hasClass('show') || $('#consultacuentacajaModal').hasClass('showing')) {
            return;
        }
        var $campo = campoDe(this);
        if ($.trim($campo.find('.codigocuentacaja').val() || '') === '') {
            return;
        }
        if (parseInt($campo.find('.cuentacaja_id').val() || '0', 10) > 0) {
            return;
        }
        resolverCodigo($campo, false);
    });

    $(document).on('click', '.eligeconsultacuentacaja', function () {
        if (!$campoActivo || !$campoActivo.length || !$('#modal-cdm-cerrar-opp').length) {
            return;
        }
        if (!$campoActivo.closest('#modal-cdm-cerrar-opp').length) {
            return;
        }
        var $tr = $(this).closest('tr');
        aplicarCuenta($campoActivo, {
            id: $.trim($tr.find('.cuentacaja_id').text() || ''),
            codigo: $.trim($tr.find('.codigo').text() || ''),
            nombre: $.trim($tr.find('.nombre').text() || ''),
        });
        $('#consultacuentacajaModal').modal('hide');
    });

    $(document).on('submit', '#form-cdm-cerrar-opp', function (e) {
        var $campo = $('#modal-cdm-cerrar-opp .tm-cuentacaja-campo').first();
        if (!resolverCodigo($campo, true)) {
            e.preventDefault();
            return false;
        }
        if (!(parseInt($campo.find('.cuentacaja_id').val() || '0', 10) > 0)) {
            e.preventDefault();
            avisar('Elegí el medio de pago antes de emitir la OPP.');
            return false;
        }
        var $btn = $('#btn-cdm-cerrar-opp-submit');
        $btn.prop('disabled', true).text('Emitiendo…');
        return true;
    });
})(jQuery);
