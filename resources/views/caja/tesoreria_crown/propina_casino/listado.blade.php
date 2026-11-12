@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $casinos = $casinos ?? [];
    $filas = $filas ?? [];
    $totales = $totales ?? ['total' => 0, 'por_casino' => []];
    $colspan = 3 + count($casinos);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $totalFilas = count($filas);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Propina de empleados' }}</title>
    <style>
        @page { size: legal landscape; margin: 12mm 8mm 16mm 8mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; margin: 0; padding: 0; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead { display: table-header-group; }
        table.data tbody { display: table-row-group; }
        table.data tbody tr:nth-child(even):not(.total-general) { background-color: #f5f5f5; }
        table.data thead tr.columnas th {
            background-color: #85C1E9;
            font-size: 6.5px;
            font-weight: bold;
            color: #17202A;
            text-align: center;
        }
        table.data tr.total-general td { background-color: #AED6F1; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo ?? 'Propina de empleados',
                'subtitulo' => $subtitulo ?? '',
                'colspan' => $colspan,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th style="width: 8%;">Día</th>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 10%;">Total</th>
                @foreach ($casinos as $casino)
                    <th>{{ $casino['etiqueta'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    <td>{{ $fila['dia'] ?? '' }}</td>
                    <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
                    @foreach ($casinos as $casino)
                        @php $monto = (float) (($fila['por_casino'][(int) $casino['id']] ?? 0)); @endphp
                        <td class="text-right">{{ number_format($monto, 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" style="text-align:center;">Sin propinas para el período.</td>
                </tr>
            @endforelse
            @if ($totalFilas > 0)
                <tr class="total-general">
                    <td colspan="2">Total general</td>
                    <td class="text-right">{{ number_format((float) ($totales['total'] ?? 0), 2, ',', '.') }}</td>
                    @foreach ($casinos as $casino)
                        @php $monto = (float) (($totales['por_casino'][(int) $casino['id']] ?? 0)); @endphp
                        <td class="text-right">{{ number_format($monto, 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
