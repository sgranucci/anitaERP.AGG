@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $coleccionLogo = ! empty($empresa_nombre)
        ? collect([(object) ['nombreempresa' => $empresa_nombre]])
        : collect();
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogo);
    $tituloReporte = $titulo ?? 'Informe gerente gastronomía';
    $subtituloReporte = $subtitulo ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 2px; }
        h3.seccion { font-size: 10px; margin: 10px 0 4px; color: #1B4F72; }
        table.data { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
        table.data th, table.data td {
            border: 1px solid #cccccc;
            padding: 3px 4px;
            font-size: 7px;
        }
        table.data thead tr { background-color: #85C1E9; color: #17202A; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .resumen { margin: 6px 0 10px; font-size: 9px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 44px; max-width: 130px; margin-right: 6px;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 13px;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtituloReporte !== '')
                    <div class="meta">{{ $subtituloReporte }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                Total neto<br>
                <strong>${{ number_format((float) ($informe['total_ventas_periodo'] ?? $informe['total_ventas_jornada'] ?? 0), 2, ',', '.') }}</strong>
            </td>
        </tr>
    </table>

    @include('ventas.gastronomia.informe_gerente.partials.export_pdf_bloques', [
        'informe' => $informe ?? [],
    ])
</body>
</html>
