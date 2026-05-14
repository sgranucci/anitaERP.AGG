@extends("theme.$theme.layout")
@section('titulo')
    Saldos de Cuentas Interbanking
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script>
    var ptrRenglon;
    var urlMovimientosInterbanking = @json(route('interbanking_movimientos'));

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

    $(document).ready(function () {
        $(document).on('click', '.enviaconsulta', function (e) {
            e.preventDefault();
            ptrRenglon = this;
            $('#movimientodiarioModal').modal('show');
        });

        $(document).on('click', '#ib_movimientos_consultar', function () {
            leeMovimientosInterbanking();
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

