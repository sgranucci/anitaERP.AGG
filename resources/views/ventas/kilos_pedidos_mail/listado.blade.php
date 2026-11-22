@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\KiloPedidoListadoFiltros;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $titulo = $perfilNombre ?? 'Kilos pedidos';
    $subtitulo = 'Reparto: '.KiloPedidoListadoFiltros::formatearRepartoTexto($filtros)
        .' · Entrega: '.KiloPedidoListadoFiltros::formatearPeriodoTexto($filtros);
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.3; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th {
            border: 1px solid #cccccc; text-align: left; padding: 3px 4px; vertical-align: top; word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 8px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'colspan' => 7,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th style="width:8%;">Reparto</th>
                <th style="width:18%;">Nombre</th>
                <th style="width:14%;">Artículo</th>
                <th style="width:28%;">Descripción</th>
                <th class="text-right" style="width:10%;">Piezas</th>
                <th class="text-right" style="width:12%;">Kilos</th>
                <th style="width:10%;">Fuente</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
                @if ($tipo === 'subtotal_reparto')
                    <tr style="background-color:#e9ecef; font-weight:bold;">
                        <td>{{ $fila['codigotransporte'] ?? '' }}</td>
                        <td>{{ $fila['nombretransporte'] ?? '' }}</td>
                        <td colspan="2">Subtotal reparto</td>
                        <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                        <td></td>
                    </tr>
                @elseif ($tipo === 'total_final')
                    <tr style="background-color:#d6eaf8; font-weight:bold;">
                        <td colspan="4">TOTAL FINAL</td>
                        <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                        <td></td>
                    </tr>
                @else
                    <tr>
                        <td></td>
                        <td></td>
                        <td>{{ $fila['sku'] ?? '' }}</td>
                        <td>{{ $fila['descripcion'] ?? '' }}</td>
                        <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                        <td>{{ $fila['fuente'] ?? '' }}</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="7">Sin pedidos para el filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
