@extends("theme.$theme.layout")
@section('titulo')
    Cuenta Corriente de Proveedores
@endsection

@section("scripts")
<style>
    #tabla-paginada th.col-acciones-cc,
    #tabla-paginada td.col-acciones-cc {
        width: 8.5rem;
        min-width: 8.5rem;
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
    }
    #tabla-paginada td.col-acciones-cc .btn-accion-tabla {
        display: inline-block;
        vertical-align: middle;
        margin: 0 2px;
    }
</style>
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/cuentacorriente/filtro.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/cuentacorriente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/cuentacorriente/modo.js")}}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
    use App\Support\Compras\ProveedorCuentacorrienteListadoFiltros;
    use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;

    $modoCuentaCorriente = ($modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE;
    $mostrarSaldoCorrido = (bool) ($mostrarSaldoCorrido ?? false);
    $monedaId = $monedaId ?? null;
    $expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
    $enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
    $abrevLocal = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();
$limpiarUrl = route('listar_cuentacorriente_proveedor', array_merge(
    ['id' => $id],
    ProveedorCuentacorrienteListadoFiltros::paraQueryStringEmpresa($filtros ?? []),
    [
        'modo_vista' => $modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE,
        'moneda_id' => CuentacorrienteSaldosPorMoneda::valorQuery($monedaId),
        'expresion' => $expresion,
    ],
    request()->only(['origen', 'vista'])
));
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuenta Corriente Proveedor: @if(($codigoproveedor ?? '') !== ''){{ $codigoproveedor }} — @endif{{ $nombreproveedor }}</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('aplicar-cuentacorriente-proveedor', false))
                        <a href="{{ route('aplicacion_cuentacorriente_proveedor', ['proveedor_id' => $id]) }}" class="btn btn-light btn-sm mr-1">
                            <i class="fa fa-compress-alt"></i> Aplicar comprobantes
                        </a>
                    @endif
                    @if (!str_contains($urlOrigen ?? '', 'editar'))
                        @if (isset($urlOrigen))
                            <a href="{{ $urlOrigen }}" class="btn btn-light btn-sm mr-1">
                                <i class="fa fa-fw fa-reply-all"></i> Volver
                            </a>
                        @else
                            <a href="javascript:history.back()" class="btn btn-light btn-sm mr-1">
                                <i class="fa fa-fw fa-reply-all"></i> Volver atr&aacute;s
                            </a>
                        @endif
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cuentacorriente-proveedor',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ProveedorCuentacorrienteListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-cuentacorriente-proveedor',
                        'toggleId' => 'btn-toggle-filtros-cuentacorriente-proveedor',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('listar_cuentacorriente_proveedor', ['id' => $id]) }}" id="form-filtros-cuentacorriente-proveedor" class="mb-0">
                <input type="hidden" name="modo_vista" id="modo_vista" value="{{ $modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE }}">
                <input type="hidden" name="moneda_id" value="{{ CuentacorrienteSaldosPorMoneda::valorQuery($monedaId) }}">
                <input type="hidden" name="expresion" value="{{ $expresion }}">
                @foreach (request()->only(['origen', 'vista']) as $modoConsultaClave => $modoConsultaValor)
                    <input type="hidden" name="{{ $modoConsultaClave }}" value="{{ $modoConsultaValor }}">
                @endforeach
                <div class="card-body py-2 border-bottom bg-white">
                    <div class="custom-control custom-switch">
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
                </div>
                @include('compras.cuentacorriente.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('compras.cuentacorriente.partials.filtros_externos')
            <div class="card-body">
                @include('includes.cuentacorriente.saldos_por_moneda', [
                    'saldosPorMoneda' => $saldosPorMoneda ?? [],
                    'equivalentePesos' => $equivalentePesos ?? [],
                    'monedaId' => $monedaId,
                    'expresion' => $expresion,
                    'ruta' => 'listar_cuentacorriente_proveedor',
                    'id' => $id,
                    'queryFiltros' => $filtrosQuery ?? [],
                ])

                <div class="table-responsive p-0">
                    @include('includes.exportar-tabla-id', [
                        'ruta' => 'listar_cuentacorriente_proveedor',
                        'id' => $id,
                        'busqueda' => '',
                        'queryExtra' => $filtrosQuery ?? [],
                    ])
                    <table class="table table-striped table-bordered table-hover tabla-acciones-fijas" id="tabla-paginada">
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
                                    <th style="width: 11%; text-align: right;">Importe</th>
                                    <th style="width: 11%; text-align: right;">Aplicado</th>
                                    <th style="width: 12%; text-align: right;">Saldo pendiente</th>
                                    <th style="width: 13%; text-align: right;">{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPendientePesos() }}</th>
                                @endif
                                <th class="width160 text-nowrap col-acciones-tabla col-acciones-cc" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $saldosCorridos = $saldosAnterioresPorMoneda ?? [];
                                $saldoPesos = (float) ($saldoAnteriorPesos ?? 0);
                            @endphp
                            @foreach ($cuentacorriente as $data)
                                @php
                                    $etiquetaComprobante = ProveedorCuentacorrienteGrillaSupport::etiquetaComprobante($data);
                                    $importes = CuentacorrienteSaldosPorMoneda::importesParaGrilla(
                                        $data,
                                        $enPesos,
                                        static fn ($total, $aplicado) => ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
                                    );
                                    $totalMostrar = $importes['total'];
                                    $aplicadoMostrar = $importes['aplicado'];
                                    $saldoPendiente = $importes['saldo_pendiente_origen'];
                                    $saldoPendientePesos = $importes['saldo_pendiente_pesos'];
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
                                        @include('compras.cuentacorriente.partials.comprobante_grilla', [
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
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto($saldoPendiente, $data->monedas->abreviatura ?? $abreviaturaFila) }}
                                        </td>
                                        <td style="text-align: right;">
                                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto($saldoPendientePesos, $abrevLocal) }}
                                        </td>
                                    @endif
                                    <td class="col-acciones-tabla col-acciones-cc">
                                        <input type="hidden" name="total" id="total" class="form-control total" value="{{ $data->total }}"/>
                                        @include('compras.cuentacorriente.partials.acciones_grilla', ['data' => $data])
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
                                $pendienteAbsoluto = static fn ($fila) => ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $fila->total, $fila->aplicado ?? null);
                                $deudaPantalla = CuentacorrienteSaldosPorMoneda::totalesEnPantalla($cuentacorriente, $pendienteAbsoluto);
                                $deudaPantallaPesos = CuentacorrienteSaldosPorMoneda::deudaPantallaEnPesos(
                                    $cuentacorriente,
                                    static fn ($total, $aplicado) => ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
                                );
                            @endphp
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="8" class="text-right">Total deuda en pantalla</td>
                                    <td style="text-align: right;">
                                        {{ CuentacorrienteSaldosPorMoneda::formatearResumen($deudaPantalla, 'total') }}
                                    </td>
                                    <td style="text-align: right;">
                                        {{ CuentacorrienteSaldosPorMoneda::formatearMonto($deudaPantallaPesos, $abrevLocal) }}
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
@include('compras.cuentacorriente.modalaplicacion')
{{ $cuentacorriente->appends($filtrosQuery ?? [])->links() }}
@endsection
