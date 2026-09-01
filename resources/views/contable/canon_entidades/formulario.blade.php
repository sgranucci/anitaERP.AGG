@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $identidad = $resultado['identidad'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $conciliacion = $resultado['conciliacion'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $nombre = (string) ($identidad['nombre'] ?? '');
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object) ['nombreempresa' => $nombre]]));
    $logoUri = $logos[0]['uri'] ?? null;
    $cuadra = ! empty($conciliacion['cuadra']);
    $bingoEscalonado = ! empty($identidad['bingo_escalonado']);
    $fechaEmision = $fecha_emision ?? now();
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    $fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Formulario canon entidades — {{ $nombre }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.35;
        }
        .encabezado { width: 100%; margin-bottom: 8px; }
        .encabezado td { vertical-align: middle; border: none; }
        h1 {
            font-size: 14px;
            margin: 0 0 2px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .subtitulo { text-align: center; font-size: 9px; color: #333; margin-bottom: 10px; }
        .caja {
            border: 1px solid #333;
            padding: 7px 8px;
            margin-bottom: 10px;
        }
        .caja h2 {
            font-size: 11px;
            margin: 0 0 6px;
            text-transform: uppercase;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
        }
        table.meta, table.grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.meta td { padding: 2px 4px 2px 0; vertical-align: top; }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 3px 5px;
        }
        table.grid th {
            background: #e8e8e8;
            font-size: 8.5px;
            text-align: center;
        }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .total { font-weight: bold; background: #f0f0f0; }
        .ok { color: #1b6b1b; font-weight: bold; }
        .desvio { color: #8a1c1c; font-weight: bold; }
        .aviso { font-size: 8px; color: #333; margin-top: 4px; }
        .firma { margin-top: 22px; width: 100%; }
        .firma td { border: none; text-align: center; width: 50%; padding-top: 28px; }
        .linea-firma { border-top: 1px solid #333; padding-top: 4px; font-size: 9px; }
        .pie { font-size: 8px; color: #555; margin-top: 10px; text-align: center; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td style="width:28%;">
                @if ($logoUri)
                    <img src="{{ $logoUri }}" alt="logo" style="max-height:52px; max-width:150px;">
                @endif
            </td>
            <td style="width:44%; text-align:center;">
                <h1>Canon entidad de bien público</h1>
                <div class="subtitulo">Formulario de liquidación · Adjunto a solicitud de pago</div>
            </td>
            <td style="width:28%; text-align:right; font-size:8.5px;">
                <div><strong>{{ $nombre }}</strong></div>
                @if (! empty($identidad['codigo']))
                    <div>{{ $identidad['codigo'] }}</div>
                @endif
                <div>CUIT {{ $identidad['cuit_formato'] ?? '' }}</div>
                @if (($identidad['domicilio'] ?? '') !== '')
                    <div>{{ $identidad['domicilio'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="caja">
        <table class="meta">
            <tr>
                <td style="width:50%;"><strong>Período:</strong> {{ $periodo_texto ?? '' }}</td>
                <td style="width:50%;"><strong>Cuenta pasivo:</strong> {{ $identidad['cuenta_etiqueta'] ?? '215010-003' }}</td>
            </tr>
            <tr>
                <td><strong>Máquinas:</strong> {{ $identidad['etiqueta_maquinas'] ?? '1% Win Electrónico' }}</td>
                <td><strong>Bingo:</strong> {{ $identidad['etiqueta_bingo'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <div class="caja">
        <h2>1. Distribución del pago entre máquinas y bingo</h2>
        <table class="grid">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Base</th>
                    <th>Canon a pagar</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Máquinas</td>
                    <td class="num">{{ $fmt((float) ($totales['base_maq'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($totales['canon_maq'] ?? 0)) }}</td>
                </tr>
                <tr>
                    <td>Bingo</td>
                    <td class="num">{{ $fmt((float) ($totales['base_bingo'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($totales['canon_bin'] ?? 0)) }}</td>
                </tr>
                <tr class="total">
                    <td>Total</td>
                    <td></td>
                    <td class="num">{{ $fmt((float) ($totales['canon_total'] ?? 0)) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="caja">
        <h2>2. Conciliación</h2>
        <table class="grid">
            <thead>
                <tr>
                    <th>Canon calculado</th>
                    <th>Σ Haber MAQ</th>
                    <th>Σ Haber BIN</th>
                    <th>Σ Haber mayor</th>
                    <th>Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="num">{{ $fmt((float) ($totales['canon_total'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($conciliacion['haber_maq'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($conciliacion['haber_bin'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($conciliacion['haber_total'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($conciliacion['diferencia'] ?? 0)) }}</td>
                    <td class="ctr {{ $cuadra ? 'ok' : 'desvio' }}">
                        {{ $cuadra ? 'CONFORME' : 'DESVÍO' }}
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="aviso">
            {{ $conciliacion['aviso_criterio'] ?? 'El pasivo a conciliar es la Σ Haber de tipos MAQ + BIN en el período. El saldo neto no representa el canon.' }}
            Tolerancia ≤ $ {{ $fmt((float) ($conciliacion['tolerancia'] ?? 1)) }}.
        </div>
    </div>

    <div class="caja">
        <h2>3. Distribución día por día</h2>
        <table class="grid">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Win Electrónico</th>
                    <th>Canon máq.</th>
                    <th>Ventas bingo</th>
                    @if ($bingoEscalonado)
                        <th>Bingo 2%</th>
                        <th>Bingo 3,25%</th>
                    @endif
                    <th>Canon bingo</th>
                    <th>Total día</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td class="ctr">{{ $fila['fecha'] ?? '' }}</td>
                        <td class="num">{{ $fmt((float) ($fila['win_electronico'] ?? 0)) }}</td>
                        <td class="num">{{ $fmt((float) ($fila['canon_maq'] ?? 0)) }}</td>
                        <td class="num">{{ $fmt((float) ($fila['ventas_bingo'] ?? 0)) }}</td>
                        @if ($bingoEscalonado)
                            <td class="num">{{ $fmt((float) ($fila['bingo_tramo_2'] ?? 0)) }}</td>
                            <td class="num">{{ $fmt((float) ($fila['bingo_tramo_325'] ?? 0)) }}</td>
                        @endif
                        <td class="num">{{ $fmt((float) ($fila['canon_bin'] ?? 0)) }}</td>
                        <td class="num">{{ $fmt((float) ($fila['canon_total'] ?? 0)) }}</td>
                        <td class="ctr" style="font-size:7.5px;">
                            @if (empty($fila['tiene_flash']))
                                Sin flash
                            @elseif (! empty($fila['excluido_maq']))
                                Win ≤ 0 · excluido
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="ctr">TOTALES</td>
                    <td class="num">{{ $fmt((float) ($totales['base_maq'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($totales['canon_maq'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($totales['base_bingo'] ?? 0)) }}</td>
                    @if ($bingoEscalonado)
                        <td></td>
                        <td></td>
                    @endif
                    <td class="num">{{ $fmt((float) ($totales['canon_bin'] ?? 0)) }}</td>
                    <td class="num">{{ $fmt((float) ($totales['canon_total'] ?? 0)) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <table class="firma">
        <tr>
            <td>
                <div class="linea-firma">Firma / Aclaración</div>
            </td>
            <td>
                <div class="linea-firma">
                    {{ $fechaEmision->format('d') }} de {{ $meses[(int) $fechaEmision->format('n')] ?? '' }} de {{ $fechaEmision->format('Y') }}
                </div>
            </td>
        </tr>
    </table>
    <div class="pie">Documento para adjuntar a la solicitud de pago · {{ $nombre }} · {{ $periodo_texto ?? '' }}</div>
</body>
</html>
