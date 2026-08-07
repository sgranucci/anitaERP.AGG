@php
    $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        collect($filas)->map(fn (array $f) => (object) ['nombreempresa' => (string) ($f['empresa'] ?? '')])
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 18px 14px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #17202A; }
        .cabecera { width: 100%; margin-bottom: 8px; }
        .cabecera td { vertical-align: middle; }
        .titulo { font-size: 13px; font-weight: bold; }
        .meta { font-size: 8px; color: #444444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data thead th { background: #85C1E9; color: #17202A; font-size: 7px; }
        table.data tbody tr:nth-child(even) td { background: #f5f5f5; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td style="width: 22%;">
                @foreach ($logos as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 38px; max-width: 140px; margin-right: 8px;">
                @endforeach
            </td>
            <td>
                <div class="titulo">{{ $titulo }}</div>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
                <div class="meta">
                    {{ count($filas) }} contrato(s)
                    @if (! empty($totales))
                        · Vencidos: {{ (int) ($totales['vencidos'] ?? 0) }}
                        · Vencen en 30 días: {{ (int) ($totales['vencen_30'] ?? 0) }}
                        · Tope: {{ number_format((float) ($totales['monto_tope'] ?? 0), 2, ',', '.') }}
                        · Recibido: {{ number_format((float) ($totales['monto_recibido'] ?? 0), 2, ',', '.') }}
                        · Facturado: {{ number_format((float) ($totales['monto_facturado'] ?? 0), 2, ',', '.') }}
                        · Consumido: {{ number_format((float) ($totales['monto_consumido'] ?? 0), 2, ',', '.') }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @include('compras.contrato_vencimiento_reporte.partials.tabla_datos', [
        'filas' => $filas,
        'puede_ver_ordencompra' => false,
        'para_excel' => false,
    ])
</body>
</html>
