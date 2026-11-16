{{-- Tabla compartida PDF / Excel del listado de importaciones (columnas dinámicas). --}}
@php
    use App\Support\Compras\ImportacionListadoColumnas;
    $catalogo = ImportacionListadoColumnas::COLUMNAS;
    $columnas = array_values(array_filter(
        $columnasVisibles ?? ImportacionListadoColumnas::defaultsVisibles(),
        static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
    ));
    if ($columnas === []) {
        $columnas = ImportacionListadoColumnas::defaultsVisibles();
    }
    $etiquetas = $etiquetasColumnas ?? [];
    $modoExcel = ! empty($modoExcel);
    $filasRender = $filasSegmentadas ?? null;
    if (! is_array($filasRender)) {
        $filasRender = [];
        foreach ($datas as $fila) {
            $filasRender[] = ['type' => 'row', 'row' => $fila];
        }
    }
    $colspan = max(1, count($columnas));
    $cabeceraPdf = $cabeceraPdf ?? null;
@endphp
<thead>
    @if (is_array($cabeceraPdf))
        @include('includes.reportes.pdf_thead_cabecera', array_merge($cabeceraPdf, ['colspan' => $colspan]))
    @endif
    <tr class="columnas">
        @foreach ($columnas as $key)
            <th>{{ $etiquetas[$key] ?? ($catalogo[$key]['label'] ?? $key) }}</th>
        @endforeach
    </tr>
</thead>
<tbody>
    @foreach ($filasRender as $filaVista)
        @if (($filaVista['type'] ?? '') === 'header')
            <tr class="lw-export-grupo lw-export-grupo-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                <td colspan="{{ $colspan }}">
                    {{ $filaVista['label'] ?? '' }}: {{ $filaVista['valor'] ?? '' }}
                    ({{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }})
                </td>
            </tr>
        @else
            @php $data = $filaVista['row']; @endphp
            <tr>
                @foreach ($columnas as $key)
                    @php
                        $esDecimal = ($catalogo[$key]['type'] ?? '') === 'decimal';
                        $numero = $esDecimal ? ImportacionListadoColumnas::valorNumerico($data, $key) : null;
                        $decimales = (int) ($catalogo[$key]['decimales'] ?? 2);
                    @endphp
                    @if ($esDecimal && $modoExcel)
                        {{-- Excel/CSV: importe numérico (formato #,##0.00 lo pone la columna). --}}
                        <td>{{ $numero === null ? '' : number_format($numero, $decimales, '.', '') }}</td>
                    @elseif ($esDecimal)
                        <td class="num">{{ ImportacionListadoColumnas::valorCelda($data, $key) }}</td>
                    @elseif ($key === 'codigo')
                        <td style="mso-number-format:'\@';">{{ ImportacionListadoColumnas::valorCelda($data, $key) }}</td>
                    @else
                        <td>{{ ImportacionListadoColumnas::valorCelda($data, $key) }}</td>
                    @endif
                @endforeach
            </tr>
        @endif
    @endforeach
</tbody>
