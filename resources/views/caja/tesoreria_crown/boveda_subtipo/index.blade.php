@extends("theme.$theme.layout")

@section('titulo')
    Subtipos de movimiento
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
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
                <h3 class="card-title">Subtipos de movimiento</h3>
                <div class="card-tools">
                    @if (can('crear-tesoreria-crown-boveda-subtipo', false) && $sala)
                        <a href="{{ route('crear_tesoreria_crown_boveda_subtipo', array_filter([
                            'sala_id' => $sala->id,
                            'tipo_id' => ($tipoId ?? 0) > 0 ? $tipoId : null,
                        ])) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo subtipo
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_boveda_subtipo',
                'queryExtra' => ($tipoId ?? 0) > 0 ? ['tipo_id' => $tipoId] : [],
                'exportRuta' => $sala ? 'listar_tesoreria_crown_boveda_subtipo' : null,
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body border-bottom py-2">
                <p class="text-muted mb-0">
                    Subtipos opcionales por tipo de movimiento de bóveda (p. ej. OEGR → Devolución en sala / Envío a otro casino).
                    Si el tipo tiene subtipos activos, al cargar el movimiento hay que elegir uno.
                    @if ($tipoFiltro ?? null)
                        <span class="d-block mt-1">
                            Filtrado por tipo
                            <strong>{{ $tipoFiltro->abreviatura }} — {{ $tipoFiltro->nombre }}</strong>.
                            <a href="{{ route('tesoreria_crown_boveda_subtipo', ['sala_id' => $sala->id ?? 0]) }}">Ver todos</a>
                        </span>
                    @endif
                </p>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Tipo</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Activo</th>
                            <th class="text-left" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subtipos as $subtipo)
                            <tr>
                                <td>{{ $subtipo->tipo?->etiqueta() ?? '—' }}</td>
                                <td>{{ $subtipo->codigo }}</td>
                                <td>{{ $subtipo->nombre }}</td>
                                <td>{{ $subtipo->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-left text-nowrap">
                                    @if (can('editar-tesoreria-crown-boveda-subtipo', false))
                                        <a href="{{ route('editar_tesoreria_crown_boveda_subtipo', $subtipo->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-boveda-subtipo', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_boveda_subtipo', $subtipo->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este subtipo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No hay subtipos en esta sala.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
