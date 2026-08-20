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
use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Ventas\ClienteCuentacorrientePreferenciasUsuario;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;

$modoCuentaCorriente = ($modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
    === ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE;
$mostrarSaldoCorrido = (bool) ($mostrarSaldoCorrido ?? false);
$monedaId = $monedaId ?? null;
$expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
$enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
$abrevLocal = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();
$queryFiltrosCliente = [
    'busqueda' => $busqueda ?? '',
    'modo_vista' => $modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE,
    'moneda_id' => CuentacorrienteSaldosPorMoneda::valorQuery($monedaId),
    'expresion' => $expresion,
];
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
                            <a href="{{ $urlOrigen }}" class="btn btn-light btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver
                            </a>
                        @else
                            <a href="javascript:history.back()" class="btn btn-light btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver atr&aacute;s
                            </a>
                        @endif
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end flex-wrap align-items-center mt-2 mt-md-0">
                    <form action="{{ route('listar_cuentacorriente_cliente', ['id' => $id]) }}" method="GET" id="form-cuentacorriente-filtros" class="d-flex flex-wrap align-items-center">
                        <input type="hidden" name="modo_vista" id="modo_vista" value="{{ $modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE }}">
                        <input type="hidden" name="moneda_id" value="{{ CuentacorrienteSaldosPorMoneda::valorQuery($monedaId) }}">
                        <input type="hidden" name="expresion" value="{{ $expresion }}">
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
                @include('includes.cuentacorriente.saldos_por_moneda', [
                    'saldosPorMoneda' => $saldosPorMoneda ?? [],
                    'equivalentePesos' => $equivalentePesos ?? [],
                    'monedaId' => $monedaId,
                    'expresion' => $expresion,
                    'ruta' => 'listar_cuentacorriente_cliente',
                    'id' => $id,
                    'queryFiltros' => $queryFiltrosCliente,
                ])

                <div class="table-responsive p-0">
                    @include('includes.exportar-tabla-id', [
                        'ruta' => 'listar_cuentacorriente_cliente',
                        'id' => $id,
                        'busqueda' => $busqueda ?? '',
                        'queryExtra' => $queryFiltrosCliente,
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
                                    <th style="width: 11%; text-align: right;">Debe</th>
                                    <th style="width: 11%; text-align: right;">Haber</th>
                                    <th style="width: 12%; text-align: right;">Saldo</th>
                                    <th style="width: 13%; text-align: right;">{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPesos() }}</th>
                                @else
                                    <th style="width: 12%; text-align: right;">Importe</th>
                                    <th style="width: 12%; text-align: right;">Aplicado</th>
                                    <th style="width: 12%; text-align: right;">Saldo pendiente</th>
                                @endif
                                <th class="width80" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $saldosCorridos = $saldosAnterioresPorMoneda ?? [];
                                $saldoPesos = (float) ($saldoAnteriorPesos ?? 0);
                            @endphp
                            @foreach ($cuentacorriente as $data)
                                @php
                                    $etiquetaComprobante = ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($data);
                                    $importes = CuentacorrienteSaldosPorMoneda::importesParaGrilla(
                                        $data,
                                        $enPesos,
                                        static fn ($total, $aplicado) => ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
                                    );
                                    $totalMostrar = $importes['total'];
                                    $aplicadoMostrar = $importes['aplicado'];
                                    $saldoPendiente = $importes['saldo_pendiente'];
                                    $abreviaturaFila = $importes['abreviatura'];
                                    $etiquetaMoneda = $importes['etiqueta_moneda'];
                                    $monedaFilaId = $importes['moneda_id'];
                                    if ($modoCuentaCorriente) {
                                        $saldosCorridos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorrido(
                                            $saldosCorridos,
                                            $monedaFilaId,
                                            (float) $data->total
                                        );
                                        $saldoFila = $saldosCorridos[$monedaFilaId] ?? 0.0;
                                        $saldoPesos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorridoPesos(
                                            $saldoPesos,
                                            $data,
                                            (float) $data->total
                                        );
                                        $saldoFilaPesos = $saldoPesos;
                                    }
                                @endphp
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
                                        {{ $etiquetaMoneda }}
                                        <input type="hidden" name="moneda" class="form-control moneda" value="{{ $data->monedas->id ?? '' }}">
                                    </td>
                                    @if ($modoCuentaCorriente)
                                        <td class="debe" style="text-align: right;">
                                            @if ($totalMostrar >= 0)
                                                {{ CuentacorrienteSaldosPorMoneda::formatearMonto($totalMostrar, $abreviaturaFila) }}
                                            @endif
                                        </td>
                                        <td class="haber" style="text-align: right;">
                                            @if ($totalMostrar < 0)
                                                {{ CuentacorrienteSaldosPorMoneda::formatearMonto(abs($totalMostrar), $abreviaturaFila) }}
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) $saldoFila, $data->monedas->abreviatura ?? $abreviaturaFila) }}
                                        </td>
                                        <td style="text-align: right;">
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) $saldoFilaPesos, $abrevLocal) }}
                                        </td>
                                    @else
                                        <td style="text-align: right;">
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto(abs($totalMostrar), $abreviaturaFila) }}
                                        </td>
                                        <td style="text-align: right;">
                                            @if ($aplicadoMostrar != 0)
                                                {{ CuentacorrienteSaldosPorMoneda::formatearMonto(abs($aplicadoMostrar), $abreviaturaFila) }}
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto($saldoPendiente, $abreviaturaFila) }}
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
                                    <td colspan="8" class="text-right">Saldos acumulados</td>
                                    <td style="text-align: right;">
                                        {{ CuentacorrienteSaldosPorMoneda::formatearResumen($saldosPorMoneda ?? [], 'saldo_cc') }}
                                    </td>
                                    <td style="text-align: right;">
                                        {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) ($equivalentePesos['saldo_cc'] ?? 0), $abrevLocal) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @else
                            @php
                                $deudaPantalla = $enPesos
                                    ? CuentacorrienteSaldosPorMoneda::deudaPantallaEnPesos(
                                        $cuentacorriente,
                                        static fn ($total, $aplicado) => ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
                                    )
                                    : CuentacorrienteSaldosPorMoneda::totalesEnPantalla(
                                        $cuentacorriente,
                                        static fn ($fila) => ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $fila->total, $fila->aplicado ?? null)
                                    );
                            @endphp
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="8" class="text-right">{{ $enPesos ? 'Total deuda en pantalla ('.$abrevLocal.')' : 'Total deuda en pantalla' }}</td>
                                    <td style="text-align: right;">
                                        @if ($enPesos)
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) $deudaPantalla, $abrevLocal) }}
                                        @else
                                            {{ CuentacorrienteSaldosPorMoneda::formatearResumen($deudaPantalla, 'total') }}
                                        @endif
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
{{ $cuentacorriente->appends($queryFiltrosCliente)->links() }}
@endsection
