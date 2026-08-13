<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud certificado sanitario {{ $cert->etiqueta }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #17202A; }
        h1 { margin: 0; font-size: 18px; letter-spacing: 0.4px; }
        .sub { font-size: 10px; color: #444; margin-top: 2px; }
        table.header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.header td { vertical-align: middle; border: none; padding: 0; }
        table.caja { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.caja th, table.caja td { border: 1px solid #888; padding: 5px 7px; font-size: 11px; }
        table.caja th { background: #85C1E9; color: #17202A; font-weight: bold; text-align: left; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #85C1E9; color: #17202A; font-weight: bold; padding: 6px 5px; border: 1px solid #888; font-size: 11px; }
        table.data td { padding: 5px; border: 1px solid #ccc; font-size: 11px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .tot { font-weight: bold; background: #f5f5f5; }
        .pie { margin-top: 12px; }
        .firma { margin-top: 32px; width: 100%; }
        .firma td { width: 33%; text-align: center; font-size: 10px; padding-top: 32px; border: none; }
        .firma .linea { border-top: 1px solid #333; padding-top: 5px; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td style="width:28%">
            @foreach ($logos as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:56px; margin-right:6px;">
            @endforeach
        </td>
        <td style="width:44%; text-align:center">
            <h1>SOLICITUD DE CERTIFICADO SANITARIO</h1>
            <div class="sub">SENASA — Carnes y productos cárnicos</div>
            <div class="sub">Para emitir / presentar</div>
        </td>
        <td style="width:28%; text-align:right">
            <strong>Nº {{ $cert->etiqueta }}</strong><br>
            @if ($cert->nro_cert_interno)
                Interno: {{ $cert->nro_cert_interno }}<br>
            @endif
            @if ($cert->nro_cert_patagonico)
                Patagónico: {{ $cert->nro_cert_patagonico }}<br>
            @endif
            Fecha: {{ optional($cert->fecha)->format('d/m/Y') }}<br>
            Hora: {{ optional($cert->created_at)->format('H:i') }}<br>
            <span class="sub">Generado {{ date('d/m/Y H:i') }}</span>
        </td>
    </tr>
</table>

<table class="caja">
    <tr>
        <th style="width:22%">Establecimiento</th>
        <td>{{ $cert->establecimiento_nro ?: config('senasa.establecimiento') }}</td>
        <th style="width:18%">Empresa</th>
        <td>{{ $empresaNombre }}</td>
    </tr>
    <tr>
        <th>Origen</th>
        <td>{{ $cert->origen }}</td>
        <th>Procedencia</th>
        <td>{{ $cert->procedencia }}</td>
    </tr>
    <tr>
        <th>PTR</th>
        <td>{{ $ptrEtiqueta }}</td>
        <th>Precinto</th>
        <td>{{ $cert->precinto }}</td>
    </tr>
    <tr>
        <th>Camión dominio</th>
        <td>{{ optional($cert->camion)->dominio }}</td>
        <th>Tipo</th>
        <td>{{ optional($cert->camion)->tipo }}</td>
    </tr>
    <tr>
        <th>Habilitación</th>
        <td colspan="3">{{ optional($cert->camion)->habilitacion }}</td>
    </tr>
    <tr>
        <th>Reparto / expreso</th>
        <td colspan="3">{{ optional($cert->transporte)->codigo }} {{ optional($cert->transporte)->nombre }}</td>
    </tr>
    <tr>
        <th>Destino</th>
        <td colspan="3">
            {{ $destinosTexto[0] }}
            @if ($destinosTexto[1] !== '')
                <br>{{ $destinosTexto[1] }}
            @endif
        </td>
    </tr>
    <tr>
        <th>Clientes</th>
        <td colspan="3">
            {{ $clientesTexto[0] }}
            @if ($clientesTexto[1] !== '')
                <br>{{ $clientesTexto[1] }}
            @endif
            @if ($clientesTexto[2] !== '')
                <br>{{ $clientesTexto[2] }}
            @endif
        </td>
    </tr>
    @if ((int) ($cert->establecimiento_destino ?? 0) > 0)
    <tr>
        <th>Establ. destino</th>
        <td colspan="3">{{ $cert->establecimiento_destino }}</td>
    </tr>
    @endif
    @if ((int) ($cert->nro_remito ?? 0) > 0)
    <tr>
        <th>Nro. remito</th>
        <td colspan="3">{{ $cert->nro_remito }}</td>
    </tr>
    @endif
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:6%">#</th>
            <th>Artículo / producto SENASA</th>
            <th style="width:16%">Marca</th>
            <th style="width:10%" class="num">Cajas</th>
            <th style="width:12%" class="num">Kg neto</th>
            <th style="width:12%" class="num">Kg bruto</th>
            <th style="width:8%" class="num">Temp.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $i => $l)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>{{ $l['descripcion'] }}</td>
            <td>{{ $l['marca'] }}</td>
            <td class="num">{{ number_format($l['cajas'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($l['kilos'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($l['bruto'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($l['temperatura'], 1, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr class="tot">
            <td></td>
            <td colspan="2">TOTALES</td>
            <td class="num">{{ number_format($totales['cajas'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($totales['kilos'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totales['bruto'], 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="caja pie">
    <tr>
        <th style="width:22%">Bultos</th>
        <td>{{ $totales['bultos'] }}</td>
        <th style="width:18%">Cajas</th>
        <td>{{ number_format($totales['cajas'], 0, ',', '.') }}</td>
    </tr>
    <tr>
        <th>Kg neto total</th>
        <td>{{ number_format($totales['kilos'], 2, ',', '.') }}</td>
        <th>Kg bruto total</th>
        <td>{{ number_format($totales['bruto'], 2, ',', '.') }}</td>
    </tr>
</table>

<table class="firma">
    <tr>
        <td><div class="linea">Responsable establecimiento</div></td>
        <td><div class="linea">Inspector veterinario</div></td>
        <td><div class="linea">Chofer / transporte</div></td>
    </tr>
</table>
</body>
</html>
