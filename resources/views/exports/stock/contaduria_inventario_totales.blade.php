@php
    $rubros = $rubros ?? [];
    $depositos = $depositos ?? [];
    $columnas = count($rubros);
    $colspan = 1 + ($columnas * 2) + 1;
    $sumas = [];
    foreach ($rubros as $rubro) {
        $sumas[$rubro['clave']] = 0.0;
    }
    $sumaGeneral = 0.0;
@endphp
<table>
    <tr>
        <td colspan="{{ $colspan }}"><strong>DEPÓSITOS - TOTALES</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">MES: {{ $mes ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">{{ $empresa ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}"></td>
    </tr>
    <tr>
        <th></th>
        @foreach ($rubros as $rubro)
            <th>SECTOR</th>
            <th>COSTO</th>
        @endforeach
        <th>TOTALES</th>
    </tr>
    @foreach ($depositos as $deposito)
        @php
            $totalFila = 0.0;
        @endphp
        <tr>
            <td>DEPÓSITO {{ $deposito['codigo'] ?? '' }}</td>
            @foreach ($rubros as $rubro)
                @php
                    $valor = round((float) ($rubro['valores'][$deposito['id']] ?? 0), 2);
                    $totalFila = round($totalFila + $valor, 2);
                    $sumas[$rubro['clave']] = round($sumas[$rubro['clave']] + $valor, 2);
                @endphp
                <td>{{ mb_strtoupper((string) ($rubro['titulo'] ?? '')) }}</td>
                <td>{{ number_format($valor, 2, '.', '') }}</td>
            @endforeach
            @php
                $sumaGeneral = round($sumaGeneral + $totalFila, 2);
            @endphp
            <td>{{ number_format($totalFila, 2, '.', '') }}</td>
        </tr>
    @endforeach
    <tr>
        <td></td>
        @foreach ($rubros as $rubro)
            <td></td>
            <td>{{ number_format((float) ($sumas[$rubro['clave']] ?? 0), 2, '.', '') }}</td>
        @endforeach
        <td>{{ number_format($sumaGeneral, 2, '.', '') }}</td>
    </tr>
</table>
