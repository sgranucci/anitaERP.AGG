$(function () {
    const modoCuentaCorriente = 'cuenta_corriente';
    const modoDeuda = 'deuda';
    const $form = $('#form-filtros-cuentacorriente-proveedor');
    const $modoInput = $('#modo_vista');
    const $switch = $('#switch-modo-vista');
    const $label = $('#label-modo-vista');

    function actualizarEtiquetaModo() {
        if ($switch.is(':checked')) {
            $label.text('Deuda (facturas impagas)');
            $modoInput.val(modoDeuda);
        } else {
            $label.text('Cuenta corriente (Debe / Haber)');
            $modoInput.val(modoCuentaCorriente);
        }
    }

    actualizarEtiquetaModo();

    $switch.on('change', function () {
        actualizarEtiquetaModo();
        $form.trigger('submit');
    });
});
