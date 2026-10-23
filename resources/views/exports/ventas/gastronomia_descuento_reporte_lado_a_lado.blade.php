@php
    $bloques = $bloques ?? [];
    $matrices = $matrices ?? [];
    $maxFilas = (int) ($max_filas ?? 0);
    $columnasBloque = (int) ($columnas_bloque ?? 7);
    $colspanHoja = max(count($bloques), 1) * $columnasBloque;
    $encabezados = ['Artículo', 'Descripción', 'Unidades', 'Costo unit.', 'Costo total', 'Precio vta.', 'Total venta'];
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $colspanHoja }}" style="height: 52px;">&#160;</td>
        </tr>
    @endif
    <tr>
        @foreach ($bloques as $bloque)
            <td colspan="{{ $columnasBloque }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Reporte descuentos gastronomía' }}</strong>
            </td>
        @endforeach
        @if ($bloques === [])
            <td colspan="{{ $columnasBloque }}">{{ $titulo ?? 'Reporte descuentos gastronomía' }}</td>
        @endif
    </tr>
    <tr>
        @foreach ($bloques as $bloque)
            <td colspan="{{ $columnasBloque }}">Generado {{ date('d/m/Y H:i') }}</td>
        @endforeach
        @if ($bloques === [])
            <td colspan="{{ $columnasBloque }}">Sin movimientos en el período.</td>
        @endif
    </tr>
    <tr>
        @foreach ($bloques as $bloque)
            <td colspan="{{ $columnasBloque }}">{{ $bloque['titulo'] ?? '' }}</td>
        @endforeach
    </tr>
    <tr>
        @foreach ($bloques as $bloque)
            <td colspan="{{ $columnasBloque }}">{{ $subtitulo ?? '' }}</td>
        @endforeach
    </tr>
    <tr>
        @foreach ($bloques as $bloque)
            @foreach ($encabezados as $encabezado)
                <th>{{ $encabezado }}</th>
            @endforeach
        @endforeach
    </tr>
    @for ($i = 0; $i < $maxFilas; $i++)
        <tr>
            @foreach ($matrices as $matriz)
                @php $fila = $matriz[$i] ?? null; @endphp
                @if ($fila === null)
                    <td colspan="{{ $columnasBloque }}"></td>
                @else
                    @foreach ($fila as $celda)
                        <td colspan="{{ (int) ($celda['colspan'] ?? 1) }}">{{ $celda['valor'] ?? '' }}</td>
                    @endforeach
                @endif
            @endforeach
        </tr>
    @endfor
</table>
