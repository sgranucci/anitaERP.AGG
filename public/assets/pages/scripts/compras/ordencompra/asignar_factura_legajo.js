(function ($) {
    'use strict';

    var OFFSET = { A: 0, B: 5, C: 10, E: 18, M: 50 };
    var NOMBRE = { 1: 'Factura', 2: 'Nota de débito', 3: 'Nota de crédito' };

    function pad3(n) {
        var s = String(n);
        while (s.length < 3) {
            s = '0' + s;
        }
        return s;
    }

    function codigoArca(tipo, letra) {
        var n = parseInt(String(tipo).replace(/\D/g, ''), 10) || 0;
        if (n <= 0) {
            return 0;
        }
        if (n >= 6) {
            return n;
        }
        return n + (OFFSET[letra] || 0);
    }

    function etiquetaBase(tipo) {
        var n = parseInt(String(tipo).replace(/\D/g, ''), 10) || 0;
        var base = n >= 1 && n <= 3 ? n : (n % 5);
        return NOMBRE[base] || ('Tipo ' + n);
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function actualizarChip() {
        var tipo = $('#oc_factura_tipo').val();
        var letra = $('#oc_factura_letra').val() || 'A';
        var cod = codigoArca(tipo, letra);
        $('.js-oc-arca-chip').text((etiquetaBase(tipo) + ' ' + letra + ' · ARCA ' + pad3(cod)).trim());
    }

    function resetForm() {
        var $form = $('#formAsignarFacturaLegajo');
        $form[0].reset();
        $('#oc_factura_tipo').val('001');
        $('#oc_factura_letra').val('A');
        $('.js-oc-tipo-pills .oc-factura-pill').removeClass('is-active')
            .filter('[data-tipo="001"]').addClass('is-active');
        $('.js-oc-letra-pills .oc-factura-pill').removeClass('is-active')
            .filter('[data-letra="A"]').addClass('is-active');
        $('.js-oc-factura-nombre').text('Sin archivo');
        $('.js-oc-factura-drop').removeClass('has-file is-over');
        $('.js-oc-factura-alert').addClass('d-none').removeClass('oc-factura-alert-ok oc-factura-alert-err').empty();
        actualizarChip();
    }

    function mostrarAlerta(ok, texto) {
        var $a = $('.js-oc-factura-alert');
        $a.removeClass('d-none oc-factura-alert-ok oc-factura-alert-err')
            .addClass(ok ? 'oc-factura-alert-ok' : 'oc-factura-alert-err')
            .text(texto);
    }

    function abrir(opts) {
        resetForm();
        $('#formAsignarFacturaLegajo').attr('action', opts.url || '');
        $('.js-oc-factura-numero').text(opts.numero || '—');
        var prov = opts.proveedor ? ' · ' + opts.proveedor : '';
        $('.js-oc-factura-proveedor').text(prov);
        $('#modalAsignarFacturaLegajo').modal('show');
    }

    $(function () {
        $(document).on('click', '.js-oc-asignar-factura', function (e) {
            e.preventDefault();
            abrir({
                url: $(this).data('url'),
                numero: $(this).data('numero'),
                proveedor: $(this).data('proveedor')
            });
        });

        $(document).on('click', '.js-oc-tipo-pills .oc-factura-pill', function () {
            $('.js-oc-tipo-pills .oc-factura-pill').removeClass('is-active');
            $(this).addClass('is-active');
            $('#oc_factura_tipo').val($(this).data('tipo'));
            actualizarChip();
        });

        $(document).on('click', '.js-oc-letra-pills .oc-factura-pill', function () {
            $('.js-oc-letra-pills .oc-factura-pill').removeClass('is-active');
            $(this).addClass('is-active');
            $('#oc_factura_letra').val($(this).data('letra'));
            actualizarChip();
        });

        $(document).on('input', '#oc_factura_tipo', actualizarChip);

        var $drop = $('.js-oc-factura-drop');
        $drop.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('is-over');
        });
        $drop.on('dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('is-over');
        });
        $drop.on('drop', function (e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files && files[0]) {
                $('#oc_factura_pdf')[0].files = files;
                $('#oc_factura_pdf').trigger('change');
            }
        });
        $(document).on('change', '#oc_factura_pdf', function () {
            var f = this.files && this.files[0];
            $('.js-oc-factura-nombre').text(f ? f.name : 'Sin archivo');
            $('.js-oc-factura-drop').toggleClass('has-file', !!f);
        });

        $(document).on('submit', '#formAsignarFacturaLegajo', function (e) {
            e.preventDefault();
            var $form = $(this);
            var url = $form.attr('action');
            if (!url) {
                mostrarAlerta(false, 'No hay orden de compra seleccionada.');
                return;
            }
            var $btn = $form.find('.js-oc-factura-submit');
            $btn.prop('disabled', true);
            var fd = new FormData(this);
            $.ajax({
                url: url,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).done(function (res) {
                mostrarAlerta(true, (res && res.mensaje) ? res.mensaje : 'Factura asignada.');
                setTimeout(function () {
                    $('#modalAsignarFacturaLegajo').modal('hide');
                    window.location.reload();
                }, 700);
            }).fail(function (xhr) {
                var msg = 'No se pudo asignar la factura.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errores) {
                        msg = [].concat(xhr.responseJSON.errores).join(' ');
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        var parts = [];
                        $.each(xhr.responseJSON.errors, function (_, v) {
                            parts = parts.concat(v);
                        });
                        msg = parts.join(' ');
                    }
                }
                mostrarAlerta(false, msg);
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        actualizarChip();
        window.OcAsignarFacturaLegajo = { abrir: abrir };
    });
})(jQuery);
