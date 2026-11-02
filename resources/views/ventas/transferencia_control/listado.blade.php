@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filas = $resultado['filas'] ?? [];
    $resumen = $resultado['resumen'] ?? [];
    $coleccion = collect($filas)->map(static function ($f) {
        $f = is_array($f) ? $f : (array) $f;
        $f['nombreempresa'] = 'Emprendimientos Crown SA';

        return (object) $f;
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccion);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Control transferencias' }}</title>
    <style>
        @page { size: legal landscape; margin: 12mm 8mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        .cabecera { margin-bottom: 8px; }
        .logos img { height: 42px; margin-right: 8px; }
        h1 { font-size: 14px; margin: 4px 0; }
        .meta { color: #444; margin-bottom: 6px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; vertical-align: top; }
        table.data thead th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="cabecera">
        <div class="logos">
            @foreach ($logosCabecera as $logo)
                <img src="{{ $logo }}">
            @endforeach
        </div>
        <h1>{{ $titulo ?? 'Control transferencias Crown' }}</h1>
        <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
        <div class="meta">
            Desde {{ \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') }}
            hasta {{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}
            · Alcance {{ $filtros['alcance'] ?? '' }}
            · Origen {{ $filtros['origen'] ?? '' }}
            · {{ (int) ($resumen['total'] ?? 0) }} incongruencias
        </div>
    </div>
    @include('ventas.transferencia_control.partials.tabla_datos', [
        'filas' => $filas,
        'paraPantalla' => false,
    ])
</body>
</html>
