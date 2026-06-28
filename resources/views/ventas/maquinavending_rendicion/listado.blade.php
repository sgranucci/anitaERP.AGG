@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rendiciones vending</title>
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
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
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
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Rendiciones m&aacute;quinas vending</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">N&ordm; cierre: correlativo &uacute;nico por empresa</div>
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
            <tr>
                <th style="width: 8%;">N&ordm; cierre (emp.)</th>
                <th style="width: 14%;">Fecha rend.</th>
                <th style="width: 10%;">Jornada</th>
                <th style="width: 16%;">Empresa</th>
                <th style="width: 16%;">M&aacute;quina</th>
                <th style="width: 8%;">PV</th>
                <th style="width: 9%;">Total ventas</th>
                <th style="width: 9%;">Total cobrado</th>
                <th style="width: 7%;">Caja</th>
                <th style="width: 6%;">Anita</th>
                <th style="width: 8%;">Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($datas as $fila)
                <tr>
                    <td>#{{ (int) $fila->numero_cierre }}</td>
                    <td>{{ $fila->fecha_rendicion?->format('d/m/Y H:i') }}</td>
                    <td>{{ $fila->fecha_jornada?->format('d/m/Y') }}</td>
                    <td>{{ $fila->nombreempresa }}</td>
                    <td>{{ $fila->maquina_nombre }}</td>
                    <td>{{ $fila->puntoventa_codigo ?: '—' }}</td>
                    <td class="text-right">${{ number_format((float) $fila->total_ventas, 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format((float) $fila->total_cobrado, 2, ',', '.') }}</td>
                    <td>{{ $fila->rendicionCaja ? 'Presentada' : 'Pendiente' }}</td>
                    <td>{{ $fila->anita_sincronizado_en ? 'OK' : '—' }}</td>
                    <td>{{ optional($fila->usuario)->nombre }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
