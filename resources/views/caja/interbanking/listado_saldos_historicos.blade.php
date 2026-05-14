@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($registros);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Saldos Interbanking (histórico)</title>
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
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Saldos Interbanking (histórico)</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>
    <table class="data">
        <colgroup>
            <col style="width: 6%;">
            <col style="width: 12%;">
            <col style="width: 10%;">
            <col style="width: 4%;">
            <col style="width: 10%;">
            <col style="width: 6%;">
            <col style="width: 8%;">
            <col style="width: 12%;">
            <col style="width: 8%;">
            <col style="width: 8%;">
            <col style="width: 8%;">
            <col style="width: 8%;">
        </colgroup>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Empresa</th>
                <th>Banco</th>
                <th>Moneda</th>
                <th>Cuenta</th>
                <th>Tipo</th>
                <th>Etiqueta</th>
                <th>Nombre</th>
                <th class="num">Débitos día</th>
                <th class="num">Créditos día</th>
                <th class="num">Saldo día</th>
                <th class="num">Balance actual (snapshot)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($registros as $r)
                <tr>
                    <td>{{ $r->fecha->format('d/m/Y') }}</td>
                    <td>{{ $r->empresa->nombre ?? '' }}</td>
                    <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                    <td>{{ $r->currency }}</td>
                    <td>{{ $r->account_number }}</td>
                    <td>{{ $r->account_type }}</td>
                    <td>{{ $r->account_label }}</td>
                    <td>{{ $r->account_name }}</td>
                    <td class="num">{{ number_format((float) $r->total_debits, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $r->total_credits, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $r->day_balance, 2, ',', '.') }}</td>
                    <td class="num">
                        @if ($r->current_operating_balance !== null)
                            {{ number_format((float) $r->current_operating_balance, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
