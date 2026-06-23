@php
    $fmtMonto = static fn ($v) => '$ '.number_format((float) $v, 2, ',', '.');
@endphp
<table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
    <thead style="background-color:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha pago</th>
            <th>Fecha emisión</th>
            <th class="text-right">Monto</th>
            <th>Terminal</th>
            <th>Número</th>
            @if ($pantalla ?? false)
                <th>Origen</th>
                <th>Estado</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila->fecha_pago ? $fila->fecha_pago->format('d/m/Y H:i') : '' }}</td>
                <td>{{ $fila->fecha_emision ? $fila->fecha_emision->format('d/m/Y H:i') : '' }}</td>
                <td class="text-right">
                    @if ($fila->monto !== null)
                        {{ $fmtMonto($fila->monto) }}
                    @endif
                </td>
                <td>{{ $fila->terminal }}</td>
                <td>{{ $fila->numero }}</td>
                @if ($pantalla ?? false)
                    <td><small>{{ $fila->origen }}</small></td>
                    <td><small>{{ str_replace('_', ' ', $fila->estado_conciliacion) }}</small></td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ ($pantalla ?? false) ? 7 : 5 }}" class="text-center text-muted">Sin registros.</td>
            </tr>
        @endforelse
    </tbody>
</table>
