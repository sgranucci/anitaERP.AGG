@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filas = $filas ?? ($resultado['filas'] ?? []);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        collect([(object) ['nombreempresa' => (string) config('app.empresa', '')]])
    );
    $titulo = $titulo ?? 'Conciliación facturación juego';
    $subtitulo = (string) ($resultado['subtitulo'] ?? '');
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $resumen = $resultado['resumen'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data thead { display: table-header-group; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas { background-color: #85C1E9; }
        table.data thead tr.columnas > th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
            background-color: #85C1E9;
        }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'colspan' => 12,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th style="width:7%;">Fecha</th>
                <th style="width:6%;">Sala</th>
                <th style="width:10%;">Rubro</th>
                <th style="width:9%;">Cuenta</th>
                <th style="width:8%;" class="text-right">Flash</th>
                <th style="width:8%;" class="text-right">Ventas</th>
                <th style="width:8%;" class="text-right">ERP</th>
                <th style="width:8%;" class="text-right">Anita</th>
                <th style="width:7%;" class="text-right">Dif.Vta</th>
                <th style="width:7%;" class="text-right">Dif.ERP</th>
                <th style="width:7%;" class="text-right">Dif.Ani</th>
                <th style="width:7%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ $fila['fecha_fmt'] ?? $fila['fecha'] }}</td>
                    <td>{{ $fila['sala_codigo'] }}</td>
                    <td>{{ $fila['rubro_label'] ?? $fila['rubro'] }}</td>
                    <td>{{ $fila['cuenta_codigo'] ?: '—' }}</td>
                    <td class="text-right">{{ number_format((float) $fila['flash'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['ventas'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['erp'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['anita'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['dif_ventas'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['dif_erp'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['dif_anita'], 2, ',', '.') }}</td>
                    <td>{{ $fila['estado'] }}</td>
                </tr>
            @endforeach
            @if ($totalFilas === 0)
                <tr>
                    <td colspan="12" style="text-align:center;">Sin filas para el rango consultado.</td>
                </tr>
            @endif
            @if ($totalFilas > 0)
                <tr>
                    <td colspan="4"><strong>Totales</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($resumen['total_ventas'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($resumen['total_erp'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($resumen['total_anita'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td colspan="4"></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
