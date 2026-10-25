@extends("theme.$theme.layout")

@section('titulo')
    Valores de tesorería
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}?v={{ filemtime(public_path('assets/pages/scripts/includes/listado-filtros.js')) }}" type="text/javascript"></script>
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
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Valores</h3>
                <div class="card-tools">
                    @include('includes.listado.filtros_toolbar', [
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios,
                        'limpiarUrl' => route('tesoreria_crown_valor', $sala ? ['sala_id' => $sala->id] : []),
                        'placeholder' => 'Serie, descripción o tipo',
                        'toggleTarget' => '#panel-filtros-tesoreria-crown-valor',
                        'formId' => 'form-filtros-tesoreria-crown-valor',
                        'nuevoRegistroUrl' => route('crear_tesoreria_crown_valor', $sala ? ['sala_id' => $sala->id] : []),
                        'nuevoRegistroCan' => 'crear-tesoreria-crown-valor',
                        'nuevoRegistroLabel' => 'Nuevo valor',
                    ])
                    @if (can('listar-tesoreria-crown-valor', false))
                        <a href="{{ route('tesoreria_crown_tipo_valor', $sala ? ['sala_id' => $sala->id] : []) }}"
                           class="btn btn-outline-secondary btn-sm ml-1"
                           title="Configurar tipos de valor">
                            <i class="fa fa-cog"></i> Tipos de valor
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_valor',
                'exportRuta' => 'listar_tesoreria_crown_valor',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            @include('caja.tesoreria_crown.partials.tipo_valor_chips')
            <form id="form-filtros-tesoreria-crown-valor" method="get" action="{{ route('tesoreria_crown_valor') }}">
                @include('caja.tesoreria_crown.partials.filtros_valor')
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Tipo</th>
                            <th>Serie</th>
                            <th>Descripción</th>
                            <th class="text-right">Denominación</th>
                            <th>Controla stock</th>
                            <th>Activo</th>
                            <th class="text-center" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $valor)
                            <tr>
                                <td>{{ $valor->tipoValor?->etiqueta() ?? '' }}</td>
                                <td>{{ $valor->serie }}</td>
                                <td>{{ $valor->descripcion }}</td>
                                <td class="text-right">{{ number_format((float) $valor->denominacion, 2, ',', '.') }}</td>
                                <td>{{ ($valor->tipoValor && $valor->tipoValor->controla_stock) ? 'Sí' : 'No' }}</td>
                                <td>{{ $valor->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-center text-nowrap">
                                    @if (can('editar-tesoreria-crown-valor', false))
                                        <a href="{{ route('editar_tesoreria_crown_valor', $valor->id) }}" class="btn-accion-tabla" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-valor', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_valor', $valor->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este valor?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">{{ $tieneCriterios ? 'No hay valores para este filtro.' : 'No hay valores en esta sala.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($datas->total() > 0)
                    <span class="text-muted">{{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }}</span>
                @endif
                {{ $datas->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
