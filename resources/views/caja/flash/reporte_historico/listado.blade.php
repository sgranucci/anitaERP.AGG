@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $empresaObj = $reporte['empresa'] ?? null;
    $filaLogo = (object) ['nombreempresa' => $empresaObj->nombre ?? ''];
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([$filaLogo]));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Flash histórico</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data thead tr { background: #85C1E9; }
        table.data tbody tr:nth-child(even) { background: #f5f5f5; }
        .text-right { text-align: right; }
        h2 { font-size: 12px; margin: 10px 0 4px; }
        .header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .header td { border: none; vertical-align: middle; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td style="width:35%">
            @foreach($logosCabecera as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:50px;max-width:160px;margin-right:8px;">
            @endforeach
        </td>
        <td style="width:45%;text-align:center;">
            <strong style="font-size:18px;">Flash Report (histórico)</strong><br>
            <span>{{ $reporte['periodo'] ?? '' }} &mdash; {{ $reporte['cantidad_dias'] ?? 0 }} d&iacute;a(s)</span><br>
            <span>Generado {{ date('d/m/Y H:i') }}</span>
        </td>
        <td style="width:20%;text-align:right;">{{ $empresaObj->nombre ?? '' }}</td>
    </tr>
</table>

@if(!empty($reporte['filas_diarias']))
<h2>Detalle por d&iacute;a</h2>
<table class="data">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Att</th>
            <th>Slot win</th>
            <th>Rul win</th>
            <th>Bingo res.</th>
            <th>AyB</th>
            <th>Estac.</th>
            <th>Gaming</th>
            <th>Revenues</th>
        </tr>
    </thead>
    <tbody>
        @foreach($reporte['filas_diarias'] as $dia)
        <tr>
            <td>{{ $dia['fecha'] }}</td>
            <td class="text-right">{{ $dia['attendance'] ?? '' }}</td>
            <td class="text-right">{{ number_format($dia['slot_win'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($dia['rul_win'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($dia['bingo_win'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $dia['flash']->ayb, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $dia['flash']->estac, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($dia['total_gaming'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($dia['total_revenues'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if(($reporte['cantidad_dias'] ?? 0) > 0)
<h2>Totales consolidados</h2>
@include('caja.flash.partials.contenido_reporte', $reporte)
@endif
</body>
</html>
