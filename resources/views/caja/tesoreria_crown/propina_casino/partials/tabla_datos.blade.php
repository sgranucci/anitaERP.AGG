@php
    $coleccion = $filas instanceof \Illuminate\Support\Collection || $filas instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $filas
        : collect($filas);
    $casinos = $casinos ?? [];
    $totales = $totales ?? ['total' => 0, 'por_casino' => []];
    $mostrarTotales = ! empty($mostrarTotales);
    $colspan = 3 + count($casinos);
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Día</th>
                <th>Fecha</th>
                <th class="text-right">Total</th>
                @foreach ($casinos as $casino)
                    <th class="text-right">{{ $casino['etiqueta'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($coleccion as $fila)
                <tr>
                    <td>{{ $fila['dia'] ?? '' }}</td>
                    <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
                    @foreach ($casinos as $casino)
                        @php $monto = (float) (($fila['por_casino'][(int) $casino['id']] ?? 0)); @endphp
                        <td class="text-right">{{ number_format($monto, 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center text-muted py-3">Sin propinas para el período.</td>
                </tr>
            @endforelse
            @if ($mostrarTotales && $coleccion->count() > 0)
                <tr class="font-weight-bold" style="background:#AED6F1;">
                    <td colspan="2">Total general</td>
                    <td class="text-right">{{ number_format((float) ($totales['total'] ?? 0), 2, ',', '.') }}</td>
                    @foreach ($casinos as $casino)
                        @php $monto = (float) (($totales['por_casino'][(int) $casino['id']] ?? 0)); @endphp
                        <td class="text-right">{{ number_format($monto, 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</div>
