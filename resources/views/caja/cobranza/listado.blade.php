@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($cobranza);
    $totalFilas = is_countable($cobranza) ? count($cobranza) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cobranzas</title>
    <style>
        @page { size: legal landscape; margin: 14mm 8mm 16mm 8mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; margin: 0; padding: 0; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead { display: table-header-group; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas > th {
            background-color: #85C1E9;
            color: #17202A;
            font-size: 7px;
            font-weight: bold;
        }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => 'Cobranzas',
                'subtitulo' => $subtitulo ?? '',
                'colspan' => 10,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th style="width: 5%;">ID</th>
                <th style="width: 12%;">Empresa</th>
                <th style="width: 8%;">N&uacute;mero</th>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 18%;">Cliente</th>
                <th style="width: 18%;">Detalle</th>
                <th style="width: 8%;">Estado</th>
                <th style="width: 6%;">Moneda</th>
                <th style="width: 9%;" class="text-right">Monto</th>
                <th style="width: 8%;" class="text-right">Prom. d&iacute;as</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cobranza as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->nombreempresa }}</td>
                    <td>{{ $data->numerotransaccion }}</td>
                    <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
                    <td>{{ $data->nombrecliente }}</td>
                    <td>{{ $data->detalle ?? '' }}</td>
                    <td>{{ $data->estado ?? '' }}</td>
                    <td>{{ $data->monedas->abreviatura ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
                    <td class="text-right">
                        @if (($data->promedio_pago_dias ?? null) === null)
                            —
                        @else
                            {{ $data->promedio_pago_dias }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
