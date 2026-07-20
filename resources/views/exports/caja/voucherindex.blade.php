@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $vouchers = $vouchers ?? collect();
    $colspan = 12;

    foreach ($vouchers as $v) {
        $v->nombreempresa = $v->nombreempresa ?? '';
    }
    $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($vouchers);
    $subtitulo = (is_countable($vouchers) ? count($vouchers) : 0).' registro(s)';

    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    // Monto de columna propia (K): número real adaptable en Excel.
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
    // Montos embebidos en texto (columna Guías): siempre texto formateado.
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
    <title>Reporte de Vouchers</title>
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
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Vouchers</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ is_countable($vouchers) ? count($vouchers) : 0 }}
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
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Vouchers</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
    @endif
    <thead>
        <tr>
            <th>ID</th>
            <th>N&uacute;mero</th>
            <th>Fecha</th>
            <th>Talonario Vouchers</th>
            <th>PAX</th>
            <th>Reserva</th>
            <th>Cantidad</th>
            <th>Proveedor</th>
            <th>Servicio</th>
            <th>Forma de pago</th>
            <th class="num">Monto Voucher</th>
            <th>Gu&iacute;as</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($vouchers as $data)
            <tr data-entry-id="{{ $data->id }}">
                <td>{{ $data->id }}</td>
                <td>{{ $data->idtalonario }}_{{ $data->numerovoucher }}</td>
                <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
                <td>{{ $data->nombretalonario }}</td>
                <td>{{ $data->nombrepasajero }}</td>
                <td>{{ $data->numeroreserva }}</td>
                <td>{{ $data->pax + $data->paxfree + $data->incluido + $data->opcional }}</td>
                <td>{{ $data->nombreproveedor ?? '' }}</td>
                <td>{{ $data->nombreservicio ?? '' }}</td>
                <td>{{ $data->nombreformapago ?? '' }}</td>
                <td class="num">{{ $fmtMonto($data->montovoucher) }}</td>
                <td>
                    <ul>
                        @foreach ($data->voucher_guias as $guia)
                            <li>{{ $guia->guias->nombre }} Porc. {{ $fmtTexto($guia->porcentajecomision) }} Comis. {{ $fmtTexto($guia->montocomision) }}</li>
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
