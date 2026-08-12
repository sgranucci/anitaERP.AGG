@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $coleccionLogos = collect($filas ?? [])
        ->filter(fn ($f) => ($f['tipo_fila'] ?? '') === 'detalle')
        ->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? '']);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalLineas = collect($filas ?? [])
        ->filter(fn ($f) => ($f['tipo_fila'] ?? 'detalle') === 'detalle')
        ->count();
    $columnasPdf = $columnas ?? [];
    $anchoTotalPdf = collect($columnasPdf)->sum(fn ($c) => (float) ($c['ancho_pdf'] ?? 3)) ?: 1;
    $importesTotales = $totales['importes'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Proyección de pagos a proveedores' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6px; color: #1a1a1a; line-height: 1.25; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 1px 2px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 5.5px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 5px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .text-center { text-align: center; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 5px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 6px; color: #444; margin-top: 3px; }
        .grupo td { background-color: #e9ecef; font-weight: bold; }
        .subtotal td { background-color: #efefef; font-weight: bold; }
        .total td { background-color: #d6eaf8; font-weight: bold; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 30%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}"
                        style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 48%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">
                    {{ $titulo ?? 'Proyección de pagos a proveedores' }}
                </h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 6px;">
                @if ($totalLineas > 0)
                    Movimientos: {{ number_format($totalLineas, 0, ',', '.') }}<br>
                @endif
                @if ((int) ($totales['proveedores'] ?? 0) > 0)
                    Proveedores: {{ (int) $totales['proveedores'] }}<br>
                @endif
                @if (array_key_exists('total_adeudado', $importesTotales))
                    <strong>Total adeudado:
                        {{ number_format((float) $importesTotales['total_adeudado'], 2, ',', '.') }}</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        <colgroup>
            @foreach ($columnasPdf as $columna)
                <col style="width: {{ round(((float) ($columna['ancho_pdf'] ?? 3) / $anchoTotalPdf) * 100, 3) }}%;">
            @endforeach
        </colgroup>
        @include('compras.proyeccion_pagos_reporte.partials.tabla_datos', [
            'filas' => $filas ?? [],
            'columnas' => $columnasPdf,
            'para_pdf' => true,
            'puede_ver_proveedor' => false,
            'puede_ver_comprobante' => false,
            'puede_ver_ordencompra' => false,
            'puede_ver_requisicion' => false,
        ])
    </table>
</body>
</html>
