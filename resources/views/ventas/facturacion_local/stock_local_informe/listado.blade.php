@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filasIterable = $filas ?? [];
    if ($filasIterable instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasIterable = $filasIterable->items();
    }
    $coleccionLogos = collect($filasIterable)->map(function ($f) {
        if (is_array($f)) {
            $f['nombreempresa'] = $f['nombreempresa'] ?? config('app.empresa');
        }
        return $f;
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Stock del local' }}</title>
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
    <h1>{{ $titulo ?? 'Stock del local' }}</h1>
    <div class="meta">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if (! empty($subtitulo))
            · {{ $subtitulo }}
        @endif
        · Filas: {{ (int) ($totales['total_filas'] ?? count($filasIterable)) }}
        · Stock: {{ number_format((float) ($totales['total_stock'] ?? 0), 0, ',', '.') }}
    </div>
    @include('ventas.facturacion_local.stock_local_informe.partials.tabla_datos', [
        'medidas' => $medidas ?? [],
        'filas' => $filasIterable,
        'puede_ver_articulo' => false,
        'table_class' => 'data',
    ])
</body>
</html>
