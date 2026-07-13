@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
    use App\Support\Contable\CierreRendicionEstacionamientoMediosCobroSupport;

    $vistaPorTurno = ! empty($vistaPorTurno);
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);

    if ($vistaPorTurno) {
        $filasFuente = $rendiciones ?? collect();
        foreach ($filasFuente as $row) {
            $row->nombreempresa = $row->empresa->nombre ?? '';
        }
        $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasFuente);
        $totalFilas = is_countable($filasFuente) ? count($filasFuente) : 0;
        $tituloReporte = 'Cierre rendiciones estacionamiento — por turno';
        if (! isset($columnasMedios)) {
            $columnasMedios = CierreRendicionEstacionamientoMediosCobroSupport::columnasDesdeFilasExport($filasFuente, true);
        }
        // ID..Invit (13) + medios + Total cobrado
        $colspan = 13 + count($columnasMedios) + 1;
    } else {
        $gruposList = $grupos ?? [];
        $paraLogos = collect($gruposList)->map(static fn ($g) => (object) [
            'nombreempresa' => (string) ($g['empresa_nombre'] ?? ''),
        ]);
        $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($paraLogos);
        $totalFilas = count($gruposList);
        $tituloReporte = 'Cierre rendiciones estacionamiento — por PV y fecha';
        if (! isset($columnasMedios)) {
            $columnasMedios = CierreRendicionEstacionamientoMediosCobroSupport::columnasDesdeFilasExport($gruposList, false);
        }
        // Fecha..Invit (8) + medios + Total cobrado + Estado + Asiento
        $colspan = 8 + count($columnasMedios) + 1 + 2;
    }

    $subtituloFiltros = trim((string) ($subtituloFiltros ?? ''));
    $leyendaMedios = '';
        if (count($columnasMedios) > 0) {
        $pares = [];
        foreach ($columnasMedios as $m) {
            $desc = (string) ($m['label_descripcion'] ?? $m['nombre'] ?? '');
            $codigo = (string) ($m['codigo'] ?? '');
            if ($desc === '') {
                continue;
            }
            $pares[] = $codigo !== '' && $codigo !== $desc
                ? $desc.' ('.$codigo.')'
                : $desc;
        }
        if ($pares !== []) {
            $leyendaMedios = 'Medios de cobro: '.implode(' | ', $pares);
        }
    }
    if ($subtituloFiltros !== '' && $leyendaMedios !== '') {
        $subtituloCompleto = $subtituloFiltros.($esExcel ? "\n" : ' — ').$leyendaMedios;
    } else {
        $subtituloCompleto = $subtituloFiltros !== '' ? $subtituloFiltros : $leyendaMedios;
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    {{-- PhpSpreadsheet usa <title> como nombre de hoja Excel (máx. 31 chars) --}}
    <title>Cierre rend. estacionamiento</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: middle;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        @if (! $esExcel)
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
            text-align: center;
            vertical-align: middle;
        }
        @else
        table.data th {
            font-size: 10px;
            font-weight: bold;
            color: #17202A;
            font-family: Arial, Helvetica, sans-serif;
            white-space: nowrap;
        }
        @endif
        .num { text-align: right; }
        table.data td.num,
        table.data th.num {
            text-align: right !important;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; white-space: normal; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .filtros-excel { font-size: 9px; color: #444444; font-family: Arial, Helvetica, sans-serif; }
    </style>
</head>
<body>
@if ($esExcel)
    {{-- logo + t&iacute;tulo + filtros + cabeceras + datos (una sola tabla) --}}
    <table class="data">
        @if ($reservarFilaLogoExcel)
            <tbody>
                <tr>
                    <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
                </tr>
            </tbody>
        @endif
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt; font-family: Arial, Helvetica, sans-serif;">{{ $tituloReporte }}</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}" class="filtros-excel">{{ $subtituloCompleto !== '' ? $subtituloCompleto : 'Sin filtros indicados' }}</td>
            </tr>
        </tbody>
        <thead>
            @include('contable.cierre_rendicion_estacionamiento.partials.listado_cabeceras', [
                'vistaPorTurno' => $vistaPorTurno,
                'columnasMedios' => $columnasMedios,
            ])
        </thead>
        <tbody>
            @include('contable.cierre_rendicion_estacionamiento.partials.listado_filas', [
                'vistaPorTurno' => $vistaPorTurno,
                'rendiciones' => $rendiciones ?? collect(),
                'grupos' => $grupos ?? [],
                'columnasMedios' => $columnasMedios,
                'colspan' => $colspan,
            ])
        </tbody>
    </table>
@else
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 15px; font-weight: bold;">{{ $tituloReporte }}</h2>
                @if ($subtituloCompleto !== '')
                    <div class="meta" style="white-space: normal;">{{ $subtituloCompleto }}</div>
                @else
                    <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @endif
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            @include('contable.cierre_rendicion_estacionamiento.partials.listado_cabeceras', [
                'vistaPorTurno' => $vistaPorTurno,
                'columnasMedios' => $columnasMedios,
            ])
        </thead>
        <tbody>
            @include('contable.cierre_rendicion_estacionamiento.partials.listado_filas', [
                'vistaPorTurno' => $vistaPorTurno,
                'rendiciones' => $rendiciones ?? collect(),
                'grupos' => $grupos ?? [],
                'columnasMedios' => $columnasMedios,
                'colspan' => $colspan,
            ])
        </tbody>
    </table>
@endif
</body>
</html>
