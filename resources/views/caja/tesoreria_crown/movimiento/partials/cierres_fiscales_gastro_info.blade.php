@php
    $filasFiscales = $cierresFiscales['filas'] ?? [];
    $totalX = (float) ($comprobante->total_x ?? 0);
@endphp
<div class="card card-outline card-secondary mt-2 mb-0">
    <div class="card-header py-1 px-3">
        <h3 class="card-title mb-0" style="font-size:.95rem;">Cierre X (automático)</h3>
    </div>
    <div class="card-body py-2 px-3">
        @if ($filasFiscales === [])
            <p class="small text-muted mb-0">
                El X se completa al crear la rendición desde el cierre de mozo.
                @if ($totalX != 0.0)
                    Ventas / X cargado: <strong>{{ number_format($totalX, 2, ',', '.') }}</strong>.
                @endif
                No hace falta elegir cierres fiscales acá (eso queda en hotel).
            </p>
        @else
            <p class="small text-muted mb-2">Vinculado al crear la rendición. Solo informativo.</p>
            <ul class="list-unstyled small mb-0">
                @foreach ($filasFiscales as $fila)
                    <li class="mb-1">
                        <span class="badge badge-info">{{ $fila['tipo'] }}</span>
                        #{{ $fila['numero'] }}
                        · {{ number_format((float) $fila['venta'], 2, ',', '.') }}
                        @if (trim((string) ($fila['nombre'] ?? '')) !== '')
                            · {{ $fila['nombre'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
