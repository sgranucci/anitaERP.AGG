@php
    use App\Support\Sueldos\EmpleadoSueldosListadoColumnas;
    $catalogo = EmpleadoSueldosListadoColumnas::catalogoActivo();
    $columnas = $columnasVisibles ?? EmpleadoSueldosListadoColumnas::defaultsVisibles();
    $columnas = array_values(array_filter(
        $columnas,
        static function ($k) use ($catalogo) {
            return isset($catalogo[$k]) && ! empty($catalogo[$k]['export']);
        }
    ));
    if ($columnas === []) {
        $columnas = array_values(array_filter(
            EmpleadoSueldosListadoColumnas::defaultsVisibles(),
            static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
        ));
    }
    $etiquetas = $etiquetasColumnas ?? [];
    $filasRender = $filasSegmentadas ?? null;
    if (! is_array($filasRender)) {
        $filasRender = [];
        foreach ($datas ?? [] as $data) {
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
                    <td>{{ EmpleadoSueldosListadoColumnas::valorCelda($data, $key) }}</td>
                @endforeach
            </tr>
        @endif
    @endforeach
</tbody>
