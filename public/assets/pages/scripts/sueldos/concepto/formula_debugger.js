(function ($) {
    'use strict';

    function token() {
        return $('#form-general input[name="_token"]').val()
            || $('meta[name="csrf-token"]').attr('content')
            || '';
    }

    function panel() {
        return $('#formula-debugger-concepto');
    }

    function validar() {
        var url = panel().data('url-validar');
        var $msg = $('#dbg-validar-msg');
        $msg.text('…').removeClass('text-success text-danger');
        $.post(url, {
            _token: token(),
            formula: $('#formula').val() || ''
        }).done(function (resp) {
            $msg.text(resp.mensaje || '')
                .toggleClass('text-success', !!resp.ok)
                .toggleClass('text-danger', !resp.ok);
        }).fail(function () {
            $msg.text('Error al validar').addClass('text-danger');
        });
    }

    function depurar() {
        var $p = panel();
        var url = $p.data('url-depurar');
        var $host = $('#dbg-host');
        if (!url || !window.FormulaDebugger) {
            return;
        }
        $host.html('<div class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Depurando…</div>');
        $.post(url, {
            _token: token(),
            empresa_id: $('#dbg-empresa-id').val() || '',
            legajo: $('#dbg-legajo').val() || '',
            empleado_id: $('#dbg-empleado-id').val() || '',
            periodo: $('#dbg-periodo').val(),
            tipo: $('#dbg-tipo').val(),
            usar_texto_formulario: 1,
            formula: $('#formula').val() || '',
            formula_cantidad: $('#formula_cantidad').val() || '',
            formula_valor: $('#formula_valor').val() || ''
        }).done(function (resp) {
            window.FormulaDebugger.pintarResultado($host, resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo depurar.';
            $host.html('<div class="alert alert-danger py-2 small mb-0"></div>').find('div').text(msg);
        });
    }

    $(document).on('click', '#btn-dbg-validar', validar);
    $(document).on('click', '#btn-dbg-run', depurar);
})(jQuery);
