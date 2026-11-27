@php
    use App\Support\Compras\AplicacionCcProveedorComprobanteListadoColumnas;
    $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
    $columnas = array_values(array_filter(
        $columnasVisibles ?? AplicacionCcProveedorComprobanteListadoColumnas::defaultsVisibles(),
        static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
    ));
    if ($columnas === []) {
        $columnas = AplicacionCcProveedorComprobanteListadoColumnas::defaultsVisibles();
    }
    $etiquetas = $etiquetasColumnas ?? [];
    $colspan = max(1, count($columnas));
    $cabeceraPdf = $cabeceraPdf ?? null;
    $resumenTotales = $resumenTotales ?? null;
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
    @foreach ($filasSegmentadas ?? [] as $filaVista)
        @if (($filaVista['type'] ?? '') === 'header')
            <tr class="lw-export-grupo lw-export-grupo-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                <td colspan="{{ $colspan }}">
                    {{ $filaVista['label'] ?? '' }}: {{ ($filaVista['valor'] ?? '') !== '' ? $filaVista['valor'] : '(vacío)' }}
                    ({{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }})
                </td>
            </tr>
        @else
            @php $data = $filaVista['row']; @endphp
            <tr>
                @foreach ($columnas as $key)
                    <td class="{{ ($catalogo[$key]['type'] ?? '') === 'decimal' ? 'num' : '' }}">
                        {{ AplicacionCcProveedorComprobanteListadoColumnas::valorCelda($data, $key) }}
                    </td>
                @endforeach
            </tr>
        @endif
    @endforeach
    @if (is_array($resumenTotales))
        <tr class="lw-export-total">
            @foreach ($columnas as $i => $key)
                @if ($key === 'total')
                    <td class="num">{{ number_format((float) $resumenTotales['total_pesos'], 2, ',', '.') }}</td>
                @elseif ($key === 'diferencia_cambio')
                    <td class="num">{{ number_format((float) $resumenTotales['diferencia_cambio'], 2, ',', '.') }}</td>
                @elseif ($i === 0)
                    <td>Total ({{ number_format((int) $resumenTotales['total'], 0, ',', '.') }})</td>
                @else
                    <td></td>
                @endif
            @endforeach
        </tr>
    @endif
</tbody>
