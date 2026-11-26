@php
    $resumen = ! empty($solo_resumen);
    $multi = ! empty($multiempresa);
    $colsTexto = $resumen ? 3 + ($multi ? 1 : 0) : 7 + ($multi ? 1 : 0);
    $colspanTotal = $colsTexto + 3;
    $num = static fn (float $v): string => number_format($v, 2, '.', '');
    $fmtFecha = static fn (string $f): string => strlen($f) >= 10 ? substr($f, 8, 2).'/'.substr($f, 5, 2).'/'.substr($f, 0, 4) : $f;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $colspanTotal }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspanTotal }}"><strong style="font-size: 16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspanTotal }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspanTotal }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspanTotal }}">
            {{ (int) ($totales['cuentas'] ?? 0) }} cuentas
            · {{ (int) ($totales['movimientos'] ?? 0) }} movimientos sin centro de costo
            · Debe {{ number_format((float) ($totales['debe'] ?? 0), 2, ',', '.') }}
            · Haber {{ number_format((float) ($totales['haber'] ?? 0), 2, ',', '.') }}
            · Saldo {{ number_format((float) ($totales['saldo'] ?? 0), 2, ',', '.') }}
        </td>
    </tr>
    <thead>
        <tr>
            @if ($multi)
                <th>Empresa</th>
            @endif
            @if ($resumen)
                <th>Cuenta</th>
                <th>Descripción</th>
                <th>Movs.</th>
            @else
                <th>Fecha</th>
                <th>Asiento</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Comprobante</th>
                <th>Descripción</th>
                <th>Mon.</th>
            @endif
            <th>Debe</th>
            <th>Haber</th>
            <th>Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php
                $tipoFila = (string) ($fila['tipo_fila'] ?? '');
                $d = (float) ($fila['debe'] ?? 0);
                $h = (float) ($fila['haber'] ?? 0);
            @endphp
            @if ($tipoFila === 'cuenta')
                <tr>
                    <td colspan="{{ $colspanTotal }}">{{ $fila['codigo_fmt'] }} {{ $fila['nombre'] }}{{ ($fila['empresa_nombre'] ?? '') !== '' ? ' · '.$fila['empresa_nombre'] : '' }} ({{ (int) $fila['cantidad'] }} sin centro de costo)</td>
                </tr>
            @elseif ($tipoFila === 'movimiento')
                <tr>
                    @if ($multi)
                        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                    @endif
                    <td>{{ $fmtFecha((string) ($fila['fecha'] ?? '')) }}</td>
                    <td>{{ $fila['numeroasiento'] }}</td>
                    <td>{{ $fila['tipo_asiento'] ?? '' }}</td>
                    <td>{{ $fila['origen'] ?? '' }}</td>
                    <td>{{ $fila['comprobante'] ?? '' }}</td>
                    <td>{{ $fila['descripcion'] ?? '' }}{{ ! empty($fila['centrocosto_id_huerfano']) ? ' [CC inexistente #'.$fila['centrocosto_id_huerfano'].']' : '' }}</td>
                    <td>{{ $fila['moneda'] ?? '' }}</td>
                    <td>{{ abs($d) > 0.004 ? $num($d) : '' }}</td>
                    <td>{{ abs($h) > 0.004 ? $num($h) : '' }}</td>
                    <td></td>
                </tr>
            @elseif ($tipoFila === 'resumen')
                <tr>
                    @if ($multi)
                        <td>{{ $fila['empresa_nombre'] !== '' ? $fila['empresa_nombre'] : 'Consolidado' }}</td>
                    @endif
                    <td>{{ $fila['codigo_fmt'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ (int) $fila['cantidad'] }}</td>
                    <td>{{ abs($d) > 0.004 ? $num($d) : '' }}</td>
                    <td>{{ abs($h) > 0.004 ? $num($h) : '' }}</td>
                    <td>{{ $num($d - $h) }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="{{ $colsTexto }}">{{ $tipoFila === 'total_general' ? 'Total general · '.(int) ($fila['cantidad'] ?? 0).' movimientos' : 'Total '.$fila['codigo_fmt'].' '.$fila['nombre'] }}</td>
                    <td>{{ abs($d) > 0.004 ? $num($d) : '' }}</td>
                    <td>{{ abs($h) > 0.004 ? $num($h) : '' }}</td>
                    <td>{{ $num($d - $h) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
