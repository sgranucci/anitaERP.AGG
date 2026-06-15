@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $tot = $totales ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Listado canjes marketing</title>
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
        .text-right { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
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
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado canjes marketing</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta']))
                    <div class="meta">
                        Período:
                        {{ $filtros['fecha_desde'] ?? '—' }}
                        @if (($filtros['fecha_hasta'] ?? '') !== ($filtros['fecha_desde'] ?? ''))
                            → {{ $filtros['fecha_hasta'] ?? '—' }}
                        @endif
                    </div>
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
            <tr>
                <th style="width: 7%;">Fecha</th>
                <th style="width: 10%;">Empresa</th>
                <th style="width: 5%;">Id VIP</th>
                <th style="width: 8%;">Nombre</th>
                <th style="width: 8%;">Apellido</th>
                <th style="width: 7%;">Nickname</th>
                <th style="width: 9%;">Mozo</th>
                <th style="width: 18%;">Producto</th>
                <th style="width: 5%;" class="text-right">Cant.</th>
                <th style="width: 6%;" class="text-right">CMV</th>
                <th style="width: 6%;" class="text-right">P. venta</th>
                <th style="width: 8%;">Sala</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td>{{ $f->fechacanje_fmt ?? '—' }}</td>
                    <td>{{ $f->nombreempresa ?? '—' }}</td>
                    <td>{{ $f->numeroid_vip ?? '—' }}</td>
                    <td>{{ $f->nombre_vip ?? '—' }}</td>
                    <td>{{ $f->apellido_vip ?? '—' }}</td>
                    <td>{{ $f->nickname ?? '' }}</td>
                    <td>{{ $f->mozo_etiqueta !== '' ? $f->mozo_etiqueta : '—' }}</td>
                    <td>{{ $f->producto ?? '—' }}</td>
                    <td class="text-right">{{ number_format((float) ($f->cantidad ?? 0), 3, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($f->cmv ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($f->precio_venta ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $f->sala !== '' ? $f->sala : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        @if (! empty($tot))
            <tfoot>
                <tr>
                    <th colspan="8">Totales</th>
                    <th class="text-right">{{ number_format((float) ($tot['cantidad_total'] ?? 0), 3, ',', '.') }}</th>
                    <th class="text-right">{{ number_format((float) ($tot['cmv_total'] ?? 0), 2, ',', '.') }}</th>
                    <th class="text-right">{{ number_format((float) ($tot['precio_venta_total'] ?? 0), 2, ',', '.') }}</th>
                    <th></th>
                </tr>
            </tfoot>
        @endif
    </table>
    <p class="meta">CMV desde lista de precios {{ $listaprecio_cmv_etiqueta ?? 'cód. 50' }} vigente a la fecha del canje.</p>
</body>
</html>
