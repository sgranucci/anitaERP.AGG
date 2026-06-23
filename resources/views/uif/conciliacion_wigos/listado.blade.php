@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filasLogo = collect($filas)->map(function ($f) use ($empresa) {
        $f->nombreempresa = $empresa->nombre ?? '';

        return $f;
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasLogo);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $totalMonto = collect($filas)->sum(fn ($f) => (float) ($f->monto ?? 0));
    $fmtMonto = static fn ($v) => '$ '.number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Conciliación Wigos' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
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
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .text-right { text-align: right; }
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
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">{{ $titulo ?? 'Conciliación Wigos UIF' }}</h2>
                @if (!empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
                @if (!empty($periodo_texto))
                    <div class="meta">Período {{ $periodo_texto }}</div>
                @endif
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}<br>
                    Total premios: {{ $fmtMonto($totalMonto) }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 18%;">Fecha pago</th>
                <th style="width: 18%;">Fecha emisión</th>
                <th style="width: 14%; text-align: right;">Monto</th>
                <th style="width: 12%;">Terminal</th>
                <th style="width: 22%;">Número</th>
                <th style="width: 10%;">Origen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ $fila->fecha_pago ? $fila->fecha_pago->format('d/m/Y H:i') : '' }}</td>
                    <td>{{ $fila->fecha_emision ? $fila->fecha_emision->format('d/m/Y H:i') : '' }}</td>
                    <td class="text-right">
                        @if ($fila->monto !== null)
                            {{ $fmtMonto($fila->monto) }}
                        @endif
                    </td>
                    <td>{{ $fila->terminal }}</td>
                    <td>{{ $fila->numero }}</td>
                    <td>{{ $fila->origen }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
