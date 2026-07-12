(function () {
    'use strict';

    var cfg = window.VIANDA_DIA || {};
    var csrf = cfg.csrf || '';

    function notify(tipo, msg) {
        if (window.toastr && typeof window.toastr[tipo] === 'function') {
            window.toastr[tipo](msg);
        } else if (tipo === 'error') {
            alert(msg);
        }
    }

    function post(url, data) {
        var opts = {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        };
        if (data !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        return fetch(url, opts).then(function (r) {
            return r.json().then(function (j) {
                return { status: r.status, body: j };
            }).catch(function () {
                return { status: r.status, body: {} };
            });
        });
    }

    // ---------- Reimprimir ----------
    function reimprimir(btn) {
        var $btn = $(btn);
        if ($btn.prop('disabled')) { return; }
        var url = $btn.data('url');
        if (!url) { return; }
        var codigo = $btn.data('codigo') || '';
        var iconoHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        post(url).then(function (res) {
            $btn.prop('disabled', false).html(iconoHtml);
            if (res.status === 200 && res.body.ok) {
                notify('success', 'Voucher ' + codigo + ' enviado a la impresora.');
            } else {
                notify('error', (res.body && res.body.error) || 'No se pudo reimprimir el voucher.');
            }
        }).catch(function () {
            $btn.prop('disabled', false).html(iconoHtml);
            notify('error', 'Error de red al reimprimir el voucher.');
        });
    }

    // ---------- Borrar ----------
    var borrarState = { url: null, redirect: null };

    function abrirBorrar(btn) {
        var $btn = $(btn);
        borrarState.url = $btn.data('url') || null;
        borrarState.redirect = $btn.data('redirect') || null;
        $('#vianda-borrar-codigo').text($btn.data('codigo') || '—');
        $('#vianda-borrar-motivo').val('');
        $('#vianda-borrar-error').addClass('d-none').text('');
        $('#vianda-borrar-confirmar').prop('disabled', false).html('<i class="fa fa-trash"></i> Borrar vianda');
        $('#modal-vianda-borrar').modal('show');
    }

    function confirmarBorrar() {
        if (!borrarState.url) { return; }
        var $btn = $('#vianda-borrar-confirmar');
        var motivo = $('#vianda-borrar-motivo').val() || '';
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Borrando…');
        $('#vianda-borrar-error').addClass('d-none').text('');
        post(borrarState.url, { motivo: motivo }).then(function (res) {
            if (res.status === 200 && res.body.ok) {
                notify('success', res.body.mensaje || 'Vianda borrada.');
                $('#modal-vianda-borrar').modal('hide');
                setTimeout(function () {
                    if (borrarState.redirect) {
                        window.location.href = borrarState.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 500);
            } else {
                $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Borrar vianda');
                $('#vianda-borrar-error').removeClass('d-none').text((res.body && res.body.error) || 'No se pudo borrar la vianda.');
            }
        }).catch(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Borrar vianda');
            $('#vianda-borrar-error').removeClass('d-none').text('Error de red al borrar la vianda.');
        });
    }

    $(function () {
        $(document).on('click', '.js-vianda-reimprimir', function () { reimprimir(this); });
        $(document).on('click', '.js-vianda-borrar', function () { abrirBorrar(this); });
        $('#vianda-borrar-confirmar').on('click', confirmarBorrar);
    });
})();
