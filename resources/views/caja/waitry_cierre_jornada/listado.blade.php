@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $filas = $filas ?? [];
    $resumen = $resumen ?? [];
    $titulo = $titulo ?? 'Cierre jornada Waitry';
    $empresaNombre = $empresaNombre ?? '';
    $colspan = 12;

    $logoPdf = $esExcel ? null : EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre);

    $fmtMoney = fn ($v) => number_format((float) $v, 2, ',', '.');
    $subtitulo = trim(implode(' · ', array_filter([
        'Órdenes Waitry: '.($resumen['ordenes_waitry'] ?? 0),
        'Facturas Anita: '.($resumen['facturas_anita_waitry'] ?? 0),
        'Tramo Waitry: $'.$fmtMoney($resumen['total_waitry'] ?? 0),
        'Anita→Waitry: $'.$fmtMoney($resumen['total_anita_enviadas_waitry'] ?? 0),
        'Anita jornada: $'.$fmtMoney($resumen['total_anita_facturado'] ?? 0),
        'Dif. global: $'.$fmtMoney($resumen['diferencia_global'] ?? 0),
    ])));

    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        if ($v === null) {
            return '—';
        }
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }
        return number_format($n, 2, ',', '.');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cierre jornada Waitry</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 8px; color: #444; margin-top: 2px; }
        .resumen { font-size: 8px; color: #333; margin: 4px 0 8px; }
    </style>
</head>
<body>
@if (! $esExcel)
    <table class="listado-header">
        <tr>
            <td style="width: 22%;">
                @if ($logoPdf)
                    <img src="{{ $logoPdf['uri'] }}" alt="{{ $logoPdf['nombre'] }}" style="max-height: 52px; max-width: 160px; vertical-align: middle;">
                @endif
            </td>
            <td style="width: 78%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">{{ $titulo }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
    @if (! empty($resumen))
        <p class="resumen">{{ $subtitulo }}</p>
    @endif
@endif

<table class="data">
    @if ($esExcel)
        @if ($reservarFilaLogoExcel)
            <tbody>
                <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
            </tbody>
        @endif
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">{{ $titulo }}</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
    @endif
    <thead>
        <tr>
            <th>Orden Waitry</th>
            <th>Ref.</th>
            <th>Fecha/hora Waitry</th>
            <th class="num">Importe Waitry</th>
            <th>Pagada W.</th>
            <th>Venta Anita</th>
            <th class="num">Total Anita</th>
            <th>Medio Waitry</th>
            <th>Cta. caja esp.</th>
            <th>Cta. caja Anita</th>
            <th class="num">Diferencia</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['waitry_order_id'] }}</td>
                <td>{{ $fila['referencia_waitry'] ?: '—' }}</td>
                <td>{{ $fila['fecha_hora_waitry'] ?: ($fila['hora_waitry'] ?: '—') }}</td>
                <td class="num">{{ $fmtNum($fila['waitry_total']) }}</td>
                <td>
                    @if ($fila['waitry_paid'] === null)
                        —
                    @elseif ($fila['waitry_paid'])
                        Sí
                    @else
                        No
                    @endif
                </td>
                <td>{{ $fila['anita_codigo'] ?? ($fila['anita_venta_id'] ? '#'.$fila['anita_venta_id'] : '—') }}</td>
                <td class="num">{{ $fmtNum($fila['anita_total']) }}</td>
                <td>{{ $fila['waitry_medio_label'] ?? ($fila['anita_totem'] ? 'TOTEM' : '—') }}</td>
                <td>{{ $fila['cuentacaja_esperada_label'] ?? '—' }}</td>
                <td>{{ $fila['anita_cuentacaja_label'] ?? '—' }}</td>
                <td class="num">{{ $fmtNum($fila['diferencia']) }}</td>
                <td>{{ $fila['estado_label'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}" style="text-align:center;">Sin datos.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
