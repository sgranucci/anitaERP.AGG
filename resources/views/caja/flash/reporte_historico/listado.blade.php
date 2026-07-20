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
    <title>Consolidated Income</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data thead tr { background: #85C1E9; }
        table.data tbody tr:nth-child(even) { background: #f5f5f5; }
        .text-right { text-align: right; }
        h2 { font-size: 11px; margin: 10px 0 4px; }
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
            <strong style="font-size:16px;">{{ $reporte['titulo'] ?? 'Consolidated Income' }}</strong><br>
            <span>{{ $reporte['periodo'] ?? '' }}
                @if(!empty($reporte['through_day']))
                    &mdash; Through day: {{ $reporte['through_day'] }}
                @endif
            </span><br>
            <span>{{ $reporte['cantidad_dias'] ?? 0 }} d&iacute;a(s) &mdash; Generado {{ date('d/m/Y H:i') }}</span>
        </td>
        <td style="width:20%;text-align:right;">{{ $empresaObj->nombre ?? '' }}</td>
    </tr>
</table>

@if(empty($reporte['filas_diarias']))
    <p>No hay registros flash en el per&iacute;odo seleccionado.</p>
@else
    @include('caja.flash.partials.tabla_consolidated_income', [
        'reporte' => $reporte,
        'modo_pdf' => true,
    ])
@endif
</body>
</html>
