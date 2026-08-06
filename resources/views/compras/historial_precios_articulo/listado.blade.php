@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $coleccionLogos = collect($filas ?? [])
        ->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? '']);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalLineas = count($filas ?? []);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Historial de precios' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.25; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 6.5px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 6px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .text-center { text-align: center; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 5px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}"
                        style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">{{ $titulo ?? 'Historial de precios' }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
                @if (! empty($totales))
                    <div class="meta">
                        Artículos: {{ (int) ($totales['total_articulos'] ?? 0) }}
                        &middot; Filas: {{ (int) ($totales['total_compras'] ?? 0) }}
                        &middot; Con variación: {{ (int) ($totales['con_variacion'] ?? 0) }}
                    </div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                @if ($totalLineas > 0)
                    Líneas: {{ $totalLineas }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        @include('compras.historial_precios_articulo.partials.tabla_datos', [
            'filas' => $filas ?? [],
            'modo' => $modo ?? 'resumen',
            'para_pdf' => true,
            'puede_ver_articulo' => false,
            'puede_ver_proveedor' => false,
            'puede_ver_recepcion' => false,
            'puede_ver_ordencompra' => false,
        ])
    </table>
</body>
</html>
