<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        td.right h1 { text-align: right; }
        h3 { font-size: 11px; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #cccccc; text-align: left; font-size: 8px; }
        td { padding: 4px; border: 1px solid #cccccc; font-size: 9px; vertical-align: top; }
        table.data { table-layout: fixed; }
        table.data td, table.data th { word-wrap: break-word; overflow-wrap: break-word; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data td.etiqueta { width: 28%; background: #f8f9f9; font-weight: bold; }
        .meta td { border: none; padding: 2px 4px; }
        .right { text-align: right; }
        .logo { max-height: 48px; max-width: 160px; }
        .muted { color: #555; font-size: 8px; }
        .total-grande {
            margin-top: 8px;
            padding: 6px 8px;
            border: 1px solid #17202A;
            font-size: 12px;
            font-weight: bold;
            text-align: right;
        }
        .firma-box { margin-top: 28px; }
        .firma-box td { border: none; text-align: center; padding-top: 8px; vertical-align: top; }
        .firma-linea { border-top: 1px solid #333; width: 80%; margin: 28px auto 6px auto; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion([
        (object) ['nombreempresa' => $empresa_nombre ?? ''],
    ]);
    $vacio = '—';
    $mostrar = static function (?string $valor) use ($vacio): string {
        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : $vacio;
    };
@endphp

<table class="meta" style="margin-bottom:8px;">
    <tr>
        <td style="width:30%;">
            @foreach ($logosCabecera as $logo)
                <img class="logo" src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}">
            @endforeach
        </td>
        <td class="right" style="width:70%; vertical-align:middle;">
            <h1>{{ $titulo }}</h1>
            <div>Interno {{ $interno }}</div>
            <div>Generado {{ date('d/m/Y H:i') }}</div>
            <div class="muted">Aviso de tesorer&iacute;a por cheque de terceros rechazado</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><strong>Empresa:</strong> {{ $mostrar($empresa_nombre ?? '') }}</td>
        <td class="right"><strong>CUIT empresa:</strong> {{ $mostrar($empresa_cuit ?? '') }}</td>
    </tr>
    <tr>
        <td><strong>Domicilio:</strong> {{ $mostrar($empresa_domicilio ?? '') }}</td>
        <td class="right"><strong>Localidad:</strong> {{ $mostrar($empresa_localidad ?? '') }}</td>
    </tr>
    <tr>
        <td><strong>Fecha de rechazo:</strong> {{ $mostrar($fecha_rechazo ?? '') }}</td>
        <td class="right"><strong>Nota de d&eacute;bito:</strong> {{ $mostrar($codigo_nd ?? '') }}</td>
    </tr>
</table>

<h3>Emisor</h3>
<table class="data">
    <tbody>
        <tr>
            <td class="etiqueta">Código y nombre</td>
            <td>{{ $mostrar(trim(($emisor_codigo ?? '').' '.($emisor_nombre ?? ''))) }}</td>
            <td class="etiqueta">CUIT</td>
            <td>{{ $mostrar($cuit ?? '') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Dirección</td>
            <td>{{ $mostrar($direccion ?? '') }}</td>
            <td class="etiqueta">Localidad</td>
            <td>{{ $mostrar($localidad ?? '') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Condición de IVA</td>
            <td>{{ $mostrar($condicion_iva ?? '') }}</td>
            <td class="etiqueta">Motivo del rechazo</td>
            <td>{{ $mostrar($motivo ?? '') }}</td>
        </tr>
    </tbody>
</table>

<h3>Cheque</h3>
<table class="data">
    <thead>
        <tr>
            <th>Banco</th>
            <th>Fecha</th>
            <th>Nro. de talón</th>
            <th>Cuenta libradora</th>
            <th>Sucursal</th>
            <th>C.P.</th>
            <th>Recibo</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $mostrar($banco ?? '') }}</td>
            <td>{{ $mostrar($fecha_cheque ?? '') }}</td>
            <td>{{ $mostrar($nro_talon ?? '') }}</td>
            <td>{{ $mostrar($cuenta_libradora ?? '') }}</td>
            <td>{{ $mostrar($sucursal ?? '') }}</td>
            <td>{{ $mostrar($codigo_postal ?? '') }}</td>
            <td>{{ $mostrar($nro_recibo ?? '') }}</td>
        </tr>
    </tbody>
</table>

<div class="total-grande">
    Importe del cheque {{ $moneda ?? '$' }} {{ $importe }}
    <span style="font-weight:normal; font-size:9px;">(cotización {{ $cotizacion }})</span>
</div>

<h3>Gastos del rechazo</h3>
<table class="data">
    <thead>
        <tr>
            <th>Concepto</th>
            <th class="right" style="width:22%;">Importe</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Gastos bancarios</td>
            <td class="right">{{ $gastos_bancarios }}</td>
        </tr>
        <tr>
            <td>Gastos administrativos</td>
            <td class="right">{{ $gastos_admin }}</td>
        </tr>
        <tr>
            <td>Interés</td>
            <td class="right">{{ $interes }}</td>
        </tr>
        <tr>
            <td><strong>Total rechazo + IVA</strong></td>
            <td class="right"><strong>{{ $total }}</strong></td>
        </tr>
    </tbody>
</table>

<table class="firma-box">
    <tr>
        <td style="width:34%;">
            <div class="firma-linea"></div>
            Aviso del rechazo a
        </td>
        <td style="width:33%;">
            <div class="firma-linea"></div>
            Fecha
        </td>
        <td style="width:33%;">
            <div class="firma-linea"></div>
            Hora
        </td>
    </tr>
</table>
</body>
</html>
