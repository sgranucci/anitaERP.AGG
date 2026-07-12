@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $art = $contexto['articulo'] ?? [];
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        $filas->map(static fn ($f) => (object) ['nombreempresa' => $f->nombreempresa ?? ''])
    );
    $sufijoUm = \App\Support\Stock\MovimientosArticuloDepositoSupport::sufijoColumnaCantidad($art['unidad_medida'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepciones artículo {{ $art['sku'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; padding: 4px; border: 1px solid #ccc; color: #17202A; }
        table.data td { padding: 3px; border: 1px solid #ccc; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<table style="width:100%; margin-bottom:8px;">
    <tr>
        <td>
            @foreach($logos as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:48px; margin-right:8px;">
            @endforeach
        </td>
        <td style="text-align:center">
            <h2 style="margin:0">Recepciones de proveedor — {{ $art['sku'] ?? '' }}</h2>
            @if (! empty($art['descripcion']))
                <div>{{ $art['descripcion'] }}</div>
            @endif
            <div>Generado {{ date('d/m/Y H:i') }} · {{ $filas->count() }} l&iacute;nea(s)</div>
        </td>
    </tr>
</table>
<table class="data">
    <thead>
        <tr>
            <th>N&ordm;</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>OC</th>
            <th>SKU l&iacute;nea</th>
            <th>Cant.{!! $sufijoUm !!}</th>
            <th>Cant. stock</th>
            <th>Precio</th>
            <th>Proveedor</th>
            <th>Empresa</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila->numerorecepcion }}</td>
            <td>{{ $fila->fecha_fmt ?? '' }}</td>
            <td>{{ $fila->tipo ?? '' }}</td>
            <td>{{ $fila->numeroordencompra ?? '' }}</td>
            <td>{{ $fila->sku_linea ?? '' }}</td>
            <td class="text-right">{{ $fila->cantidad_fmt ?? '' }}</td>
            <td class="text-right">{{ $fila->cantidad_stock_fmt ?? '' }}</td>
            <td class="text-right">{{ $fila->precio_fmt ?? '' }}</td>
            <td>{{ $fila->nombreproveedor ?? '' }}</td>
            <td>{{ $fila->nombreempresa ?? '' }}</td>
            <td>{{ $fila->estado_recepcion ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
