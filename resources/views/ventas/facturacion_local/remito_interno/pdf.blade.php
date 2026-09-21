<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Remito interno {{ $remito->numero ?? '' }}</title>
    <style type="text/css">
        @page { margin: 12mm; }
        html, body { height: auto; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }
        table.ri-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.ri-header td { border: none; vertical-align: top; padding: 0 4px 0 0; }
        .ri-logo { width: 48%; }
        .ri-meta {
            width: 52%;
            font-size: 13px;
            text-align: right;
            line-height: 1.45;
        }
        .ri-titulo {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px 0;
            color: #17202A;
        }
        .ri-bloque {
            margin-top: 8px;
            font-size: 12px;
            line-height: 1.4;
        }
        table.ri-items {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
            margin-top: 12px;
            table-layout: fixed;
        }
        table.ri-items td,
        table.ri-items th {
            border: 1px solid #cccccc;
            padding: 4px 3px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.ri-items thead tr {
            background-color: #85C1E9;
            color: #17202A;
        }
        table.ri-items tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.tabla-medidas-remito-ferli {
            border-collapse: collapse;
            width: 100%;
            font-size: 10px;
        }
        table.tabla-medidas-remito-ferli th,
        table.tabla-medidas-remito-ferli td {
            border: 1px solid #999;
            padding: 2px 3px;
            text-align: center;
        }
        table.tabla-medidas-remito-ferli th {
            background: #eaf2f8;
            font-weight: bold;
        }
        .ri-leyenda { margin-top: 12px; font-size: 12px; }
        .ri-totales { margin-top: 8px; text-align: right; font-size: 13px; font-weight: bold; }
        .ri-footer { margin-top: 24px; font-size: 11px; color: #555; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $nombreEmpresa = $remito->empresa->nombre
        ?? ($remito->localVenta->empresa->nombre ?? null)
        ?? config('app.empresa');
    $logoDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresa);
    $logoUri = $logoDat['uri'] ?? null;
    $localTxt = trim(($remito->localVenta->codigo ?? '').' — '.($remito->localVenta->nombre ?? ''));
    $depTxt = trim(($remito->deposito->codigo ?? '').' — '.($remito->deposito->nombre ?? ''));
    $items = $items ?? [];
    $totalPares = (float) ($totalPares ?? 0);
@endphp

<table class="ri-header">
    <tr>
        <td class="ri-logo">
            @if ($logoUri)
                <img style="margin: 4px 0; max-height: 70px; max-width: 180px;" src="{{ $logoUri }}" alt="">
            @endif
            <div class="ri-bloque">
                <strong>{{ $nombreEmpresa }}</strong><br>
                Remito interno de mercadería
            </div>
        </td>
        <td class="ri-meta">
            <div class="ri-titulo">REMITO INTERNO N° {{ $remito->numero }}</div>
            <strong>Tipo:</strong> {{ \App\Support\Ventas\FacturacionLocal\RemitoInternoNumeracionSupport::TIPO_ANITA }}
            {{ \App\Support\Ventas\FacturacionLocal\RemitoInternoNumeracionSupport::LETRA_ANITA }}
            @php
                $sucPdf = '';
                if ($remito->localVenta) {
                    $sucPdf = \App\Support\Ventas\FacturacionLocal\RemitoInternoNumeracionSupport::sucursalDesdeLocal($remito->localVenta);
                }
            @endphp
            @if ($sucPdf !== '')
                — suc. {{ $sucPdf }}
            @endif
            <br>
            <strong>Fecha:</strong> {{ optional($remito->fecha)->format('d/m/Y') }}<br>
            <strong>Local origen:</strong> {{ $localTxt }}<br>
            <strong>Depósito:</strong> {{ $depTxt }}<br>
            @if ($remito->destinatario)
                <strong>Destinatario:</strong> {{ $remito->destinatario }}<br>
            @endif
            @if ($remito->leyenda)
                <strong>Leyenda:</strong> {{ $remito->leyenda }}
            @endif
        </td>
    </tr>
</table>

<table class="ri-items">
    <thead>
        <tr>
            <th style="width:14%;">ARTÍCULO</th>
            <th style="width:30%;">DESCRIPCIÓN / COLOR</th>
            <th style="width:42%;">MEDIDAS</th>
            <th style="width:14%;" class="text-center">TOTAL PARES</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $item)
            @php
                $detalle = trim((string) ($item['detalle'] ?? ''));
                $color = trim((string) ($item['color'] ?? ''));
                if ($color !== '' && $detalle !== '' && ! str_contains(mb_strtoupper($detalle), mb_strtoupper($color))) {
                    $detalle .= ' '.$color;
                } elseif ($detalle === '' && $color !== '') {
                    $detalle = $color;
                }
                $medidas = is_array($item['medidas'] ?? null) ? $item['medidas'] : [];
                $pares = (float) ($item['cantidad'] ?? 0);
            @endphp
            <tr>
                <td>{{ $item['sku'] ?? '' }}</td>
                <td>{{ $detalle }}</td>
                <td>
                    @if ($medidas !== [])
                        <table class="tabla-medidas-remito-ferli">
                            <tr>
                                @foreach ($medidas as $m)
                                    <th>{{ $m['medida'] ?? '' }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($medidas as $m)
                                    <td>{{ number_format((float) ($m['cantidad'] ?? 0), 0, ',', '.') }}</td>
                                @endforeach
                            </tr>
                        </table>
                    @else
                        —
                    @endif
                </td>
                <td style="text-align:center;"><strong>{{ number_format($pares, 0, ',', '.') }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align:center;">Sin ítems</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="3" style="text-align:right;"><strong>TOTAL DE PARES</strong></td>
            <td style="text-align:center;"><strong>{{ number_format($totalPares, 0, ',', '.') }}</strong></td>
        </tr>
    </tbody>
</table>

@if ($remito->observacion)
    <div class="ri-leyenda">
        <strong>Observación:</strong> {{ $remito->observacion }}
    </div>
@endif

<div class="ri-footer">
    Generado {{ date('d/m/Y H:i') }} — Documento interno (no fiscal).
</div>
</body>
</html>
