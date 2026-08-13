@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filasIterable = $filas ?? [];
    if ($filasIterable instanceof \Illuminate\Support\Collection) {
        $filasIterable = $filasIterable->all();
    }
    $coleccionLogos = collect($filasIterable)->map(fn ($f) => (object) [
        'nombreempresa' => is_array($f) ? ($f['nombreempresa'] ?? '') : '',
    ]);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $kpis = $kpis ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Recepción de proveedores' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #333; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #666; margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .text-right { text-align: right; }
        .logos { margin-bottom: 8px; }
        .logos img { max-height: 42px; margin-right: 8px; }
        .rpr-header-empresa td { background: #1B4F72; color: #fff; }
        .rpr-header-grupo td { background: #D6EAF8; color: #1B4F72; }
        .rpr-subtotal td { background: #F4F6F7; }
        .aviso { font-size: 8px; color: #856404; margin-bottom: 6px; }
    </style>
</head>
<body>
    @if (! empty($logosCabecera))
        <div class="logos">
            @foreach ($logosCabecera as $logo)
                @if (! empty($logo['url']))
                    <img src="{{ $logo['url'] }}" alt="">
                @endif
            @endforeach
        </div>
    @endif
    <h1>{{ $titulo ?? 'Recepción de proveedores' }}</h1>
    <div class="meta">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if (! empty($subtitulo))
            · {{ $subtitulo }}
        @endif
        · Líneas: {{ (int) ($kpis['cantidad_filas'] ?? $totales['cantidad_filas'] ?? 0) }}
        · COM: {{ (int) ($kpis['cantidad_com'] ?? 0) }}
        · Importe MN: {{ number_format((float) ($kpis['importe_mn'] ?? 0), 2, ',', '.') }}
    </div>
    @if (! empty($advertencia_cotizacion))
        <div class="aviso">{{ $advertencia_cotizacion }}</div>
    @endif
    @include('stock.recepcion_proveedor_reporte.partials.tabla_datos', [
        'filas' => $filasIterable,
        'modo' => $modo ?? 'detalle',
        'para_pdf' => true,
        'columnas_completas' => false,
        'table_class' => 'data',
        'puede_ver_recepcion' => false,
        'puede_ver_articulo' => false,
        'puede_ver_ordencompra' => false,
        'puede_ver_requisicion' => false,
        'puede_ver_proveedor' => false,
        'puede_ver_cuentacontable' => false,
        'puede_ver_comprobante' => false,
    ])
</body>
</html>
