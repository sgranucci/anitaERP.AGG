@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($registros);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Movimientos Interbanking (persistidos)</title>
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
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Movimientos Interbanking (persistidos)</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>
    <table class="data">
        <colgroup>
            <col style="width: 10%;">
            <col style="width: 11%;">
            <col style="width: 9%;">
            <col style="width: 10%;">
            <col style="width: 4%;">
            <col style="width: 7%;">
            <col style="width: 4%;">
            <col style="width: 8%;">
            <col style="width: 27%;">
            <col style="width: 10%;">
        </colgroup>
        <thead>
            <tr>
                <th>Fecha proceso</th>
                <th>Empresa</th>
                <th>Banco</th>
                <th>Cuenta</th>
                <th>Moneda</th>
                <th>Tipo API</th>
                <th>D/C</th>
                <th class="num">Importe</th>
                <th>Descripción</th>
                <th>Comprobante</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($registros as $r)
                <tr>
                    <td>{{ $r->process_date ? $r->process_date->format('d/m/Y H:i') : '—' }}</td>
                    <td>{{ $r->empresa->nombre ?? '' }}</td>
                    <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                    <td>{{ $r->account_number }}</td>
                    <td>{{ $r->currency }}</td>
                    <td>{{ $r->movement_type }}</td>
                    <td>{{ $r->debit_credit_type }}</td>
                    <td class="num">{{ number_format((float) $r->amount, 2, ',', '.') }}</td>
                    <td>{{ (string) ($r->code_description_bank ?? '') }}</td>
                    <td>{{ $r->voucher_number ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
