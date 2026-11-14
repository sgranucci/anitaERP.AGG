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
                <p class="text-muted mb-2">
                    Subtipos por tipo de movimiento. Si el tipo tiene subtipos vigentes en la fecha de jornada, al cargar hay que elegir uno.
                    @if ($tipoFiltro ?? null)
                        <span class="d-block mt-1">
                            Filtrado por tipo
                            <strong>{{ $tipoFiltro->abreviatura }} — {{ $tipoFiltro->nombre }}</strong>.
                            <a href="{{ route('tesoreria_crown_boveda_subtipo', ['sala_id' => $sala->id ?? 0]) }}">Ver todos</a>
                        </span>
                    @endif
                </p>
                @if ($sala)
                    <form method="get" class="form-inline" action="{{ route('tesoreria_crown_boveda_subtipo') }}">
                        <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                        @if (($tipoId ?? 0) > 0)
                            <input type="hidden" name="tipo_id" value="{{ $tipoId }}">
                        @endif
                        <label class="mr-2 mb-0" for="filtro_vigencia_st">Vigencia</label>
                        <select name="filtro_vigencia" id="filtro_vigencia_st" class="form-control form-control-sm mr-2">
                            <option value="vigentes" @selected(($filtroVigencia ?? 'vigentes') === 'vigentes')>Vigentes en fecha</option>
                            <option value="vencidos" @selected(($filtroVigencia ?? '') === 'vencidos')>Vencidos</option>
                            <option value="futuros" @selected(($filtroVigencia ?? '') === 'futuros')>Futuros</option>
                            <option value="todos" @selected(($filtroVigencia ?? '') === 'todos')>Todos</option>
                        </select>
                        <input type="date" name="fecha_vigencia" class="form-control form-control-sm mr-2"
                               value="{{ $fechaVigencia ?? now()->toDateString() }}">
                        <button type="submit" class="btn btn-sm btn-outline-info">Aplicar</button>
                    </form>
                @endif
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Tipo</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Billetaje</th>
                            <th>Efecto</th>
                            <th>Vig. desde</th>
                            <th>Vig. hasta</th>
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
                                <td>{{ ($subtipo->mueve_billetaje ?? true) ? 'Sí' : 'No (informativo)' }}</td>
                                <td>
                                    @if ($subtipo->signo !== null && $subtipo->signo !== '')
                                        {{ \App\Support\Caja\TesoreriaCrownBovedaSupport::efectoTexto((string) $subtipo->signo) }}
                                    @else
                                        Hereda
                                    @endif
                                </td>
                                <td>{{ $subtipo->vigente_desde?->format('d/m/Y') }}</td>
                                <td>{{ $subtipo->vigente_hasta?->format('d/m/Y') }}</td>
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
                                <td colspan="9" class="text-center text-muted py-3">No hay subtipos en esta sala.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
