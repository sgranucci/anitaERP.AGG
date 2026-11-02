(function ($) {
    'use strict';

    var ns = '.ppGenerarOp';
    var campoActivo = null;
    var modalAbriendo = false;

    function resolverCarpetaBase() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function modalAbierto() {
        var modal = document.getElementById('consultacuentacajaModal');
        return modalAbriendo || (modal && modal.classList.contains('show'));
    }

    function asignarCuentacaja(data) {
        if (!campoActivo || !data || !data.id) {
            return;
        }
        var hidden = campoActivo.querySelector('.cuentacaja_id');
        var codigo = campoActivo.querySelector('.codigocuentacaja');
        var nombre = campoActivo.querySelector('.descripcioncuentacaja');
        if (hidden) {
            hidden.value = data.id;
        }
        if (codigo) {
            codigo.value = data.codigo || '';
            codigo.removeAttribute('data-cuentacaja-invalido');
        }
        if (nombre) {
            nombre.value = data.nombre || '';
        }
    }

    function limpiar() {
        if (!campoActivo) {
            return;
        }
        var hidden = campoActivo.querySelector('.cuentacaja_id');
        var codigo = campoActivo.querySelector('.codigocuentacaja');
        var nombre = campoActivo.querySelector('.descripcioncuentacaja');
        if (hidden) {
            hidden.value = '';
        }
        if (codigo) {
            codigo.value = '';
        }
        if (nombre) {
            nombre.value = '';
        }
    }

    function abrirModal() {
        modalAbriendo = true;
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').one('shown.bs.modal' + ns, function () {
            modalAbriendo = false;
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function resolverPorCodigo(alertar, input) {
        if (modalAbierto()) {
            return;
        }
        campoActivo = input.closest('.tm-cuentacaja-campo');
        var codigo = String($(input).val() || '').trim();
        if (!codigo) {
            limpiar();
            return;
        }
        if ($(input).attr('data-cuentacaja-invalido') === codigo) {
            return;
        }
        var empresaId = $('#empresa_id').val() || '';
        $.ajax({
            url: resolverCarpetaBase() + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo),
            type: 'GET',
            dataType: 'json',
            data: { empresa_id: empresaId }
        }).done(function (data) {
            if (data && parseInt(data.id, 10) > 0) {
                asignarCuentacaja(data);
                return;
            }
            $(input).attr('data-cuentacaja-invalido', codigo);
            if (alertar) {
                alert('No se encontró la cuenta de caja.');
            }
        }).fail(function () {
            $(input).attr('data-cuentacaja-invalido', codigo);
            if (alertar) {
                alert('No se encontró la cuenta de caja.');
            }
        });
    }

    $(document).on('click' + ns, '#form-generar-op-programa .consultacuentacaja', function (e) {
        e.preventDefault();
        campoActivo = this.closest('.tm-cuentacaja-campo');
        abrirModal();
    });

    $(document).on('keydown' + ns, '#form-generar-op-programa .codigocuentacaja', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            campoActivo = this.closest('.tm-cuentacaja-campo');
            abrirModal();
            return;
        }
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            e.stopPropagation();
            resolverPorCodigo(true, this);
        }
    });

    $(document).on('blur' + ns, '#form-generar-op-programa .codigocuentacaja', function () {
        resolverPorCodigo(false, this);
    });

    $(document).on('click' + ns, '.eligeconsultacuentacaja', function (e) {
        if (!campoActivo) {
            return;
        }
        e.stopImmediatePropagation();
        var tr = $(this).closest('tr');
        var id = parseInt(String(tr.find('.cuentacaja_id').text() || '').trim(), 10);
        if (!(id > 0)) {
            return;
        }
        asignarCuentacaja({
            id: id,
            codigo: tr.find('.codigo').text().trim(),
            nombre: tr.find('.nombre').text().trim()
        });
        $('#consultacuentacajaModal').modal('hide');
        campoActivo = null;
    });

    $('#form-generar-op-programa').on('submit', function (e) {
        var form = this;
        if (!form.checkValidity()) {
            return;
        }
        var n = $(form).find('input[name$="[incluir]"]:checked').length;
        if (n < 1) {
            e.preventDefault();
            alert('No quedó ninguna orden tildada.');
            return;
        }
        var cuenta = document.getElementById('ppgen_cuentacaja_id');
        if (cuenta && !(parseInt(cuenta.value || '0', 10) > 0)) {
            e.preventDefault();
            alert('Elegí la cuenta de la transferencia.');
            return;
        }
        if (!window.confirm('Se van a generar ' + n + ' orden(es) de pago confirmadas, con asiento y réplica a Anita.')) {
            e.preventDefault();
            return;
        }
        var overlay = document.getElementById('pp-generar-op-overlay');
        if (overlay) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }
    });
})(jQuery);
