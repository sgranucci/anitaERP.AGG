{{-- Tabla compartida PDF / Excel del listado de artículos (columnas de la vista). --}}
@php
    use App\Support\Stock\ArticuloListadoColumnas;
    $catalogo = ArticuloListadoColumnas::catalogoActivo();
    $columnas = ArticuloListadoColumnas::columnasExportables($columnasVisibles ?? []);
    $etiquetas = $etiquetasColumnas ?? [];
    $filasRender = $filasSegmentadas ?? null;
    if (! is_array($filasRender)) {
        $filasRender = [];
        foreach ($articulos as $data) {
            $filasRender[] = ['type' => 'row', 'row' => $data];
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
        @foreach ($columnas as $keyCol)
            <th>{{ $etiquetas[$keyCol] ?? ($catalogo[$keyCol]['label'] ?? $keyCol) }}</th>
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
                @foreach ($columnas as $keyCol)
                    <td>{{ ArticuloListadoColumnas::valorCelda($data, $keyCol) }}</td>
                @endforeach
            </tr>
        @endif
    @endforeach
</tbody>
