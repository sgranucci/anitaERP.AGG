@extends("theme.$theme.layout")

@section('titulo')
    Cotización de tesorería
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}?v={{ filemtime(public_path('assets/pages/scripts/includes/listado-filtros.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/cotizacion_tesoreria/filtro.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cotización de tesorería</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-tesoreria-crown-cotizacion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios,
                        'limpiarUrl' => route('tesoreria_crown_cotizacion', $sala ? ['sala_id' => $sala->id] : []),
                        'placeholder' => 'Fecha (d/m/Y) o año',
                        'toggleTarget' => '#panel-filtros-tesoreria-crown-cotizacion',
                        'nuevoRegistroUrl' => route('crear_tesoreria_crown_cotizacion', $sala ? ['sala_id' => $sala->id] : []),
                        'nuevoRegistroCan' => 'crear-tesoreria-crown-cotizacion',
                        'nuevoRegistroLabel' => 'Nueva cotización',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('tesoreria_crown_cotizacion') }}" id="form-filtros-tesoreria-crown-cotizacion" class="mb-0">
                @include('caja.tesoreria_crown.cotizacion.partials.filtros_listado')
            </form>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_cotizacion',
                'exportRuta' => 'listar_tesoreria_crown_cotizacion',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th rowspan="2">Jornada</th>
                            @foreach ($tipos as $tipo)
                                <th colspan="2" class="text-center">{{ $tipo->codigo }}</th>
                            @endforeach
                            <th rowspan="2" class="text-center" style="width:70px;">Acciones</th>
                        </tr>
                        <tr>
                            @foreach ($tipos as $tipo)
                                <th class="text-center">Compra</th>
                                <th class="text-center">Venta</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td>{{ $fila['jornada_texto'] }}</td>
                                @foreach ($tipos as $tipo)
                                    @php $tasas = $fila['tasas'][$tipo->id] ?? ['compra' => null, 'venta' => null]; @endphp
                                    <td class="text-right">{{ $tasas['compra'] === null ? '' : number_format((float) $tasas['compra'], 4, ',', '.') }}</td>
                                    <td class="text-right">{{ $tasas['venta'] === null ? '' : number_format((float) $tasas['venta'], 4, ',', '.') }}</td>
                                @endforeach
                                <td class="text-center">
                                    @if (can('editar-tesoreria-crown-cotizacion', false))
                                        <a href="{{ route('editar_tesoreria_crown_cotizacion', ['sala_id' => $sala->id, 'jornada' => $fila['jornada']]) }}" class="btn-accion-tabla" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + ($tipos->count() * 2) }}" class="text-center text-muted py-4">
                                    No hay cotizaciones para esta sala.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($datas->total() > 0)
                    <span class="text-muted">{{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }}</span>
                @endif
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
