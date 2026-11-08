{{-- Barra para elegir jornada fuera del escritorio (Mesas, Bóveda, Slots, …). --}}
@php
    $ruta = $ruta ?? '';
    $queryExtra = $queryExtra ?? [];
    $fechaValor = $jornada?->fecha?->format('Y-m-d')
        ?? ($fechaPedida ?? '');
@endphp
@if (($sala ?? null) && $ruta !== '')
    <div class="card-body py-2 border-bottom bg-light">
        <div class="d-flex flex-wrap align-items-center" style="gap:.45rem;">
            <form method="get" action="{{ route($ruta) }}" class="form-inline mb-0 d-flex flex-wrap align-items-center" style="gap:.4rem;" data-enter-campos="1">
                <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                @foreach ($queryExtra as $clave => $valor)
                    @if ($valor !== null && $valor !== '')
                        <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                    @endif
                @endforeach
                <label for="tc-jornada-fecha" class="mb-0 small text-muted">Jornada</label>
                <input type="date" name="fecha" id="tc-jornada-fecha" class="form-control form-control-sm" style="width:10.5rem;"
                       value="{{ $fechaValor }}" required>
                <button type="submit" class="btn btn-sm btn-outline-primary">Ir</button>
            </form>
            @if ($jornada ?? null)
                <span class="badge badge-{{ $jornada->estaAbierta() ? 'success' : 'secondary' }}">
                    {{ $jornada->estaAbierta() ? 'Abierta' : 'Cerrada' }}
                </span>
                <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-desktop"></i> Escritorio
                </a>
            @elseif (trim((string) ($fechaPedida ?? '')) !== '')
                <span class="text-danger small">No hay jornada el {{ \Illuminate\Support\Carbon::parse($fechaPedida)->format('d/m/Y') }}.</span>
            @endif
            @if (can('listar-tesoreria-crown-jornada', false))
                <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-calendar"></i> Listado
                </a>
            @endif
        </div>
    </div>
@endif
