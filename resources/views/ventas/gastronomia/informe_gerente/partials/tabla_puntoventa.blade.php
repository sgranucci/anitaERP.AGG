<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>PV</th>
            <th>Nombre</th>
            <th class="text-right">Facturas</th>
            <th class="text-right">NC</th>
            <th class="text-right" title="Waitry pagado sin facturar en Anita (jornada abierta)">Waitry s/f</th>
            <th class="text-right">Total neto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td class="text-right">{{ $fila['cantidad_facturas'] }}</td>
                <td class="text-right">{{ $fila['cantidad_notas_credito'] }}</td>
                <td class="text-right">
                    @if (!empty($fila['waitry_sin_facturar']) && (float) $fila['waitry_sin_facturar'] > 0)
                        ${{ number_format($fila['waitry_sin_facturar'], 2, ',', '.') }}
                        @if (!empty($fila['cantidad_waitry_sin_facturar']))
                            <span class="text-muted small">({{ (int) $fila['cantidad_waitry_sin_facturar'] }})</span>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-right">${{ number_format($fila['total'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">Sin puntos de venta gastronomía activos.</td></tr>
        @endforelse
    </tbody>
</table>
