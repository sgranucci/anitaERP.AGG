@php
    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerArticulo = ! $paraPdf && ($puede_ver_articulo ?? false);
    $puedeVerProveedor = ! $paraPdf && ($puede_ver_proveedor ?? false);
    $puedeVerRecepcion = ! $paraPdf && ($puede_ver_recepcion ?? false);
    $puedeVerOrdencompra = ! $paraPdf && ($puede_ver_ordencompra ?? false);
    $modo = $modo ?? \App\Support\Compras\HistorialPreciosArticuloFiltros::MODO_RESUMEN;
    $esDetalle = $modo === \App\Support\Compras\HistorialPreciosArticuloFiltros::MODO_DETALLE;

    $formatearNum = static function ($v, $dec = 2) use ($paraExcel) {
        if ($v === null || $v === '') {
            return '';
        }
        if ($paraExcel) {
            return (float) $v;
        }

        $formatted = number_format((float) $v, $dec, ',', '.');
        if ($dec <= 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), ',');
    };

    $formatearFecha = static function ($fecha) {
        if ($fecha === null || trim((string) $fecha) === '') {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    };

    $claseVariacion = static function ($pct) {
        if ($pct === null || $pct === '') {
            return '';
        }
        $n = (float) $pct;
        if ($n > 0) {
            return 'text-danger';
        }
        if ($n < 0) {
            return 'text-success';
        }

        return '';
    };
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>SKU</th>
        <th>Descripci&oacute;n</th>
        <th>C&oacute;d.Prov.</th>
        <th>Proveedor</th>
        <th class="text-right">
            @if ($esDetalle)
                Precio
            @else
                &Uacute;ltimo precio
            @endif
        </th>
        <th class="text-right">Precio anterior</th>
        <th class="text-right">Variaci&oacute;n $</th>
        <th class="text-right">Variaci&oacute;n %</th>
        <th>
            Fecha
            @if ($esDetalle)
                compra
            @else
                &uacute;lt. compra
            @endif
        </th>
        <th>Mon</th>
        <th>Recepci&oacute;n</th>
        <th>OC</th>
        <th>Empresa</th>
        @if ($esDetalle)
            <th class="text-right">Cantidad</th>
        @else
            <th>F. precio ant.</th>
        @endif
    </tr>
</thead>
<tbody>
@forelse ($filas ?? [] as $fila)
    @php
        $variacionPct = $fila['variacion_pct'] ?? null;
        $clsVar = $claseVariacion($variacionPct);
    @endphp
    <tr>
        <td>
            @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['sku'] ?? '' }}</a>
            @else
                {{ $fila['sku'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['descripcion_articulo'] ?? '' }}</td>
        <td>
            @if ($puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0)
                <a href="{{ route('editar_proveedor', array_merge(['id' => $fila['proveedor_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['codigoproveedor'] ?? '' }}</a>
            @else
                {{ $fila['codigoproveedor'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['nombreproveedor'] ?? '' }}</td>
        <td class="text-right">{{ $formatearNum($fila['precio_ultimo'] ?? null, 4) }}</td>
        <td class="text-right">{{ $formatearNum($fila['precio_anterior'] ?? null, 4) }}</td>
        <td class="text-right {{ $clsVar }}">{{ $formatearNum($fila['variacion_abs'] ?? null, 4) }}</td>
        <td class="text-right {{ $clsVar }}">
            @if ($variacionPct !== null)
                @if ($paraExcel)
                    {{ (float) $variacionPct }}
                @else
                    {{ number_format((float) $variacionPct, 2, ',', '.') }}%
                @endif
            @endif
        </td>
        <td>{{ $formatearFecha($fila['fecha_ultima'] ?? null) }}</td>
        <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
        <td>
            @if ($puedeVerRecepcion && (int) ($fila['recepcion_id'] ?? 0) > 0)
                <a href="{{ route('editar_recepcion_proveedor', array_merge(['id' => $fila['recepcion_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['numerorecepcion'] ?? '' }}</a>
            @else
                {{ $fila['numerorecepcion'] ?? '' }}
            @endif
        </td>
        <td>
            @if ($puedeVerOrdencompra && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                <a href="{{ route('editar_ordencompra', array_merge(['id' => $fila['ordencompra_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['numeroordencompra'] ?? '' }}</a>
            @else
                {{ $fila['numeroordencompra'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
        @if ($esDetalle)
            <td class="text-right">{{ $formatearNum($fila['cantidad'] ?? null, 4) }}</td>
        @else
            <td>{{ $formatearFecha($fila['fecha_anterior'] ?? null) }}</td>
        @endif
    </tr>
@empty
    <tr>
        <td colspan="14" class="text-center text-muted">Sin compras confirmadas para los filtros indicados.</td>
    </tr>
@endforelse
</tbody>
