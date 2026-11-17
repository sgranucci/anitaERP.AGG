/**
 * Grilla + modal estándar de cuentas contables para
 * "cuentas como debajo del límite" (mayor por concepto / ERP).
 */
(function (window, $) {
    'use strict';

    if (!$ || !window.jQuery) {
        return;
    }

    var PREFIX = 'mco';
    var campoActivo = null;
    var abriendoModal = false;
    var caretAlFinal = false;
    var buscarTimer = null;
    var cuentasSel = {};

    function sel(id) {
        return $('#' + PREFIX + '-' + id);
    }

    function normalizarCodigo(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function formatearCodigo(codigo) {
        var digits = normalizarCodigo(codigo);
        if (digits.length < 9) {
            digits = digits.padStart(9, '0');
        }
        if (digits.length !== 9) {
            return String(codigo || '').trim();
        }

        return digits.substring(0, 6) + '-' + digits.substring(6, 9);
    }

    function textoConsultaModal(valor) {
        var trimmed = String(valor || '').trim();
        if (trimmed !== '' && /^[\d\-\s.]+$/.test(trimmed) && /\d/.test(trimmed)) {
            return normalizarCodigo(trimmed);
        }

        return trimmed;
    }

    function empresaIdConsulta() {
        var $checks = $('input[name="empresa_ids[]"]');
        if ($checks.length) {
            var $marcados = $checks.filter(':checked');
            var $fuente = $marcados.length ? $marcados : $checks.filter('[type="hidden"]');
            if ($fuente.length) {
                return parseInt($fuente.first().val(), 10) || 0;
            }
        }

        return 0;
    }

    function limpiarCampo($campo) {
        $campo.find('.codigocuentacontable').val('');
        $campo.find('.nombrecuentacontable').val('');
    }

    function actualizarBadge() {
        var n = Object.keys(cuentasSel).length;
        var $badge = sel('badge-cuentas-debajo-limite');
        if (!$badge.length) {
            return;
        }
        if (n > 0) {
            $badge.text(String(n)).show();
        } else {
            $badge.hide();
        }
    }

    function sincronizarHidden() {
        var codigos = Object.keys(cuentasSel)
            .map(function (c) {
                return parseInt(c, 10);
            })
            .filter(function (c) {
                return c > 0;
            })
            .sort(function (a, b) {
                return a - b;
            });

        sel('cuentas-como-debajo-limite').val(codigos.join('\n'));
        sel('aviso-sin-cuentas-debajo').toggle(codigos.length === 0);
        actualizarBadge();
    }

    function renderTabla() {
        var $tbody = sel('tbody-cuentas-debajo-limite');
        if (!$tbody.length) {
            return;
        }

        var codigos = Object.keys(cuentasSel)
            .map(function (c) {
                return parseInt(c, 10);
            })
            .filter(function (c) {
                return c > 0;
            })
            .sort(function (a, b) {
                return a - b;
            });

        var html = '';
        codigos.forEach(function (codigo) {
            var item = cuentasSel[String(codigo)] || {};
            var codigoFmt = item.codigo_fmt || formatearCodigo(codigo);
            var nombre = item.nombre || '';
            html +=
                '<tr data-codigo="' +
                codigo +
                '">' +
                '<td>' +
                $('<div>').text(codigoFmt).html() +
                '</td>' +
                '<td>' +
                $('<div>').text(nombre).html() +
                '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-outline-danger btn-xs mco-btn-quitar-cuenta-debajo" title="Quitar">' +
                '<i class="fa fa-times"></i>' +
                '</button>' +
                '</td>' +
                '</tr>';
        });

        $tbody.html(html);
        sincronizarHidden();
    }

    function agregarCuenta(data) {
        var codigoRaw = normalizarCodigo(data.codigo || data.codigo_fmt || '');
        var codigo = parseInt(codigoRaw, 10) || 0;
        if (!codigo) {
            return false;
        }

        var key = String(codigo);
        if (cuentasSel[key]) {
            return false;
        }

        cuentasSel[key] = {
            codigo: codigo,
            codigo_fmt: formatearCodigo(data.codigo || codigo),
            nombre: data.nombre || '',
        };
        renderTabla();

        return true;
    }

    function quitarCuenta(codigo) {
        var key = String(parseInt(normalizarCodigo(codigo), 10) || 0);
        if (!key || key === '0' || !cuentasSel[key]) {
            return;
        }
        delete cuentasSel[key];
        renderTabla();
    }

    function cargarDesdeDom() {
        cuentasSel = {};
        sel('tbody-cuentas-debajo-limite')
            .find('tr')
            .each(function () {
                var codigo = parseInt(normalizarCodigo($(this).attr('data-codigo')), 10) || 0;
                if (!codigo) {
                    return;
                }
                cuentasSel[String(codigo)] = {
                    codigo: codigo,
                    codigo_fmt: $(this).find('td').eq(0).text().trim(),
                    nombre: $(this).find('td').eq(1).text().trim(),
                };
            });
        sincronizarHidden();
    }

    function resolverNombre($campo, onOk) {
        var codigo = normalizarCodigo($campo.find('.codigocuentacontable').val());
        var empresaId = empresaIdConsulta();

        if (!codigo) {
            limpiarCampo($campo);
            return;
        }
        if (!empresaId) {
            $campo.find('.nombrecuentacontable').val('');
            return;
        }

        var urlCta =
            carpetaBase +
            '/contable/cuentacontable/leercuentacontableporcodigo/' +
            empresaId +
            '/' +
            codigo;

        $.get(urlCta, function (data) {
            if (data && data.id > 0) {
                $campo.find('.codigocuentacontable').val(formatearCodigo(data.codigo || codigo));
                $campo.find('.nombrecuentacontable').val(data.nombre || '');
                if (typeof onOk === 'function') {
                    onOk({
                        codigo: data.codigo || codigo,
                        nombre: data.nombre || '',
                    });
                }
            } else {
                alert('No existe la cuenta para la empresa seleccionada');
                limpiarCampo($campo);
            }
        });
    }

    function agregarDesdeCampo() {
        var $campo = sel('cuenta-debajo-campo');
        if (!$campo.length) {
            return;
        }

        var codigo = normalizarCodigo($campo.find('.codigocuentacontable').val());
        if (!codigo) {
            alert('Ingrese un código de cuenta');
            return;
        }

        var nombre = $campo.find('.nombrecuentacontable').val().trim();
        if (nombre) {
            if (!agregarCuenta({ codigo: codigo, nombre: nombre })) {
                alert('La cuenta ya está en la lista');
            } else {
                limpiarCampo($campo);
            }
            return;
        }

        resolverNombre($campo, function (data) {
            if (!agregarCuenta(data)) {
                alert('La cuenta ya está en la lista');
            } else {
                limpiarCampo($campo);
            }
        });
    }

    function adoptarPendiente() {
        var $campo = sel('cuenta-debajo-campo');
        if (!$campo.length) {
            return;
        }
        var codigo = normalizarCodigo($campo.find('.codigocuentacontable').val());
        if (!codigo) {
            return;
        }
        agregarCuenta({
            codigo: codigo,
            nombre: String($campo.find('.nombrecuentacontable').val() || '').trim(),
        });
        limpiarCampo($campo);
    }

    function modalAbierto() {
        var $modal = $('#consultacuentaModal');
        return (
            abriendoModal ||
            ($modal.length > 0 &&
                ($modal.hasClass('show') || $modal.hasClass('in') || $modal.is(':visible')))
        );
    }

    function ponerCaretAlFinal(el) {
        if (!el || typeof el.setSelectionRange !== 'function') {
            return;
        }
        var len = String(el.value || '').length;
        try {
            el.setSelectionRange(len, len);
        } catch (e) {}
    }

    function buscarCuentasModal(consulta) {
        var empresaId = empresaIdConsulta();
        if (!empresaId) {
            $('#datoscuentas').html('<tr><td colspan="4">Seleccione al menos una empresa</td></tr>');
            return;
        }

        $('#consultaempresa_id').val(empresaId);

        $.ajax({
            url: carpetaBase + '/contable/cuentacontable/consultacuentacontable',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: {
                consulta: textoConsultaModal(consulta),
                empresa_id: empresaId,
            },
        })
            .done(function (respuesta) {
                var html = '';
                if (respuesta && typeof respuesta === 'object' && respuesta.data !== undefined) {
                    html = respuesta.data;
                } else if (typeof respuesta === 'string') {
                    try {
                        var parsed = JSON.parse(respuesta);
                        html = parsed.data || '';
                    } catch (e) {
                        html = respuesta;
                    }
                }
                $('#datoscuentas').html(html);
            })
            .fail(function () {
                $('#datoscuentas').html('<tr><td colspan="4">Error al consultar cuentas</td></tr>');
            });
    }

    function programarBusqueda(consulta) {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(function () {
            buscarCuentasModal(consulta);
        }, 280);
    }

    function elegirUnicaCuentaModal() {
        var $btns = $('#datoscuentas .eligeconsultacuentacontable');
        if ($btns.length !== 1) {
            return false;
        }
        var el = $btns.get(0);
        if (el && typeof el.click === 'function') {
            el.click();
            return true;
        }
        $btns.trigger('click');
        return true;
    }

    function abrirModal($campo, opciones) {
        var empresaId = empresaIdConsulta();
        if (!empresaId) {
            if (!(opciones && opciones.silencioso)) {
                alert('Debe seleccionar al menos una empresa');
            }
            return false;
        }

        campoActivo = $campo && $campo.length ? $campo : null;
        abriendoModal = true;
        caretAlFinal = !!(opciones && opciones.caretAlFinal);
        $('#consultaempresa_id').val(empresaId);
        $('#consultacuentaModal').modal('show');
        return true;
    }

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function onKeydownF1Capture(e) {
        if (!esTeclaF1(e)) {
            return;
        }
        var target = e.target;
        if (!target || target.disabled) {
            return;
        }
        var $target = $(target);
        var $campo = $target.closest('.mco-cuenta-debajo-campo');
        if (!$campo.length) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirModal($campo);
    }

    function syncVisibilidadErp() {
        var $wrap = sel('panel-cuentas-debajo-limite-wrap');
        if (!$wrap.length) {
            return;
        }
        var $erp = $('#' + PREFIX + '-fuente-erp');
        var esErp = $erp.length === 0 || $erp.is(':checked');
        $wrap.toggle(esErp);
    }

    function activar() {
        if (!sel('panel-cuentas-debajo-limite-wrap').length) {
            return;
        }

        cargarDesdeDom();
        syncVisibilidadErp();

        $('#' + PREFIX + '-fuente-erp, #' + PREFIX + '-fuente-anita')
            .off('change.mcoCuentasDebajo')
            .on('change.mcoCuentasDebajo', syncVisibilidadErp);

        var $panel = sel('panel-cuentas-debajo-limite');
        var $btn = sel('btn-cuentas-debajo-limite');
        $panel
            .off('show.bs.collapse.mcoCuentasDebajo hide.bs.collapse.mcoCuentasDebajo')
            .on('show.bs.collapse.mcoCuentasDebajo', function () {
                $btn.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                $btn.attr('aria-expanded', 'true');
            })
            .on('hide.bs.collapse.mcoCuentasDebajo', function () {
                $btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $btn.attr('aria-expanded', 'false');
            });

        $(document)
            .off('click.mcoCuentasDebajo', '#' + PREFIX + '-btn-agregar-cuenta-debajo')
            .on('click.mcoCuentasDebajo', '#' + PREFIX + '-btn-agregar-cuenta-debajo', function (e) {
                e.preventDefault();
                agregarDesdeCampo();
            });

        $(document)
            .off('click.mcoCuentasDebajo', '.mco-btn-quitar-cuenta-debajo')
            .on('click.mcoCuentasDebajo', '.mco-btn-quitar-cuenta-debajo', function (e) {
                e.preventDefault();
                quitarCuenta($(this).closest('tr').attr('data-codigo'));
            });

        $(document)
            .off('click.mcoCuentasDebajo', '.mco-cuenta-debajo-campo .consultacuentacontable')
            .on('click.mcoCuentasDebajo', '.mco-cuenta-debajo-campo .consultacuentacontable', function (e) {
                e.preventDefault();
                abrirModal($(this).closest('.mco-cuenta-debajo-campo'));
            });

        $(document)
            .off('keydown.mcoCuentasDebajo', '.mco-cuenta-debajo-campo .codigocuentacontable')
            .on('keydown.mcoCuentasDebajo', '.mco-cuenta-debajo-campo .codigocuentacontable', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    if (modalAbierto()) {
                        return;
                    }
                    agregarDesdeCampo();
                }
            });

        $(document)
            .off('input.mcoCuentasDebajo', '.mco-cuenta-debajo-campo .codigocuentacontable')
            .on('input.mcoCuentasDebajo', '.mco-cuenta-debajo-campo .codigocuentacontable', function () {
                if (this.readOnly || this.disabled) {
                    return;
                }
                var texto = textoConsultaModal(this.value || '');
                if (texto === '' || !(empresaIdConsulta() > 0)) {
                    return;
                }
                var $campo = $(this).closest('.mco-cuenta-debajo-campo');
                campoActivo = $campo;
                var $buscar = $('#consultacuentacontable');
                $buscar.val(texto);
                if (document.activeElement === $buscar.get(0)) {
                    ponerCaretAlFinal($buscar.get(0));
                }
                if (modalAbierto()) {
                    programarBusqueda(texto);
                    return;
                }
                abrirModal($campo, { caretAlFinal: true, silencioso: true });
            });

        document.removeEventListener('keydown', onKeydownF1Capture, true);
        document.addEventListener('keydown', onKeydownF1Capture, true);

        $('#consultacuentaModal')
            .off('shown.bs.modal.mcoCuentasDebajo')
            .on('shown.bs.modal.mcoCuentasDebajo', function () {
                var valor = '';
                if (campoActivo && campoActivo.length) {
                    valor = textoConsultaModal(campoActivo.find('.codigocuentacontable').val());
                }
                abriendoModal = false;
                var caret = caretAlFinal;
                caretAlFinal = false;
                clearTimeout(buscarTimer);
                var $buscar = $('#consultacuentacontable');
                if (!caret || String($buscar.val() || '').trim() === '') {
                    $buscar.val(valor);
                }
                buscarCuentasModal(String($buscar.val() || '').trim());
                setTimeout(function () {
                    var el = $buscar.get(0);
                    if (!el) {
                        return;
                    }
                    el.focus();
                    if (caret) {
                        ponerCaretAlFinal(el);
                    }
                }, 0);
            })
            .off('hidden.bs.modal.mcoCuentasDebajo')
            .on('hidden.bs.modal.mcoCuentasDebajo', function () {
                abriendoModal = false;
                caretAlFinal = false;
                clearTimeout(buscarTimer);
            });

        $(document)
            .off('keydown.mcoCuentasDebajoEnter', '#consultacuentacontable')
            .on('keydown.mcoCuentasDebajoEnter', '#consultacuentacontable', function (e) {
                if (e.key !== 'Enter' && e.keyCode !== 13) {
                    return;
                }
                if (!campoActivo || !campoActivo.hasClass('mco-cuenta-debajo-campo')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (!elegirUnicaCuentaModal()) {
                    buscarCuentasModal(String(this.value || '').trim());
                }
            });

        $('#consultacuentaModal')
            .off('submit.mcoCuentasDebajo', 'form')
            .on('submit.mcoCuentasDebajo', 'form', function (e) {
                e.preventDefault();
            });

        $(document)
            .off('keyup.mcoCuentasDebajo', '#consultacuentacontable')
            .on('keyup.mcoCuentasDebajo', '#consultacuentacontable', function (e) {
                if (!campoActivo || !campoActivo.hasClass('mco-cuenta-debajo-campo')) {
                    return;
                }
                if (e.key === 'Enter' || e.keyCode === 13) {
                    return;
                }
                programarBusqueda($(this).val().trim());
            });

        $(document)
            .off('click.mcoCuentasDebajoElige', '.eligeconsultacuentacontable')
            .on('click.mcoCuentasDebajoElige', '.eligeconsultacuentacontable', function () {
                if (!campoActivo || !campoActivo.hasClass('mco-cuenta-debajo-campo')) {
                    return;
                }
                var $tr = $(this).closest('tr');
                var codigo = $tr.find('.codigocuentacontable').first().text().trim();
                var nombre = $tr.find('.nombrecuentacontable').first().text().trim();
                if (!agregarCuenta({ codigo: codigo, nombre: nombre })) {
                    alert('La cuenta ya está en la lista');
                } else {
                    limpiarCampo(campoActivo);
                }
                $('#consultacuentaModal').modal('hide');
            });

        $('#form-mayor-concepto')
            .off('submit.mcoCuentasDebajo')
            .on('submit.mcoCuentasDebajo', function () {
                adoptarPendiente();
                sincronizarHidden();
            });
    }

    $(activar);
})(window, window.jQuery);
