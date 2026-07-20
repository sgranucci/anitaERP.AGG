@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $caja_movimiento = $caja_movimiento ?? collect();
    $esIguassu = config('app.empresa') === 'Iguassu Travel';
    $colspan = $esIguassu ? 10 : 9;

    $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($caja_movimiento);
    $subtitulo = (is_countable($caja_movimiento) ? count($caja_movimiento) : 0).' registro(s)';

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
    $fmtTexto = function ($v) use ($esExcel, $formatoNumero) {
        $n = (float) $v;
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
    <title>Ingresos y Egresos</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        table.data ul { margin: 0; padding-left: 14px; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 8px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
@if (! $esExcel)
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Ingresos y Egresos</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ is_countable($caja_movimiento) ? count($caja_movimiento) : 0 }}
            </td>
        </tr>
    </table>
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
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Ingresos y Egresos</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
    @endif
    <thead>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>N&uacute;mero</th>
            <th>Fecha</th>
            <th>Tipo de transacci&oacute;n</th>
            <th>Concepto</th>
            <th>Detalle</th>
            @if ($esIguassu)
                <th>Orden de servicio</th>
            @endif
            <th class="num">Monto en $</th>
            <th>Movimientos</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($caja_movimiento as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombreempresa }}</td>
                <td>{{ $data->numerotransaccion }}</td>
                <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
                <td>{{ $data->nombretipotransaccion_caja }}</td>
                <td>{{ $data->nombreconceptogasto ?? '' }}</td>
                <td>{{ $data->detalle ?? '' }}</td>
                @if ($esIguassu)
                    <td>{{ $data->ordenservicio_id }}</td>
                @endif
                <td class="num">
                    @php
                        $totalIngreso = 0;
                        $totalEgreso = 0;
                        foreach ($data->caja_movimiento_cuentacajas as $movimiento) {
                            $coef = $movimiento->moneda_id > 1 ? $movimiento->cotizacion : 1.0;
                            $totalIngreso += ($movimiento->monto > 0 ? $movimiento->monto * $coef : 0);
                            $totalEgreso += ($movimiento->monto < 0 ? abs($movimiento->monto * $coef) : 0);
                        }
                        $montoFila = $totalIngreso != 0 ? $totalIngreso : $totalEgreso;
                    @endphp
                    {{ $fmtMonto($montoFila) }}
                </td>
                <td>
                    <ul>
                        @foreach ($data->caja_movimiento_cuentacajas as $movimiento)
                            <li>{{ $movimiento->cuentacajas->nombre }} {{ $movimiento->monto != 0 ? $fmtTexto($movimiento->monto) : '' }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}" style="text-align:center;">Sin registros</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
