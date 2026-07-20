@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $ventas = $ventas ?? collect();
    $colspan = 5;

    foreach ($ventas as $c) {
        $c->nombreempresa = $c->nombreempresa ?? ($c->puntoventas->empresas->nombre ?? '');
    }
    $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($ventas);
    $subtitulo = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($ventas) ? count($ventas) : 0).' registro(s)';

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
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobantes de Ventas</title>
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
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Comprobantes de Ventas</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ is_countable($ventas) ? count($ventas) : 0 }}
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
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Comprobantes de Ventas</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>{{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
    @endif
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>Cliente</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ventas as $comprobante)
            <tr>
                <td>{{ $comprobante->id ?? '' }}</td>
                <td>{{ date('d/m/Y', strtotime($comprobante->fecha ?? '')) }}</td>
                <td>
                    {{ $comprobante->tipotransacciones->nombre ?? '' }}
                    {{ $comprobante->clientes->condicionivas->letra ?? '' }}
                    {{ $comprobante->puntoventas->codigo ?? '' }}-{{ $comprobante->numerocomprobante }}
                </td>
                <td>{{ $comprobante->clientes->nombre ?? '' }}</td>
                <td class="num">{{ $fmtMonto($comprobante->total) }}</td>
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
