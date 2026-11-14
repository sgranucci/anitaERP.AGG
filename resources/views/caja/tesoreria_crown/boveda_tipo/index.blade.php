@extends("theme.$theme.layout")

@section('titulo')
    Tipos de movimiento
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
                <h3 class="card-title">Tipos de movimiento</h3>
                <div class="card-tools">
                    @if (can('crear-tesoreria-crown-boveda-tipo', false) && $sala)
                        <a href="{{ route('crear_tesoreria_crown_boveda_tipo', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo tipo
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_boveda_tipo',
                'exportRuta' => $sala ? 'listar_tesoreria_crown_boveda_tipo' : null,
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body border-bottom py-2">
                <p class="text-muted mb-2">
                    Catálogo de tipos de movimiento de bóveda (efecto en saldo y operación).
                    Corte unificado: 01/11/2026. Los subtipos se cargan en
                    <a href="{{ route('tesoreria_crown_boveda_subtipo', array_filter(['sala_id' => $sala->id ?? null])) }}">Subtipos de movimiento</a>.
                </p>
                @if ($sala)
                    <form method="get" class="form-inline" action="{{ route('tesoreria_crown_boveda_tipo') }}">
                        <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                        <label class="mr-2 mb-0" for="filtro_vigencia">Vigencia</label>
                        <select name="filtro_vigencia" id="filtro_vigencia" class="form-control form-control-sm mr-2">
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
                            <th>Código</th>
                            <th>Abrev.</th>
                            <th>Nombre</th>
                            <th>Efecto</th>
                            <th>Operación</th>
                            <th>Rep. propina</th>
                            <th>Vig. desde</th>
                            <th>Vig. hasta</th>
                            <th>Activo</th>
                            <th class="text-left" style="width:110px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tipos as $tipo)
                            <tr>
                                <td>{{ $tipo->codigo }}</td>
                                <td>{{ $tipo->abreviatura }}</td>
                                <td>{{ $tipo->nombre }}</td>
                                <td>{{ \App\Support\Caja\TesoreriaCrownBovedaSupport::efectoTexto((string) $tipo->signo) }}</td>
                                <td>{{ $operacionesMap[(string) $tipo->operacion] ?? $tipo->operacion }}</td>
                                <td>{{ ($tipo->para_reporte_propina ?? false) ? 'Sí' : 'No' }}</td>
                                <td>{{ $tipo->vigente_desde?->format('d/m/Y') }}</td>
                                <td>{{ $tipo->vigente_hasta?->format('d/m/Y') }}</td>
                                <td>{{ $tipo->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-left text-nowrap">
                                    @if (can('listar-tesoreria-crown-boveda-subtipo', false) && $sala)
                                        <a href="{{ route('tesoreria_crown_boveda_subtipo', ['sala_id' => $sala->id, 'tipo_id' => $tipo->id]) }}" class="btn-accion-tabla tooltipsC" title="Subtipos">
                                            <i class="fa fa-sitemap"></i>
                                        </a>
                                    @endif
                                    @if (can('editar-tesoreria-crown-boveda-tipo', false))
                                        <a href="{{ route('editar_tesoreria_crown_boveda_tipo', $tipo->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-boveda-tipo', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_boveda_tipo', $tipo->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este tipo de movimiento?');">
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
                                <td colspan="10" class="text-center text-muted py-3">No hay tipos en esta sala.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
