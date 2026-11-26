@php
    $dh = ($formato ?? 'saldo') === 'debe_haber';
    $indices = array_keys($columnas ?? []);
    $mostrarTotal = count($indices) > 1;
    $cantCeldas = count($indices) + ($mostrarTotal ? 1 : 0);
    $colspanTotal = 2 + $cantCeldas * ($dh ? 2 : 1);
    $num = static fn (float $v): string => number_format($v, 2, '.', '');
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $colspanTotal }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspanTotal }}"><strong style="font-size: 16pt;">{{ $titulo ?? 'Totales por centro de costo' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspanTotal }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspanTotal }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ((int) ($totales['cuentas'] ?? 0) > 0)
        <tr>
            <td colspan="{{ $colspanTotal }}">
                {{ (int) ($totales['cuentas'] ?? 0) }} cuentas
                · {{ (int) ($totales['columnas'] ?? 0) }} columnas
                · Debe {{ number_format((float) ($totales['debe'] ?? 0), 2, ',', '.') }}
                · Haber {{ number_format((float) ($totales['haber'] ?? 0), 2, ',', '.') }}
                · Saldo {{ number_format((float) ($totales['saldo'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th {!! $dh ? 'rowspan="2"' : '' !!}>Nro. Cta.</th>
            <th {!! $dh ? 'rowspan="2"' : '' !!}>Descripción</th>
            @foreach ($indices as $i)
                @php $col = $columnas[$i]; @endphp
                <th {!! $dh ? 'colspan="2"' : '' !!}>{{ $col['nombre'] }}{{ $col['tipo'] === 'sin' ? '' : ($col['tipo'] === 'grupo' ? ' (Grupo '.$col['codigo'].')' : ' (CC '.$col['codigo'].')') }}</th>
            @endforeach
            @if ($mostrarTotal)
                <th {!! $dh ? 'colspan="2"' : '' !!}>Total</th>
            @endif
        </tr>
        @if ($dh)
            <tr>
                @foreach ($indices as $i)
                    <th>Debe</th>
                    <th>Haber</th>
                @endforeach
                @if ($mostrarTotal)
                    <th>Debe</th>
                    <th>Haber</th>
                @endif
            </tr>
        @endif
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php
                $tipoFila = (string) ($fila['tipo_fila'] ?? 'cuenta');
                $esDato = in_array($tipoFila, ['cuenta', 'total_nivel', 'total_empresa', 'total_general'], true);
                $sangria = in_array($tipoFila, ['titulo', 'total_nivel', 'cuenta'], true)
                    ? str_repeat("\u{00A0}\u{00A0}\u{00A0}", min(max((int) ($fila['nivel'] ?? 0) - 1, 0), 6))
                    : '';
            @endphp
            @if ($tipoFila === 'header_empresa')
                <tr>
                    <td colspan="{{ $colspanTotal }}">{{ $fila['nombre'] ?? '' }}</td>
                </tr>
            @else
                <tr>
                    <td>{{ $fila['codigo_fmt'] ?? '' }}</td>
                    <td>{{ $sangria }}{{ $fila['nombre'] ?? '' }}</td>
                    @foreach ($indices as $i)
                        @php
                            $d = (float) ($fila['debe'][$i] ?? 0);
                            $h = (float) ($fila['haber'][$i] ?? 0);
                            $hay = $esDato && (abs($d) > 0.004 || abs($h) > 0.004);
                        @endphp
                        @if ($dh)
                            <td>{{ $hay && abs($d) > 0.004 ? $num($d) : '' }}</td>
                            <td>{{ $hay && abs($h) > 0.004 ? $num($h) : '' }}</td>
                        @else
                            <td>{{ $hay ? $num($d - $h) : '' }}</td>
                        @endif
                    @endforeach
                    @if ($mostrarTotal)
                        @php
                            $td = (float) ($fila['tot_debe'] ?? 0);
                            $th = (float) ($fila['tot_haber'] ?? 0);
                            $hayTotal = $esDato && (abs($td) > 0.004 || abs($th) > 0.004);
                        @endphp
                        @if ($dh)
                            <td>{{ $hayTotal && abs($td) > 0.004 ? $num($td) : '' }}</td>
                            <td>{{ $hayTotal && abs($th) > 0.004 ? $num($th) : '' }}</td>
                        @else
                            <td>{{ $hayTotal ? $num($td - $th) : '' }}</td>
                        @endif
                    @endif
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
