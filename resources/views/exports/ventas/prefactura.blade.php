<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>PRE-FACTURA {{ $pedido->id ?? '' }}</title>
    <style type="text/css">
        @page { margin: 10mm; }
        html, body { height: auto; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }
        table.prefactura-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border-bottom: 2px solid #333;
            padding-bottom: 4px;
        }
        table.prefactura-header td {
            border: none;
            vertical-align: top;
            padding: 0 4px 6px 0;
        }
        .prefactura-logo { width: 48%; }
        .prefactura-meta {
            width: 52%;
            text-align: right;
            line-height: 1.45;
            font-size: 12px;
        }
        .prefactura-titulo {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }
        .prefactura-aviso {
            margin: 6px 0 8px 0;
            padding: 4px 6px;
            border: 1px solid #999;
            background: #f5f5f5;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
        }
        .prefactura-cliente {
            margin: 0 0 10px 0;
            font-size: 12px;
            line-height: 1.4;
        }
        table.prefactura-items {
            border-collapse: collapse;
            width: 100%;
            font-size: 10px;
            table-layout: fixed;
        }
        table.prefactura-items th,
        table.prefactura-items td {
            border: 1px solid #cccccc;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.prefactura-items thead tr {
            background-color: #85C1E9;
        }
        table.prefactura-items th {
            font-weight: bold;
            color: #17202A;
            text-align: left;
        }
        table.prefactura-items tbody tr:nth-child(even) {
            background-color: #f5f5f5;
        }
        table.prefactura-items td.num,
        table.prefactura-items th.num {
            text-align: right;
        }
        tr.fila-total-pares td {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .prefactura-totales-wrap {
            width: 100%;
            margin-top: 8px;
            text-align: right;
        }
        table.prefactura-totales {
            width: auto;
            max-width: 55%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.prefactura-totales td {
            padding: 3px 8px;
            border: 1px solid #cccccc;
        }
        table.prefactura-totales td.lbl {
            text-align: left;
            background: #f8f9fa;
        }
        table.prefactura-totales td.val {
            text-align: right;
            white-space: nowrap;
            min-width: 90px;
        }
        table.prefactura-totales tr.fila-total-final td {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .prefactura-leyenda {
            margin-top: 10px;
            font-size: 11px;
        }
        .prefactura-leyenda strong {
            display: block;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $nombreMarca = trim((string) ($pedido->mventas->nombre ?? ''));
    $logoUri = null;

    if ($nombreMarca !== '') {
        $baseMarca = basename(str_replace(['..', '\\', '/'], '', $nombreMarca));
        foreach ([public_path('assets/img/empresa'), public_path('storage/imagenes/logos')] as $dirLogo) {
            if (! is_dir($dirLogo)) {
                continue;
            }
            foreach (['jpg', 'jpeg', 'png'] as $extLogo) {
                $rutaMarca = $dirLogo.'/logo'.$baseMarca.'.'.$extLogo;
                if (is_file($rutaMarca)) {
                    $bin = @file_get_contents($rutaMarca);
                    if ($bin !== false && $bin !== '') {
                        $mime = $extLogo === 'png' ? 'image/png' : 'image/jpeg';
                        $logoUri = 'data:'.$mime.';base64,'.base64_encode($bin);
                        break 2;
                    }
                }
            }
        }
    }

    if ($logoUri === null) {
        $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre(
            $nombreMarca !== '' ? $nombreMarca : (string) config('app.empresa')
        );
        $logoUri = $logoEmpresaDat['uri'] ?? null;
    }

    $codigoClientePedido = trim((string) ($pedido->clientes->codigo ?? ''));
    $nombreClientePedido = trim((string) ($pedido->clientes->nombre ?? ''));
    $clientePedidoDisplay = $codigoClientePedido !== '' && $nombreClientePedido !== ''
        ? $codigoClientePedido.' - '.$nombreClientePedido
        : ($nombreClientePedido !== '' ? $nombreClientePedido : $codigoClientePedido);

    $pedidoId = (string) ($pedido->id ?? '');
    $pedidoCodigo = trim((string) ($pedido->codigo ?? ''));
    $mostrarCodigoExtra = $pedidoCodigo !== '' && $pedidoCodigo !== $pedidoId;

    $itemsId = $itemsId ?? [];
    $tblImpuesto = $tblImpuesto ?? [];
    $conceptosTotales = $conceptosTotales ?? [];
    $lineas = $pedido->pedido_combinaciones ?? collect();
    $totalPares = 0;
@endphp

<table class="prefactura-header">
    <tr>
        <td class="prefactura-logo">
            @if ($logoUri)
                <img src="{{ $logoUri }}" alt="" style="max-height: 70px; max-width: 200px; margin: 2px 0;">
            @endif
        </td>
        <td class="prefactura-meta">
            <div class="prefactura-titulo">PRE-FACTURA</div>
            <strong>Pedido Nro.: {{ $pedidoId }}</strong><br>
            @if ($mostrarCodigoExtra)
                <strong>Código: {{ $pedidoCodigo }}</strong><br>
            @endif
            <strong>Fecha emisión: {{ date('d/m/Y') }}</strong><br>
            <strong>Fecha pedido: {{ date('d/m/Y', strtotime($pedido->fecha ?? '')) }}</strong>
        </td>
    </tr>
</table>

<div class="prefactura-aviso">DOCUMENTO NO VÁLIDO COMO FACTURA</div>

<div class="prefactura-cliente">
    <strong>Cliente:</strong> {{ $clientePedidoDisplay }}<br>
    <strong>Transporte:</strong> {{ $pedido->transportes->nombre ?? '' }}<br>
    <strong>Lugar de entrega:</strong> {{ $pedido->lugarentrega ?? '' }}
</div>

<table class="prefactura-items">
    <thead>
        <tr>
            <th style="width: 12%;">Sku</th>
            <th style="width: 22%;">Descripción</th>
            <th style="width: 20%;">Combinación</th>
            <th style="width: 26%;">Módulo</th>
            <th class="num" style="width: 8%;">Pares</th>
            <th class="num" style="width: 12%;">Precio</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $item)
            @if (in_array($item->id, $itemsId))
                @foreach ($tblImpuesto as $filaImp)
                    @if (($filaImp['id'] ?? null) == $item->id)
                        @php
                            $cantItem = (float) ($filaImp['cantidad'] ?? 0);
                            $precioItem = (float) ($filaImp['precio'] ?? 0);
                            $totalPares += $cantItem;
                        @endphp
                        <tr>
                            <td>{{ $item->articulos->sku ?? ($filaImp['sku'] ?? '') }}</td>
                            <td>{{ $item->articulos->descripcion ?? ($filaImp['descripcion'] ?? '') }}</td>
                            <td>{{ ($item->combinaciones->codigo ?? '') }}-{{ ($item->combinaciones->nombre ?? '') }}</td>
                            <td>{{ $item->modulos->nombre ?? '' }}</td>
                            <td class="num">{{ number_format($cantItem, 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($precioItem, 2, ',', '.') }}</td>
                        </tr>
                    @endif
                @endforeach
            @endif
        @endforeach
        <tr class="fila-total-pares">
            <td colspan="4" style="text-align: right;">TOTAL PARES</td>
            <td class="num">{{ number_format($totalPares, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

@if (count($conceptosTotales) > 0)
    <div class="prefactura-totales-wrap">
        <table class="prefactura-totales">
            @foreach ($conceptosTotales as $itemTotal)
                @php $esTotal = ($itemTotal['concepto'] ?? '') === 'Total'; @endphp
                @if ($esTotal)
                    <tr class="fila-total-final">
                        <td class="lbl">{{ $itemTotal['concepto'] ?? '' }}</td>
                        <td class="val">{{ number_format((float) ($itemTotal['importe'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @else
                    <tr>
                        <td class="lbl">{{ $itemTotal['concepto'] ?? '' }}</td>
                        <td class="val">{{ number_format((float) ($itemTotal['importe'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    </div>
@endif

@if (trim((string) ($pedido->leyenda ?? '')) !== '')
    <div class="prefactura-leyenda">
        <strong>Leyendas</strong>
        {{ $pedido->leyenda }}
    </div>
@endif
</body>
</html>
