$(function () {
    $('.reporte-empresas-checkboxes').each(function () {
        initReporteEmpresasCheckboxes($(this));
    });
});

function initReporteEmpresasCheckboxes($contenedor) {
    if (!$contenedor.length) {
        return;
    }

    var prefix = String($contenedor.data('id-prefix') || 'reporte');
    var empresaUnica = $contenedor.data('empresa-unica') === 1 || $contenedor.data('empresa-unica') === '1';

    var cfg = {
        lista: '#' + prefix + '_empresas_asignadas_hidden',
        validacion: '#' + prefix + '_empresa_ids_validacion',
        contador: '#' + prefix + '_empresas_contador .reporte-empresas-checkboxes-contador-n',
        inputSelector: 'input.reporte-empresa-check-input[name="empresa_ids[]"]',
    };

    function checks() {
        return $(cfg.lista).find(cfg.inputSelector);
    }

    function sincronizarEstado() {
        var $checks = checks();
        var seleccionados = $checks.filter(':checked').length;

        $checks.each(function () {
            $(this).closest('.reporte-empresa-check-item').toggleClass('is-checked', this.checked);
        });

        if (cfg.validacion) {
            $(cfg.validacion).val(seleccionados ? '1' : '');
        }

        var $contador = $(cfg.contador);
        if ($contador.length) {
            $contador.text(seleccionados);
        }

        $contenedor.trigger('reporte-empresas-cambiadas');
    }

    function marcarTodas(marcar) {
        checks().prop('checked', !!marcar);
        sincronizarEstado();
    }

    if (!empresaUnica) {
        $contenedor.on('change', cfg.inputSelector, function () {
            sincronizarEstado();
        });

        $contenedor.on('click', '.btn-reporte-empresas-todas', function () {
            marcarTodas(true);
        });

        $contenedor.on('click', '.btn-reporte-empresas-ninguna', function () {
            marcarTodas(false);
        });

        sincronizarEstado();
    }

    $contenedor.on('click', '.btn-toggle-consolidar-empresas', function () {
        var $btn = $(this);
        var $input = $($btn.data('input'));
        if (!$input.length) {
            return;
        }
        var activo = String($input.val()) !== '1';
        $input.val(activo ? '1' : '0');
        $btn.toggleClass('btn-success', activo);
        $btn.toggleClass('btn-outline-secondary', !activo);
    });
}
