@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filas = $resultado['filas'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $meta = $resultado['meta'] ?? [];
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        collect($filas)->map(fn ($f) => (object) ['nombreempresa' => $f['nombre_empresa'] ?? ''])
    );
    $titulo = 'Revalúo contable de bienes de uso';
    $subtitulo = 'Al '.(\Carbon\Carbon::parse($meta['fecha_revaluo'] ?? now())->format('d/m/Y'));
    if (! empty($meta['indice_nombre'])) {
        $subtitulo .= ' — Índice: '.$meta['indice_nombre'];
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data tbody tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'colspan' => 12,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => count($filas),
        ])
        <tr class="columnas">
            <th>Código</th>
            <th>Descripción</th>
            <th>V.U.</th>
            <th>Trans.</th>
            <th>V. origen</th>
            <th>Amort. acum.</th>
            <th>Amort. ej.</th>
            <th>Amort. mens.</th>
            <th>V. residual</th>
            <th>Coef.</th>
            <th>V. actual</th>
            <th>Incr. revalúo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f['codigo_bien'] }}</td>
                <td>{{ $f['descripcion'] }}</td>
                <td class="num">{{ $f['vida_util'] }}</td>
                <td class="num">{{ $f['vida_util_transcurrida'] }}</td>
                <td class="num">{{ number_format((float) $f['valor_origen_cierre'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['amort_acum_inicio'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['amort_ejercicio'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['amort_mensual'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['valor_residual_contable'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['coef_act'], 3, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['valor_actual'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $f['incr_revaluo'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
