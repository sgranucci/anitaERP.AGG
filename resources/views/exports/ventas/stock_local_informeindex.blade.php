@php
    $medidas = $medidas ?? [];
    $filasLista = $filas ?? [];
    if ($filasLista instanceof \Illuminate\Support\Collection) {
        $filasLista = $filasLista->all();
    }
    $modoApertura = false;
    foreach ($filasLista as $f) {
        if (($f['tipo_fila'] ?? '') === 'apertura') {
            $modoApertura = true;
            break;
        }
    }
    $cols = (int) ($total_columnas ?? (5 + count($medidas) + 1));
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $cols }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $cols }}"><strong style="font-size:16pt;">{{ $titulo ?? 'Stock del local' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $cols }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $cols }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if (! empty($totales))
        <tr>
            <td colspan="{{ $cols }}">
                Filas: {{ (int) ($totales['total_filas'] ?? 0) }}
                · Grupos: {{ (int) ($totales['total_grupos'] ?? 0) }}
                · Stock total: {{ (float) ($totales['total_stock'] ?? 0) }}
                @if (! empty($totales['origen']))
                    · Origen: {{ $totales['origen'] }}
                @endif
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Color</th>
            <th>Color desc.</th>
            <th>Concepto</th>
            @foreach ($medidas as $med)
                <th>{{ (string) $med === '48' ? 'UN' : $med }}</th>
            @endforeach
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filasLista as $fila)
            @php
                $cants = $fila['cantidades'] ?? [];
            @endphp
            <tr>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['color'] ?? '' }}</td>
                <td>{{ $fila['color_desc'] ?? '' }}</td>
                <td>{{ $fila['concepto'] ?? 'Stock' }}</td>
                @foreach ($medidas as $med)
                    <td>{{ (float) ($cants[(string) $med] ?? 0) }}</td>
                @endforeach
                <td>{{ (float) ($fila['total'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
