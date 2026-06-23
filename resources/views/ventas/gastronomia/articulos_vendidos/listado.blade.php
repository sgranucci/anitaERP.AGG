<!DOCTYPE html>
<html>
    <title>Artículos vendidos gastronomía</title>
    <head>
        <style>
            table {
                font-family: arial, sans-serif;
                border-collapse: collapse;
                width: 100%;
            }
            td, th {
                border: 1px solid #dddddd;
                text-align: left;
                padding: 8px;
            }
            tr:nth-child(even) {
                background-color: #dddddd;
            }
            .text-right { text-align: right; }
        </style>
    </head>
    <body>
        <h2>Artículos vendidos — gastronomía</h2>
        @if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta']))
            <p>
                Jornada:
                <strong>{{ $filtros['fecha_desde'] ?? '—' }}</strong>
                @if (($filtros['fecha_hasta'] ?? '') !== ($filtros['fecha_desde'] ?? ''))
                    → <strong>{{ $filtros['fecha_hasta'] ?? '—' }}</strong>
                @endif
            </p>
        @endif
        <table class="table table-striped table-bordered table-hover">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th>Subcategoría</th>
                    <th>Punto de venta</th>
                    <th>Depósito</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Importe</th>
                    <th class="text-right">Comprob.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $f)
                    <tr>
                        <td>{{ $f->sku ?? '—' }}</td>
                        <td>{{ $f->descripcion ?? '—' }}</td>
                        <td>{{ trim((string) ($f->subcategoria_nombre ?? '')) !== '' ? $f->subcategoria_nombre : '—' }}</td>
                        <td>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</td>
                        <td>{{ $f->deposito_etiqueta !== '' ? $f->deposito_etiqueta : '—' }}</td>
                        <td class="text-right">{{ number_format((float) ($f->cantidad_total ?? 0), 3, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($f->importe_total ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ (int) ($f->cantidad_comprobantes ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
            @if (! empty($totales))
                <tfoot>
                    <tr>
                        <th colspan="5">Totales</th>
                        <th class="text-right">{{ number_format((float) ($totales['cantidad_total'] ?? 0), 3, ',', '.') }}</th>
                        <th class="text-right">{{ number_format((float) ($totales['importe_total'] ?? 0), 2, ',', '.') }}</th>
                        <th class="text-right">{{ (int) ($totales['cantidad_comprobantes'] ?? 0) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </body>
</html>
