@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Cuentas Interbanking
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos de Cuentas Interbanking</h3>
                &nbsp;- ID: {{ $account_number }} - {{ $account_type }} - {{ $account_name }}
                <div class="card-tools">
                    <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-sin-ordenar">
                    <thead>
                        <tr>
                            <th class="width20">Fecha</th>
                            <th style="width: 12%; text-align: right;">Débitos</th>
                            <th style="width: 12%; text-align: right;">Créditos</th>
                            <th style="width: 12%; text-align: right;">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historical_balances as $data)
                        <tr>
                            <td>{{\Carbon\Carbon::parse($data['operation_date'])->format('d/m/Y')}}</td>
                            <td style="text-align:right;">{{number_format($data['total_debits'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{number_format($data['total_credits'] ?? 0, 2)}}</td>
                            <td style="text-align:right;">{{$currency}} {{number_format($data['day_balance'] ?? 0, 2)}}</td>
                            <td></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
