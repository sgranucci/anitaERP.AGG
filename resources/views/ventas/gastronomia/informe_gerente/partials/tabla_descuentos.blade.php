@php
    $filas = $datos['filas'] ?? [];
    $sin = $datos['sin_descuento'] ?? ['cantidad' => 0, 'importe' => 0];
@endphp
<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>Código</th>
            <th>Descuento</th>
            <th class="text-right">Facturas</th>
            <th class="text-right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td class="text-right">{{ $fila['cantidad'] }}</td>
                <td class="text-right">${{ number_format($fila['importe'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        @if (($sin['cantidad'] ?? 0) > 0)
            <tr class="table-active">
                <td>—</td>
                <td>Sin descuento</td>
                <td class="text-right">{{ $sin['cantidad'] }}</td>
                <td class="text-right">${{ number_format($sin['importe'], 2, ',', '.') }}</td>
            </tr>
        @endif
        @if ($filas === [] && ($sin['cantidad'] ?? 0) === 0)
            <tr><td colspan="4" class="text-center text-muted">Sin facturas en el período.</td></tr>
        @endif
    </tbody>
</table>
