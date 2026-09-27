@php
    $colspan = max(1, count($columnasVisibles ?? []));
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}"><strong>Listado de proveedores</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (trim((string) ($subtitulo ?? '')) !== '')
            <tr>
                <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan }}">Registros: {{ (int) ($totalFilas ?? 0) }}</td>
        </tr>
    </tbody>
    @include('compras.proveedor.partials.tabla_listado_export', [
        'proveedores' => $proveedores,
        'filasSegmentadas' => $filasSegmentadas ?? null,
        'columnasVisibles' => $columnasVisibles ?? null,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
</table>
