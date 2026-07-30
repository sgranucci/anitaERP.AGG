<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14px 16px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #17202A; font-size: 7px; }
        .cabecera { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .cabecera td { vertical-align: middle; }
        .logo { height: 36px; }
        .titulo { font-size: 14px; font-weight: bold; }
        .meta { font-size: 7.5px; color: #555; margin-bottom: 4px; }
        .parte { font-size: 8px; font-weight: bold; margin: 8px 0 2px; }
        table.tabla-columnas-pdf { width: 100%; border-collapse: collapse; margin-top: 2px; page-break-inside: auto; }
        table.tabla-columnas-pdf th {
            background-color: #85C1E9;
            color: #17202A;
            border: 1px solid #cccccc;
            padding: 2px 3px;
            text-align: left;
            font-size: 6.5px;
            font-weight: bold;
        }
        table.tabla-columnas-pdf td {
            border: 1px solid #cccccc;
            padding: 1px 3px;
            font-size: 6.5px;
        }
        table.tabla-columnas-pdf .grupo-tipo td { background-color: #D5E8F5; font-weight: bold; }
        table.tabla-columnas-pdf .subtotal-tipo td { background-color: #f0f0f0; font-weight: bold; }
        table.tabla-columnas-pdf tfoot td { background-color: #eaf2f8; font-weight: bold; }
        .text-right { text-align: right; }
        .col-grupo { text-align: center; }
        .salto { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $coleccionLogo = ! empty($empresa_nombre)
            ? collect([(object) ['nombreempresa' => $empresa_nombre]])
            : collect();
        $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogo);
        $particiones = $particiones ?? [];
    @endphp

    <table class="cabecera">
        <tr>
            <td style="width: 25%;">
                @foreach ($logos as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="logo">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <div class="titulo">Reporte de viandas</div>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right;">
                <div class="meta">
                    Unidades: {{ number_format((float) ($resultado['gran_total_unidades'] ?? 0), 0, ',', '.') }}
                    · Costo: {{ number_format((float) ($resultado['gran_total_costo'] ?? 0), 2, ',', '.') }}
                    · Venta: {{ number_format((float) ($resultado['gran_total_venta'] ?? 0), 2, ',', '.') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="meta">{{ $subtitulo ?? '' }}</div>

    @forelse ($particiones as $idx => $particion)
        @if ($idx > 0)
            <div class="salto"></div>
        @endif
        @if (($particion['total_partes'] ?? 1) > 1)
            <div class="parte">Centros de costo — parte {{ $particion['indice'] }} de {{ $particion['total_partes'] }}</div>
        @endif
        @include('ventas.gastronomia.descuento_reporte.partials.tabla_columnas', [
            'resultado' => $resultado,
            'vista_columnas_chunk' => $particion,
            'puede_ver_articulo' => false,
            'sin_wrapper' => true,
            'modo_pdf' => true,
            'table_class' => 'tabla-columnas-pdf',
        ])
    @empty
        <div class="meta">Sin datos para el filtro seleccionado.</div>
    @endforelse
</body>
</html>
