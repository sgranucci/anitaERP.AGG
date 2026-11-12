@php
    $casinos = $casinos ?? [];
    $filas = $filas ?? [];
    $totales = $totales ?? ['total' => 0, 'por_casino' => []];
    $logos = $logos ?? [];
    $colspan = 3 + count($casinos);
@endphp
<table>
    @if (!empty($logos))
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim((string) ($subtitulo ?? '')) !== '')
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ((int) ($totalDias ?? 0) > 0)
        <tr>
            <td colspan="{{ $colspan }}">{{ (int) $totalDias }} día(s) · Total {{ number_format((float) ($totales['total'] ?? 0), 2, '.', '') }}</td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Día</th>
            <th>Fecha</th>
            <th>Total</th>
            @foreach ($casinos as $casino)
                <th>{{ $casino['etiqueta'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['dia'] ?? '' }}</td>
                <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                <td>{{ number_format((float) ($fila['total'] ?? 0), 2, '.', '') }}</td>
                @foreach ($casinos as $casino)
                    @php $monto = (float) (($fila['por_casino'][(int) $casino['id']] ?? 0)); @endphp
                    <td>{{ number_format($monto, 2, '.', '') }}</td>
                @endforeach
            </tr>
        @endforeach
        @if (count($filas) > 0)
            <tr>
                <td>Total general</td>
                <td></td>
                <td>{{ number_format((float) ($totales['total'] ?? 0), 2, '.', '') }}</td>
                @foreach ($casinos as $casino)
                    @php $monto = (float) (($totales['por_casino'][(int) $casino['id']] ?? 0)); @endphp
                    <td>{{ number_format($monto, 2, '.', '') }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
