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
        $('#op-envio-historial').addClass('d-none');
        $('#op-envio-historial-body').empty();
        $('#op-envio-archivos-actuales').addClass('d-none').text('');
        resetFilasArchivo();
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

    function marcarMailEnviadoEnGrilla(pagoproveedorId) {
        if (!pagoproveedorId) {
            return;
        }
        var $celda = $('.js-op-mail-celda[data-pagoproveedor-id="' + pagoproveedorId + '"]');
        if (!$celda.length) {
            return;
        }
        $celda.html('<i class="fa fa-envelope js-op-mail-icono" title="Enviado por correo" style="color:#1e8449;font-size:12px;opacity:.8"></i>');
    }

    function resetFilasArchivo() {
        var $tbody = $('#op-envio-tbody-archivos');
        $tbody.find('tr.op-envio-archivo-fila').not(':first').remove();
        $tbody.find('input[type=file]').val('');
        $('#op-envio-agrega-archivo').prop('disabled', false);
    }

    function cantidadFilasArchivo() {
        return $('#op-envio-tbody-archivos tr.op-envio-archivo-fila').length;
    }

    function archivosSeleccionados() {
        var files = [];
        $('#op-envio-tbody-archivos .op-envio-archivo').each(function () {
            if (this.files && this.files.length && this.files[0]) {
                files.push(this.files[0]);
            }
        });
        return files;
    }

    function textoCelda(valor) {
        return document.createTextNode(valor == null ? '' : String(valor));
    }

    function pintarHistorialEnvios(envios) {
        var $body = $('#op-envio-historial-body');
        $body.empty();
        if (!envios || !envios.length) {
            $('#op-envio-historial').addClass('d-none');
            return;
        }
        envios.forEach(function (envio) {
            var tr = document.createElement('tr');
            ['fecha', 'usuario', 'destinatarios'].forEach(function (campo) {
                var td = document.createElement('td');
                td.appendChild(textoCelda(envio[campo]));
                tr.appendChild(td);
            });
            var tdMensaje = document.createElement('td');
            tdMensaje.style.whiteSpace = 'pre-wrap';
            if (!envio.texto_guardado) {
                var nota = document.createElement('span');
                nota.className = 'text-muted';
                nota.textContent = 'Envío anterior: el destinatario quedó en la historia y el texto adicional no.';
                tdMensaje.appendChild(nota);
            } else if (!$.trim(envio.mensaje || '')) {
                var vacio = document.createElement('span');
                vacio.className = 'text-muted';
                vacio.textContent = 'Sin texto adicional';
                tdMensaje.appendChild(vacio);
            } else {
                tdMensaje.appendChild(textoCelda(envio.mensaje));
            }
            tr.appendChild(tdMensaje);
            $body.append(tr);
        });
        $('#op-envio-historial').removeClass('d-none');
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
            pintarHistorialEnvios(data.envios || []);
            $('#op_envio_proveedor_email').val(data.email || '');
            if (data.mensaje) {
                $('#op-envio-proveedor-aviso').removeClass('d-none').text(data.mensaje);
            }
            if (data.advertencia_estado) {
                $('#op-envio-proveedor-advertencia').removeClass('d-none').text(data.advertencia_estado);
            }
            var yaAsociados = parseInt(data.archivos_asociados, 10) || 0;
            if (yaAsociados > 0) {
                var textoAsoc = yaAsociados === 1
                    ? 'Esta OP ya tiene 1 archivo asociado. Los que adjunte ahora se suman y también se envían en este correo.'
                    : 'Esta OP ya tiene ' + yaAsociados + ' archivos asociados. Los que adjunte ahora se suman y también se envían en este correo.';
                $('#op-envio-archivos-actuales').removeClass('d-none').text(textoAsoc);
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
        var adjuntos = archivosSeleccionados();
        var avisoAdj = adjuntos.length
            ? '\nArchivos adjuntos: ' + adjuntos.length
            : '';
        if (!window.confirm('¿Confirma el envío de la orden de pago por correo?\n\nDestino: ' + email + avisoAdj)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        var url = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') +
            '/compras/pagoproveedor/' + opIdActual + '/enviar-proveedor';
        var token = $('meta[name="csrf-token"]').attr('content') ||
            $('input[name="_token"]').first().val();
        var fd = new FormData();
        fd.append('_token', token);
        fd.append('email', email);
        fd.append('mensaje', $('#op_envio_proveedor_mensaje').val() || '');
        adjuntos.forEach(function (file) {
            fd.append('nombrearchivos[]', file);
        });

        $.ajax({
            url: url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (data) {
            if (data && data.mensaje === 'ok') {
                marcarMailEnviadoEnGrilla(opIdActual);
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
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                var msgs = [];
                Object.keys(xhr.responseJSON.errors).forEach(function (k) {
                    (xhr.responseJSON.errors[k] || []).forEach(function (m) {
                        msgs.push(m);
                    });
                });
                if (msgs.length) {
                    msg = msgs.join(' ');
                }
            }
            alert(msg);
            $btn.prop('disabled', false);
        });
    });

    $('#op-envio-agrega-archivo').on('click', function (e) {
        e.preventDefault();
        if (cantidadFilasArchivo() >= 10) {
            return;
        }
        var tpl = document.getElementById('op-envio-template-archivo');
        var tbody = document.getElementById('op-envio-tbody-archivos');
        if (!tpl || !tbody || !tpl.content) {
            return;
        }
        tbody.appendChild(document.importNode(tpl.content, true));
        if (cantidadFilasArchivo() >= 10) {
            $('#op-envio-agrega-archivo').prop('disabled', true);
        }
    });

    $(document).on('click', '.op-envio-quitar-archivo', function (e) {
        e.preventDefault();
        var $tbody = $('#op-envio-tbody-archivos');
        var $fila = $(this).closest('tr.op-envio-archivo-fila');
        if ($tbody.find('tr.op-envio-archivo-fila').length <= 1) {
            $fila.find('input[type=file]').val('');
            return;
        }
        $fila.remove();
        $('#op-envio-agrega-archivo').prop('disabled', false);
    });

    $('#modalOpEnviarProveedor').on('hidden.bs.modal', function () {
        resetModal();
    });
}(jQuery));
