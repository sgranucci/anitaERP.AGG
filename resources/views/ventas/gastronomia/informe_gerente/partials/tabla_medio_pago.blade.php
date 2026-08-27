@php
    $filas = $filas ?? [];
    $total = 0.0;
    $cantidad = 0;
    foreach ($filas as $fila) {
        $total += (float) ($fila['total'] ?? 0);
        $cantidad += (int) ($fila['cantidad'] ?? 0);
    }
@endphp
<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>Código</th>
            <th>Medio de pago</th>
            <th class="text-right">Cobranzas</th>
            <th class="text-right">%</th>
            <th class="text-right">Total cobrado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] !== '' ? $fila['codigo'] : '—' }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad'] ?? 0) }}</td>
                <td class="text-right">{{ number_format((float) ($fila['porcentaje'] ?? 0), 1, ',', '.') }}%</td>
                <td class="text-right">${{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Sin cobranzas con medio de pago en el período.</td></tr>
        @endforelse
    </tbody>
    @if ($filas !== [])
        <tfoot>
            <tr class="table-active">
                <th colspan="2">Total</th>
                <th class="text-right">{{ $cantidad }}</th>
                <th class="text-right">100%</th>
                <th class="text-right">${{ number_format($total, 2, ',', '.') }}</th>
            </tr>
        </tfoot>
    @endif
</table>
