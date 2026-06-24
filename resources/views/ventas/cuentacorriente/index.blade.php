@extends("theme.$theme.layout")
@section('titulo')
    Cuenta Corriente de Clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cuentacorriente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cuentacorriente/modo.js")}}" type="text/javascript"></script>
@endsection

<?php
use App\Helpers\biblioteca;
use App\Support\Ventas\ClienteCuentacorrientePreferenciasUsuario;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;

$modoCuentaCorriente = ($modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
    === ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE;
?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuenta Corriente Cliente: {{ $nombrecliente }}</h3>
                <div class="card-tools">
                    @if (!str_contains($urlOrigen ?? '', 'editar'))
                        @if (isset($urlOrigen))
                            <a href="{{ $urlOrigen }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver
                            </a>
                        @else
                            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver atr&aacute;s
                            </a>
                        @endif
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end flex-wrap align-items-center mt-2 mt-md-0">
                    <form action="{{ route('listar_cuentacorriente_cliente', ['id' => $id]) }}" method="GET" id="form-cuentacorriente-filtros" class="d-flex flex-wrap align-items-center">
                        <input type="hidden" name="modo_vista" id="modo_vista" value="{{ $modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE }}">
                        <div class="custom-control custom-switch mr-3 mb-2">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="switch-modo-vista"
                                   @checked(! $modoCuentaCorriente)>
                            <label class="custom-control-label" for="switch-modo-vista" id="label-modo-vista">
                                @if ($modoCuentaCorriente)
                                    Cuenta corriente (Debe / Haber)
                                @else
                                    Deuda (facturas impagas)
                                @endif
                            </label>
                        </div>
                        <div class="btn-group mb-2">
                            <input type="text" name="busqueda" class="form-control" placeholder="Busqueda ..." value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="info-box mb-0 bg-light">
                            <span class="info-box-icon bg-info"><i class="fas fa-balance-scale"></i></span>
                            <span class="info-box-content">
                                <span class="info-box-text">Saldo cuenta corriente</span>
                                <span class="info-box-number">{{ number_format($saldoCuentaCorriente ?? 0, 2) }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box mb-0 bg-light">
                            <span class="info-box-icon bg-warning"><i class="fas fa-file-invoice-dollar"></i></span>
                            <span class="info-box-content">
                                <span class="info-box-text">Total deuda (facturas impagas)</span>
                                <span class="info-box-number">{{ number_format($totalDeuda ?? 0, 2) }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive p-0">
                    @include('includes.exportar-tabla-id', [
                        'ruta' => 'listar_cuentacorriente_cliente',
                        'id' => $id,
                        'busqueda' => $busqueda ?? '',
                        'queryExtra' => ['modo_vista' => $modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE],
                    ])
                    <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead>
                            <tr>
                                <th class="width20">ID</th>
                                <th>Empresa</th>
                                <th>Fecha</th>
                                <th>Vencimiento</th>
                                <th>Comprobante</th>
                                <th>Moneda</th>
                                @if ($modoCuentaCorriente)
                                    <th style="width: 12%; text-align: right;">Debe</th>
                                    <th style="width: 12%; text-align: right;">Haber</th>
                                    <th style="width: 12%; text-align: right;">Saldo</th>
                                @else
                                    <th style="width: 12%; text-align: right;">Importe</th>
                                    <th style="width: 12%; text-align: right;">Aplicado</th>
                                    <th style="width: 12%; text-align: right;">Saldo pendiente</th>
                                @endif
                                <th class="width80" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $saldo = (float) ($saldoAnterior ?? 0); @endphp
                            @foreach ($cuentacorriente as $data)
                                @php
                                    $etiquetaComprobante = ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($data);
                                    $aplicado = (float) ($data->aplicado ?? 0);
                                    $saldoPendiente = ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $data->total, $data->aplicado ?? null);
                                @endphp
                                @if ($modoCuentaCorriente)
                                    @php $saldo += (float) $data->total; @endphp
                                @endif
                                <tr>
                                    <td class="cuentacorriente_id">{{ $data->id }}</td>
                                    <td>{{ $data->empresas->nombre ?? '' }}</td>
                                    <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
                                    <td>{{ date('d/m/Y', strtotime($data->fechavencimiento ?? '')) }}</td>
                                    <td class="comprobante">
                                        @include('ventas.cuentacorriente.partials.comprobante_grilla', [
                                            'data' => $data,
                                            'etiquetaComprobante' => $etiquetaComprobante,
                                        ])
                                    </td>
                                    <td>
                                        {{ $data->monedas->abreviatura ?? '' }}
                                        <input type="hidden" name="moneda" class="form-control moneda" value="{{ $data->monedas->id ?? '' }}">
                                    </td>
                                    @if ($modoCuentaCorriente)
                                        <td class="debe" style="text-align: right;">
                                            @if ($data->total >= 0)
                                                {{ number_format($data->total, 2) }}
                                            @endif
                                        </td>
                                        <td class="haber" style="text-align: right;">
                                            @if ($data->total < 0)
                                                {{ number_format(abs($data->total), 2) }}
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            {{ number_format($saldo, 2) }}
                                        </td>
                                    @else
                                        <td style="text-align: right;">
                                            {{ number_format(abs($data->total), 2) }}
                                        </td>
                                        <td style="text-align: right;">
                                            @if ($aplicado != 0)
                                                {{ number_format(abs($aplicado), 2) }}
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            {{ number_format($saldoPendiente, 2) }}
                                        </td>
                                    @endif
                                    <td>
                                        <input type="hidden" name="total" id="total" class="form-control total" value="{{ $data->total }}"/>
                                        @include('ventas.cuentacorriente.partials.acciones_grilla', ['data' => $data])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($modoCuentaCorriente)
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="8" class="text-right">Saldo al cierre de la p&aacute;gina</td>
                                    <td style="text-align: right;">{{ number_format($saldo, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @else
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="8" class="text-right">Total deuda en pantalla</td>
                                    <td style="text-align: right;">
                                        {{ number_format($cuentacorriente->sum(fn ($fila) => ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $fila->total, $fila->aplicado ?? null)), 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@include('ventas.cuentacorriente.modalaplicacion')
{{ $cuentacorriente->appends(['busqueda' => $busqueda ?? '', 'modo_vista' => $modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE])->links() }}
@endsection
