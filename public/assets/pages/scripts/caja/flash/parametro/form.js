(function ($) {
    'use strict';

    var SELECTOR_BUDGET_DEC = '.flash-budget-decimal';
    var SELECTOR_BUDGET_ENT = '.flash-budget-entero';

    function parseDecimal(str, decimales) {
        if (str == null || str === '') {
            return 0;
        }
        var dec = decimales == null ? 2 : decimales;
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        var n = parseFloat(t);
        if (isNaN(n)) {
            return 0;
        }
        var factor = Math.pow(10, dec);
        return Math.round(n * factor) / factor;
    }

    function parseEntero(str) {
        return Math.round(parseDecimal(str, 0));
    }

    function fmtDecimal(n, decimales) {
        var dec = decimales == null ? 2 : decimales;
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec
        });
    }

    function fmtEntero(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function formatearDecimalInput(el, dec) {
        if (!el || el.value === '' || el.value == null) {
            if (el) {
                el.value = '';
            }
            return;
        }
        el.value = fmtDecimal(parseDecimal(el.value, dec), dec);
    }

    function formatearEnteroInput(el) {
        if (!el || el.value === '' || el.value == null) {
            if (el) {
                el.value = '';
            }
            return;
        }
        el.value = fmtEntero(parseEntero(el.value));
    }

    function desformatearDecimalInput(el, dec) {
        if (!el || el.value === '') {
            return;
        }
        el.value = String(parseDecimal(el.value, dec));
    }

    function desformatearEnteroInput(el) {
        if (!el || el.value === '') {
            return;
        }
        el.value = String(parseEntero(el.value));
    }

    function fmt(n) {
        return fmtDecimal(n, 4);
    }

    function recalcularTotales() {
        var season = 0, bingo = 0, slot = 0, rul = 0, poker = 0, estac = 0;
        $('#tbody-indices-flash tr[data-fila-indice]').each(function () {
            var $tr = $(this);
            season += parseFloat($tr.find('[name$="[season_index]"]').val()) || 0;
            bingo += parseFloat($tr.find('[name$="[sindex_bingo]"]').val()) || 0;
            slot += parseFloat($tr.find('[name$="[sindex_slot]"]').val()) || 0;
            rul += parseFloat($tr.find('[name$="[sindex_rul]"]').val()) || 0;
            poker += parseFloat($tr.find('[name$="[sindex_poker]"]').val()) || 0;
            estac += parseFloat($tr.find('[name$="[sindex_estac]"]').val()) || 0;
        });
        $('#foot-season, #tot-season').text(fmt(season));
        $('#foot-bingo, #tot-sbingo').text(fmt(bingo));
        $('#foot-slot, #tot-sslot').text(fmt(slot));
        $('#foot-rul, #tot-srul').text(fmt(rul));
        $('#foot-poker, #tot-spoker').text(fmt(poker));
        $('#foot-estac, #tot-sestac').text(fmt(estac));
    }

    function fechaLabel(iso) {
        if (!iso || iso.length < 10) { return ''; }
        return iso.substr(8, 2) + '/' + iso.substr(5, 2) + '/' + iso.substr(0, 4);
    }

    function renderFilas(indices) {
        var html = '';
        (indices || []).forEach(function (fila, i) {
            html += '<tr data-fila-indice>' +
                '<td><input type="hidden" name="indices[' + i + '][fecha]" value="' + (fila.fecha || '') + '">' +
                '<span class="fecha-label">' + fechaLabel(fila.fecha) + '</span></td>' +
                '<td><input type="number" min="0" step="1" class="form-control form-control-sm text-right idx-num" name="indices[' + i + '][vehiculos]" value="' + (fila.vehiculos || 0) + '"></td>' +
                '<td><input type="number" min="0" step="1" class="form-control form-control-sm text-right idx-num" name="indices[' + i + '][customer]" value="' + (fila.customer || 0) + '"></td>' +
                '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season" name="indices[' + i + '][season_index]" value="' + (fila.season_index || 0) + '"></td>' +
                '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season" name="indices[' + i + '][sindex_bingo]" value="' + (fila.sindex_bingo || 0) + '"></td>' +
                '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season" name="indices[' + i + '][sindex_slot]" value="' + (fila.sindex_slot || 0) + '"></td>' +
                '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season" name="indices[' + i + '][sindex_rul]" value="' + (fila.sindex_rul || 0) + '"></td>' +
                '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season" name="indices[' + i + '][sindex_poker]" value="' + (fila.sindex_poker || 0) + '"></td>' +
                '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season" name="indices[' + i + '][sindex_estac]" value="' + (fila.sindex_estac || 0) + '"></td>' +
                '</tr>';
        });
        $('#tbody-indices-flash').html(html);
        recalcularTotales();
    }

    function generarDias() {
        var periodo = $('#periodo').val();
        if (!periodo) {
            alert('Seleccione el período (mes).');
            return;
        }
        var $btn = $('#btn-generar-dias').prop('disabled', true);
        $.ajax({
            url: window.flashParametroDiasUrl || '/caja/flash/parametro/api/dias-periodo',
            method: 'GET',
            data: { periodo: periodo }
        }).done(function (resp) {
            if (resp && resp.ok && resp.indices) {
                renderFilas(resp.indices);
                $('a[href="#tab-indices"]').tab('show');
            } else {
                alert((resp && resp.mensaje) || 'No se pudieron generar los días.');
            }
        }).fail(function () {
            alert('Error al generar los días del período.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    function normalizarBudgetsAntesDeEnviar(form) {
        $(form).find(SELECTOR_BUDGET_DEC).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseDecimal(this.value, 2));
        });
        $(form).find(SELECTOR_BUDGET_ENT).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseEntero(this.value));
        });
    }

    function initFormatoBudgets() {
        $(SELECTOR_BUDGET_DEC).each(function () {
            formatearDecimalInput(this, 2);
        });
        $(SELECTOR_BUDGET_ENT).each(function () {
            formatearEnteroInput(this);
        });
    }

    $(function () {
        initFormatoBudgets();

        $(document).on('focus', SELECTOR_BUDGET_DEC, function () {
            desformatearDecimalInput(this, 2);
            this.select();
        });
        $(document).on('blur', SELECTOR_BUDGET_DEC, function () {
            formatearDecimalInput(this, 2);
        });
        $(document).on('focus', SELECTOR_BUDGET_ENT, function () {
            desformatearEnteroInput(this);
            this.select();
        });
        $(document).on('blur', SELECTOR_BUDGET_ENT, function () {
            formatearEnteroInput(this);
        });

        $(document).on('submit', '#form-general', function () {
            normalizarBudgetsAntesDeEnviar(this);
        });

        if (!$('#tabla-indices-flash').length) {
            return;
        }

        $(document).on('input change', '#tbody-indices-flash .idx-season', recalcularTotales);
        $('#btn-generar-dias').on('click', generarDias);

        var soloAlta = !$('#periodo').prop('readonly');
        if (soloAlta) {
            $('#periodo').on('change', function () {
                if ($('#tbody-indices-flash tr[data-fila-indice]').length === 0) {
                    generarDias();
                }
            });
        }

        recalcularTotales();
    });
})(jQuery);
