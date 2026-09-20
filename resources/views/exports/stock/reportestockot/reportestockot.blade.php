@php
    $conFoto = ($imprimefoto ?? '') === 'CON_FOTO';
    $medidasCols = $medidas_columnas ?? [];
    $colspan = (int) ($total_columnas ?? (4 + count($medidasCols) + 7 + ($conFoto ? 1 : 0)));
    $totalParesGeneral = 0.0;
@endphp
<table>
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 14pt;">{{ $titulo ?? 'Stock por OT' }}</strong>
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            @if ($conFoto)
                <th>FOTO</th>
            @endif
            <th>LINEA</th>
            <th>ART.</th>
            <th>DESCRIPCION</th>
            @foreach ($medidasCols as $medida)
                <th>{{ $medida }}</th>
            @endforeach
            <th>PS.</th>
            <th>Q M</th>
            <th>N</th>
            <th>TT.PS.</th>
            <th>PRECIO</th>
            <th>SITUACION</th>
            <th>NUMERO OT</th>
            <th>DEPOSITO</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $lote)
            @include('exports.stock.reportestockot.imprimeunrenglon', [
                'lote' => $lote,
                'imprimefoto' => $imprimefoto,
                'medidas_columnas' => $medidasCols,
            ])
            @php
                $totalParesGeneral += (float) ($lote['total_pares'] ?? 0);
            @endphp
        @endforeach
        <tr>
            @if ($conFoto)
                <td></td>
            @endif
            <td></td>
            <td></td>
            <td></td>
            @foreach ($medidasCols as $medida)
                <td></td>
            @endforeach
            <td><strong>TOTAL</strong></td>
            <td></td>
            <td></td>
            <td align="right"><strong>{{ number_format($totalParesGeneral, 0, ',', '.') }}</strong></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>
