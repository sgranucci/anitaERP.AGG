@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Stock\ArticuloSaldosDepositoSupport;

    $depositos = $depositos ?? collect();
    $filasIterable = $filas ?? [];
    if ($filasIterable instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasIterable = $filasIterable->items();
    }
    $coleccionLogos = collect($filasIterable);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Existencias por depósito' }}</title>
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
    <h1>{{ $titulo ?? 'Existencias por depósito' }}</h1>
    <div class="meta">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if (! empty($subtitulo))
            · {{ $subtitulo }}
        @endif
        · Artículos: {{ (int) ($totales['total_articulos'] ?? count($filasIterable)) }}
    </div>
  @include('stock.existencias_deposito_reporte.partials.tabla_datos', [
      'depositos' => $depositos,
      'filas' => $filasIterable,
      'totales' => $totales ?? [],
      'puede_ver_articulo' => $puede_ver_articulo ?? false,
      'table_class' => 'data',
  ])
</body>
</html>
