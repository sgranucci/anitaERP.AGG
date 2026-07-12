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
                invalidHandler: function (event, validator) {
                    if (!validator.errorList.length) {
                        return;
                    }
                    var primer = validator.errorList[0].element;
                    mostrarSolapaDelPrimerCampoInvalido(primer);
                    notificarCamposObligatoriosPendientes(primer, validator.numberOfInvalids());
                    enfocarCampoInvalido(primer);
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

/** Secciones de solapas usadas en CRUD multipágina (clientes, requisiciones, UIF, etc.). */
var SECCIONES_SOLAPA_FORM = '.form1,.form2,.form3,.form4,.form5,.form6,.form7,.form8,.form9';

/** Contenedores ocultos por regla de negocio: no validar sus required mientras estén ocultos. */
var CONTENEDORES_REQUERIDO_CONDICIONAL = '#div-actividadso,#div-cumplenormativaso';

function valorCampoObligatorio(campo) {
    if (campo.type === 'checkbox' || campo.type === 'radio') {
        const grupo = document.querySelectorAll(
            'input[name="' + String(campo.name).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]:not(:disabled)'
        );
        return Array.from(grupo).some(function (el) {
            return el.checked;
        });
    }
    return (campo.value ?? '').toString().trim() !== '';
}

function campoObligatorioDebeValidarse(campo) {
    if (campo.closest && campo.closest('.modal')) {
        return false;
    }
    if (campo.disabled || $(campo).is(':disabled')) {
        return false;
    }
    var $wrap = $(campo).closest(CONTENEDORES_REQUERIDO_CONDICIONAL);
    if ($wrap.length && !$wrap.is(':visible')) {
        return false;
    }
    return true;
}

function camposObligatoriosEnFormulario(form) {
    var set = new Set();
    form.querySelectorAll('[required]').forEach(function (el) {
        set.add(el);
    });
    form.querySelectorAll('select.required, input.required, textarea.required').forEach(function (el) {
        set.add(el);
    });
    return Array.from(set);
}

function numeroSolapaDesdeElemento(el) {
    var $sec = $(el).closest(SECCIONES_SOLAPA_FORM);
    if (!$sec.length) {
        return null;
    }
    var cls = ($sec.attr('class') || '').split(/\s+/).filter(function (c) {
        return /^form\d+$/.test(c);
    })[0];
    return cls ? cls.replace('form', '') : null;
}

function tituloSolapaFormulario(numeroSolapa) {
    var $btn = $('#botonform' + numeroSolapa);
    if ($btn.length) {
        return $btn.text().replace(/\s+/g, ' ').trim();
    }
    var $pane = $('.form' + numeroSolapa).first();
    if ($pane.length) {
        var paneId = $pane.attr('id');
        if (paneId) {
            var $link = $('a[data-toggle="tab"][href="#' + paneId + '"], a[data-bs-toggle="tab"][href="#' + paneId + '"]');
            if ($link.length) {
                return $link.text().replace(/\s+/g, ' ').trim();
            }
        }
    }
    return 'sección ' + numeroSolapa;
}

function activarSolapaBootstrapDesdePane($pane) {
    if (!$pane || !$pane.length) {
        return false;
    }
    var paneId = $pane.attr('id');
    if (!paneId) {
        return false;
    }
    var $link = $('a[data-toggle="tab"][href="#' + paneId + '"], a[data-bs-toggle="tab"][href="#' + paneId + '"]');
    if (!$link.length) {
        return false;
    }
    if (typeof $link.tab === 'function') {
        $link.tab('show');
        return true;
    }
    $link.trigger('click');
    return true;
}

function activarSolapaFormulario(numeroSolapa) {
    var $btn = $('#botonform' + numeroSolapa);
    if ($btn.length) {
        $btn.trigger('click');
        return;
    }
    var $pane = $('.form' + numeroSolapa).first();
    if (activarSolapaBootstrapDesdePane($pane)) {
        return;
    }
    $(SECCIONES_SOLAPA_FORM).hide();
    $('.form' + numeroSolapa).show();
}

function marcarCampoObligatorio(campo, invalido) {
    campo.style.borderColor = invalido ? '#dc3545' : '';
    $(campo).closest('.form-group').toggleClass('has-error', invalido);
}

function validarCamposObligatoriosFormulario(form) {
    var primerInvalido = null;
    var cantidadInvalidos = 0;

    camposObligatoriosEnFormulario(form).forEach(function (campo) {
        if (!campoObligatorioDebeValidarse(campo)) {
            marcarCampoObligatorio(campo, false);
            return;
        }
        var vacio = !valorCampoObligatorio(campo);
        marcarCampoObligatorio(campo, vacio);
        if (vacio) {
            cantidadInvalidos++;
            if (!primerInvalido) {
                primerInvalido = campo;
            }
        }
    });

    return {
        valido: cantidadInvalidos === 0,
        primerInvalido: primerInvalido,
        cantidadInvalidos: cantidadInvalidos,
    };
}

function mostrarSolapaDelPrimerCampoInvalido(campo) {
    if (!campo) {
        return;
    }
    var $pane = $(campo).closest('.tab-pane');
    if ($pane.length && activarSolapaBootstrapDesdePane($pane)) {
        return;
    }
    var numeroSolapa = numeroSolapaDesdeElemento(campo);
    if (numeroSolapa) {
        activarSolapaFormulario(numeroSolapa);
    }
}

function etiquetaCampoObligatorio(campo) {
    if (!campo) {
        return '';
    }
    var id = campo.id || '';
    if (id) {
        var $label = $('label[for="' + String(id).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
        if ($label.length) {
            return $label.text().replace(/\*/g, '').replace(/\s+/g, ' ').trim();
        }
    }
    var $grp = $(campo).closest('.form-group');
    if ($grp.length) {
        var $lbl = $grp.find('label.control-label, label.col-form-label, label.requerido').first();
        if ($lbl.length) {
            return $lbl.text().replace(/\*/g, '').replace(/\s+/g, ' ').trim();
        }
    }
    return (campo.name || '').replace(/\[\]/g, '');
}

function notificarCamposObligatoriosPendientes(primerInvalido, cantidad) {
    var mensaje = 'Complete los campos obligatorios';
    if (cantidad > 1) {
        mensaje += ' (' + cantidad + ' pendientes)';
    }
    var numeroSolapa = numeroSolapaDesdeElemento(primerInvalido);
    if (numeroSolapa) {
        mensaje += ' en: ' + tituloSolapaFormulario(numeroSolapa);
    }
    var etiquetaCampo = etiquetaCampoObligatorio(primerInvalido);
    if (etiquetaCampo) {
        mensaje += '. Falta: ' + etiquetaCampo;
    }
    mensaje += '.';

    if (typeof Biblioteca !== 'undefined' && typeof Biblioteca.notificaciones === 'function') {
        Biblioteca.notificaciones(mensaje, 'Formulario incompleto', 'warning');
    } else {
        alert(mensaje);
    }
}

function enfocarCampoInvalido(campo) {
    if (!campo) {
        return;
    }
    setTimeout(function () {
        try {
            campo.focus({ preventScroll: true });
        } catch (e) {
            campo.focus();
        }
        if (typeof campo.scrollIntoView === 'function') {
            campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 200);
}

$('#form-general').on('submit', function (event) {
    var resultado = validarCamposObligatoriosFormulario(this);
    if (resultado.valido) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    mostrarSolapaDelPrimerCampoInvalido(resultado.primerInvalido);
    notificarCamposObligatoriosPendientes(resultado.primerInvalido, resultado.cantidadInvalidos);
    enfocarCampoInvalido(resultado.primerInvalido);
});

$(document).on('input change', '#form-general select, #form-general input, #form-general textarea', function () {
    if (!this.hasAttribute('required') && !$(this).hasClass('required')) {
        return;
    }
    if (valorCampoObligatorio(this)) {
        marcarCampoObligatorio(this, false);
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