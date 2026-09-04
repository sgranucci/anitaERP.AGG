<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Pedido de ventas {{ $pdf['numeroPedido'] }}</title>
    <style>
        @page { margin: 12mm 12mm 14mm 12mm; }
        html, body { width: 100%; margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            color: #17202A;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .pdf-cabecera { margin-bottom: 8px; page-break-inside: avoid; }
        .pdf-cabecera td { border: none; vertical-align: top; padding: 0; }
        .logo-empresa {
            max-width: 150px;
            max-height: 54px;
            width: auto;
            height: auto;
        }
        .marca {
            font-size: 13px;
            font-weight: bold;
            color: #1B4F72;
            margin-top: 2px;
        }
        .cuit { font-size: 8px; color: #555; margin-top: 1px; }
        .titulo-doc {
            font-size: 15px;
            font-weight: bold;
            color: #17202A;
            margin: 0;
            text-align: right;
        }
        .subtitulo-doc {
            font-size: 11px;
            font-weight: bold;
            color: #1B4F72;
            margin: 3px 0 0 0;
            text-align: right;
        }
        .meta-doc {
            font-size: 8px;
            color: #555;
            margin: 4px 0 0 0;
            text-align: right;
        }
        h2 {
            font-size: 10px;
            margin: 8px 0 4px 0;
            padding: 4px 6px;
            background: #85C1E9;
            color: #17202A;
            border: 1px solid #5dade2;
        }
        table.cabecera td {
            border: 1px solid #cccccc;
            padding: 4px 6px;
            vertical-align: top;
            font-size: 9px;
        }
        table.cabecera .lbl {
            background: #D6EAF8;
            font-weight: bold;
            width: 14%;
            color: #1B4F72;
        }
        table.cabecera .val { width: 36%; }
        .entrega-block { line-height: 1.35; }
        table.items { margin-top: 2px; }
        table.items th {
            background: #85C1E9;
            color: #17202A;
            border: 1px solid #cccccc;
            padding: 4px 3px;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
        }
        table.items td {
            border: 1px solid #cccccc;
            padding: 3px 3px;
            font-size: 8px;
            vertical-align: top;
        }
        table.items tbody tr:nth-child(even) td { background: #f5f5f5; }
        table.items tbody tr.fason-row td {
            background: #fef9e7;
            font-size: 7.5px;
            color: #5d4e00;
            border-top: none;
            padding: 2px 4px 4px 4px;
        }
        .num { text-align: right; white-space: nowrap; }
        .cen { text-align: center; }
        .totales-cant {
            margin: 8px 0 4px 0;
            font-size: 9px;
            font-weight: bold;
        }
        table.pdf-totales {
            width: 58%;
            margin: 4px 0 0 auto;
            border-collapse: collapse;
            page-break-inside: avoid;
            font-size: 9px;
        }
        table.pdf-totales td {
            border: 1px solid #cccccc;
            padding: 4px 6px;
        }
        table.pdf-totales td.etiq {
            background: #f8f9f9;
            width: 68%;
        }
        table.pdf-totales tr.final td {
            background: #D6EAF8;
            font-weight: bold;
            font-size: 10px;
            color: #1B4F72;
        }
        .leyenda {
            margin-top: 10px;
            font-size: 8px;
            border: 1px solid #cccccc;
            padding: 6px;
            background: #fafafa;
            page-break-inside: avoid;
        }
        .leyenda strong { color: #1B4F72; }
        .pie {
            margin-top: 10px;
            font-size: 7px;
            color: #777;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $simbolo = trim((string) ($pdf['moneda'] ?? '$'));
    if ($simbolo === '') {
        $simbolo = '$';
    }
    $fmt = static function ($n, int $dec = 2): string {
        return number_format((float) $n, $dec, ',', '.');
    };
@endphp

<table class="pdf-cabecera">
    <colgroup>
        <col style="width:48%;">
        <col style="width:52%;">
    </colgroup>
    <tr>
        <td>
            @if (! empty($pdf['logoUri']))
                <img class="logo-empresa" src="{{ $pdf['logoUri'] }}" alt="">
            @endif
            <div class="marca">{{ $pdf['empresaNombre'] }}</div>
            @if ($pdf['empresaCuit'] !== '')
                <div class="cuit">CUIT {{ $pdf['empresaCuit'] }}</div>
            @endif
        </td>
        <td>
            <p class="titulo-doc">PEDIDO DE VENTAS</p>
            <p class="subtitulo-doc">{{ $pdf['numeroPedido'] }}</p>
            <p class="meta-doc">
                Fecha: {{ $pdf['fecha'] }}
                &nbsp;&middot;&nbsp;
                Entrega: {{ $pdf['fechaEntrega'] }}
                <br>
                Estado: {{ $pdf['estado'] }}
            </p>
        </td>
    </tr>
</table>

<h2>Datos del pedido</h2>
<table class="cabecera">
    <tr>
        <td class="lbl">Cliente</td>
        <td class="val" colspan="3">
            {{ $pdf['clienteCodigo'] }}
            @if ($pdf['clienteNombre'] !== '')
                &mdash; {{ $pdf['clienteNombre'] }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Lugar de entrega</td>
        <td class="val" colspan="3">
            <div class="entrega-block">
                @forelse ($pdf['lugarEntregaLineas'] as $linea)
                    {{ $linea }}
                    @if (! $loop->last)
                        <br>
                    @endif
                @empty
                    —
                @endforelse
            </div>
        </td>
    </tr>
    <tr>
        <td class="lbl">Condici&oacute;n de venta</td>
        <td class="val">{{ $pdf['condicionVenta'] }}</td>
        <td class="lbl">Stock</td>
        <td class="val">{{ $pdf['stock'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Vendedor</td>
        <td class="val">{{ $pdf['vendedor'] }}</td>
        <td class="lbl">Transporte</td>
        <td class="val">{{ $pdf['transporte'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Horario de atenci&oacute;n</td>
        <td class="val">{{ $pdf['horarioAtencion'] !== '' ? $pdf['horarioAtencion'] : '—' }}</td>
        <td class="lbl">Orden de compra</td>
        <td class="val">{{ $pdf['ordenCompra'] !== '' ? $pdf['ordenCompra'] : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Moneda / cotiz.</td>
        <td class="val" colspan="3">{{ $simbolo }} / {{ $pdf['cotizacion'] }}</td>
    </tr>
</table>

<h2>Detalle de art&iacute;culos</h2>
<table class="items">
    <thead>
        <tr>
            <th style="width:4%;">It</th>
            <th style="width:12%;">Art&iacute;culo</th>
            <th style="width:24%;">Descripci&oacute;n</th>
            <th style="width:8%;">Fec.Ent.</th>
            <th style="width:9%;">Cantidad</th>
            <th style="width:5%;">Umd</th>
            <th style="width:8%;">Cant.Alt.</th>
            <th style="width:5%;">Umd</th>
            <th style="width:9%;">Precio</th>
            <th style="width:10%;">Total</th>
            <th style="width:6%;">Dto %</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pdf['items'] as $item)
            <tr>
                <td class="cen">{{ $item['numeroitem'] }}</td>
                <td>{{ $item['sku'] }}</td>
                <td>
                    {{ $item['descripcion'] }}
                    @if ($item['articulo_cliente'] !== '')
                        <br><span style="color:#555;font-size:7px;">Art. cliente: {{ $item['articulo_cliente'] }}</span>
                    @endif
                </td>
                <td class="cen">{{ $item['fechaentrega'] }}</td>
                <td class="num">{{ $fmt($item['cantidad'], 2) }}</td>
                <td class="cen">{{ $item['umd'] }}</td>
                <td class="num">
                    @if (abs($item['cantidad_alter']) > 0.0000001)
                        {{ $fmt($item['cantidad_alter'], 2) }}
                    @endif
                </td>
                <td class="cen">{{ $item['umd_alter'] }}</td>
                <td class="num">{{ $simbolo }} {{ $fmt($item['precio'], 3) }}</td>
                <td class="num">{{ $fmt($item['total'], 2) }}</td>
                <td class="num">{{ $fmt($item['descuento'], 2) }}</td>
            </tr>
            @if ($item['fason'])
                <tr class="fason-row">
                    <td colspan="11">
                        Precio Fason: {{ $simbolo }} {{ $fmt($item['fason']['precio_fason'], 3) }}
                        &nbsp;&mdash;&nbsp; Env.Cli.: {{ $fmt($item['fason']['env_cli'], 2) }}
                        &nbsp;&mdash;&nbsp; Interforming: {{ $fmt($item['fason']['interforming'], 2) }}
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

@if (count($pdf['totalesCantidad']) > 0)
    <div class="totales-cant">
        Total cant.
        @foreach ($pdf['totalesCantidad'] as $tc)
            {{ $fmt($tc['cantidad'], 2) }} {{ $tc['umd'] }}
            @if (! $loop->last)
                &nbsp;
            @endif
        @endforeach
    </div>
@endif

<table class="pdf-totales">
    <tr>
        <td class="etiq">Total del pedido sin dto</td>
        <td class="num">{{ $simbolo }} {{ $fmt($pdf['totalSinDescuento']) }}</td>
    </tr>
    @foreach ($pdf['conceptosTotales'] as $concepto)
        @php
            $nombreConcepto = (string) ($concepto['concepto'] ?? '');
            $esTotal = strcasecmp($nombreConcepto, 'Total') === 0;
            $esSubtotal = strcasecmp($nombreConcepto, 'Subtotal') === 0;
        @endphp
        @if (! $esSubtotal)
            @if ($esTotal)
                <tr class="final">
                    <td class="etiq">Total final</td>
                    <td class="num">{{ $simbolo }} {{ $fmt($concepto['importe'] ?? 0) }}</td>
                </tr>
            @else
                <tr>
                    <td class="etiq">{{ $nombreConcepto }}</td>
                    <td class="num">{{ $simbolo }} {{ $fmt($concepto['importe'] ?? 0) }}</td>
                </tr>
            @endif
        @endif
    @endforeach
    @if (count($pdf['conceptosTotales']) === 0)
        <tr class="final">
            <td class="etiq">Total final</td>
            <td class="num">{{ $simbolo }} {{ $fmt($pdf['totalSinDescuento']) }}</td>
        </tr>
    @endif
</table>

@if ($pdf['leyenda'] !== '')
    <div class="leyenda">
        <strong>Leyenda</strong><br>
        {{ $pdf['leyenda'] }}
    </div>
@endif

<div class="pie">Generado {{ date('d/m/Y H:i') }} &middot; {{ $pdf['empresaNombre'] }}</div>
</body>
</html>
