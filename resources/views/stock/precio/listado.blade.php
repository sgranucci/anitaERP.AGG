@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $totalFilas = is_countable($precios) ? count($precios) : 0;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $subtituloFiltros = $subtituloFiltros ?? \App\Support\Stock\PrecioListadoFiltros::subtituloExport(
        $filtros ?? ['fecha_vigencia' => $fechaReferencia ?? date('Y-m-d')],
        $listasPrecio ?? collect()
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Precios de venta</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 40%; text-align: center;">
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Precios de venta</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">Vigencia al {{ date('d/m/Y', strtotime($fechaReferencia)) }}</div>
                @if (trim((string) $subtituloFiltros) !== '')
                    <div class="meta">{{ $subtituloFiltros }}</div>
                @endif
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;">ID</th>
                <th style="width: 8%;">SKU</th>
                <th style="width: 22%;">Descripci&oacute;n</th>
                <th style="width: 12%;">Categor&iacute;a</th>
                <th style="width: 10%;">Lista</th>
                <th style="width: 8%;">Vigencia</th>
                <th style="width: 6%;">Moneda</th>
                <th style="width: 8%;" class="num">Precio</th>
                <th style="width: 8%;" class="num">Prec. ant.</th>
                <th style="width: 14%;">Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($precios as $precio)
                <tr>
                    <td>{{ $precio->id }}</td>
                    <td>{{ $precio->sku }}</td>
                    <td>{{ $precio->articulo_descripcion }}</td>
                    <td>{{ $precio->categoria_nombre }}</td>
                    <td>{{ $precio->listaprecio_nombre }}</td>
                    <td>{{ $precio->fechavigencia ? date('d/m/Y', strtotime($precio->fechavigencia)) : '' }}</td>
                    <td>{{ $precio->moneda_nombre }}</td>
                    <td class="num">{{ number_format((float) $precio->precio, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $precio->precioanterior, 2, ',', '.') }}</td>
                    <td>{{ $precio->usuario_nombre }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
