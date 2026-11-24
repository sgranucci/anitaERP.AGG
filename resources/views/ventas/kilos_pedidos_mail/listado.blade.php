@php
    use Carbon\Carbon;

    $formatear = static fn ($v) => number_format((float) $v, 2, '.', ',');
    $formatearKgEntero = static fn ($v) => number_format((float) $v, 0, '.', ',');

    $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
    $fechaHasta = (string) ($filtros['fecha_hasta'] ?? $fechaDesde);
    $fechaTxt = static function (string $ymd): string {
        if ($ymd === '') {
            return '';
        }
        try {
            return Carbon::parse($ymd)->format('d-m-y');
        } catch (\Throwable $e) {
            return $ymd;
        }
    };

    $repartoDesde = trim((string) ($filtros['reparto_desde'] ?? ''));
    $repartoHasta = trim((string) ($filtros['reparto_hasta'] ?? ''));
    if ($repartoDesde === '' && $repartoHasta === '') {
        $repartoDesdeTxt = '0';
        $repartoHastaTxt = '999999';
    } else {
        $repartoDesdeTxt = $repartoDesde !== '' ? $repartoDesde : '1';
        $repartoHastaTxt = ($repartoHasta !== '' && $repartoHasta !== '999999') ? $repartoHasta : '999999';
    }

    $empresaNombre = trim((string) ($empresaNombre ?? ''));
    if ($empresaNombre === '') {
        $empresaNombre = (string) (\App\Models\Configuracion\Empresa::query()->value('nombre') ?? config('app.empresa'));
    }
    $horaTxt = now()->format('H:i');
    $fechaCabecera = now()->format('d-m-y');
    $repartos = $repartos ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Kilos pedidos</title>
    <style>
        body {
            font-family: DejaVu Sans Mono, Courier New, monospace;
            font-size: 9px;
            color: #111;
            line-height: 1.25;
            margin: 8px 12px;
        }
        .titulo { font-size: 12px; font-weight: bold; margin: 0; }
        .subtitulo { font-size: 10px; margin: 0 0 4px 0; }
        .meta { margin: 0 0 2px 0; }
        .sep { margin: 4px 0; letter-spacing: 0; }
        table.datos {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.datos thead { display: table-header-group; }
        table.datos th, table.datos td {
            padding: 1px 3px;
            vertical-align: top;
            border: none;
            font-weight: normal;
        }
        table.datos th {
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            font-weight: bold;
            text-align: left;
        }
        .num { text-align: right; white-space: nowrap; }
        .art { width: 8%; }
        .desc { width: 52%; }
        .piezas, .cajas { width: 13%; }
        .kilos { width: 14%; }
        .agrup {
            font-weight: bold;
            padding-top: 6px;
            padding-bottom: 2px;
        }
        .total-final td {
            font-weight: bold;
            border-top: 1px solid #333;
            border-bottom: 2px solid #333;
            padding-top: 4px;
            padding-bottom: 4px;
        }
        h3.repartos {
            font-size: 10px;
            font-weight: bold;
            margin: 14px 0 4px 0;
        }
        table.repartos {
            border-collapse: collapse;
            width: 70%;
            table-layout: fixed;
        }
        table.repartos th, table.repartos td {
            padding: 1px 3px;
            border: none;
            text-align: left;
        }
        table.repartos th {
            border-bottom: 1px solid #333;
            font-weight: bold;
        }
        table.repartos .num { text-align: right; }
        .col-rep { width: 18%; }
        .col-den { width: 52%; }
        .col-carga { width: 30%; }
    </style>
</head>
<body>
    <p class="titulo">Kilos pedidos</p>
    <p class="subtitulo">x reparto y agrupacion</p>
    <p class="meta">Empresa : {{ $empresaNombre }} &nbsp;&nbsp; Hora : {{ $horaTxt }}</p>
    <p class="meta">Fecha : {{ $fechaCabecera }}</p>
    <p class="meta">Desde reparto {{ $repartoDesdeTxt }} hasta reparto {{ $repartoHastaTxt }}</p>
    <p class="meta">Desde {{ $fechaTxt($fechaDesde) }} hasta {{ $fechaTxt($fechaHasta) }}</p>
    <p class="meta">Kilos teoricos - Pendientes y cumplidos</p>

    <table class="datos">
        <thead>
            <tr>
                <th class="art">Art.</th>
                <th class="desc">Descripcion</th>
                <th class="piezas num">Piezas</th>
                <th class="kilos num">Kilos</th>
                <th class="cajas num">Cajas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
                @if ($tipo === 'encabezado_agrupacion')
                    <tr>
                        <td class="agrup" colspan="5">
                            Agrupacion: {{ $fila['codigocategoria'] ?? '' }}
                            {{ $fila['nombrecategoria'] ?? '' }}
                        </td>
                    </tr>
                @elseif ($tipo === 'total_final')
                    <tr class="total-final">
                        <td colspan="2">Total general</td>
                        <td class="num">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                        <td class="num">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                        <td class="num">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $fila['sku'] ?? '' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) ($fila['descripcion'] ?? ''), 30, '') }}</td>
                        <td class="num">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                        <td class="num">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                        <td class="num">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="5">Sin pedidos para el filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($repartos))
        <h3 class="repartos">Repartos con pedidos:</h3>
        <table class="repartos">
            <thead>
                <tr>
                    <th class="col-rep">Reparto</th>
                    <th class="col-den">Denominacion</th>
                    <th class="col-carga num">Carga (kg)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($repartos as $rep)
                    <tr>
                        <td>{{ $rep['codigo'] ?? '' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) ($rep['nombre'] ?? ''), 15, '') }}</td>
                        <td class="num">{{ $formatearKgEntero($rep['total_kilo'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
