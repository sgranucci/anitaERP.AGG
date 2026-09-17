@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Cierre de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        collect($filas)->map(fn ($f) => (object) $f)
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
        table.data { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data thead th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .seccion td { background: #D6EAF8 !important; font-weight: bold; }
        .total td { background: #D5D8DC !important; font-weight: bold; }
        .text-right { text-align: right; }
        h3 { font-size: 11px; margin: 12px 0 4px; }
    </style>
</head>
<body>
    <table style="width:100%; margin-bottom: 8px;">
        <tr>
            <td style="width:20%;">
                @foreach ($logos as $logo)
                    @if (!empty($logo['uri']))
                        <img src="{{ $logo['uri'] }}" style="max-height:40px;">
                    @endif
                @endforeach
            </td>
            <td style="text-align:center;">
                <h2 style="margin:0;font-size:16px;">{{ $titulo }}</h2>
                <div>Generado {{ date('d/m/Y H:i') }}</div>
                <div>{{ $subtitulo }}</div>
            </td>
            <td style="width:20%;"></td>
        </tr>
    </table>

    @include('caja.cierre_caja_reporte.partials.secciones_export', [
        'resultado' => $resultado,
        'esPdf' => true,
    ])
</body>
</html>
