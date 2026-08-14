@php
    $filas = $bloque['filas'] ?? [];
    $listas = $bloque['listas'] ?? [];
    $error = $bloque['error'] ?? null;
    $listaAnterior = (string) ($listas['lista_anterior'] ?? '');
    $listaActual = (string) ($listas['lista_actual'] ?? '');
    $mesAnterior = (string) ($listas['mes_anterior_label'] ?? 'mes anterior');
    $mesActual = (string) ($listas['mes_actual_label'] ?? 'mes actual');
@endphp

@if ($error)
    <div class="alert alert-warning py-2 mb-0 rounded-0 border-0">
        <i class="fa fa-exclamation-triangle"></i> {{ $error }}
        <span class="small d-block">Los costos Anita no pudieron cargarse; el resto de columnas sigue disponible.</span>
    </div>
@endif

<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th class="text-right">Cant.</th>
            <th class="text-right">P. venta</th>
            <th class="text-right" title="Lista Anita {{ $listaAnterior }}">{{ $mesAnterior }}<br><span class="small font-weight-normal">({{ $listaAnterior }})</span></th>
            <th class="text-right" title="Lista Anita {{ $listaActual }}">{{ $mesActual }}<br><span class="small font-weight-normal">({{ $listaActual }})</span></th>
            <th class="text-right">Δ costo %</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php
                $pct = $fila['pct_diferencia_costo'] ?? null;
            @endphp
            <tr>
                <td>{{ $fila['posicion'] ?? '' }}</td>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td class="text-right">{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">
                    @if (($fila['precio_venta'] ?? null) !== null)
                        ${{ number_format((float) $fila['precio_venta'], 2, ',', '.') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-right">
                    @if (($fila['costo_mes_anterior'] ?? null) !== null)
                        ${{ number_format((float) $fila['costo_mes_anterior'], 2, ',', '.') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-right">
                    @if (($fila['costo_mes_actual'] ?? null) !== null)
                        ${{ number_format((float) $fila['costo_mes_actual'], 2, ',', '.') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-right">
                    @if ($pct !== null)
                        {{ $pct > 0 ? '+' : '' }}{{ number_format((float) $pct, 2, ',', '.') }}%
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted">Sin ventas en el período.</td></tr>
        @endforelse
    </tbody>
</table>
