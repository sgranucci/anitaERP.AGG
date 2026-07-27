@php
    $clave = \App\Support\Stock\RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
    $intercompanyPdf = \App\Support\Stock\RecepcionProveedorIntercompanySupport::detalleIntercompanyPdf($recepcion);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>COM {{ $recepcion->numerorecepcion }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }
        .header { width: 100%; border-bottom: 2px solid #333; margin-bottom: 12px; padding-bottom: 8px; }
        .header td { vertical-align: middle; border: none; }
        h1 { margin: 0; font-size: 16px; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; font-weight: bold; padding: 4px; border: 1px solid #ccc; font-size: 8px; }
        table.data td { padding: 4px; border: 1px solid #ccc; vertical-align: top; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .warn { background: #fff3cd !important; }
        .badge { font-size: 7px; font-weight: bold; }
        .totales { margin-top: 10px; text-align: right; font-size: 10px; font-weight: bold; }
        .obs { margin-top: 8px; font-size: 8px; border: 1px solid #ccc; padding: 6px; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td style="width:35%">
            @foreach ($logos as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:50px; margin-right:8px;">
            @endforeach
        </td>
        <td style="width:40%; text-align:center">
            <h1>Comprobante de recepción (COM)</h1>
            <div class="meta">{{ $clave['tipo'] }} {{ $clave['letra'] }} {{ $clave['sucursal'] }}-{{ $clave['nro'] }}</div>
            <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
        </td>
        <td style="width:25%; text-align:right">
            <strong>{{ optional($recepcion->empresas)->nombre }}</strong><br>
            Fecha: {{ $recepcion->fecha?->format('d/m/Y') }}
        </td>
    </tr>
</table>

<table style="width:100%; margin-bottom:10px; font-size:8px;">
    <tr>
        <td><strong>Proveedor:</strong> {{ optional($recepcion->proveedores)->nombre }}</td>
        <td><strong>OC:</strong> {{ optional($recepcion->ordencompras)->numeroordencompra }}</td>
        <td><strong>Factura/remito:</strong> {{ $recepcion->numerofactura ?: '—' }}</td>
    </tr>
    <tr>
        <td><strong>Estado:</strong> {{ $recepcion->estado }}</td>
        <td><strong>Tipo:</strong> {{ $recepcion->tipo }}</td>
        <td><strong>Usuario:</strong> {{ optional($recepcion->creousuarios)->nombre ?? '—' }}</td>
    </tr>
</table>

@if($recepcion->fl_precio_diferencia || $recepcion->fl_diferencia_cantidad || $recepcion->fl_articulo_extra || $recepcion->fl_faltante_oc)
<div class="obs">
    <strong>Diferencias detectadas:</strong>
    @if($recepcion->fl_precio_diferencia) [Precio] @endif
    @if($recepcion->fl_diferencia_cantidad) [Cantidad] @endif
    @if($recepcion->fl_articulo_extra) [Artículo extra/sustituto] @endif
    @if($recepcion->fl_faltante_oc) [Faltante OC] @endif
    @if($recepcion->fl_laboratorio) [Laboratorio] @endif
    @if($recepcion->resumen_diferencias)<br>{!! nl2br(e($recepcion->resumen_diferencias)) !!}@endif
</div>
@endif

@if(!empty($intercompanyPdf['es_intercompany']))
<div class="obs warn">
    <strong>Ingreso intercompany:</strong>
    la recepción es de <strong>{{ optional($recepcion->empresas)->nombre }}</strong>
    y la mercadería ingresó en depósito(s) de {{ implode('; ', $intercompanyPdf['empresas_deposito']) }}.
</div>
@endif

<table class="data">
    <thead>
        <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>SKU</th>
            <th>Descripción</th>
            <th class="num">Cant. OC</th>
            <th class="num">Cant. rec.</th>
            <th class="num" title="Cantidad en UM stock = cantidad remito × coeficiente (articulo_proveedor si existe)">Stock equiv.</th>
            <th class="num">Precio OC</th>
            <th class="num">Precio rec.</th>
            <th class="num">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach($recepcion->recepcion_proveedor_articulos as $linea)
        @php
            $rowClass = ($linea->fl_precio_diferencia || $linea->fl_cantidad_diferencia || $linea->fl_articulo_distinto) ? 'warn' : '';
            $importe = (float)$linea->cantidad * (float)$linea->precio;
            $coefPdf = (float) ($linea->coeficienteconversion ?? 1);
            if ($coefPdf <= 0) {
                $coefPdf = 1.0;
            }
            $cantStockPdf = round((float) $linea->cantidad * $coefPdf, 6);
            $umCompraPdf = trim((string) ($linea->ocr_unidad_compra ?? ''));
        @endphp
        <tr class="{{ $rowClass }}">
            <td>{{ $linea->orden }}</td>
            <td><span class="badge">{{ $linea->tipo_linea }}</span></td>
            <td>{{ optional($linea->articulos)->sku }}</td>
            <td>
                {{ optional($linea->articulos)->descripcion }}
                @if ($umCompraPdf !== '' || $coefPdf != 1.0)
                    <br><span style="font-size:7px;color:#555;">
                        Conv.: {{ $umCompraPdf !== '' ? $umCompraPdf.' ' : '' }}×{{ rtrim(rtrim(number_format($coefPdf, 6, '.', ''), '0'), '.') }}
                        → stock {{ number_format($cantStockPdf, 4, ',', '.') }}
                    </span>
                @endif
            </td>
            <td class="num">{{ $linea->cantidad_oc !== null ? number_format((float)$linea->cantidad_oc, 4, ',', '.') : '—' }}</td>
            <td class="num">{{ number_format((float)$linea->cantidad, 4, ',', '.') }}</td>
            <td class="num">{{ number_format($cantStockPdf, 4, ',', '.') }}</td>
            <td class="num">{{ $linea->precio_ordencompra !== null ? number_format((float)$linea->precio_ordencompra, 4, ',', '.') : '—' }}</td>
            <td class="num">{{ number_format((float)$linea->precio, 4, ',', '.') }}</td>
            <td class="num">{{ number_format($importe, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="totales">Total neto (sin IVA): {{ number_format($total, 2, ',', '.') }}</div>

@if($recepcion->recepcion_proveedor_partes_unicas->isNotEmpty())
<h3 style="font-size:10px; margin-top:12px;">Números de parte única (NPU)</h3>
<table class="data">
    <thead>
        <tr>
            <th>Línea</th>
            <th>SKU</th>
            <th>Nº parte</th>
        </tr>
    </thead>
    <tbody>
        @foreach($recepcion->recepcion_proveedor_partes_unicas->sortBy(fn ($p) => [$p->recepcion_proveedor_articulo_id, $p->numeroparte]) as $parte)
        @php $linea = $parte->recepcion_proveedor_articulos; @endphp
        <tr>
            <td>{{ $linea->orden ?? $linea->penvp_orden ?? '—' }}</td>
            <td>{{ optional($linea->articulos)->sku ?? '—' }}</td>
            <td class="num"><strong>{{ $parte->numeroparte }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($recepcion->observacion)
<div class="obs"><strong>Observación:</strong> {{ $recepcion->observacion }}</div>
@endif
</body>
</html>
