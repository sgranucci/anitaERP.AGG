@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filas ?? []));
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $tituloReporte = $titulo ?? 'Comisiones de vendedores — resumen';
    $subtituloReporte = $subtitulo ?? '';
    $colspan = 5;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.35; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc; text-align: left; padding: 3px 4px; vertical-align: top;
            word-wrap: break-word; font-size: 7px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        table.data tr { page-break-inside: avoid; }
        .text-right { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $tituloReporte,
                'subtitulo' => $subtituloReporte,
                'colspan' => $colspan,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr>
                <th>N.Cli.</th>
                <th>Cliente</th>
                <th class="text-right">Neto</th>
                <th class="text-right">Comisi&oacute;n</th>
                <th>C.U.I.T.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas ?? [] as $fila)
                @php $tipo = $fila['tipo_fila'] ?? 'cliente'; @endphp
                @if ($tipo === 'header_vendedor')
                    <tr style="background-color: #85C1E9; font-weight: bold;">
                        <td colspan="{{ $colspan }}">
                            {{ $fila['descripcion'] ?? '' }}
                            — {{ number_format((float) ($fila['porcentaje'] ?? 0), 2, ',', '.') }}%
                            {{ $fila['aplica_sobre'] ?? '' }}
                        </td>
                    </tr>
                @elseif ($tipo === 'total_vendedor' || $tipo === 'total_final')
                    <tr style="background-color: {{ $tipo === 'total_final' ? '#AED6F1' : '#D6EAF8' }}; font-weight: bold;">
                        <td colspan="2">{{ $fila['descripcion'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['gravado'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['comision'] ?? 0), 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $fila['cliente_codigo'] ?? '' }}</td>
                        <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['gravado'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['comision'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $fila['cuit'] ?? '' }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
