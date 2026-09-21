@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($registros);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Facturas Local</title>
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
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
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
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Facturas Local</h2>
                <div class="meta">
                    @if (! empty($localNombre))
                        Local: {{ $localNombre }} ·
                    @endif
                    Desde {{ \Illuminate\Support\Carbon::parse($desde)->format('d-m-Y') }}
                    hasta {{ \Illuminate\Support\Carbon::parse($hasta)->format('d-m-Y') }}
                    · Generado {{ date('d-m-Y H:i') }}
                    · {{ is_countable($registros) ? count($registros) : 0 }} registro(s)
                </div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Venta ID</th>
                <th>Fecha</th>
                <th>Comprobante</th>
                <th>Local</th>
                <th>Cliente</th>
                <th>Punto de venta</th>
                <th class="num">Total</th>
                <th>NC</th>
                <th>CAE</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($registros as $r)
                @php
                    $v = $r->venta;
                    $pvTxt = $v ? trim(($v->puntoventas->codigo ?? '').' '.($v->puntoventas->nombre ?? '')) : '';
                    $clienteTxt = $v?->nombre ?: ($v?->clientes?->nombre ?? '—');
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
                    <td>{{ trim(($r->localVenta->codigo ?? '').' '.($r->localVenta->nombre ?? '')) ?: '—' }}</td>
                    <td>{{ $clienteTxt }}</td>
                    <td>{{ $pvTxt !== '' ? $pvTxt : '—' }}</td>
                    <td class="num">{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $r->ventaNc?->codigo ?? ($r->venta_nc_id ?? '—') }}</td>
                    <td>{{ $v?->cae ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
