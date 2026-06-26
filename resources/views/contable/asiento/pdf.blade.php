<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Asiento {{ $data->numeroasiento }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 8px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; }
        .cabecera td { width: 25%; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 14%; }
        .muted { color: #555; font-size: 8px; }
        .pdf-cabecera { margin-bottom: 10px; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa {
            max-width: 180px;
            max-height: 56px;
            width: auto;
            height: auto;
            object-fit: contain;
            vertical-align: middle;
        }
        .movimientos th, .movimientos td { font-size: 9px; padding: 4px 5px; word-wrap: break-word; }
        .movimientos .num { text-align: right; white-space: nowrap; }
        .totales td { font-weight: bold; background: #f5f5f5; }
    </style>
</head>
<body>
    @php
        use App\Support\Configuracion\EmpresaLogoArchivo;
        $nombreEmpresaLogo = optional($data->empresas)->nombre;
        $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresaLogo);
        $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($data->asiento_movimientos as $movimiento) {
            if ($movimiento->monto >= 0) {
                $totalDebe += $movimiento->monto;
            } else {
                $totalHaber += abs($movimiento->monto);
            }
        }
    @endphp
    <table class="pdf-cabecera">
        <tr>
            <td style="width:55%;">
                @if ($logoEmpresaDataUri)
                    <img class="logo-empresa" src="{{ $logoEmpresaDataUri }}" alt="">
                @endif
                <div style="font-size: 12px; font-weight: bold; margin-top: 4px;">{{ $nombreEmpresaLogo ?? '—' }}</div>
            </td>
            <td style="text-align: right;">
                <h1 style="margin-top: 0;">Asiento contable Nº {{ $data->numeroasiento }}</h1>
                <p class="muted" style="margin: 0;">Generado el {{ date('d/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <h2>Datos generales</h2>
    <table class="cabecera">
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ optional($data->empresas)->nombre ?? '—' }}</td>
            <td class="lbl">Tipo de asiento</td>
            <td>{{ optional($data->tipoasientos)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Fecha</td>
            <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—' }}</td>
            <td class="lbl">Generado por</td>
            <td>{{ optional($data->usuarios)->nombre ?? optional($data->usuarios)->usuario ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">ID interno</td>
            <td>{{ $data->id }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="lbl">Observaciones</td>
            <td colspan="3">{{ $data->observacion ?? '—' }}</td>
        </tr>
    </table>

    <h2>Movimientos</h2>
    <table class="movimientos">
        <thead>
            <tr>
                <th style="width: 8%;">Código</th>
                <th style="width: 22%;">Cuenta</th>
                <th style="width: 14%;">Centro de costo</th>
                <th style="width: 6%;">Moneda</th>
                <th style="width: 10%;" class="num">Debe</th>
                <th style="width: 10%;" class="num">Haber</th>
                <th style="width: 8%;" class="num">Cotización</th>
                <th style="width: 22%;">Detalle</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->asiento_movimientos as $movimiento)
                <tr>
                    <td>{{ optional($movimiento->cuentacontables)->codigo ?? '—' }}</td>
                    <td>{{ optional($movimiento->cuentacontables)->nombre ?? '—' }}</td>
                    <td>{{ optional($movimiento->centrocostos)->nombre ?? '—' }}</td>
                    <td>{{ optional($movimiento->monedas)->abreviatura ?? optional($movimiento->monedas)->nombre ?? '—' }}</td>
                    <td class="num">
                        @if ($movimiento->monto >= 0)
                            {{ number_format($movimiento->monto, 2, ',', '.') }}
                        @endif
                    </td>
                    <td class="num">
                        @if ($movimiento->monto < 0)
                            {{ number_format(abs($movimiento->monto), 2, ',', '.') }}
                        @endif
                    </td>
                    <td class="num">{{ number_format((float) $movimiento->cotizacion, 4, ',', '.') }}</td>
                    <td>{{ $movimiento->observacion ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totales">
                <td colspan="4" class="num">Totales</td>
                <td class="num">{{ number_format($totalDebe, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totalHaber, 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
