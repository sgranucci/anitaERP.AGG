@extends("theme.$theme.layout")
@section('titulo')
    Numerador fiscal local
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/venta_serie_numerador/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\VentaSerieNumeradorListadoFiltros;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Numerador fiscal local</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-venta-serie-numerador',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => VentaSerieNumeradorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('venta_serie_numerador'),
                        'placeholder' => 'Búsqueda (PV, tipo ARCA)…',
                        'toggleTarget' => '#panel-filtros-venta-serie-numerador',
                        'toggleId' => 'btn-toggle-filtros-venta-serie-numerador',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('venta_serie_numerador') }}" id="form-filtros-venta-serie-numerador" class="mb-0">
                @include('ventas.venta_serie_numerador.partials.filtros_listado', [
                    'limpiarUrl' => route('venta_serie_numerador'),
                ])
            </form>
            <div class="card-body">
                @if(empty($enUso))
                    <div class="alert alert-warning py-2">
                        El módulo está armado. Los facturadores <strong>no</strong> lo usan todavía
                        (<code>FACTURACION_NUMERADOR_FISCAL_EN_USO=false</code>).
                        La serie es tipo ARCA + punto de venta (001 FAC A, 006 FAC B, 201 FCE A).
                    </div>
                @endif
                @can('sembrar-venta-serie-numerador')
                    <form method="post" action="{{ route('sembrar_venta_serie_numerador') }}" class="mb-3"
                          onsubmit="return confirm('¿Sembrar las series con el máximo de Anita (tipo ARCA + sucursal)? El ERP solo entra si está tildado el fallback y Anita no tiene esa serie. No baja un último ya mayor.');">
                        @csrf
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="usar_fallback_erp" id="usar_fallback_erp" value="1" checked>
                            <label class="form-check-label" for="usar_fallback_erp">
                                Usar ventas del ERP si Anita no tiene la serie
                            </label>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            Sembrar desde Anita
                        </button>
                    </form>
                @endcan
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_venta_serie_numerador',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <div class="table-responsive p-0">
                <table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>PV</th>
                            <th>Nombre</th>
                            <th>Modo</th>
                            <th>Tipo ARCA</th>
                            <th>Serie</th>
                            <th class="text-right">Último</th>
                            <th class="text-right">Piso</th>
                            <th class="text-right">Próximo</th>
                            <th class="text-right">Máx. venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->puntoventa->codigo ?? $data->puntoventa_id }}</td>
                            <td>{{ $data->puntoventa->nombre ?? '' }}</td>
                            <td>{{ $data->puntoventa->modofacturacion ?? '' }}</td>
                            <td>{{ str_pad((string) $data->codigo_afip, 3, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $data->etiqueta }}</td>
                            <td class="text-right">{{ number_format((int) $data->ultimo_numero, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((int) $data->piso, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((int) $data->proximo, 0, ',', '.') }}</td>
                            <td class="text-right">{{ $data->max_venta !== null ? number_format((int) $data->max_venta, 0, ',', '.') : '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No hay series. Usá Sembrar desde Anita.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if(method_exists($datas, 'links'))
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
