@extends("theme.$theme.layout")

@section('titulo')
    Depósitos de tesorería
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
                <h3 class="card-title">Depósitos</h3>
                <div class="card-tools">
                    @include('includes.listado.filtros_toolbar', [
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios,
                        'limpiarUrl' => route('tesoreria_crown_deposito', $sala ? ['sala_id' => $sala->id] : []),
                        'placeholder' => 'Código, nombre o sala',
                        'toggleTarget' => '#panel-filtros-tesoreria-crown-deposito',
                        'formId' => 'form-filtros-tesoreria-crown-deposito',
                        'nuevoRegistroUrl' => route('crear_tesoreria_crown_deposito', $sala ? ['sala_id' => $sala->id] : []),
                        'nuevoRegistroCan' => 'crear-tesoreria-crown-deposito',
                        'nuevoRegistroLabel' => 'Nuevo depósito',
                    ])
                    @if (can('listar-tesoreria-crown-deposito', false))
                        <a href="{{ route('tesoreria_crown_tipo_deposito', $sala ? ['sala_id' => $sala->id] : []) }}"
                           class="btn btn-outline-secondary btn-sm ml-1"
                           title="Configurar tipos de depósito">
                            <i class="fa fa-cog"></i> Tipos de depósito
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_deposito',
                'exportRuta' => 'listar_tesoreria_crown_deposito',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            @include('caja.tesoreria_crown.partials.tipo_valor_chips', [
                'rutaChips' => 'tesoreria_crown_deposito',
                'paramTipo' => 'tipo_deposito_id',
                'ariaChips' => 'Tipo de depósito',
            ])
            <form id="form-filtros-tesoreria-crown-deposito" method="get" action="{{ route('tesoreria_crown_deposito') }}">
                @include('caja.tesoreria_crown.partials.filtros_deposito')
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Sala</th>
                            <th>Empresa</th>
                            <th>Activo</th>
                            <th class="text-center" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $deposito)
                            <tr>
                                <td>{{ $deposito->codigo }}</td>
                                <td>{{ $deposito->nombre }}</td>
                                <td>{{ $deposito->tipoDeposito?->etiqueta() ?? '' }}</td>
                                <td>{{ trim(($deposito->sala->codigo ?? '').' '.($deposito->sala->nombre ?? '')) }}</td>
                                <td>{{ $deposito->empresa->nombre ?? '' }}</td>
                                <td>{{ $deposito->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-center text-nowrap">
                                    @if (can('editar-tesoreria-crown-deposito', false))
                                        <a href="{{ route('editar_tesoreria_crown_deposito', $deposito->id) }}" class="btn-accion-tabla" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-deposito', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_deposito', $deposito->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este depósito?');">
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
                                <td colspan="7" class="text-center text-muted py-3">{{ $tieneCriterios ? 'No hay depósitos para este filtro.' : 'No hay depósitos en esta sala.' }}</td>
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
