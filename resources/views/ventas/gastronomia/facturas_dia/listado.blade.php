@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($registros);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Facturas gastronomía del día</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
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
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #d4e6f1; }
        table.data th { font-size: 7px; font-weight: bold; color: #1a1a1a; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .num { text-align: right; white-space: nowrap; }
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
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Facturas gastronomía del día</h2>
                <div class="meta">Fecha jornada: {{ \Illuminate\Support\Carbon::parse($fecha)->format('d-m-Y') }} · PC: {{ $identificador_pc }} · Generado {{ date('d-m-Y H:i') }}</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>
    <table class="data">
        <colgroup>
            <col style="width: 7%;">
            <col style="width: 10%;">
            <col style="width: 12%;">
            <col style="width: 20%;">
            <col style="width: 14%;">
            <col style="width: 9%;">
            <col style="width: 8%;">
            <col style="width: 20%;">
        </colgroup>
        <thead>
            <tr>
                <th>Venta ID</th>
                <th>Fecha</th>
                <th>Comprobante</th>
                <th>Cliente</th>
                <th>Punto de venta</th>
                <th class="num">Total</th>
                <th>Cuenta gastro.</th>
                <th>PC emisión</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($registros as $r)
                @php
                    $v = $r->venta;
                    $pvTxt = $v ? trim(($v->puntoventas->codigo ?? '').' '.($v->puntoventas->nombre ?? '')) : '';
                @endphp
                <tr>
                    <td>{{ $r->venta_id }}</td>
                    <td>
                        @if ($v?->fecha)
                            {{ \Illuminate\Support\Carbon::parse($v->fecha)->format('d-m-Y') }}
                            @if ($v->created_at)
                                {{ ' '.$v->created_at->format('H:i:s') }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $v?->codigo ?? '—' }}</td>
                    <td>{{ $v ? \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreReceptorFactura($v) : '—' }}</td>
                    <td>{{ $pvTxt !== '' ? $pvTxt : '—' }}</td>
                    <td class="num">{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $r->cuenta_gastronomia_id ?? '—' }}</td>
                    <td>{{ $r->identificador_pc ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
