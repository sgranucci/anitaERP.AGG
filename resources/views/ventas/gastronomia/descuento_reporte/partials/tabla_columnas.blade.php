@php
    use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;

    $modoPdf = ! empty($modo_pdf);
    $columnasFijasPantalla = ! $modoPdf && empty($sin_wrapper);
    $vista = $vista_columnas_chunk ?? ($resultado['vista_columnas'] ?? null);
    $columnas = $vista['columnas'] ?? [];
    $filas = $vista['filas'] ?? [];
    $grupos = $vista['grupos'] ?? null;
    if ($grupos === null && $filas !== []) {
        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($filas);
        $grupos = $agrupado['grupos'];
    }
    $grupos = $grupos ?? [];
    $totalesPorColumna = $vista['totales_por_columna'] ?? [];
    $subCols = 3;
    $colsFijas = 4;
    $colspanTotal = $colsFijas + count($columnas) * $subCols;
    $tableClass = trim(($table_class ?? 'table table-sm table-striped table-bordered table-hover mb-0') . ($modoPdf ? ' tabla-columnas-pdf' : '') . ($columnasFijasPantalla ? ' tabla-descuento-reporte-col-fijas' : ''));
@endphp
@if ($vista && $columnas !== [])
    <div class="{{ $columnasFijasPantalla ? 'table-responsive descuento-reporte-columnas-wrap' : (! empty($sin_wrapper) ? '' : 'table-responsive') }}">
        <table class="{{ $tableClass }} tabla-descuento-reporte">
            <thead>
                <tr>
                    <th rowspan="2" class="align-middle {{ $modoPdf ? 'col-art' : '' }}{{ $columnasFijasPantalla ? ' col-fija-1' : '' }}">Artículo</th>
                    <th rowspan="2" class="align-middle {{ $modoPdf ? 'col-desc' : '' }}{{ $columnasFijasPantalla ? ' col-fija-2' : '' }}">Descripción</th>
                    <th rowspan="2" class="align-middle text-right {{ $modoPdf ? 'col-num' : '' }}{{ $columnasFijasPantalla ? ' col-fija-3' : '' }}">Costo unit.</th>
                    <th rowspan="2" class="align-middle text-right {{ $modoPdf ? 'col-num' : '' }}{{ $columnasFijasPantalla ? ' col-fija-4' : '' }}">Precio vta.</th>
                    @foreach ($columnas as $col)
                        <th colspan="{{ $subCols }}" class="text-center {{ $modoPdf ? 'col-grupo' : '' }}">{{ $col['titulo'] ?? '' }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($columnas as $col)
                        <th class="text-right">Unid.</th>
                        <th class="text-right">Costo</th>
                        <th class="text-right">Venta</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($grupos as $grupo)
                    <tr class="{{ $modoPdf ? 'grupo-tipo' : 'table-info' }} font-weight-bold">
                        <td colspan="{{ $colspanTotal }}" class="{{ $columnasFijasPantalla ? 'col-fija-grupo-total' : '' }}">
                            Tipo: {{ $grupo['tipo_nombre'] }}
                            ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})
                        </td>
                    </tr>
                    @foreach ($grupo['filas'] as $fila)
                        <tr>
                            <td class="{{ $modoPdf ? '' : 'text-nowrap' }}{{ $columnasFijasPantalla ? ' col-fija-1' : '' }}">
                                @if (! $modoPdf && ($puede_ver_articulo ?? false) && (int) ($fila['articulo_id'] ?? 0) > 0)
                                    <a href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       target="_blank" rel="noopener" class="text-primary">
                                        {{ $fila['sku'] ?? '—' }}
                                    </a>
                                @else
                                    {{ $fila['sku'] ?? '—' }}
                                @endif
                            </td>
                            <td class="{{ $columnasFijasPantalla ? 'col-fija-2' : '' }}">{{ $fila['descripcion'] ?? '—' }}</td>
                            <td class="text-right{{ $columnasFijasPantalla ? ' col-fija-3' : '' }}">{{ number_format((float) ($fila['costo_unitario'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right{{ $columnasFijasPantalla ? ' col-fija-4' : '' }}">{{ number_format((float) ($fila['precio_venta'] ?? 0), 2, ',', '.') }}</td>
                            @foreach ($columnas as $col)
                                @php
                                    $celda = ($fila['celdas'] ?? [])[$col['clave'] ?? ''] ?? null;
                                @endphp
                                @if ($celda)
                                    <td class="text-right">{{ number_format((float) ($celda['unidades'] ?? 0), 0, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($celda['costo_total'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($celda['total_venta'] ?? 0), 2, ',', '.') }}</td>
                                @else
                                    <td colspan="{{ $subCols }}" class="text-center text-muted">—</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="{{ $modoPdf ? 'subtotal-tipo' : '' }} font-weight-bold" style="{{ $modoPdf ? '' : 'background-color: #f0f0f0;' }}">
                        <td colspan="{{ $colsFijas }}" class="{{ $columnasFijasPantalla ? 'col-fija-grupo-total' : '' }}">
                            Total parcial {{ $grupo['tipo_nombre'] }}
                        </td>
                        @foreach ($columnas as $col)
                            @php
                                $sumaUnid = 0.0;
                                $sumaCosto = 0.0;
                                $sumaVenta = 0.0;
                                foreach ($grupo['filas'] as $filaGrupo) {
                                    $celda = ($filaGrupo['celdas'] ?? [])[$col['clave'] ?? ''] ?? null;
                                    if (! $celda) {
                                        continue;
                                    }
                                    $sumaUnid += (float) ($celda['unidades'] ?? 0);
                                    $sumaCosto += (float) ($celda['costo_total'] ?? 0);
                                    $sumaVenta += (float) ($celda['total_venta'] ?? 0);
                                }
                            @endphp
                            <td class="text-right">{{ number_format($sumaUnid, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($sumaCosto, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($sumaVenta, 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspanTotal }}" class="text-center text-muted">Sin datos.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($totalesPorColumna !== [] && $grupos !== [])
                <tfoot>
                    <tr class="table-active font-weight-bold">
                        <td colspan="{{ $colsFijas }}" class="{{ $columnasFijasPantalla ? 'col-fija-grupo-total' : '' }}">Total final</td>
                        @foreach ($totalesPorColumna as $totCol)
                            @php $tot = $totCol['totales'] ?? []; @endphp
                            <td class="text-right">{{ number_format((float) ($tot['unidades'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($tot['costo_total'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($tot['total_venta'] ?? 0), 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif
