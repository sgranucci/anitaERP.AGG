@php
    $fmtImporte = static function ($valor) {
        $valor = (float) $valor;

        return abs($valor) <= 0.004 ? '' : number_format($valor, 2, ',', '.');
    };
    $horasTabla = $horas ?? [];
@endphp
<thead>
    <tr>
        <th class="text-nowrap">D&iacute;a</th>
        <th class="text-nowrap">Fecha</th>
        @foreach ($horasTabla as $hora)
            <th class="text-right">{{ str_pad((string) $hora, 2, '0', STR_PAD_LEFT) }}</th>
        @endforeach
        <th class="text-right">Total</th>
        <th class="text-right">Promedio</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas ?? [] as $fila)
        <tr>
            <td class="text-capitalize text-nowrap">{{ $fila['dia'] ?? '' }}</td>
            <td class="text-nowrap">{{ ! empty($fila['fecha']) ? \Illuminate\Support\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
            @foreach ($horasTabla as $hora)
                <td class="text-right">{{ $fmtImporte($fila['importes'][$hora] ?? 0) }}</td>
            @endforeach
            <td class="text-right font-weight-bold">{{ $fmtImporte($fila['total'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImporte($fila['promedio'] ?? 0) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($horasTabla) + 4 }}" class="text-center text-muted py-4">
                Sin jornadas para los filtros indicados.
            </td>
        </tr>
    @endforelse
</tbody>
@if (! empty($mostrar_totales ?? true))
    <tfoot>
        <tr class="table-active font-weight-bold">
            <td colspan="2" class="text-right">Total general</td>
            @foreach ($horasTabla as $hora)
                <td class="text-right">{{ $fmtImporte($totales_hora[$hora] ?? 0) }}</td>
            @endforeach
            <td class="text-right">{{ $fmtImporte($total_general ?? 0) }}</td>
            <td class="text-right">{{ $fmtImporte($promedio_hora ?? 0) }}</td>
        </tr>
    </tfoot>
@endif
