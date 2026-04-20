var Biblioteca = function () {
    return {
        validacionGeneral: function (id, reglas, mensajes) {
            const formulario = $('#' + id);
            formulario.validate({
                rules: reglas,
                messages: mensajes,
                errorElement: 'span', //default input error message container
                errorClass: 'help-block help-block-error', // default input error message class
                focusInvalid: false, // do not focus the last invalid input
                ignore: "", // validate all fields including form hidden input
                highlight: function (element, errorClass, validClass) { // hightlight error inputs
                    $(element).closest('.form-group').addClass('has-error'); // set error class to the control group
                },
                unhighlight: function (element) { // revert the change done by hightlight
                    $(element).closest('.form-group').removeClass('has-error'); // set error class to the control group
                },
                success: function (label) {
                    label.closest('.form-group').removeClass('has-error'); // set success class to the control group
                },
                errorPlacement: function (error, element) {
                    if ($(element).is('select') && element.hasClass('bs-select')) {//PARA LOS SELECT BOOSTRAP
                        error.insertAfter(element);//element.next().after(error);
                    } else if ($(element).is('select') && element.hasClass('select2-hidden-accessible')) {
                        element.next().after(error);
                    } else if (element.attr("data-error-container")) {
                        error.appendTo(element.attr("data-error-container"));
                    } else {
                        error.insertAfter(element); // default placement for everything else
                    }
                },
                invalidHandler: function (event, validator) { //display error alert on form submit
                    
                },
                submitHandler: function (form) {
                    return true;
                }
            });
        },
        notificaciones: function (mensaje, titulo, tipo) {
            toastr.options = {
                closeButton: true,
                newestOnTop: true,
                positionClass: 'toast-top-right',
                preventDuplicates: true,
                timeOut: '5000'
            };
            if (tipo == 'error') {
                toastr.error(mensaje, titulo);
            } else if (tipo == 'success') {
                toastr.success(mensaje, titulo);
            } else if (tipo == 'info') {
                toastr.info(mensaje, titulo);
            } else if (tipo == 'warning') {
                toastr.warning(mensaje, titulo);
            }
        },
    }
}();

function fNumero(Pvalor, Pdecimal)
{
	var pre = (new Intl.NumberFormat('en-US', {minimumFractionDigits: Pdecimal}).format(parseFloat(Pvalor).toFixed(Pdecimal)));

	return pre;
}

function calculaCoeficienteMoneda(aMoneda, deMoneda, Cotizacion)
{
    if (aMoneda == deMoneda)
        return 1.;

    if (aMoneda == 1)
        return Cotizacion;

    if (aMoneda > 1 && deMoneda == 1)
        return 1/Cotizacion;

    return 1.;
}

// Recibe fecha YYYY-MM-DD y saca fecha DD-MM-YYYY

function formateaFecha(fecha)
{
    let anio = fecha.substring(0, 4);
    let mes = fecha.substring(5, 7);
    let dia = fecha.substring(8, 10);

    let fechaFormateada = dia + "-" + mes + "-" + anio;

    return fechaFormateada;
}

function redondearDecimales(numero, decimales) {
    numeroRegexp = new RegExp('\\d\\.(\\d){' + decimales + ',}');   // Expresion regular para numeros con un cierto numero de decimales o mas
    if (numeroRegexp.test(numero)) {         // Ya que el numero tiene el numero de decimales requeridos o mas, se realiza el redondeo
        return Number(numero.toFixed(decimales));
    } else {
        return Number(numero.toFixed(decimales)) === 0 ? 0 : numero;  // En valores muy bajos, se comprueba si el numero es 0 (con el redondeo deseado), si no lo es se devuelve el numero otra vez.
    }
}

$('#form-general').on('submit', function() {
    let primerCampoInvalido = null;
    
    // 1. Obtener todos los campos requeridos
    const camposRequeridos = this.querySelectorAll('[required]');

    camposRequeridos.forEach(campo => {
        // 2. Verificar si el campo está vacío
        if (!campo.value.trim()) {
            // Marcar campo inválido (opcional, ej: borde rojo)
            campo.style.borderColor = 'red';
            
            if (!primerCampoInvalido) {
                primerCampoInvalido = campo;
            }
        } else {
            // Limpiar si ya se rellenó
            campo.style.borderColor = '';
        }
    });

    // 3. Si hay campos inválidos, detener envío y mostrar la pestaña
    if (primerCampoInvalido) {
        event.preventDefault(); // Detiene el formulario
        // Encontrar la solapa padre
        const solapaContenedora = primerCampoInvalido.closest('.tab-content');
        if (solapaContenedora) {
            // Activar la solapa (aquí llamas a tu función para cambiar de pestaña)
            activarSolapa(solapaContenedora.id);
            alert('Por favor, rellene todos los campos obligatorios.');
        }
        primerCampoInvalido.focus();
    }
});

// Función para cambiar la visualización de las solapas
function activarSolapa(tabId) {
    // Ocultar todo
    document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
    // Mostrar la solapa con el error
    document.getElementById(tabId).style.display = 'block';
}

function formatarCUIT(input) {
    // 1. Eliminar todo lo que no sea número
    let value = input.value.replace(/\D/g, '');
    
    // 2. Limitar a 11 dígitos
    value = value.substring(0, 11);
    
    // 3. Aplicar guiones (XX-XXXXXXXX-X)
    if (value.length > 2 && value.length <= 10) {
        value = value.substring(0, 2) + '-' + value.substring(2);
    } else if (value.length > 10) {
        value = value.substring(0, 2) + '-' + value.substring(2, 10) + '-' + value.substring(10);
    }
    
    // 4. Actualizar el valor del input
    input.value = value;
}