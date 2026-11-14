{{-- Rango Desde/Hasta solo en menú Anexos (I y VIII). El escritorio de jornada no usa este partial. --}}
@php
    $ruta = $ruta ?? '';
    $queryExtra = $queryExtra ?? [];
    $fechaDesdeValor = $fechaDesde
        ?? ($jornada?->fecha?->format('Y-m-d'))
        ?? (trim((string) ($fechaPedida ?? '')) !== '' ? $fechaPedida : null)
        ?? ($fechaSugerida ?? '');
    $fechaHastaValor = $fechaHasta
        ?? $fechaDesdeValor
        ?? '';
@endphp
@if (($sala ?? null) && $ruta !== '')
    <div class="card-body py-2 border-bottom bg-light">
        <div class="d-flex flex-wrap align-items-center" style="gap:.45rem;">
            <form method="get" action="{{ route($ruta) }}" class="form-inline mb-0 d-flex flex-wrap align-items-end" style="gap:.4rem;" data-enter-campos="1">
                <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                <input type="hidden" name="consultar" value="1">
                @foreach ($queryExtra as $clave => $valor)
                    @if ($valor !== null && $valor !== '')
                        <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                    @endif
                @endforeach
                <div>
                    <label for="tc-anexo-fecha-desde" class="mb-0 small text-muted d-block">Desde</label>
                    <input type="date" name="fecha_desde" id="tc-anexo-fecha-desde" class="form-control form-control-sm" style="width:10.5rem;"
                           value="{{ $fechaDesdeValor }}" required>
                </div>
                <div>
                    <label for="tc-anexo-fecha-hasta" class="mb-0 small text-muted d-block">Hasta</label>
                    <input type="date" name="fecha_hasta" id="tc-anexo-fecha-hasta" class="form-control form-control-sm" style="width:10.5rem;"
                           value="{{ $fechaHastaValor }}" required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Consultar</button>
            </form>
            @if (($jornadasRango ?? collect())->isNotEmpty())
                <span class="badge badge-info">
                    {{ $jornadasRango->count() }} jornada{{ $jornadasRango->count() === 1 ? '' : 's' }}
                </span>
                @if (($jornada ?? null) && $jornadasRango->count() === 1)
                    <span class="badge badge-{{ $jornada->estaAbierta() ? 'success' : 'secondary' }}">
                        {{ $jornada->estaAbierta() ? 'Abierta' : 'Cerrada' }}
                    </span>
                    <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-desktop"></i> Escritorio
                    </a>
                @endif
            @elseif (($fechaDesde ?? null) || ($fechaHasta ?? null) || trim((string) ($fechaPedida ?? '')) !== '')
                <span class="text-danger small">No hay jornadas en el rango.</span>
            @endif
            @if (can('listar-tesoreria-crown-jornada', false))
                <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-calendar"></i> Listado
                </a>
            @endif
            <span class="text-muted small ml-1">Máx. {{ \App\Support\Caja\TesoreriaCrownAnexoRangoSupport::MAX_DIAS }} días. Solo menú Anexos.</span>
        </div>
    </div>
@endif
