@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datos);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #17202A; }
        .cab { width: 100%; margin-bottom: 8px; }
        .cab td { vertical-align: middle; }
        h1 { font-size: 14px; margin: 0; }
        .meta { font-size: 8px; color: #555; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px; }
        table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="cab">
        <tr>
            <td style="width:120px">
                @foreach ($logos as $logo)
                    <img src="{{ $logo['uri'] }}" style="height:40px">
                @endforeach
            </td>
            <td>
                <h1>Entregas de indumentaria</h1>
                <div class="meta">
                    Año {{ $filtros['anio'] }}
                    @if ($filtros['fecha_desde']) · Desde {{ $filtros['fecha_desde'] }} @endif
                    @if ($filtros['fecha_hasta']) · Hasta {{ $filtros['fecha_hasta'] }} @endif
                    · Generado {{ now()->format('d/m/Y H:i') }}
                    · Registros: {{ $datos->count() }}
                    · Total unidades: {{ rtrim(rtrim(number_format($totalCantidad, 2, ',', '.'), '0'), ',') }}
                </div>
            </td>
        </tr>
    </table>

    @include('sueldos.entrega_prenda.partials.tabla_datos', ['enPantalla' => false])
</body>
</html>
