@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $tot = $totales ?? [];
    $tituloReporte = $titulo ?? 'Mayor analítico por cuenta contable';
    $multiempresa = count($filtros['empresa_ids'] ?? []) > 1
        || empty($filtros['consolidar_empresas']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 6.5px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (!empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        @include('contable.mayor_plano_cuenta.partials.tabla_datos', [
            'filas' => $filas,
            'puede_ver_asiento' => false,
            'puede_ver_cuenta' => false,
            'puede_ver_ordencompra' => false,
            'multiempresa' => $multiempresa,
        ])
    </table>

    @if (! empty($tot))
        <p class="meta" style="margin-top: 8px;">
            Totales: {{ (int) ($tot['cantidad_cuentas'] ?? 0) }} cuentas,
            {{ (int) ($tot['cantidad_filas'] ?? 0) }} líneas,
            Debe {{ number_format((float) ($tot['total_debe'] ?? 0), 2, ',', '.') }},
            Haber {{ number_format((float) ($tot['total_haber'] ?? 0), 2, ',', '.') }}
        </p>
    @endif
</body>
</html>
