@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport;

    $ficha = $ficha ?? [];
    $resultado = $resultado ?? [];
    $filas = $resultado['filas'] ?? [];
    $resumen = $resultado['resumen'] ?? [];
    $plantilla = (string) ($ficha['plantilla'] ?? 'biyemas');
    $municipio = (string) ($ficha['municipio'] ?? '');
    $cuit = (string) ($ficha['cuit'] ?? '');
    $legajo = (string) ($ficha['legajo'] ?? '');
    $nombre = (string) ($ficha['nombre'] ?? '');
    $pie = (string) ($ficha['pie_razon_social'] ?? $nombre);
    $domicilio = (string) ($ficha['domicilio'] ?? '');
    $direccionExtra = (string) ($ficha['direccion_extra'] ?? '');
    $telefono = (string) ($ficha['telefono'] ?? '');
    $firmante = (string) ($ficha['firmante_nombre'] ?? 'Marisol Gonzalez');
    $cargo = (string) ($ficha['firmante_cargo'] ?? 'Impuestos');
    $desde = (string) ($resultado['fecha_desde'] ?? '');
    $hasta = (string) ($resultado['fecha_hasta'] ?? '');
    $fechaNota = $fecha_nota ?? now();

    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object) ['nombreempresa' => $nombre]]));
    $logoUri = $logos[0]['uri'] ?? null;

    $fmtFechaTabla = static function (string $ymd) use ($plantilla): string {
        $ts = strtotime($ymd);
        if ($plantilla === 'rebisco') {
            return date('j/n/Y', $ts);
        }

        return date('d/m/y', $ts);
    };

    $fmtImporte = static function (float $n): string {
        if (abs($n) < 0.01) {
            return '-';
        }

        return number_format($n, 2, '.', ',');
    };

    $mesNombre = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    $mesDesde = (int) date('n', strtotime($desde));
    $anioDesde = (int) date('Y', strtotime($desde));
    $diaDesde = date('d', strtotime($desde));
    $diaHasta = date('d', strtotime($hasta));
    $mesTxt = $mesNombre[$mesDesde] ?? '';

    $etiquetaQuincena = CanonMunicipalCalendarioSupport::etiquetaQuincena($desde, $hasta);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Nota canon municipal — {{ $pie }}</title>
    <style>
        @page { margin: 18mm 18mm 18mm 18mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            line-height: 1.45;
        }
        .encabezado { width: 100%; margin-bottom: 14px; }
        .encabezado td { vertical-align: top; border: none; }
        .municipio-fecha { margin: 18px 0 14px; }
        .cuerpo { text-align: justify; margin: 12px 0 18px; }
        table.nota {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.nota th, table.nota td {
            border: 1px solid #333;
            padding: 4px 6px;
        }
        table.nota th {
            background: #e8e8e8;
            text-align: center;
            font-size: 10px;
        }
        table.nota td.num { text-align: right; }
        table.nota td.fecha { text-align: center; }
        .firma { margin-top: 36px; text-align: center; }
        .firma .nombre { font-weight: bold; margin-top: 28px; }
        .meta-rebisco { font-size: 10px; margin-bottom: 6px; }
        .linea { border-bottom: 1px solid #333; margin: 4px 0 12px; }
    </style>
</head>
<body>
    @if ($plantilla === 'kandiko')
        <table class="encabezado">
            <tr>
                <td style="width:38%;">
                    @if ($logoUri)
                        <img src="{{ $logoUri }}" alt="logo" style="max-height:70px; max-width:180px;">
                    @endif
                </td>
                <td style="width:62%; text-align:right; font-size:10px;">
                    @if ($domicilio !== '')
                        <div>{{ $domicilio }}</div>
                    @endif
                    @if ($direccionExtra !== '')
                        <div>{{ $direccionExtra }}</div>
                    @endif
                    @if ($telefono !== '')
                        <div>Tel/Fax :{{ $telefono }}</div>
                    @endif
                </td>
            </tr>
        </table>
    @elseif ($plantilla === 'rebisco')
        <table class="encabezado">
            <tr>
                <td style="width:50%;">
                    @if ($logoUri)
                        <img src="{{ $logoUri }}" alt="logo" style="max-height:70px; max-width:200px;">
                    @endif
                </td>
                <td style="width:50%; text-align:right;" class="meta-rebisco">
                    <div>CUIT: {{ $cuit }}</div>
                    <div>Leg: {{ $legajo }}</div>
                </td>
            </tr>
        </table>
        <div class="linea"></div>
    @else
        {{-- biyemas --}}
        <table class="encabezado">
            <tr>
                <td style="width:40%;">
                    @if ($logoUri)
                        <img src="{{ $logoUri }}" alt="logo" style="max-height:70px; max-width:200px;">
                    @endif
                </td>
                <td style="width:60%; text-align:right; font-size:10px;">
                    @if ($domicilio !== '')
                        <div>{{ $domicilio }}</div>
                    @endif
                    @if ($direccionExtra !== '')
                        <div>{{ $direccionExtra }}</div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <div class="municipio-fecha">
        {{ $municipio }}, {{ $fechaNota->format('d') }} de {{ $mesNombre[(int) $fechaNota->format('n')] ?? '' }}@if ($plantilla === 'rebisco') {{ $fechaNota->format('Y') }}@else de {{ $fechaNota->format('Y') }}@endif
    </div>

    @if ($plantilla !== 'rebisco')
        <div>Señores</div>
    @endif
    <div>Municipalidad de {{ $municipio }}</div>
    <div style="margin-bottom:12px;"><strong>PRESENTE</strong></div>

    <div>De nuestra mayor consideración:</div>

    <div class="cuerpo">
        @if ($plantilla === 'rebisco')
            A través de la presente, adjuntamos el detalle de la {{ $etiquetaQuincena }} quincena del
            {{ $diaDesde }} A {{ $diaHasta }} de {{ $mesTxt }} de {{ $anioDesde }}, con la respectiva liquidación.
        @elseif ($plantilla === 'kandiko')
            A través de la presente, adjuntamos el detalle de la recaudación de la Sala de Bingo de la
            firma {{ $pie }} – Cuit: {{ $cuit }} Legajo {{ $legajo }} desde {{ $diaDesde }} al {{ $diaHasta }}
            de {{ ucfirst($mesTxt) }} de {{ $anioDesde }}, con la respectiva liquidación.
        @else
            A través de la presente, adjuntamos el detalle de la recaudación de la Sala de Bingo de
            {{ $pie }} con Cuit n° {{ $cuit }} Legajo Municipal N°{{ $legajo }}
            {{ $diaDesde }} al {{ $diaHasta }} de {{ $mesTxt }} de {{ $anioDesde }}, con la respectiva liquidación.
        @endif
    </div>

    <table class="nota">
        <thead>
            <tr>
                <th style="width:22%;">FECHA</th>
                <th style="width:39%;">Recaudación</th>
                <th style="width:39%;">4%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td class="fecha">{{ $fmtFechaTabla($fila['fecha']) }}</td>
                    <td class="num">{{ $fmtImporte((float) $fila['venta']) }}</td>
                    <td class="num">{{ $fmtImporte((float) $fila['canon']) }}</td>
                </tr>
            @endforeach
            @if ($plantilla === 'rebisco')
                <tr>
                    <td colspan="3" style="border:none; height:10px;"></td>
                </tr>
            @endif
            <tr>
                <td style="font-weight:bold; text-align:center;">TOTALES</td>
                <td class="num" style="font-weight:bold;">{{ $fmtImporte((float) ($resumen['total_flash'] ?? 0)) }}</td>
                <td class="num" style="font-weight:bold;">{{ $fmtImporte((float) ($resumen['canon_4'] ?? 0)) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:18px;">Sin otro particular, saludamos atentamente.</div>

    <div class="firma">
        <div class="nombre">{{ $firmante }}</div>
        <div>{{ $cargo }}</div>
        <div>{{ $pie }}</div>
    </div>
</body>
</html>
