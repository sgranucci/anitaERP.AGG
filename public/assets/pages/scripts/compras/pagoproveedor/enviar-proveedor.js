(function ($) {
    'use strict';

    var opIdActual = null;

    function resetModal() {
        opIdActual = null;
        $('#op-envio-proveedor-cargando').addClass('d-none');
        $('#op-envio-proveedor-error').addClass('d-none').text('');
        $('#op-envio-proveedor-form-wrap').addClass('d-none');
        $('#op-envio-proveedor-aviso').addClass('d-none').text('');
        $('#op-envio-proveedor-advertencia').addClass('d-none').text('');
        $('#op-envio-proveedor-email-error').addClass('d-none').text('');
        $('#op_envio_proveedor_email').val('').removeClass('is-invalid');
        $('#op_envio_proveedor_mensaje').val('');
        $('#op_envio_proveedor_confirmar').addClass('d-none').prop('disabled', false);
        $('#modalOpEnviarProveedor .modal-title').text('Enviar orden de pago por email');
    }

    function mostrarError(msg) {
        $('#op-envio-proveedor-error').removeClass('d-none').text(msg || 'No se pudo completar la operación.');
        $('#op-envio-proveedor-form-wrap').addClass('d-none');
        $('#op_envio_proveedor_confirmar').addClass('d-none');
    }

    function parseEmails(raw) {
        return String(raw || '')
            .split(/[\s,;]+/)
            .map(function (p) { return $.trim(p); })
            .filter(function (p) { return p !== ''; });
    }

    function emailValido(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /**
     * @returns {{ok: boolean, emails: string[], invalidos: string[], mensaje: string}}
     */
    function validarEmailsCampo() {
        var raw = $.trim($('#op_envio_proveedor_email').val());
        var partes = parseEmails(raw);
        var emails = [];
        var invalidos = [];
        var vistos = {};

        if (!partes.length) {
            return {
                ok: false,
                emails: [],
                invalidos: [],
                mensaje: 'Indique al menos un email de destino.'
            };
        }

        partes.forEach(function (p) {
            if (!emailValido(p)) {
                invalidos.push(p);
                return;
            }
            var key = p.toLowerCase();
            if (!vistos[key]) {
                vistos[key] = true;
                emails.push(p);
            }
        });

        if (invalidos.length) {
            return {
                ok: false,
                emails: emails,
                invalidos: invalidos,
                mensaje: 'Email(s) inválido(s): ' + invalidos.join(', ')
            };
        }

        return { ok: true, emails: emails, invalidos: [], mensaje: '' };
    }

    function mostrarErrorEmail(msg) {
        var $input = $('#op_envio_proveedor_email');
        var $err = $('#op-envio-proveedor-email-error');
        if (msg) {
            $input.addClass('is-invalid');
            $err.removeClass('d-none').text(msg);
        } else {
            $input.removeClass('is-invalid');
            $err.addClass('d-none').text('');
        }
    }

    function abrirModalEnvioProveedor(pagoproveedorId) {
        if (!pagoproveedorId) {
            return;
        }
        resetModal();
        opIdActual = pagoproveedorId;
        $('#modalOpEnviarProveedor').modal('show');
        $('#op-envio-proveedor-cargando').removeClass('d-none');

        var url = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') +
            '/compras/pagoproveedor/' + pagoproveedorId + '/datos-envio-proveedor';

        $.get(url).done(function (data) {
            $('#op-envio-proveedor-cargando').addClass('d-none');
            if (!data || !data.puede_enviar) {
                mostrarError((data && data.mensaje) ? data.mensaje : 'No se puede enviar esta OP por email.');
                return;
            }
            $('#op-envio-proveedor-form-wrap').removeClass('d-none');
            $('#op_envio_proveedor_email').val(data.email || '');
            if (data.mensaje) {
                $('#op-envio-proveedor-aviso').removeClass('d-none').text(data.mensaje);
            }
            if (data.advertencia_estado) {
                $('#op-envio-proveedor-advertencia').removeClass('d-none').text(data.advertencia_estado);
            }
            var titulo = 'Enviar ' + (data.etiqueta_op || ('OP #' + pagoproveedorId));
            if (data.proveedor_nombre) {
                titulo += ' — ' + data.proveedor_nombre;
            }
            $('#modalOpEnviarProveedor .modal-title').text(titulo);
            $('#op_envio_proveedor_confirmar').removeClass('d-none');
            setTimeout(function () {
                $('#op_envio_proveedor_email').trigger('focus');
            }, 300);
        }).fail(function (xhr) {
            $('#op-envio-proveedor-cargando').addClass('d-none');
            var msg = 'No se pudieron cargar los datos de envío.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            mostrarError(msg);
        });
    }

    window.abrirModalEnvioProveedorOp = abrirModalEnvioProveedor;

    $(document).on('click', '.js-op-enviar-proveedor', function (e) {
        e.preventDefault();
        var id = $(this).data('pagoproveedor-id');
        abrirModalEnvioProveedor(id);
    });

    $(document).on('input', '#op_envio_proveedor_email', function () {
        mostrarErrorEmail('');
    });

    $('#op_envio_proveedor_confirmar').on('click', function () {
        if (!opIdActual) {
            return;
        }

        var validacion = validarEmailsCampo();
        if (!validacion.ok) {
            mostrarErrorEmail(validacion.mensaje);
            $('#op_envio_proveedor_email').trigger('focus');
            return;
        }
        mostrarErrorEmail('');

        var email = validacion.emails.join(', ');
        if (!window.confirm('¿Confirma el envío de la orden de pago por correo?\n\nDestino: ' + email)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        var url = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') +
            '/compras/pagoproveedor/' + opIdActual + '/enviar-proveedor';
        var token = $('meta[name="csrf-token"]').attr('content') ||
            $('input[name="_token"]').first().val();

        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: token,
                email: email,
                mensaje: $('#op_envio_proveedor_mensaje').val()
            }
        }).done(function (data) {
            if (data && data.mensaje === 'ok') {
                $('#modalOpEnviarProveedor').modal('hide');
                if (typeof toastr !== 'undefined') {
                    toastr.success('La orden de pago fue enviada por correo.');
                } else {
                    alert('La orden de pago fue enviada por correo.');
                }
            } else {
                alert((data && data.errores) ? data.errores : 'No se pudo enviar el correo.');
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            var msg = 'No se pudo enviar el correo.';
            if (xhr.responseJSON && xhr.responseJSON.errores) {
                msg = xhr.responseJSON.errores;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.email) {
                msg = xhr.responseJSON.errors.email.join(' ');
            }
            alert(msg);
            $btn.prop('disabled', false);
        });
    });

    $('#modalOpEnviarProveedor').on('hidden.bs.modal', function () {
        resetModal();
    });
}(jQuery));
