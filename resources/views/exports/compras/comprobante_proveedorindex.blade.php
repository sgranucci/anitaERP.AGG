@php
    use App\Support\Compras\ComprobanteProveedorEstados;
    use App\Support\Compras\ComprobanteProveedorListadoColumnas;
    use App\Support\Listado\ListadoExportPresentacionSupport;
    $catalogo = ComprobanteProveedorListadoColumnas::catalogoActivo();
    $columnas = $columnasVisibles ?? ComprobanteProveedorListadoColumnas::defaultsVisibles();
    $columnas = array_values(array_filter(
        $columnas,
        static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
    ));
    if ($columnas === []) {
        $columnas = ComprobanteProveedorListadoColumnas::defaultsVisibles();
    }
    $etiquetas = $etiquetasColumnas ?? [];
    $colspan = max(1, count($columnas));
    $filtros = $filtros ?? [];
    $filtrosExcel = $filtros;
    $filtrosExcel['orden'] = $filtrosExcel['orden'] ?? ($filtrosExcel['sort'] ?? []);
    $partesSub = ['Generado '.date('d/m/Y H:i'), (is_countable($datas) ? count($datas) : 0).' registro(s)'];
    $presentacion = ListadoExportPresentacionSupport::subtitulo(
        $filtrosExcel,
        $etiquetas,
        \App\Support\Compras\ComprobanteProveedorListadoFiltros::camposOrdenables()
    );
    if ($presentacion !== '') {
        $partesSub[] = $presentacion;
    }
    if (! empty($filtros['empresa_id'])) {
        $partesSub[] = 'Empresa id: '.$filtros['empresa_id'];
    } elseif (($filtros['empresa_scope'] ?? '') === 'todas') {
        $partesSub[] = 'Todas las empresas asignadas';
    }
    $estadoFiltro = (string) ($filtros['estado'] ?? '');
    if ($estadoFiltro !== '' && $estadoFiltro !== ComprobanteProveedorEstados::FILTRO_TODOS) {
        $partesSub[] = 'Estado: '.ComprobanteProveedorEstados::etiqueta($estadoFiltro);
    }
    $subtitulo = implode(' — ', $partesSub);
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
    <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Listado de comprobantes de proveedor</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $subtitulo }}</strong></td>
    </tr>
    <thead>
        <tr>
            @foreach ($columnas as $key)
                <th>{{ $etiquetas[$key] ?? ($catalogo[$key]['label'] ?? $key) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
        <tr>
            @foreach ($columnas as $key)
                @php
                    $metaCol = $catalogo[$key] ?? [];
                    $attrCol = (string) ($metaCol['attr'] ?? $key);
                    $crudo = $data->{$attrCol} ?? null;
                @endphp
                @if (($metaCol['type'] ?? '') === 'decimal' && $crudo !== null && $crudo !== '')
                    <td>{{ (float) $crudo }}</td>
                @else
                    <td>{{ ComprobanteProveedorListadoColumnas::valorCelda($data, $key) }}</td>
                @endif
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
