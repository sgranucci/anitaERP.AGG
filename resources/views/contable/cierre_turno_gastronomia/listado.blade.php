@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $filas = $filas ?? collect();
    $subtituloFiltros = $subtituloFiltros ?? '';
    $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $colspan = 10;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cierres gastro</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 4px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
@if ($esExcel)
    <table>
        @if ($reservarFilaLogoExcel)
            <tbody>
                <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
            </tbody>
        @endif
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Cierres de turno gastronom&iacute;a (Contable)</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtituloFiltros }}</strong></td>
            </tr>
        </tbody>
    </table>
@else
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 150px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 13px; font-weight: bold;">Cierres de turno gastronom&iacute;a (Contable)</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtituloFiltros }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 7px;">
                Registros: {{ $filas->count() }}
            </td>
        </tr>
    </table>
@endif

<table class="data">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Fecha / hora</th>
            <th>Referencia</th>
            <th>Empresa</th>
            <th>PC</th>
            <th>Punto venta</th>
            <th>Turno</th>
            <th>Jornada</th>
            <th>Usuario</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>{{ $f->tipo_etiqueta }}</td>
                <td>{{ $f->fecha_hora }}</td>
                <td>{{ $f->referencia }}</td>
                <td>{{ $f->nombreempresa }}</td>
                <td>{{ $f->identificador_pc }}</td>
                <td>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</td>
                <td>{{ $f->turno_nombre }}</td>
                <td>{{ $f->fecha_jornada }}</td>
                <td>{{ $f->usuario }}</td>
                <td class="num">{{ number_format((float) $f->total, 2, ',', '.') }}</td>
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
