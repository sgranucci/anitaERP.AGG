@php
    $filaLogo = $reservarFilaLogoExcel ?? false;
    $filaTitulo = $filaLogo ? 2 : 1;
    $filaCabecera = $filaLogo ? 4 : 3;
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtMonto = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }
        return number_format($n, 2, ',', '.');
    };
    $columnasFijas = empty($clasificar_por_host) ? 7 : 8;
    $colspanTotal = $columnasFijas + count($resultado['columnas'] ?? []);
    $cortarJurisdiccion = ! empty($cortar_por_jurisdiccion);
    $jurisdiccionAnterior = null;
@endphp
<table>
    @if ($filaLogo)
        <tr>
            <td colspan="{{ $colspanTotal }}"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspanTotal }}" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'IVA VENTAS' }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspanTotal }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <th>Cliente</th>
        <th>Nombre</th>
        <th>CUIT</th>
        <th>Fecha</th>
        <th>PV</th>
        @if (! empty($clasificar_por_host))
            <th>Host</th>
        @endif
        <th>Tipo</th>
        <th>Comprobante</th>
        @foreach ($resultado['columnas'] ?? [] as $col)
            <th>{{ $col['label'] }}</th>
        @endforeach
    </tr>
    @foreach ($filas as $fila)
        @php
            $jurClave = (string) ((int) ($fila['provincia_id'] ?? 0));
            $jurLabel = (string) ($fila['provincia_label'] ?? 'Sin jurisdicción');
        @endphp
        @if ($cortarJurisdiccion && $jurClave !== $jurisdiccionAnterior)
            <tr>
                <td colspan="{{ $colspanTotal }}" style="font-weight: bold; background-color: #fdebd0;">
                    Jurisdicción: {{ $jurLabel }}
                </td>
            </tr>
            @php $jurisdiccionAnterior = $jurClave; @endphp
        @endif
        <tr>
            <td>{{ $fila['cliente_codigo'] ?? '' }}</td>
            <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
            <td>{{ $fila['cuit'] ?? '' }}</td>
            <td>{{ $fila['fecha_mov'] ?? '' }}</td>
            <td>{{ $fila['puntoventa_codigo'] ?? '' }}</td>
            @if (! empty($clasificar_por_host))
                <td>{{ $fila['host'] ?? '' }}</td>
            @endif
            <td>{{ $fila['tipo'] ?? '' }}</td>
            <td>{{ $fila['comprobante'] ?? '' }}</td>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <td>{{ $fmtMonto($fila['columnas'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endforeach
    @if (! empty($resultado['totales_general']))
        <tr>
            <td colspan="{{ empty($clasificar_por_host) ? 7 : 8 }}">TOTAL GENERAL</td>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <td>{{ $fmtMonto($resultado['totales_general'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endif
    @if ($cortarJurisdiccion && ! empty($resultado['totales_por_jurisdiccion']))
        <tr>
            <td colspan="{{ $colspanTotal }}" style="font-weight: bold;">Totales por jurisdicción</td>
        </tr>
        <tr>
            <th>Jurisdicción</th>
            <th>Comp.</th>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <th>{{ $col['label'] }}</th>
            @endforeach
            @for ($i = 0; $i < max(0, $columnasFijas - 2); $i++)
                <th></th>
            @endfor
        </tr>
        @foreach ($resultado['totales_por_jurisdiccion'] as $tot)
            <tr>
                <td>{{ $tot['provincia_label'] ?? 'Sin jurisdicción' }}</td>
                <td>{{ (int) ($tot['cantidad'] ?? 0) }}</td>
                @foreach ($resultado['columnas'] ?? [] as $col)
                    <td>{{ $fmtMonto($tot['columnas'][$col['key']] ?? 0) }}</td>
                @endforeach
                @for ($i = 0; $i < max(0, $columnasFijas - 2); $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    @endif
</table>
