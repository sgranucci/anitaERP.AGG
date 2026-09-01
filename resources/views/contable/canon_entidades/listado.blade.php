@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filasParaLogo = $filasParaLogo ?? [];
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasParaLogo));
    $tituloReporte = $titulo ?? 'F2015 · Canon entidades';
    $resultado = $resultado ?? [];
    $identidad = $resultado['identidad'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $conciliacion = $resultado['conciliacion'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $bingoEscalonado = ! empty($identidad['bingo_escalonado']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1a1a1a;
            margin: 0;
        }
        table.data {
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            padding: 2px 4px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 6px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 7px; color: #444; }
        .excluido { background-color: #fcf3cf; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width:32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height:52px; max-width:160px; margin-right:8px;">
                @endforeach
            </td>
            <td style="width:46%; text-align:center;">
                <h2 style="margin:0; font-size:14px;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width:22%; text-align:right;" class="meta">
                @if (! empty($conciliacion['cuadra']))
                    Estado: Conforme
                @else
                    Estado: Desvío
                @endif
            </td>
        </tr>
    </table>

    <p class="meta">
        {{ $identidad['nombre'] ?? '' }}
        · {{ $identidad['codigo'] ?? '' }}
        · CUIT {{ $identidad['cuit_formato'] ?? '' }}
        · Bingo {{ $identidad['etiqueta_bingo'] ?? '' }}
        · {{ $identidad['cuenta_etiqueta'] ?? '215010-003' }}
    </p>
    <p class="meta">
        Máquinas {{ number_format((float) ($totales['canon_maq'] ?? 0), 2, ',', '.') }}
        · Bingo {{ number_format((float) ($totales['canon_bin'] ?? 0), 2, ',', '.') }}
        · Total {{ number_format((float) ($totales['canon_total'] ?? 0), 2, ',', '.') }}
        · Σ Haber {{ number_format((float) ($conciliacion['haber_total'] ?? 0), 2, ',', '.') }}
        · Dif. {{ number_format((float) ($conciliacion['diferencia'] ?? 0), 2, ',', '.') }}
    </p>

    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="num">Win Electrónico</th>
                <th class="num">Canon máq.</th>
                <th class="num">Ventas bingo</th>
                @if ($bingoEscalonado)
                    <th class="num">Bingo 2%</th>
                    <th class="num">Bingo 3,25%</th>
                @endif
                <th class="num">Canon bingo</th>
                <th class="num">Total día</th>
                <th class="num">Σ Haber día</th>
                <th class="num">Dif. día</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr class="{{ ! empty($fila['excluido_maq']) ? 'excluido' : '' }}">
                    <td>{{ $fila['fecha'] ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($fila['win_electronico'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['canon_maq'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['ventas_bingo'] ?? 0), 2, ',', '.') }}</td>
                    @if ($bingoEscalonado)
                        <td class="num">{{ number_format((float) ($fila['bingo_tramo_2'] ?? 0), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) ($fila['bingo_tramo_325'] ?? 0), 2, ',', '.') }}</td>
                    @endif
                    <td class="num">{{ number_format((float) ($fila['canon_bin'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['canon_total'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['haber_total'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['dif_dia'] ?? 0), 2, ',', '.') }}</td>
                    <td>
                        @if (empty($fila['tiene_flash']))
                            Sin flash
                        @elseif (! empty($fila['excluido_maq']))
                            Win ≤ 0 · excluido
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        @if ($filas !== [])
            <tfoot>
                <tr>
                    <td><strong>Totales</strong></td>
                    <td class="num">{{ number_format((float) ($totales['base_maq'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($totales['canon_maq'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($totales['base_bingo'] ?? 0), 2, ',', '.') }}</td>
                    @if ($bingoEscalonado)
                        <td></td>
                        <td></td>
                    @endif
                    <td class="num">{{ number_format((float) ($totales['canon_bin'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($totales['canon_total'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($conciliacion['haber_total'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($conciliacion['diferencia'] ?? 0), 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
