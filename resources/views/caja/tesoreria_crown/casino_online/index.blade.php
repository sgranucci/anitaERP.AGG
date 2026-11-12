@extends("theme.$theme.layout")

@section('titulo')
    Casino online diario
@endsection

@section('styles')
<style>
.co-btn-carga {
    background: linear-gradient(135deg, #8e44ad 0%, #2980b9 55%, #16a085 100%);
    border: 0;
    color: #fff !important;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(52, 73, 94, .25);
}
.co-btn-carga:hover { filter: brightness(1.06); color: #fff !important; }
.co-btn-carga .fa { margin-right: .35rem; }
</style>
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    <i class="fa fa-globe mr-1"></i> Casino online diario
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    @if (can('crear-tesoreria-crown-casino-online', false))
                        <a href="{{ route('crear_tesoreria_crown_casino_online', ['fecha' => now()->toDateString()]) }}" class="btn btn-sm co-btn-carga">
                            <i class="fa fa-cloud-upload"></i> Cargar día
                        </a>
                    @endif
                    <a href="{{ route('flash_crown') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Flash
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('tesoreria_crown_casino_online') }}" class="px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-end" style="gap:.5rem;">
                <div>
                    <label for="fecha_desde" class="mb-0 small text-muted d-block">Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" style="width:10.5rem;" value="{{ $fechaDesde }}">
                </div>
                <div>
                    <label for="fecha_hasta" class="mb-0 small text-muted d-block">Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" style="width:10.5rem;" value="{{ $fechaHasta }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
            </form>
            <div class="card-body table-responsive p-0">
                <p class="px-3 pt-2 mb-1 text-muted small">
                    Dato global (una sola carga por día): Casinos del Río, Betsur y Apuestas deportivas. Alimenta el Flash completo, la solapa de edición y Gaming.
                </p>
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th class="text-right">OL Win</th>
                            <th class="text-right">BS Win</th>
                            <th class="text-right">AD Win</th>
                            <th class="text-right">Att OL</th>
                            <th class="text-right">Att BS</th>
                            <th class="text-right">Att AD</th>
                            <th style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td>
                                    <a class="text-primary" href="{{ route('editar_tesoreria_crown_casino_online', ['id' => $fila->id]) }}">
                                        {{ $fila->fecha?->format('d/m/Y') }}
                                    </a>
                                </td>
                                <td class="text-right">{{ number_format((float) $fila->resultado, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->betsur_resultado, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->ad_resultado, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((int) $fila->attendance, 0, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((int) $fila->betsur_attendance, 0, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((int) $fila->ad_attendance, 0, ',', '.') }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-tesoreria-crown-casino-online', false))
                                        <a href="{{ route('editar_tesoreria_crown_casino_online', ['id' => $fila->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-casino-online', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_casino_online', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar la carga del {{ $fila->fecha?->format('d/m/Y') }}?');">
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
                                <td colspan="8" class="text-center text-muted py-3">Sin cargas en el rango.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($filas->hasPages())
                <div class="card-footer py-2">
                    {{ $filas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
