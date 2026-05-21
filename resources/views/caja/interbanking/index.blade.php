@extends("theme.$theme.layout")
@section('titulo')
    Saldos de Cuentas Interbanking
@endsection

@section('styles')
<style>
    .ib-tabla-transferencias-scroll {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
    }
    .ib-tabla-transferencias-scroll table {
        margin-bottom: 0;
    }
    #transferenciasModal .modal-body {
        overflow-x: auto;
    }
</style>
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script>
    var ptrRenglon;
    var urlMovimientosInterbanking = @json(route('interbanking_movimientos'));
    var urlTransferenciasInterbanking = @json(route('interbanking_transferencias'));
    var urlComprobanteTransferenciaTpl = @json(str_replace('999999999', '__ID__', route('interbanking_transferencia_comprobante', ['id' => 999999999])));
    var urlDetalleTransferenciaApi = @json(route('interbanking_transferencia_detalle_api'));
    var ptrRenglonTransferencias;

    function urlComprobanteTransferencia(persistedId) {
        return urlComprobanteTransferenciaTpl.replace('__ID__', String(persistedId));
    }

    function ibResumenCuentaJs(cuenta) {
        if (!cuenta || cuenta === '') {
            return { banco: '—', denominacion: '—', cuit: '—', cbu: '—' };
        }
        if (typeof cuenta === 'string') {
            if (cuenta.charAt(0) === '{') {
                try {
                    cuenta = JSON.parse(cuenta);
                } catch (e) {
                    return { banco: '—', denominacion: cuenta, cuit: '—', cbu: '—' };
                }
            } else {
                return { banco: '—', denominacion: cuenta, cuit: '—', cbu: '—' };
            }
        }
        if (typeof cuenta !== 'object') {
            return { banco: '—', denominacion: '—', cuit: '—', cbu: '—' };
        }
        return {
            banco: cuenta.bank_name || cuenta.bankName || '—',
            denominacion: cuenta.account_label || cuenta.accountLabel || cuenta.denomination || cuenta.name || '—',
            cuit: cuenta.taxpayer_cuit || cuenta.taxpayerCuit || cuenta.customer_cuit || cuenta.customerCuit || cuenta.cuit || '—',
            cbu: cuenta.account_cbu || cuenta.accountCbu || cuenta.cbu || '—'
        };
    }

    function ibAbrirDetalleTransferenciaApi(transfer) {
        var $modal = $('#ibDetalleTransferenciaModal');
        var $body = $('#ib-detalle-transferencia-body');
        $modal.find('.modal-title').text('Transferencia #' + (transfer.transfer_id || ''));
        $body.html('<p class="text-muted mb-0">Cargando…</p>');
        $modal.modal('show');

        $.ajax({
            url: urlDetalleTransferenciaApi,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: JSON.stringify({ transfer: transfer }),
            success: function (resp) {
                if (resp.ok && resp.html) {
                    $modal.find('.modal-title').text(resp.titulo || 'Detalle de transferencia');
                    $body.html(resp.html);
                } else {
                    $body.html('<p class="text-danger mb-0">No se pudo cargar el detalle.</p>');
                }
            },
            error: function () {
                $body.html('<p class="text-danger mb-0">Error al consultar el detalle.</p>');
            }
        });
    }

    function padBankNumber3(s) {
        var d = (s || '').replace(/\D/g, '');
        if (d === '') {
            return '';
        }
        d = d.replace(/^0+/, '');
        if (d === '') {
            d = '0';
        }
        while (d.length < 3) {
            d = '0' + d;
        }
        if (d.length > 3) {
            d = d.slice(-3);
        }
        return d;
    }

    function leeMovimientosInterbanking() {
        var $tr = $(ptrRenglon).closest('tr');
        var empresa_id = $tr.find('.empresa_id').text().trim();
        var account_number = $tr.find('.account_number').text().trim();
        var bank_number = padBankNumber3($tr.find('.bank_number').text());
        var account_type = ($tr.find('.account_type').text().trim() || 'CC').substring(0, 2).toUpperCase();
        if (account_type !== 'CC' && account_type !== 'CA') {
            account_type = 'CC';
        }
        var currencyRaw = ($tr.find('.currency').text().trim() || 'ARS').toUpperCase();
        var currency = 'ARS';
        if (currencyRaw === 'USD' || currencyRaw === 'U$S' || currencyRaw === 'US$') {
            currency = 'USD';
        } else if (currencyRaw === 'ARS' || currencyRaw === '$') {
            currency = 'ARS';
        }

        var params = {
            empresa_id: empresa_id,
            account_number: account_number,
            bank_number: bank_number,
            account_type: account_type,
            currency: currency,
            movement_type: $('#ib_movimiento_tipo').val(),
            limit: $('#ib_limit').val() || '100',
            page: $('#ib_page').val() || '0'
        };
        var ds = $('#ib_date_since').val();
        var du = $('#ib_date_until').val();
        if (ds) {
            params.date_since = ds;
        }
        if (du) {
            params.date_until = du;
        }

        $('#tbody-movimientodiario').html('<tr><td colspan="8">Consultando…</td></tr>');
        $('#ib_movimientos_pie').text('');

        if (!empresa_id || !account_number || !bank_number) {
            $('#tbody-movimientodiario').html('<tr><td colspan="8">Faltan datos de cuenta o empresa para consultar movimientos.</td></tr>');
            return;
        }

        $.get(urlMovimientosInterbanking, params, function (resp) {
            $('#tbody-movimientodiario').empty();
            if (!resp.ok) {
                $('#tbody-movimientodiario').append($('<tr/>').append($('<td colspan="8"/>').text(resp.error || 'Error al consultar.')));
                return;
            }
            var rows = resp.movements_detail || [];
            var gen = resp.general_data || {};
            var pie = 'Total registros: ' + (gen.total_rows != null ? gen.total_rows : '—')
                + ' | Página: ' + (gen.page != null ? gen.page : params.page)
                + ' | Límite: ' + (gen.limit != null ? gen.limit : params.limit);
            $('#ib_movimientos_pie').text(pie);

            if (rows.length === 0) {
                $('#tbody-movimientodiario').append($('<tr/>').append($('<td colspan="8"/>').text('Sin movimientos para los filtros indicados.')));
                return;
            }

            $.each(rows, function (i, m) {
                var dc = (m.debit_credit_type || '').toString().toUpperCase();
                var monto = parseFloat(m.amount);
                if (isNaN(monto)) {
                    monto = 0;
                }
                var debito = dc === 'D' ? fNumero(monto, 2) : '';
                var credito = dc === 'C' ? fNumero(monto, 2) : '';
                var fecha = m.process_date ? new Date(m.process_date).toLocaleString('es-AR') : '';

                var tr = $('<tr/>');
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(fecha)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" style="text-align:right;" readonly/>').val(debito)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" style="text-align:right;" readonly/>').val(credito)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(m.code_description_bank || '')));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(m.operation_code_ib || '')));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(m.voucher_number != null ? String(m.voucher_number) : '')));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(m.account_cbu || '')));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(m.depositor_description || '')));
                $('#tbody-movimientodiario').append(tr);
            });
        }).fail(function () {
            $('#tbody-movimientodiario').html('<tr><td colspan="8">Error de comunicación con el servidor.</td></tr>');
        });
    }

    function defaultDateUntil() {
        return new Date().toISOString().slice(0, 10);
    }

    function defaultDateSince() {
        var d = new Date();
        d.setDate(d.getDate() - 60);
        return d.toISOString().slice(0, 10);
    }

    function ibCuentaTransferencia(cuenta) {
        if (cuenta == null || cuenta === '') {
            return '';
        }
        if (typeof cuenta === 'string' || typeof cuenta === 'number') {
            return String(cuenta);
        }
        if (typeof cuenta === 'object') {
            var n = cuenta.account_number || cuenta.accountNumber || cuenta.number || '';
            var b = cuenta.bank_number || cuenta.bankNumber || '';
            if (n && b) {
                return b + '-' + n;
            }
            return n ? String(n) : JSON.stringify(cuenta);
        }
        return '';
    }

    function leeTransferenciasInterbanking() {
        var $tr = $(ptrRenglonTransferencias).closest('tr');
        var empresa_id = $tr.find('.empresa_id').text().trim();
        var account_number = $tr.find('.account_number').text().trim();
        var bank_number = padBankNumber3($tr.find('.bank_number').text());
        var account_type = ($tr.find('.account_type').text().trim() || 'CC').substring(0, 2).toUpperCase();
        if (account_type !== 'CC' && account_type !== 'CA') {
            account_type = 'CC';
        }
        var currencyRaw = ($tr.find('.currency').text().trim() || 'ARS').toUpperCase();
        var currency = 'ARS';
        if (currencyRaw === 'USD' || currencyRaw === 'U$S' || currencyRaw === 'US$') {
            currency = 'USD';
        } else if (currencyRaw === 'ARS' || currencyRaw === '$') {
            currency = 'ARS';
        }

        var params = {
            empresa_id: empresa_id,
            debit_account_number: account_number,
            debit_bank_number: bank_number,
            debit_account_type: account_type,
            debit_currency: currency,
            limit: $('#ib_tr_limit').val() || '100',
            page: $('#ib_tr_page').val() || '0'
        };
        var ds = $('#ib_tr_date_since').val();
        var du = $('#ib_tr_date_until').val();
        if (ds) {
            params.date_since = ds;
        }
        if (du) {
            params.date_until = du;
        }

        var colSpan = 12;
        $('#tbody-transferencias').html('<tr><td colspan="' + colSpan + '">Consultando…</td></tr>');
        $('#ib_transferencias_pie').text('');

        if (!empresa_id || !account_number || !bank_number) {
            $('#tbody-transferencias').html('<tr><td colspan="' + colSpan + '">Faltan datos de cuenta o empresa para consultar transferencias.</td></tr>');
            return;
        }

        $.get(urlTransferenciasInterbanking, params, function (resp) {
            $('#tbody-transferencias').empty();
            if (!resp.ok) {
                $('#tbody-transferencias').append($('<tr/>').append($('<td colspan="' + colSpan + '"/>').text(resp.error || 'Error al consultar.')));
                return;
            }
            var rows = resp.transfers || [];
            var comprobanteIds = resp.comprobante_ids || {};
            var gen = resp.general_data || {};
            var pie = 'Total registros: ' + (gen.total_rows != null ? gen.total_rows : '—')
                + ' | Página: ' + (gen.page != null ? gen.page : params.page)
                + ' | Límite: ' + (gen.limit != null ? gen.limit : params.limit);
            if (resp.filas_persistidas != null) {
                pie += ' | Persistidas: ' + resp.filas_persistidas;
            }
            $('#ib_transferencias_pie').text(pie);

            if (rows.length === 0) {
                $('#tbody-transferencias').append($('<tr/>').append($('<td colspan="' + colSpan + '"/>').text('Sin transferencias para los filtros indicados.')));
                return;
            }

            $.each(rows, function (i, t) {
                var monto = parseFloat(t.amount);
                if (isNaN(monto)) {
                    monto = 0;
                }
                var fechaObj = t.request_date ? new Date(t.request_date) : null;
                var fechaTransferencia = fechaObj
                    ? fechaObj.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })
                    : '';
                var horaTransferencia = fechaObj
                    ? fechaObj.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false })
                    : '';
                var tipo = t.transfer_type_description || t.transfer_type_code || '';

                var debito = ibResumenCuentaJs(t.debit_account);
                var credito = ibResumenCuentaJs(t.credit_account);
                var persistedId = comprobanteIds[t.transfer_id] || comprobanteIds[String(t.transfer_id)];

                var tr = $('<tr/>');
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(fechaTransferencia)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(horaTransferencia)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(tipo)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" style="text-align:right;" readonly/>').val(fNumero(monto, 2))));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(t.currency || '')));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(debito.cbu)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(credito.banco)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(credito.denominacion)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(credito.cuit)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(credito.cbu)));
                tr.append($('<td/>').append($('<input type="text" class="form-control form-control-sm" readonly/>').val(t.transfer_id != null ? String(t.transfer_id) : '')));

                var $acc = $('<td class="text-nowrap"/>');
                if (persistedId) {
                    $acc.append(
                        $('<a/>', {
                            href: urlComprobanteTransferencia(persistedId),
                            target: '_blank',
                            rel: 'noopener',
                            class: 'btn-accion-tabla tooltipsC',
                            title: 'Imprimir comprobante (PDF)'
                        }).append($('<i/>', { class: 'fa fa-print text-primary' }))
                    );
                }
                $acc.append(
                    $('<button/>', {
                        type: 'button',
                        class: 'btn-accion-tabla tooltipsC ib-ver-detalle-transferencia-api',
                        title: 'Ver datos completos de cuentas'
                    }).append($('<i/>', { class: 'fa fa-info-circle text-info' }))
                );
                tr.data('transfer', t);
                tr.find('.ib-ver-detalle-transferencia-api').on('click', function () {
                    ibAbrirDetalleTransferenciaApi($(this).closest('tr').data('transfer'));
                });
                tr.append($acc);

                $('#tbody-transferencias').append(tr);
            });
        }).fail(function () {
            $('#tbody-transferencias').html('<tr><td colspan="' + colSpan + '">Error de comunicación con el servidor.</td></tr>');
        });
    }

    $(document).ready(function () {
        $(document).on('click', '.enviaconsulta', function (e) {
            e.preventDefault();
            ptrRenglon = this;
            $('#movimientodiarioModal').modal('show');
        });

        $(document).on('click', '.enviatransferencias', function (e) {
            e.preventDefault();
            ptrRenglonTransferencias = this;
            $('#transferenciasModal').modal('show');
        });

        $(document).on('click', '#ib_movimientos_consultar', function () {
            leeMovimientosInterbanking();
        });

        $(document).on('click', '#ib_transferencias_consultar', function () {
            leeTransferenciasInterbanking();
        });
    });

    $('#movimientodiarioModal').on('show.bs.modal', function () {
        var $tr = $(ptrRenglon).closest('tr');
        var account_number = $tr.find('.account_number').text().trim();
        var account_name = $tr.find('.account_name').text().trim();
        $(this).find('.modal-title').text('Movimientos Interbanking — ' + account_number + (account_name ? ' — ' + account_name : ''));
        $('#ib_movimiento_tipo').val('dia');
        $('#ib_date_since').val('');
        $('#ib_date_until').val('');
        $('#ib_limit').val('100');
        $('#ib_page').val('0');
        leeMovimientosInterbanking();
    });

    $('#aceptamovimientodiarioModal').on('click', function () {
        $('#movimientodiarioModal').modal('hide');
    });

    $('#transferenciasModal').on('show.bs.modal', function () {
        var $tr = $(ptrRenglonTransferencias).closest('tr');
        var account_number = $tr.find('.account_number').text().trim();
        var account_name = $tr.find('.account_name').text().trim();
        $(this).find('.modal-title').text('Transferencias Interbanking — ' + account_number + (account_name ? ' — ' + account_name : ''));
        $('#ib_tr_date_since').val(defaultDateSince());
        $('#ib_tr_date_until').val(defaultDateUntil());
        $('#ib_tr_limit').val('100');
        $('#ib_tr_page').val('0');
        leeTransferenciasInterbanking();
    });

    $('#aceptatransferenciasModal').on('click', function () {
        $('#transferenciasModal').modal('hide');
    });
</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuentas Interbanking</h3>
                <div class="card-tools">
                    @if (can('listar-interbanking-movimientos-persistidos', false))
                        <a href="{{ route('interbanking_movimientos_persistidos') }}" class="btn btn-tool btn-sm">Movimientos persistidos</a>
                    @endif
                    @if (can('listar-interbanking-transferencias-persistidas', false))
                        <a href="{{ route('interbanking_transferencias_persistidas') }}" class="btn btn-tool btn-sm">Transferencias persistidas</a>
                    @endif
                    @if (can('listar-saldos-interbanking-historico', false))
                        <a href="{{ route('interbanking_saldos_historicos') }}" class="btn btn-tool btn-sm">Consulta saldos históricos</a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">Banco</th>
                            <th>Moneda</th>
                            <th>Numero de Cuenta</th>
                            <th>Tipo Cuenta</th>
                            <th>Etiqueta Cuenta</th>
                            <th>Nombre Cuenta</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Balance Contable</th>
                            <th>Balance Inicial</th>
                            <th>Balance Actual</th>
                            <th>Balance 24hs</th>
                            <th>Balance 48hs</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cuentas as $data)
                        <tr>
                            <td>
                                {{ $data['nombrebanco'] ?? '' }}
                                <span class="bank_number" style="display:none">{{ $data['bank_number'] ?? $data['bankNumber'] ?? '' }}</span>
                                <span class="empresa_id" style="display:none">{{ $data['empresa_id'] ?? '' }}</span>
                            </td>
                            <td class="currency">{{$data['currency'] ?? ''}}</td>
                            <td class="account_number">{{$data['account_number'] ?? ''}}</td>
                            <td class="account_type">{{$data['account_type'] ?? ''}}</td>
                            <td class="account_label">{{$data['account_label'] ?? ''}}</td>
                            <td class="account_name">{{$data['account_name'] ?? ''}}</td>
                            <td>{{ $data['nombre_empresa'] ?? '' }}</td>
                            <td>{{\Carbon\Carbon::parse($data['row_date'])->format('d/m/Y')}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['countable_balance'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['initial_operating_balance'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['current_operating_balance'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['projected_balance_24hs'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['balances']['projected_balance_48hs'] ?? 0, 2)}}</td>
                            <td>
                       			@if (can('ver-movimientos-cuenta-interbanking', false))
                                    <a href="#" class="btn-accion-tabla enviaconsulta tooltipsC" title="Ver movimientos de la cuenta">
                                        <i class="fa fa-edit"></i>
                                    </a>
								@endif
                       			@if (can('ver-transferencias-cuenta-interbanking', false))
                                    <a href="#" class="btn-accion-tabla enviatransferencias tooltipsC" title="Ver transferencias de la cuenta">
                                        <i class="fa fa-exchange-alt"></i>
                                    </a>
								@endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@include('caja.interbanking.modalmovimientodiario')
@include('caja.interbanking.modaltransferencias')
@include('caja.interbanking.partials.modal_detalle_transferencia')

